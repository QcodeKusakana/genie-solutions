<?php
declare(strict_types=1);

/**
 * Configuration centralisée. Aucun secret en dur : tout vient du .env.
 * Retourne un tableau ; utiliser config('db.host') via app/includes/bootstrap.php.
 */
require_once __DIR__ . '/env.php';

$root = dirname(__DIR__, 2);

return [
    'app' => [
        'env'    => env('APP_ENV', 'production'),
        'debug'  => env_bool('APP_DEBUG', false),
        'url'    => rtrim((string) env('APP_URL', ''), '/'),
        'secret' => (string) env('APP_SECRET', ''),
    ],
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'pass' => env('DB_PASS', ''),
    ],
    'mail' => [
        'host'       => env('MAIL_HOST', ''),
        'port'       => (int) env('MAIL_PORT', '587'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'user'       => env('MAIL_USER', ''),
        'pass'       => env('MAIL_PASS', ''),
    ],
    'paths' => [
        'root'    => $root,
        'public'  => $root . '/public',
        'uploads' => $root . '/public/uploads',
        'private' => $root . '/storage/private',
        'logs'    => $root . '/storage/logs',
        'backups' => $root . '/storage/backups',
        'sql'     => $root . '/database',
    ],
];
