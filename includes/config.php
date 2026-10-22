<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Configuration centralisée. Aucun secret en dur : tout vient du .env.
 * Retourne un tableau ; utiliser config('db.host') via app/includes/bootstrap.php.
 */
require_once __DIR__ . '/env.php';

$root = dirname(__DIR__); // includes/ → racine du projet (= racine web)

return [
    'app' => [
        'env'    => env('APP_ENV', 'production'),
        'debug'  => env_bool('APP_DEBUG', false),
        'url'    => rtrim((string) env('APP_URL', ''), '/'),
        'secret' => (string) env('APP_SECRET', ''),
        'form_min_seconds' => max(0, (int) env('FORM_MIN_SECONDS', '3')), // délai humain minimal avant envoi d'un formulaire
    ],
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'genie_solutions'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],
    'mail' => [
        'host'       => env('MAIL_HOST', ''),
        'port'       => (int) env('MAIL_PORT', '587'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'user'       => env('MAIL_USER', ''),
        'pass'       => env('MAIL_PASS', ''),
        'verify_peer' => env_bool('MAIL_VERIFY_PEER', true), // ne désactiver qu'en test local (certificat auto-signé)
        'timeout'    => 10,
    ],
    'paths' => [
        'root'    => $root,
        'uploads' => $root . '/uploads',
        'private' => $root . '/storage/private',
        'logs'    => $root . '/storage/logs',
        'backups' => $root . '/storage/backups',
        'sql'     => $root . '/database',
    ],
];
