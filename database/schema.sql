-- =============================================================================
-- Génie Solutions — Schéma de base de données (MySQL 8 / MariaDB 10.4+)
-- Jeu de caractères : utf8mb4 — Moteur : InnoDB (clés étrangères obligatoires)
--
-- Ce fichier ne contient QUE la structure. Les données de référence (rôles,
-- permissions, paramètres, contenus fournis) sont dans seed.sql ; les données
-- de démonstration, clairement identifiées [DÉMO], sont dans demo.sql.
--
-- Conventions :
--   * created_at / updated_at sur toutes les tables de contenu.
--   * Suppression : CASCADE pour les dépendances pures (images d'un projet),
--     SET NULL pour les références facultatives (média, auteur),
--     RESTRICT pour protéger les données métier (un service utilisé ne
--     peut pas être supprimé sans traitement préalable).
--   * Aucune colonne JSON : les champs variables des devis sont stockés dans
--     quote_request_details (clé/valeur relationnelle).
--   * Les adresses IP ne sont jamais stockées en clair dans les tables
--     publiques : seul un hachage salé (ip_hash) est conservé.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Utilisateurs, rôles, permissions
-- -----------------------------------------------------------------------------

CREATE TABLE roles (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(50)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Rôle protégé contre la suppression',
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(60)  NOT NULL COMMENT 'Ex. quotes.manage',
    label       VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un utilisateur possède un seul rôle (principe du moindre privilège, modèle simple).
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id         SMALLINT UNSIGNED NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL COMMENT 'password_hash() — jamais de mot de passe en clair',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seul le hachage SHA-256 du jeton est stocké : une fuite de la base ne permet pas de réinitialiser un compte.
CREATE TABLE password_reset_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prt_token (token_hash),
    KEY idx_prt_user (user_id),
    KEY idx_prt_expires (expires_at),
    CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation des tentatives de connexion (par e-mail et par IP hachée).
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190) NOT NULL,
    ip_hash      CHAR(64)     NOT NULL,
    was_success  TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la_email_time (email, attempted_at),
    KEY idx_la_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation de débit des formulaires publics (devis, contact, témoignage).
CREATE TABLE rate_limits (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket      VARCHAR(40) NOT NULL COMMENT 'Ex. quote_form, contact_form',
    ip_hash     CHAR(64)    NOT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rl_bucket_ip_time (bucket, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Médias et paramètres
-- -----------------------------------------------------------------------------

CREATE TABLE media (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stored_name   VARCHAR(80)  NOT NULL COMMENT 'Nom aléatoire généré par le serveur',
    original_name VARCHAR(255) NOT NULL COMMENT 'Affichage uniquement, jamais utilisé comme chemin',
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    width         SMALLINT UNSIGNED NULL,
    height        SMALLINT UNSIGNED NULL,
    alt_text      VARCHAR(255) NULL,
    uploaded_by   INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_stored (stored_name),
    KEY idx_media_uploader (uploaded_by),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres clé/valeur modifiables depuis l'administration.
-- Les secrets (mot de passe SMTP, etc.) ne sont PAS ici : ils restent dans .env.
CREATE TABLE site_settings (
    setting_key   VARCHAR(80) NOT NULL,
    setting_value TEXT        NULL,
    setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
    updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Contenu éditorial
-- -----------------------------------------------------------------------------

CREATE TABLE pages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(120) NOT NULL,
    title           VARCHAR(190) NOT NULL,
    content         MEDIUMTEXT   NULL COMMENT 'HTML assaini à l’enregistrement',
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    og_media_id     INT UNSIGNED NULL,
    status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug),
    KEY idx_pages_status (status),
    CONSTRAINT fk_pages_og FOREIGN KEY (og_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sections modifiables de la page d'accueil (hero, présentation, appel à l'action…).
CREATE TABLE home_sections (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    section_key VARCHAR(40)  NOT NULL COMMENT 'hero, intro, gallery, cta…',
    title       VARCHAR(190) NULL,
    subtitle    TEXT         NULL,
    body        TEXT         NULL,
    media_id    INT UNSIGNED NULL,
    cta_label   VARCHAR(80)  NULL,
    cta_url     VARCHAR(255) NULL,
    cta2_label  VARCHAR(80)  NULL,
    cta2_url    VARCHAR(255) NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_home_key (section_key),
    CONSTRAINT fk_home_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partenaires affichés dans le bandeau défilant de la page d’accueil.
CREATE TABLE partners (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    website_url VARCHAR(255) NULL,
    media_id    INT UNSIGNED NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partners_active_sort (is_active, sort_order),
    CONSTRAINT fk_partners_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arguments « Pourquoi choisir Génie Solutions ? »
CREATE TABLE why_arguments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    icon        VARCHAR(60) NULL COMMENT 'Nom d’une icône Bootstrap Icons',
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_why_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Étapes du processus de commande.
CREATE TABLE order_steps (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(120) NOT NULL,
    description TEXT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_steps_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question    VARCHAR(255) NOT NULL,
    answer      TEXT NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_faqs_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Témoignages : jamais publiés sans validation (status = approved).
CREATE TABLE testimonials (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_name   VARCHAR(120) NOT NULL,
    author_role   VARCHAR(150) NULL,
    content       TEXT NOT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by   INT UNSIGNED NULL,
    reviewed_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_testimonials_status (status, submitted_at),
    CONSTRAINT fk_testimonials_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Services, catalogue, réalisations
-- -----------------------------------------------------------------------------

CREATE TABLE services (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug              VARCHAR(120) NOT NULL,
    name              VARCHAR(150) NOT NULL,
    short_description VARCHAR(500) NOT NULL,
    description       MEDIUMTEXT NULL,
    approach_text     TEXT NULL COMMENT 'Bloc « notre approche » de la page service',
    cta_text          VARCHAR(255) NULL,
    icon              VARCHAR(60)  NULL COMMENT 'Classe Bootstrap Icons, ex. bi-printer',
    quote_profile     ENUM('generic','print','textile','embroidery') NOT NULL DEFAULT 'generic' COMMENT 'Champs spécifiques du formulaire de devis',
    media_id          INT UNSIGNED NULL,
    seo_title         VARCHAR(190) NULL,
    seo_description   VARCHAR(320) NULL,
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    status            ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_list (status, sort_order),
    CONSTRAINT fk_services_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prestations listées sur la page d'un service (« selon disponibilité »).
CREATE TABLE service_offerings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id  INT UNSIGNED NOT NULL,
    label       VARCHAR(190) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_offerings_service (service_id, sort_order),
    CONSTRAINT fk_offerings_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope       ENUM('project','product') NOT NULL,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_scope_slug (scope, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue : produits ou prestations. Le prix est facultatif (aucun prix supposé).
CREATE TABLE products (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id    INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED NULL,
    slug          VARCHAR(140) NOT NULL,
    name          VARCHAR(190) NOT NULL,
    description   TEXT NULL,
    price_amount  DECIMAL(12,2) NULL COMMENT 'NULL = sur devis',
    price_note    VARCHAR(120) NULL COMMENT 'Ex. « à partir de », « par unité »',
    media_id      INT UNSIGNED NULL,
    seo_title     VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    sort_order    SMALLINT NOT NULL DEFAULT 0,
    status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_service (service_id, status, sort_order),
    KEY idx_products_category (category_id),
    CONSTRAINT fk_products_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_products_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id    INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED NULL,
    slug          VARCHAR(140) NOT NULL,
    title         VARCHAR(190) NOT NULL,
    summary       VARCHAR(500) NULL,
    description   MEDIUMTEXT NULL,
    client_name   VARCHAR(150) NULL COMMENT 'Affiché uniquement avec l’accord du client',
    completed_on  DATE NULL,
    cover_media_id INT UNSIGNED NULL,
    seo_title     VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    is_demo       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Donnée de démonstration à supprimer avant production',
    status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at  DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    KEY idx_projects_list (status, published_at),
    KEY idx_projects_service (service_id, status),
    KEY idx_projects_category (category_id),
    CONSTRAINT fk_projects_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    media_id    INT UNSIGNED NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pi_project_media (project_id, media_id),
    KEY idx_pi_sort (project_id, sort_order),
    CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Demandes de devis
-- -----------------------------------------------------------------------------

CREATE TABLE quote_requests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference     VARCHAR(24)  NOT NULL COMMENT 'Aléatoire, non séquentielle (non devinable)',
    full_name     VARCHAR(120) NOT NULL,
    organization  VARCHAR(150) NULL,
    phone         VARCHAR(40)  NOT NULL,
    email         VARCHAR(190) NOT NULL,
    service_id    INT UNSIGNED NULL,
    product_id    INT UNSIGNED NULL,
    quantity      VARCHAR(60)  NULL COMMENT 'Texte libre : « 500 », « 20 polos »…',
    description   TEXT NOT NULL,
    desired_date  DATE NULL,
    delivery_mode VARCHAR(60)  NULL,
    status        ENUM('nouvelle','en_etude','devis_envoye','acceptee','refusee','en_production','terminee','annulee')
                  NOT NULL DEFAULT 'nouvelle',
    assigned_to   INT UNSIGNED NULL,
    consent_at    DATETIME NOT NULL COMMENT 'Consentement au traitement des données',
    ip_hash       CHAR(64) NULL,
    notified_at   DATETIME NULL COMMENT 'Renseigné uniquement si l’envoi e-mail a été confirmé',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quotes_reference (reference),
    KEY idx_quotes_status_date (status, created_at),
    KEY idx_quotes_service (service_id),
    KEY idx_quotes_product (product_id),
    KEY idx_quotes_assigned (assigned_to),
    KEY idx_quotes_email (email),
    CONSTRAINT fk_quotes_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotes_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotes_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Champs spécifiques au service choisi (tailles, tissu, format, finitions…).
CREATE TABLE quote_request_details (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id    INT UNSIGNED NOT NULL,
    field_key   VARCHAR(60)  NOT NULL,
    field_value VARCHAR(500) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qrd_quote_key (quote_id, field_key),
    CONSTRAINT fk_qrd_quote FOREIGN KEY (quote_id) REFERENCES quote_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pièces jointes : stockées HORS du répertoire public (storage/private/quotes).
CREATE TABLE quote_attachments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id      INT UNSIGNED NOT NULL,
    stored_name   VARCHAR(80)  NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qa_stored (stored_name),
    KEY idx_qa_quote (quote_id),
    CONSTRAINT fk_qa_quote FOREIGN KEY (quote_id) REFERENCES quote_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_status_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id    INT UNSIGNED NOT NULL,
    old_status  VARCHAR(20) NULL,
    new_status  VARCHAR(20) NOT NULL,
    changed_by  INT UNSIGNED NULL COMMENT 'NULL = création par le visiteur',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_qsh_quote (quote_id, created_at),
    KEY idx_qsh_user (changed_by),
    CONSTRAINT fk_qsh_quote FOREIGN KEY (quote_id) REFERENCES quote_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_qsh_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes internes : jamais visibles du client.
CREATE TABLE quote_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    note        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_qn_quote (quote_id, created_at),
    KEY idx_qn_user (user_id),
    CONSTRAINT fk_qn_quote FOREIGN KEY (quote_id) REFERENCES quote_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_qn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Messages, journaux, redirections
-- -----------------------------------------------------------------------------

CREATE TABLE contact_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(40)  NULL,
    subject     VARCHAR(190) NOT NULL,
    message     TEXT NOT NULL,
    status      ENUM('new','read','archived') NOT NULL DEFAULT 'new',
    consent_at  DATETIME NOT NULL,
    ip_hash     CHAR(64) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'activité administrative. Ne contient jamais de mot de passe ni de jeton.
CREATE TABLE activity_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NULL,
    action       VARCHAR(60) NOT NULL COMMENT 'Ex. login.success, quote.status_changed',
    entity_type  VARCHAR(40) NULL,
    entity_id    INT UNSIGNED NULL,
    details      VARCHAR(500) NULL,
    ip_hash      CHAR(64) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_al_user (user_id, created_at),
    KEY idx_al_action (action, created_at),
    KEY idx_al_entity (entity_type, entity_id),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_path   VARCHAR(255) NOT NULL,
    to_path     VARCHAR(255) NOT NULL,
    http_code   SMALLINT NOT NULL DEFAULT 301,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_redirects_from (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
