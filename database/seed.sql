-- =============================================================================
-- Génie Solutions — Données de référence (OBLIGATOIRES à l'installation)
--
-- Contient : rôles, permissions, paramètres par défaut, contenus rédactionnels
-- fournis par l'entreprise (services, FAQ, À propos…).
--
-- NE CONTIENT AUCUN compte utilisateur : créer le premier administrateur avec
--   php bin/create-admin.php
-- NE CONTIENT AUCUNE coordonnée, aucun prix, aucun témoignage : ces
-- informations doivent être saisies dans l'administration.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- Rôles
INSERT INTO roles (slug, name, description, is_system) VALUES
('super_admin',     'Super administrateur',  'Accès total, y compris rôles et utilisateurs', 1),
('admin',           'Administrateur',        'Gestion complète du site, sans gestion des rôles', 1),
('content_manager', 'Gestionnaire de contenu', 'Pages, services, réalisations, FAQ, médias', 1),
('sales_manager',   'Gestionnaire commercial', 'Devis, messages et témoignages', 1);

-- ---------------------------------------------------------------- Permissions
INSERT INTO permissions (code, label) VALUES
('dashboard.view',     'Voir le tableau de bord'),
('pages.manage',       'Gérer les pages et sections d’accueil'),
('services.manage',    'Gérer les services et catégories'),
('products.manage',    'Gérer le catalogue'),
('projects.manage',    'Gérer les réalisations'),
('media.manage',       'Gérer la médiathèque'),
('faqs.manage',        'Gérer la FAQ'),
('testimonials.manage','Valider les témoignages'),
('quotes.view',        'Consulter les demandes de devis'),
('quotes.manage',      'Traiter les devis (statuts, notes)'),
('quotes.export',      'Exporter les devis en CSV'),
('messages.manage',    'Gérer les messages de contact'),
('settings.manage',    'Gérer les paramètres du site'),
('seo.manage',         'Gérer le référencement SEO'),
('users.manage',       'Gérer les utilisateurs'),
('roles.manage',       'Gérer les rôles et permissions'),
('logs.view',          'Consulter le journal d’activité'),
('backups.manage',     'Gérer les sauvegardes');

-- super_admin : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'super_admin';

-- admin : tout sauf la gestion des rôles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'admin' AND p.code <> 'roles.manage';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view','pages.manage','services.manage','products.manage',
                'projects.manage','media.manage','faqs.manage','seo.manage')
WHERE r.slug = 'content_manager';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view','quotes.view','quotes.manage','quotes.export',
                'messages.manage','testimonials.manage')
WHERE r.slug = 'sales_manager';

-- ---------------------------------------------------------------- Paramètres
-- Coordonnées VOLONTAIREMENT vides : aucune donnée inventée.
INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
('company_name',        'Génie Solutions', 'identity'),
('tagline',             'Votre vision, notre savoir-faire, des résultats à la hauteur de vos ambitions.', 'identity'),
('footer_text',         'Des solutions en imprimerie digitale et offset, couture industrielle et broderie professionnelle pour accompagner vos projets de communication et de personnalisation textile.', 'identity'),
('logo_media_id',       '', 'identity'),
('favicon_media_id',    '', 'identity'),
('announcement_message','', 'contact'),
('phone',               '', 'contact'),
('whatsapp',            '', 'contact'),
('email',               '', 'contact'),
('address',             '', 'contact'),
('opening_hours',       '', 'contact'),
('facebook_url',        '', 'social'),
('instagram_url',       '', 'social'),
('linkedin_url',        '', 'social'),
('currency_code',       '', 'commerce'),
('locale',              'fr', 'commerce'),
('mail_from_address',   '', 'mail'),
('mail_from_name',      'Génie Solutions', 'mail'),
('mail_notify_address', '', 'mail'),
('seo_default_title',   'Génie Solutions — Imprimerie, couture industrielle et broderie', 'seo'),
('seo_default_description', 'Imprimerie digitale et offset, couture industrielle et broderie professionnelle : demandez un devis adapté à votre projet.', 'seo'),
('maintenance_mode',    '0', 'system'),
('quote_max_attachment_mb', '5', 'system'),
('quote_retention_months',  '24', 'system');

-- ---------------------------------------------------------------- Catégories de réalisations
INSERT INTO categories (scope, name, slug, sort_order) VALUES
('project', 'Impression',          'impression', 1),
('project', 'Confection textile',  'confection',  2),
('project', 'Broderie',            'broderie',    3);

-- ---------------------------------------------------------------- Services (contenus fournis)
INSERT INTO services (slug, name, short_description, description, approach_text, cta_text, icon, quote_profile, sort_order, status) VALUES
('imprimerie-digitale-offset',
 'Imprimerie digitale et offset',
 'Donnez de la visibilité à votre marque grâce à des supports imprimés adaptés à votre communication.',
 '<p>L’impression joue un rôle essentiel dans la visibilité d’une entreprise, d’une institution ou d’un événement. Génie Solutions vous accompagne dans le choix de supports imprimés adaptés à votre objectif, à votre identité visuelle et aux caractéristiques de votre projet.</p>',
 'Nous prenons en compte le format, le support, la quantité, les couleurs, les finitions et le délai souhaité afin de préparer une proposition adaptée à votre commande.',
 'Parlez-nous de votre projet d’impression et demandez votre devis.',
 'bi-printer', 'print', 1, 'published'),
('couture-industrielle',
 'Couture industrielle',
 'Des vêtements conçus pour répondre à vos besoins professionnels et institutionnels.',
 '<p>Génie Solutions propose des solutions de confection textile destinées aux entreprises, aux établissements scolaires, aux organisations et aux professionnels ayant besoin de vêtements personnalisés ou de tenues en série.</p>',
 'Le type de vêtement, le tissu, les tailles, les couleurs, les quantités et les éventuelles personnalisations sont étudiés afin de préciser les exigences de production.',
 'Présentez-nous votre commande textile pour obtenir une proposition adaptée.',
 'bi-scissors', 'textile', 2, 'published'),
('broderie',
 'Broderie professionnelle',
 'Personnalisez vos vêtements et textiles grâce à la broderie de logos, noms, emblèmes et motifs.',
 '<p>La broderie permet de personnaliser les vêtements et les textiles avec une finition distinctive. Génie Solutions vous accompagne dans la préparation de vos projets de broderie pour renforcer l’identité visuelle de votre entreprise, de votre organisation ou de votre événement.</p>',
 'Le motif, les couleurs, les dimensions, le support textile et la quantité sont pris en compte avant la validation de la commande.',
 'Envoyez votre motif ou votre logo et demandez un devis.',
 'bi-palette', 'embroidery', 3, 'published');

INSERT INTO service_offerings (service_id, label, sort_order)
SELECT s.id, o.label, o.sort_order FROM services s JOIN (
  SELECT 'imprimerie-digitale-offset' AS slug, 'Cartes de visite' AS label, 1 AS sort_order UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Flyers et dépliants', 2 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Affiches publicitaires', 3 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Brochures et catalogues', 4 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Invitations et faire-part', 5 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Documents professionnels', 6 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Banderoles et supports grand format', 7 UNION ALL
  SELECT 'imprimerie-digitale-offset', 'Autres supports personnalisés', 8 UNION ALL
  SELECT 'couture-industrielle', 'Uniformes scolaires', 1 UNION ALL
  SELECT 'couture-industrielle', 'Tenues professionnelles', 2 UNION ALL
  SELECT 'couture-industrielle', 'Vêtements de travail', 3 UNION ALL
  SELECT 'couture-industrielle', 'Tenues pour événements', 4 UNION ALL
  SELECT 'couture-industrielle', 'Vêtements personnalisés', 5 UNION ALL
  SELECT 'couture-industrielle', 'Confection en série selon les spécifications convenues', 6 UNION ALL
  SELECT 'broderie', 'Broderie de logos', 1 UNION ALL
  SELECT 'broderie', 'Personnalisation de polos et de chemises', 2 UNION ALL
  SELECT 'broderie', 'Marquage de tenues professionnelles', 3 UNION ALL
  SELECT 'broderie', 'Personnalisation d’uniformes', 4 UNION ALL
  SELECT 'broderie', 'Broderie sur casquettes et autres supports compatibles', 5 UNION ALL
  SELECT 'broderie', 'Commandes personnalisées en série', 6
) o ON o.slug = s.slug;

-- ---------------------------------------------------------------- Page d'accueil
INSERT INTO home_sections (section_key, title, subtitle, body, cta_label, cta_url, cta2_label, cta2_url, sort_order) VALUES
('hero',
 'Des solutions professionnelles pour donner vie à vos idées.',
 'De l’impression à la confection textile, en passant par la broderie personnalisée, Génie Solutions vous accompagne dans la réalisation de vos projets de communication visuelle et de production textile.',
 NULL, 'Demander un devis', '/demande-de-devis', 'Découvrir nos services', '/services', 1),
('intro',
 'Le savoir-faire au service de vos projets',
 NULL,
 'Chez Génie Solutions, nous réunissons créativité, précision et savoir-faire technique pour répondre à vos besoins en imprimerie, couture industrielle et broderie. Nous vous accompagnons de l’expression de votre besoin jusqu’à la réalisation de votre commande, avec une approche professionnelle et personnalisée.',
 NULL, NULL, NULL, NULL, 2),
('gallery',
 'Découvrez notre savoir-faire en images',
 'Explorez une sélection de réalisations en impression, confection textile et broderie. Chaque projet illustre un besoin particulier et une solution adaptée.',
 NULL, 'Voir toutes nos réalisations', '/realisations', NULL, NULL, 3),
('cta',
 'Vous avez un projet en tête ?',
 'Besoin d’imprimer des supports publicitaires, de confectionner des uniformes ou de personnaliser vos vêtements par broderie ? Présentez-nous votre besoin et recevez une proposition adaptée à votre demande.',
 NULL, 'Demander un devis', '/demande-de-devis', 'Nous contacter', '/contact', 4);

INSERT INTO why_arguments (title, description, icon, sort_order) VALUES
('Une approche personnalisée', 'Chaque projet est étudié en fonction de vos besoins, de vos quantités et de vos contraintes.', 'bi-person-check', 1),
('Une attention portée aux finitions', 'Nous accordons une importance particulière à la présentation, à la précision et à la cohérence du résultat attendu.', 'bi-bullseye', 2),
('Des solutions adaptées', 'Nous vous aidons à identifier les prestations et les options correspondant à votre projet.', 'bi-sliders', 3),
('Un accompagnement professionnel', 'De la demande de devis à la réalisation, nous privilégions une communication claire et un suivi adapté.', 'bi-chat-dots', 4);

INSERT INTO order_steps (title, description, sort_order) VALUES
('Présentation du besoin', 'Vous nous décrivez votre projet : service, quantités, caractéristiques souhaitées.', 1),
('Demande de devis', 'Vous remplissez le formulaire ; une référence de suivi vous est communiquée.', 2),
('Validation du devis', 'Après examen, une proposition vous est transmise pour validation.', 3),
('Production', 'Votre commande est réalisée selon les spécifications convenues.', 4),
('Livraison ou retrait', 'Selon les modalités proposées par l’entreprise.', 5);

-- ---------------------------------------------------------------- FAQ (contenus fournis)
INSERT INTO faqs (question, answer, sort_order) VALUES
('Quels services propose Génie Solutions ?', 'Nous proposons des services d’imprimerie digitale et offset, de couture industrielle et de broderie professionnelle, selon les prestations disponibles.', 1),
('Comment obtenir un devis ?', 'Remplissez le formulaire de demande de devis en précisant le service recherché, les quantités, les caractéristiques souhaitées et votre délai. Notre équipe pourra étudier votre demande.', 2),
('Puis-je commander des vêtements personnalisés ?', 'Vous pouvez soumettre un projet de confection ou de personnalisation textile. Les possibilités seront confirmées en fonction du type de vêtement, des quantités et des exigences techniques.', 3),
('Puis-je fournir mon propre logo ou modèle ?', 'Oui, vous pouvez joindre un fichier à votre demande lorsque le formulaire le permet. La compatibilité du fichier et son utilisation pour la production devront être vérifiées.', 4),
('Quels sont les délais de réalisation ?', 'Les délais dépendent de la nature du travail, des quantités, des matériaux et de la charge de production. Un délai indicatif devra être communiqué après examen de la commande.', 5),
('Comment suivre ma demande ?', 'Utilisez la référence communiquée lors de votre demande ou contactez l’entreprise par les moyens officiels indiqués sur le site.', 6),
('Les prix sont-ils affichés sur le site ?', 'Les tarifs peuvent être publiés lorsqu’ils sont définis. Pour les commandes personnalisées, une demande de devis permet d’évaluer les besoins spécifiques.', 7);

-- ---------------------------------------------------------------- Pages
INSERT INTO pages (slug, title, content, seo_title, seo_description, status) VALUES
('a-propos',
 'Génie Solutions, votre partenaire en impression et production textile',
 '<p>Génie Solutions propose des services d’imprimerie digitale et offset, de couture industrielle et de broderie professionnelle. Notre vocation est d’aider nos clients à concrétiser leurs projets grâce à des solutions adaptées à leurs besoins en communication visuelle et en personnalisation textile.</p><p>Nous accordons une place importante à l’écoute, à la compréhension des exigences de chaque commande et à la qualité des finitions. Notre approche consiste à analyser votre besoin, à vous orienter vers les solutions appropriées et à assurer un suivi cohérent de votre demande.</p><h2>Notre mission</h2><p>Accompagner nos clients dans la réalisation de leurs projets d’impression et de production textile en proposant des solutions professionnelles adaptées à leurs exigences.</p><h2>Notre vision</h2><p>Développer une entreprise reconnue pour son sérieux, sa capacité d’adaptation et la qualité de son accompagnement dans les domaines de l’impression, de la confection et de la broderie.</p><h2>Nos valeurs</h2><ul><li>Professionnalisme.</li><li>Créativité.</li><li>Précision.</li><li>Écoute du client.</li><li>Responsabilité.</li><li>Recherche constante d’amélioration.</li></ul>',
 'À propos — Génie Solutions',
 'Découvrez Génie Solutions : mission, vision et valeurs d’un partenaire en impression, couture industrielle et broderie.',
 'published'),
('politique-de-confidentialite', 'Politique de confidentialité', NULL, 'Politique de confidentialité — Génie Solutions', NULL, 'draft'),
('mentions-legales',             'Mentions légales',               NULL, 'Mentions légales — Génie Solutions', NULL, 'draft');
