-- Migration étape 3 : à exécuter UNIQUEMENT si la base a été installée avec le schéma de l'étape 1/2.
ALTER TABLE services ADD COLUMN icon VARCHAR(60) NULL COMMENT 'Classe Bootstrap Icons, ex. bi-printer' AFTER cta_text;
UPDATE services SET icon = 'bi-printer' WHERE slug = 'imprimerie-digitale-offset' AND icon IS NULL;
UPDATE services SET icon = 'bi-scissors' WHERE slug = 'couture-industrielle' AND icon IS NULL;
UPDATE services SET icon = 'bi-palette' WHERE slug = 'broderie' AND icon IS NULL;
