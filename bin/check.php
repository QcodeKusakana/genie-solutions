<?php
declare(strict_types=1);

/**
 * Diagnostic de l'installation (ligne de commande uniquement).
 *
 *   php bin/check.php                      vérifie PHP, la configuration, la base, les dossiers, le SMTP
 *   php bin/check.php --url=https://site   vérifie en plus que les fichiers privés ne sont PAS accessibles en HTTP
 *
 * Code de sortie : 0 = rien de bloquant ; 1 = au moins un problème bloquant.
 * N'affiche jamais de mot de passe ni de secret.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
define('GS_ROOT', $root); // requis par les gardes d'accès direct des fichiers inclus
require_once $root . '/includes/env.php';

$blocking = 0;
$warnings = 0;
function out(string $tag, string $msg, ?string $fix = null): void
{
    global $blocking, $warnings;
    if ($tag === 'KO') { $blocking++; }
    if ($tag === '!!') { $warnings++; }
    echo sprintf('  [%s] %s', $tag, $msg), "\n";
    if ($fix !== null && $tag !== 'OK') {
        echo '        → ', $fix, "\n";
    }
}
function section(string $t): void { echo "\n", $t, "\n"; }

$url = null;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('#^--url=(https?://\S+)$#', $a, $m)) { $url = rtrim($m[1], '/'); }
}

echo "Génie Solutions — diagnostic de l'installation\n";

/* ------------------------------------------------------------------ PHP */
section('1. PHP');
PHP_VERSION_ID >= 80100 ? out('OK', 'PHP ' . PHP_VERSION) : out('KO', 'PHP ' . PHP_VERSION . ' : version 8.1 minimum requise', 'Mettez PHP à jour (Laragon : menu → PHP → Version).');
$exts = ['pdo_mysql' => true, 'mbstring' => true, 'fileinfo' => true, 'dom' => true, 'openssl' => false, 'gd' => false];
foreach ($exts as $ext => $required) {
    if (extension_loaded($ext)) {
        out('OK', "extension $ext");
    } else {
        out($required ? 'KO' : '!!', "extension $ext absente" . ($required ? '' : ' (recommandée)'),
            "Activez « extension=$ext » dans php.ini (Laragon : menu → PHP → Extensions)."
            . ($ext === 'gd' ? ' Nécessaire pour le redimensionnement d’images (administration).' : ''));
    }
}

/* ------------------------------------------------------------------ Configuration */
section('2. Fichier de configuration');
$paths = env_search_paths();
$vars = env_load();
$env = (string) ($vars['APP_ENV'] ?? (getenv('APP_ENV') ?: 'production'));
$found = env_file_path();
if ($found !== null) {
    $inside = str_starts_with($found, $root . DIRECTORY_SEPARATOR) || str_starts_with($found, $root . '/');
    out('OK', 'fichier d’environnement : ' . ($inside ? '.' . substr($found, strlen($root)) : $found));
    if ($inside) {
        out($env === 'production' ? '!!' : 'OK', 'le fichier se trouve dans le dossier du site' . ($env === 'production' ? ' (racine web)' : ' (acceptable en local)'),
            'En production, placez-le un cran au-dessus : ' . dirname($root) . '/genie-solutions.env');
    }
} else {
    out('KO', 'aucun fichier d’environnement trouvé. Chemins cherchés :', 'Copiez .env.example vers l’un d’eux puis renseignez-le.');
    foreach ($paths as $p) { echo '          - ', $p, "\n"; }
}
$g = static fn(string $k): string => (string) ($vars[$k] ?? getenv($k) ?: '');
foreach (['DB_NAME', 'DB_USER'] as $k) {
    $g($k) !== '' ? out('OK', "$k renseigné") : out('KO', "$k est vide", "Renseignez $k dans le fichier d’environnement.");
}
$g('DB_PASS') !== '' ? out('OK', 'DB_PASS renseigné') : out($env === 'production' ? '!!' : 'OK', 'DB_PASS est vide' . ($env === 'production' ? '' : ' (normal sous Laragon : root sans mot de passe)'), 'Interdit en production : définissez un mot de passe.');
strlen($g('APP_SECRET')) >= 32
    ? out('OK', 'APP_SECRET défini (' . strlen($g('APP_SECRET')) . ' caractères)')
    : out('KO', 'APP_SECRET absent ou trop court (32 caractères minimum)', 'Générez-le : php -r "echo bin2hex(random_bytes(32));" — requis par les formulaires (limitation de débit).');
$g('APP_URL') !== '' ? out('OK', 'APP_URL = ' . $g('APP_URL')) : out('!!', 'APP_URL est vide', 'Sans elle, pas d’URL canonique ni d’image Open Graph (SEO). Ex. http://genie-solutions.test');
out('OK', "APP_ENV = $env");
if ($env === 'production' && in_array(strtolower($g('APP_DEBUG')), ['1', 'true', 'yes', 'on'], true)) {
    out('!!', 'APP_DEBUG actif en production', 'Mettez APP_DEBUG=false.');
}

/* ------------------------------------------------------------------ Base de données */
section('3. Base de données');
$pdo = null;
if ($g('DB_NAME') === '' || $g('DB_USER') === '') {
    out('KO', 'connexion impossible : DB_NAME / DB_USER manquants');
} else {
    $host = $g('DB_HOST') ?: '127.0.0.1';
    $port = (int) ($g('DB_PORT') ?: 3306);
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $serverOk = false;
    try {
        $srv = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $g('DB_USER'), $g('DB_PASS'), $opt);
        $serverOk = true;
        $ver = (string) $srv->query('SELECT VERSION()')->fetchColumn();
        out('OK', "serveur MySQL/MariaDB joignable ($host:$port, version $ver) et identifiants acceptés");
    } catch (PDOException $e) {
        $m = [];
        preg_match('/\[(\d{4})\]/', $e->getMessage(), $m);
        $c = (int) ($m[1] ?? 0);
        if ($c === 1045) {
            out('KO', 'identifiants refusés (DB_USER / DB_PASS)', 'Sous Laragon : DB_USER=root et DB_PASS vide. Sinon, vérifiez l’utilisateur MySQL.');
        } elseif ($c === 2002 || $c === 2003) {
            out('KO', "serveur injoignable sur $host:$port", 'Démarrez MySQL (Laragon : bouton « Démarrer tout »). Essayez DB_HOST=127.0.0.1 plutôt que localhost.');
        } else {
            out('KO', 'connexion impossible (code ' . ($c ?: '?') . ')', 'Consultez storage/logs/php-error.log.');
        }
    }
    if ($serverOk) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname={$g('DB_NAME')};charset=utf8mb4", $g('DB_USER'), $g('DB_PASS'), $opt);
            out('OK', 'base « ' . $g('DB_NAME') . ' » accessible');
        } catch (PDOException $e) {
            out('KO', 'la base « ' . $g('DB_NAME') . ' » n’existe pas ou est inaccessible', 'Créez-la (utf8mb4_unicode_ci), puis : php bin/install.php --demo  (ou --create-db si vos droits le permettent).');
        }
    }
}

if ($pdo !== null) {
    // Tables attendues : lues dans schema.sql (source unique)
    $schema = (string) file_get_contents($root . '/database/schema.sql');
    preg_match_all('/CREATE TABLE (\w+)/', $schema, $mm);
    $expected = $mm[1];
    $have = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    $have = array_map('strtolower', $have);
    $missing = array_values(array_filter($expected, static fn($t) => !in_array(strtolower($t), $have, true)));
    if ($missing === []) {
        out('OK', count($expected) . ' tables présentes');
    } elseif (count($missing) === count($expected)) {
        out('KO', 'la base est VIDE : le script d’installation n’a pas été exécuté', 'Lancez : php bin/install.php --demo   (sans --demo en production).');
    } else {
        out('KO', 'tables manquantes : ' . implode(', ', $missing), 'Base d’une ancienne version : appliquez les fichiers de database/migrations/ dans l’ordre.');
    }
    if ($missing === []) {
        // Colonnes ajoutées après la première version
        foreach (['services' => ['icon', 'quote_profile']] as $tbl => $cols) {
            $have = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
            $have->execute([$tbl]);
            $have = array_map('strtolower', $have->fetchAll(PDO::FETCH_COLUMN));
            foreach ($cols as $c) {
                in_array($c, $have, true) ? out('OK', "colonne $tbl.$c") : out('KO', "colonne $tbl.$c absente (base d’une version antérieure)",
                    'Appliquez database/migrations/' . ($c === 'icon' ? '002_services_icon.sql' : '003_services_quote_profile.sql') . '.');
            }
        }
        $n = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        $n('SELECT COUNT(*) FROM roles') > 0 && $n('SELECT COUNT(*) FROM permissions') > 0
            ? out('OK', 'rôles et permissions présents') : out('KO', 'rôles/permissions absents (seed.sql non importé)', 'Réinstallez : php bin/install.php.');
        $n('SELECT COUNT(*) FROM site_settings') > 0 ? out('OK', 'paramètres du site présents') : out('KO', 'table site_settings vide', 'Réinstallez : php bin/install.php.');
        $n("SELECT COUNT(*) FROM services WHERE status = 'published'") > 0 ? out('OK', 'services publiés présents') : out('!!', 'aucun service publié', 'Importez seed.sql ou publiez des services depuis l’administration.');
        $n('SELECT COUNT(*) FROM users') > 0 ? out('OK', 'au moins un administrateur') : out('!!', 'aucun compte administrateur', 'Créez-le : php bin/create-admin.php');
        if ($n('SELECT COUNT(*) FROM projects WHERE is_demo = 1') > 0) {
            out($env === 'production' ? '!!' : 'OK', 'données de démonstration présentes' . ($env === 'production' ? ' EN PRODUCTION' : ' (normal en local)'),
                'Supprimez-les : DELETE FROM projects WHERE is_demo = 1;');
        }
        // Principe du moindre privilège pour le compte de l'application
        $grants = implode(' ', array_map('strval', array_map('current', $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM))));
        if (preg_match('/ALL PRIVILEGES|\bDROP\b|\bCREATE\b|\bALTER\b/i', $grants)) {
            out($env === 'production' ? '!!' : 'OK', 'le compte MySQL peut modifier la structure (DROP/CREATE/ALTER)' . ($env === 'production' ? '' : ' — acceptable en local'),
                'En production, utilisez pour le site un compte limité à SELECT, INSERT, UPDATE, DELETE.');
        } else {
            out('OK', 'compte MySQL à privilèges limités (pas de DDL)');
        }
    }
}

/* ------------------------------------------------------------------ Dossiers */
section('4. Dossiers et limites');
foreach (['storage/logs' => 'journaux', 'storage/private/quotes' => 'pièces jointes des devis', 'uploads' => 'images du site'] as $dir => $why) {
    $p = $root . '/' . $dir;
    if (!is_dir($p)) {
        out('!!', "$dir absent ($why)", "Créez-le : mkdir $dir");
    } elseif (!is_writable($p)) {
        out('!!', "$dir non modifiable par cet utilisateur ($why)", 'Sur le serveur web, donnez les droits d’écriture à l’utilisateur du serveur (www-data, etc.).');
    } else {
        out('OK', "$dir accessible en écriture ($why)");
    }
}
$ini = static function (string $k): int {
    $v = trim((string) ini_get($k)); $n = (int) $v;
    return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
};
out('OK', sprintf('upload_max_filesize = %s ; post_max_size = %s (pièces jointes limitées au plus petit, 5 Mo max)', ini_get('upload_max_filesize'), ini_get('post_max_size')));
if ($ini('upload_max_filesize') < 5 << 20) {
    out('!!', 'upload_max_filesize < 5 Mo : les pièces jointes seront limitées à ' . ini_get('upload_max_filesize'), 'Augmentez upload_max_filesize et post_max_size dans php.ini si besoin.');
}

/* ------------------------------------------------------------------ SMTP */
section('5. E-mails (facultatif)');
$mh = $g('MAIL_HOST');
if ($mh === '') {
    out('OK', 'SMTP non configuré : les demandes sont enregistrées en base, sans notification par e-mail');
} else {
    $mp = (int) ($g('MAIL_PORT') ?: 587);
    $fp = @stream_socket_client("tcp://$mh:$mp", $en, $es, 5);
    if ($fp) {
        fclose($fp);
        out('OK', "serveur SMTP joignable ($mh:$mp, chiffrement : " . ($g('MAIL_ENCRYPTION') ?: 'tls') . ')');
    } else {
        out('!!', "serveur SMTP injoignable ($mh:$mp)", 'Vérifiez MAIL_HOST/MAIL_PORT et que l’hébergeur autorise les connexions SMTP sortantes.');
    }
}

/* ------------------------------------------------------------------ Exposition HTTP */
if ($url !== null) {
    section("6. Exposition en HTTP ($url) : ces adresses doivent répondre 403 ou 404");
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 8, 'follow_location' => 0], 'ssl' => ['verify_peer' => !in_array(strtolower($g('MAIL_VERIFY_PEER')), ['0', 'false'], true)]]);
    foreach (['/.env', '/.env.example', '/includes/config.php', '/includes/bootstrap.php', '/php/home.php', '/pages/home.php', '/menu/menu.php',
              '/database/schema.sql', '/storage/logs/php-error.log', '/bin/install.php', '/bin/check.php', '/tests/step2.sh', '/README.md'] as $path) {
        $body = @file_get_contents($url . $path, false, $ctx, 0, 4096);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) { $code = (int) $m[1]; }
        }
        if ($code === 0) {
            out('!!', "$path : pas de réponse", 'Le site est-il joignable à cette adresse ?');
        } elseif (in_array($code, [403, 404], true)) {
            out('OK', "$path → $code");
        } elseif ($code === 200 && ($body === '' || $body === false)) {
            out('OK', "$path → 200 mais corps vide (protégé par les gardes PHP)");
        } else {
            out('KO', "$path → $code : ACCESSIBLE", 'Vérifiez AllowOverride All / mod_rewrite (Apache) ou les règles Nginx du README. Ne déployez pas database/, tests/, README.md.');
        }
    }
}

echo "\n", str_repeat('-', 60), "\n";
if ($blocking === 0) {
    echo $warnings === 0 ? "Tout est en ordre.\n" : "Aucun problème bloquant ($warnings avertissement" . ($warnings > 1 ? 's' : '') . ").\n";
} else {
    echo "$blocking problème" . ($blocking > 1 ? 's' : '') . ' bloquant' . ($blocking > 1 ? 's' : '') . " à corriger (lignes [KO] ci-dessus)."
        . ($warnings ? " $warnings avertissement" . ($warnings > 1 ? 's' : '') . '.' : '') . "\n";
}
exit($blocking === 0 ? 0 : 1);
