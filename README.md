# Génie Solutions — site web (PHP procédural / MySQL)

> État : le site public, les formulaires et le schéma MySQL sont en place. L’administration comprend le suivi commercial et la gestion du contenu : pages statiques, FAQ, services, catalogue, réalisations et paramètres, avec permissions, validation des entrées et publication explicite.

## Organisation (adaptée de la base `atcm`)

La **racine du projet est la racine web** (organisation « à plat », comme la base et le site BIS).

| Dossier | Rôle | Équivalent dans la base `atcm` |
|---|---|---|
| `index.php` | Point d'entrée unique, table de routes (URL propres : `/services/broderie`) | `index.php` (`?page=` + scandir) |
| `pages/` | **Vues** de chaque page (+ `partials/`, `error.php`) | `pages/` |
| `php/` | **Logique** de chaque page : lit les données, appelle `render_page()` | `php/` |
| `menu/` | `head.php` (`<head>`), `menu.php` (annonce + navigation), `footer.php` | `menu/menu.php`, `footer.php` |
| `css/` `js/` `img/` `lib/` | Ressources publiques (`lib/` : Bootstrap et icônes en local) | idem |
| `uploads/` | Médias téléversés (aucune exécution de script) | `admin/uploads/` |
| `admin/` | Administration (`index.php`, `assets/`) | `admin/` (`?pages=`) |
| `includes/` | Code partagé privé : config, PDO, helpers, requêtes SQL, rendu | *(nouveau)* remplace `php/main_functions.php` |
| `storage/` `database/` `bin/` `tests/` | Journaux/fichiers privés, SQL, scripts CLI, tests | *(nouveau)* |

Principe : `php/<page>.php` prépare les données → `pages/<page>.php` les affiche, entouré de `menu/`.


## Administration

L’interface est accessible sur /admin/index.php. La connexion vérifie le compte actif, applique les permissions définies par rôle, enregistre les tentatives et renouvelle l’identifiant de session. Un compte administrateur n’est volontairement pas inclus ; après l’installation, créez le premier compte avec php bin/create-admin.php et choisissez vous-même un mot de passe fort.

Selon les permissions du compte, le panneau permet de consulter les indicateurs, rechercher/filtrer les devis, consulter une demande, modifier son statut et ajouter des notes internes, ainsi que lire et classer les messages de contact. Les pièces jointes sont téléchargées depuis le stockage privé avec vérification du chemin et envoi en pièce jointe.

Les images extraites des PDF sont conservées dans storage/private/media-imports/. Les aperçus sont servis par l’interface après contrôle de la permission media.manage. Elles ne sont pas ajoutées au site public ni aux réalisations tant que les légendes, textes alternatifs et autorisations de publication n’ont pas été validés. Voir docs/sources-pdf.md.

Le module « Pages du site » permet de modifier À propos, la politique de confidentialité et les mentions légales. Les URL restent fixes et le HTML est assaini. Les FAQ, services, catalogue, réalisations, partenaires, témoignages et contenus de l’accueil se gèrent dans l’administration. La médiathèque accepte les images téléversées depuis l’ordinateur et permet leur réutilisation sur les réalisations. La page Paramètres regroupe l’identité, les coordonnées, les réseaux sociaux, la devise et le SEO; les secrets restent dans l’environnement. Les textes juridiques et les contenus de démonstration doivent être remplacés/validés par l’entreprise avant publication.

## Ce qui a été corrigé par rapport à la base `atcm`

- Identifiants de base **jamais en dur** : `.env`, de préférence **hors racine web**.
- Routage par **table explicite** (pas de `scandir` à chaque requête), URL lisibles pour le SEO.
- **Gardes d'accès direct** : tout fichier inclus s'arrête si `GS_ROOT` n'est pas défini ; `bootstrap.php` exige `GS_ENTRY`.
- Dossiers privés bloqués par `.htaccess` (racine **et** dans chaque dossier).
- Aucune CDN, aucun style/script en ligne → CSP stricte ; pas de jQuery/owl/wow (JavaScript natif).

## Installation locale (Laragon / PHP 8.1+)

1. Créer la base `genie_solutions` (utf8mb4) et deux comptes MySQL : installation (`ALL`, usage unique) et applicatif (`SELECT, INSERT, UPDATE, DELETE`).
2. Copier `.env.example` vers **`../genie-solutions.env`** (dossier parent, hors racine web) ou à défaut `.env`. Renseigner `DB_*` et `APP_SECRET`.
   Ordre de recherche : variable `GS_ENV_FILE` → `../genie-solutions.env` → `.env`.
3. `php bin/install.php --demo` (sans `--demo` en production). Refuse de tourner si la base contient déjà des tables.
4. Passer `.env` sur le compte applicatif, puis `php bin/create-admin.php` (mot de passe saisi à l'invite).
5. Lancer : `php -S 127.0.0.1:8000 index.php` — ou virtual host Laragon sur la racine du projet.

Base déjà installée avant : vérifier les migrations `002_services_icon.sql`, `003_services_quote_profile.sql` et `004_partners.sql` dans `database/migrations/` avant de mettre à jour le site.

## Dépannage : « 503 – Service momentanément indisponible »

Cette page s'affiche quand le site ne peut pas lire sa base de données. **Lancez d'abord le diagnostic** :

```
php bin/check.php                         # PHP, configuration, base, tables, dossiers, SMTP
php bin/check.php --url=https://votre-site  # + vérifie que les fichiers privés ne sont PAS accessibles
```

Sur un poste de développement (`localhost`, `*.test`), la page 503 affiche elle-même la cause et le correctif ;
sur un site en ligne elle reste volontairement générique (la cause est dans `storage/logs/php-error.log`).

| Cause | Symptôme | Correctif |
|---|---|---|
| Aucun fichier de configuration | « Aucune configuration… » | Copier `.env.example` vers `.env` (ou `../genie-solutions.env`) et le renseigner |
| Mauvais identifiants | « MySQL refuse l'utilisateur… » | Laragon : `DB_USER=root`, `DB_PASS` vide |
| Base inexistante | « La base… n'existe pas » | Créer la base (utf8mb4) |
| Base vide / obsolète | « tables absentes ou obsolètes » | `php bin/install.php --demo`, ou les migrations de `database/migrations/` |
| MySQL arrêté | « Le serveur MySQL ne répond pas » | Démarrer MySQL (Laragon : « Démarrer tout ») |

## Formulaires, pièces jointes et e-mails

- **La base de données est la source de vérité** : une demande est enregistrée même si l'e-mail échoue.
  `quote_requests.notified_at` n'est renseigné que si le serveur SMTP a répondu « 250 » après `DATA`.
- **SMTP** (facultatif) : `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (`tls` = STARTTLS, `ssl`, `none`), `MAIL_USER`, `MAIL_PASS` dans le fichier d'environnement ;
  adresses `mail_from_address` et `mail_notify_address` dans les paramètres du site. Le certificat TLS est vérifié ;
  les identifiants ne partent jamais en clair hors boucle locale. `MAIL_VERIFY_PEER=false` : uniquement pour un test local.
- **Anti-abus** : jeton CSRF à usage unique, champ piège, délai minimal (`FORM_MIN_SECONDS`, 3 s), 8 envois/heure/IP (devis et contact comptés séparément).
  ⚠ Derrière un proxy ou un hébergeur mutualisé qui masque l'IP, `REMOTE_ADDR` peut être commun à tous les visiteurs :
  la limite s'appliquerait alors à tout le monde. À vérifier à la mise en ligne.
- **Pièces jointes** : JPG, PNG, WebP, PDF (type déterminé par le contenu), 5 Mo maximum (et limites PHP `upload_max_filesize` / `post_max_size`),
  stockées dans `storage/private/quotes/` sous un nom aléatoire. Un fichier est contrôlé, **pas désinfecté** : un JPEG « polyglotte »
  contenant du code est accepté mais ne peut jamais être exécuté ni servi. L'administration devra le télécharger avec
  `Content-Disposition: attachment` et `X-Content-Type-Options: nosniff`, après vérification des droits.
- Conservation des demandes (`quote_retention_months`) : paramètre prévu, **purge non encore implémentée**.

## Tests

```
php tests/step2_php.php                       # session, CSRF, flash, helpers, contrastes WCAG
php tests/step3_php.php                       # assainisseur HTML (XSS), pagination, prix, slugs
bash tests/step_diag.sh                       # page 503 selon la cause de panne + aide visible seulement en local
bash tests/step5_mail.sh                      # client SMTP contre un faux serveur (STARTTLS, SSL, auth, injections, échecs)
bash tests/step5.sh                           # formulaires : CSRF, validation, uploads piégés, anti-robots, débit, pannes SMTP/base
bash tests/step2.sh 8089                      # HTTP : en-têtes, CSP, dossiers privés, XSS (serveur PHP intégré)
bash tests/step3.sh 8089                      # HTTP : pages publiques (crée puis supprime ses données de test)
bash tests/step2.sh http://127.0.0.1:8081     # idem sur un Apache déjà lancé (valide le .htaccess)
bash tests/step3.sh http://127.0.0.1:8081
NODE_PATH=<node_modules avec jsdom> node tests/lightbox.test.js   # comportement de la visionneuse
```

`step3.sh` utilise le compte applicatif et nettoie ses données (préfixes `test-`, `t3-`).
`step5.sh` et `step5_mail.sh` utilisent le compte **root** MariaDB local (socket) pour inspecter la base et simuler une panne
(`RENAME TABLE`). **Ne jamais les exécuter sur une base de production.**

## Sécurité de la structure à plat : à lire avant la mise en ligne

Toute la protection des dossiers privés repose sur `.htaccess` (Apache 2.4, `AllowOverride All`, `mod_rewrite`).
Contrôle négatif réalisé : **sans** `.htaccess`, les fichiers PHP internes ne renvoient rien (gardes), mais
`.env`, `database/*.sql` et `README.md` seraient lisibles. Donc, en production :

1. Placer le fichier d'environnement **hors de la racine web** (`../genie-solutions.env`).
2. **Ne pas déployer** `database/`, `tests/`, `README.md`, `.env.example` (ou les protéger séparément).
3. Vérifier après déploiement : `/.env`, `/includes/config.php`, `/database/schema.sql` doivent répondre 403/404 (`bash tests/step2.sh https://votre-domaine`).
4. Sur **Nginx / IIS** le `.htaccess` est ignoré : reproduire les règles (exemple Nginx **non testé**) :

```
location ~ ^/(includes|php|pages|menu|storage|database|bin|tests)(/|$) { deny all; }
location ~ /\.                                                        { deny all; }
location ~* \.(sql|log|md|bak|ini|json|ya?ml|env)$                    { deny all; }
location ^~ /uploads/ { location ~* \.(php[0-9s]?|phtml|phar|html?|svg|js)$ { deny all; } }
location / { try_files $uri $uri/ /index.php?$query_string; }
# PHP-FPM : n'exécuter que /index.php et /admin/index.php
```

## Vérifications restantes avant la mise en ligne

- Remplacer les coordonnées de démonstration et vérifier les coordonnées réelles dans Paramètres.
- Retirer les réalisations marquées « démonstration » et publier uniquement des projets autorisés par l'entreprise.
- Remplacer les médias de démonstration par les photos approuvées et vérifier leurs textes alternatifs.
- Finaliser et faire valider les mentions légales et la politique de confidentialité.
- Configurer SMTP si l'entreprise veut recevoir les notifications par e-mail.
- Vérifier les parcours sur le domaine HTTPS définitif et refaire `php bin/check.php --url=https://votre-domaine` après déploiement.
- La purge automatique des demandes selon `quote_retention_months` n'est pas encore implémentée.
