-- =============================================================================
-- Génie Solutions — DONNÉES DE DÉMONSTRATION (facultatif, jamais en production)
-- Tout est préfixé « [DÉMO] » et marqué is_demo = 1 pour être retrouvé et
-- supprimé facilement : DELETE FROM projects WHERE is_demo = 1;
-- Les coordonnées ci-dessous sont FICTIVES (plage réservée aux exemples).
-- =============================================================================
SET NAMES utf8mb4;

UPDATE site_settings SET setting_value = '[DÉMO] Numéro fictif : +000 00 000 0000' WHERE setting_key = 'announcement_message';
UPDATE site_settings SET setting_value = '+000 00 000 0000'     WHERE setting_key = 'phone';
UPDATE site_settings SET setting_value = 'contact@exemple.invalid' WHERE setting_key = 'email';
UPDATE site_settings SET setting_value = '[DÉMO] Adresse à renseigner' WHERE setting_key = 'address';
UPDATE site_settings SET setting_value = '[DÉMO] Horaires à renseigner' WHERE setting_key = 'opening_hours';

INSERT INTO projects (service_id, category_id, slug, title, summary, description, is_demo, status, published_at)
SELECT s.id, c.id, d.slug, d.title, d.summary, d.description, 1, 'published', NOW()
FROM (
  SELECT 'imprimerie-digitale-offset' AS svc, 'impression' AS cat, 'demo-impression-flyers' AS slug,
         '[DÉMO] Flyers promotionnels' AS title, 'Exemple de réalisation fictive.' AS summary,
         '<p>[DÉMO] Contenu d’exemple à remplacer par une vraie réalisation.</p>' AS description
  UNION ALL
  SELECT 'couture-industrielle', 'confection', 'demo-confection-uniformes',
         '[DÉMO] Uniformes scolaires', 'Exemple de réalisation fictive.',
         '<p>[DÉMO] Contenu d’exemple à remplacer par une vraie réalisation.</p>'
  UNION ALL
  SELECT 'broderie', 'broderie', 'demo-broderie-polos',
         '[DÉMO] Polos brodés', 'Exemple de réalisation fictive.',
         '<p>[DÉMO] Contenu d’exemple à remplacer par une vraie réalisation.</p>'
) d
JOIN services s   ON s.slug = d.svc
JOIN categories c ON c.scope = 'project' AND c.slug = d.cat;
