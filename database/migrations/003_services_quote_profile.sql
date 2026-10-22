-- Migration étape 5 : à exécuter UNIQUEMENT sur une base installée avec un schéma antérieur.
ALTER TABLE services ADD COLUMN quote_profile ENUM('generic','print','textile','embroidery') NOT NULL DEFAULT 'generic'
    COMMENT 'Champs spécifiques du formulaire de devis' AFTER icon;
UPDATE services SET quote_profile = 'print'      WHERE slug = 'imprimerie-digitale-offset';
UPDATE services SET quote_profile = 'textile'    WHERE slug = 'couture-industrielle';
UPDATE services SET quote_profile = 'embroidery' WHERE slug = 'broderie';
