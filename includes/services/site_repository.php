<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Accès aux données du site public (toutes les requêtes SQL du front sont ici).
 * Chaque fonction est tolérante aux pannes pour que le layout ne casse pas la page.
 */

/** @return list<array{slug:string,name:string}> */
function repo_published_services(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query(
                "SELECT id, slug, name, short_description, icon, quote_profile FROM services WHERE status = 'published' ORDER BY sort_order, name"
            )->fetchAll();
        } catch (Throwable $e) {
            error_log('repo_published_services: ' . $e->getMessage());
            $cache = [];
        }
    }

    return $cache;
}

/** Retourne une section d'accueil active par sa clé, ou null. */
function repo_home_section(string $key): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM home_sections WHERE section_key = :k AND is_active = 1');
        $st->execute(['k' => $key]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('repo_home_section: ' . $e->getMessage());

        return null;
    }
}

/** Slugs des pages publiées (une seule requête par page) : sert à n'afficher que des liens valides. */
function repo_published_page_slugs(): array
{
    static $slugs = null;

    return $slugs ??= array_column(repo_rows("SELECT slug FROM pages WHERE status = 'published'"), 'slug');
}

/** Retourne une page publiée par son slug, ou null. */
function repo_published_page(string $slug): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM pages WHERE slug = :s AND status = 'published'");
        $st->execute(['s' => $slug]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('repo_published_page: ' . $e->getMessage());

        return null;
    }
}

/** URL publique d'un média (image raster téléversée), ou null. */
function repo_media_url(?int $mediaId): ?array
{
    if (!$mediaId) {
        return null;
    }
    try {
        $st = db()->prepare('SELECT stored_name, alt_text, width, height FROM media WHERE id = :id');
        $st->execute(['id' => $mediaId]);
        $row = $st->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'url'    => '/uploads/' . rawurlencode($row['stored_name']),
            'alt'    => (string) $row['alt_text'],
            'width'  => $row['width'],
            'height' => $row['height'],
        ];
    } catch (Throwable $e) {
        error_log('repo_media_url: ' . $e->getMessage());

        return null;
    }
}

/* -------------------------------------------------------------------------
 * Contenu des pages publiques (étape 3)
 * ------------------------------------------------------------------------- */

/** Exécute un SELECT préparé et retourne toutes les lignes ; [] + journal en cas d'erreur. */
function repo_rows(string $sql, array $params = []): array
{
    try {
        $st = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue(is_int($k) ? $k + 1 : $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return $st->fetchAll();
    } catch (Throwable $e) {
        error_log('repo_rows: ' . $e->getMessage());

        return [];
    }
}

function repo_row(string $sql, array $params = []): ?array
{
    return repo_rows($sql, $params)[0] ?? null;
}

function repo_service_by_slug(string $slug): ?array
{
    $row = repo_row("SELECT * FROM services WHERE slug = :s AND status = 'published'", ['s' => $slug]);
    if ($row !== null) {
        $row['media'] = repo_media_url($row['media_id'] ? (int) $row['media_id'] : null);
        $row['offerings'] = array_column(repo_rows(
            'SELECT label FROM service_offerings WHERE service_id = :id ORDER BY sort_order, id',
            ['id' => (int) $row['id']]
        ), 'label');
    }

    return $row;
}

function repo_why_arguments(): array
{
    return repo_rows('SELECT title, description, icon FROM why_arguments WHERE is_active = 1 ORDER BY sort_order, id');
}

function repo_order_steps(): array
{
    return repo_rows('SELECT title, description FROM order_steps WHERE is_active = 1 ORDER BY sort_order, id');
}

function repo_faqs(): array
{
    return repo_rows('SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order, id');
}

/** Témoignages : UNIQUEMENT ceux validés (status = approved). */
function repo_approved_testimonials(int $limit = 6): array
{
    return repo_rows(
        "SELECT author_name, author_role, content FROM testimonials WHERE status = 'approved' ORDER BY reviewed_at DESC, id DESC LIMIT :n",
        ['n' => $limit]
    );
}

/** Catégories de réalisations ayant au moins une réalisation publiée. */
function repo_project_categories(): array
{
    return repo_rows(
        "SELECT c.slug, c.name, COUNT(p.id) AS n
           FROM categories c JOIN projects p ON p.category_id = c.id AND p.status = 'published'
          WHERE c.scope = 'project'
          GROUP BY c.id, c.slug, c.name, c.sort_order
          ORDER BY c.sort_order, c.name"
    );
}

const PROJECT_CARD_COLUMNS = "p.id, p.slug, p.title, p.summary, p.completed_on, s.name AS service_name, s.slug AS service_slug, s.icon AS service_icon,
        c.name AS category_name, m.stored_name, m.alt_text, m.width, m.height";

/**
 * Réalisations publiées, avec filtre facultatif par catégorie et pagination SQL
 * (comptage, bornage de la page demandée, puis lecture : 2 requêtes).
 *
 * @return array{items:list<array>,pg:array}
 */
function repo_projects_page(?string $categorySlug, int $perPage, int $requestedPage): array
{
    $where = "p.status = 'published'";
    $params = [];
    if ($categorySlug !== null) {
        $where .= ' AND c.slug = :cat';
        $params['cat'] = $categorySlug;
    }
    $from = 'FROM projects p JOIN services s ON s.id = p.service_id
             LEFT JOIN categories c ON c.id = p.category_id AND c.scope = \'project\'
             LEFT JOIN media m ON m.id = p.cover_media_id';

    $total = (int) (repo_row("SELECT COUNT(*) AS n $from WHERE $where", $params)['n'] ?? 0);
    $pg    = paginate($total, $requestedPage, $perPage);
    $items = repo_rows(
        'SELECT ' . PROJECT_CARD_COLUMNS . " $from WHERE $where
          ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT :lim OFFSET :off",
        $params + ['lim' => $pg['per'], 'off' => $pg['offset']]
    );

    return ['items' => $items, 'pg' => $pg];
}

/** Dernières réalisations (page d'accueil, pages de service). $serviceId = null : tous les services. */
function repo_latest_projects(int $limit, ?int $serviceId = null, ?int $excludeId = null): array
{
    $where = "p.status = 'published'";
    $params = [];
    if ($serviceId !== null) {
        $where .= ' AND p.service_id = :sid';
        $params['sid'] = $serviceId;
    }
    if ($excludeId !== null) {
        $where .= ' AND p.id <> :ex';
        $params['ex'] = $excludeId;
    }

    return repo_rows(
        'SELECT ' . PROJECT_CARD_COLUMNS . " FROM projects p JOIN services s ON s.id = p.service_id
           LEFT JOIN categories c ON c.id = p.category_id AND c.scope = 'project'
           LEFT JOIN media m ON m.id = p.cover_media_id
          WHERE $where ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT :lim",
        $params + ['lim' => $limit]
    );
}

function repo_project_by_slug(string $slug): ?array
{
    $row = repo_row(
        "SELECT p.*, s.name AS service_name, s.slug AS service_slug, s.icon AS service_icon, c.name AS category_name,
                m.stored_name, m.alt_text, m.width, m.height
           FROM projects p JOIN services s ON s.id = p.service_id
           LEFT JOIN categories c ON c.id = p.category_id
           LEFT JOIN media m ON m.id = p.cover_media_id
          WHERE p.slug = :s AND p.status = 'published'",
        ['s' => $slug]
    );
    if ($row !== null) {
        $row['images'] = repo_rows(
            'SELECT pi.caption, m.stored_name, m.alt_text, m.width, m.height
               FROM project_images pi JOIN media m ON m.id = pi.media_id
              WHERE pi.project_id = :id ORDER BY pi.sort_order, pi.id',
            ['id' => (int) $row['id']]
        );
    }

    return $row;
}

const PRODUCT_CARD_COLUMNS = "pr.id, pr.slug, pr.name, pr.description, pr.price_amount, pr.price_note,
        s.name AS service_name, s.slug AS service_slug, s.icon AS service_icon, m.stored_name, m.alt_text, m.width, m.height";

function repo_has_published_products(): bool
{
    static $has = null;

    return $has ??= (repo_row("SELECT 1 AS x FROM products WHERE status = 'published' LIMIT 1") !== null);
}

/** @return array{items:list<array>,pg:array} */
function repo_products_page(?string $serviceSlug, int $perPage, int $requestedPage): array
{
    $where = "pr.status = 'published'";
    $params = [];
    if ($serviceSlug !== null) {
        $where .= ' AND s.slug = :svc';
        $params['svc'] = $serviceSlug;
    }
    $from = 'FROM products pr JOIN services s ON s.id = pr.service_id AND s.status = \'published\'
             LEFT JOIN media m ON m.id = pr.media_id';
    $total = (int) (repo_row("SELECT COUNT(*) AS n $from WHERE $where", $params)['n'] ?? 0);
    $pg    = paginate($total, $requestedPage, $perPage);
    $items = repo_rows(
        'SELECT ' . PRODUCT_CARD_COLUMNS . " $from WHERE $where ORDER BY s.sort_order, pr.sort_order, pr.name LIMIT :lim OFFSET :off",
        $params + ['lim' => $pg['per'], 'off' => $pg['offset']]
    );

    return ['items' => $items, 'pg' => $pg];
}

function repo_product_by_slug(string $slug): ?array
{
    return repo_row(
        "SELECT pr.*, s.name AS service_name, s.slug AS service_slug, m.stored_name, m.alt_text, m.width, m.height
           FROM products pr JOIN services s ON s.id = pr.service_id AND s.status = 'published'
           LEFT JOIN media m ON m.id = pr.media_id
          WHERE pr.slug = :s AND pr.status = 'published'",
        ['s' => $slug]
    );
}

/** Produits d'un service (page de service). */
function repo_products_of_service(int $serviceId, int $limit = 6): array
{
    return repo_rows(
        "SELECT pr.slug, pr.name, pr.price_amount, pr.price_note FROM products pr
          WHERE pr.service_id = :id AND pr.status = 'published' ORDER BY pr.sort_order, pr.name LIMIT :lim",
        ['id' => $serviceId, 'lim' => $limit]
    );
}
