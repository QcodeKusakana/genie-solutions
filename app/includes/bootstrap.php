<?php
declare(strict_types=1);

/**
 * Amorçage commun (public, admin, CLI) : configuration, erreurs, base de données.
 * Les modules suivants (session, CSRF, permissions…) s'ajoutent ici aux étapes suivantes.
 */
if (defined('GS_BOOTSTRAPPED')) {
    return;
}
define('GS_BOOTSTRAPPED', true);
define('GS_ROOT', dirname(__DIR__, 2));

/** Accès à la configuration par chemin pointé : config('db.host'). */
function config(?string $path = null): mixed
{
    static $cfg = null;
    $cfg ??= require GS_ROOT . '/app/config/config.php';
    if ($path === null) {
        return $cfg;
    }
    $node = $cfg;
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return null;
        }
        $node = $node[$part];
    }

    return $node;
}

// Gestion des erreurs : jamais d'affichage détaillé hors mode debug local.
$debug = config('app.debug') === true && config('app.env') === 'local';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', config('paths.logs') . '/php-error.log');

date_default_timezone_set('UTC'); // stockage en UTC ; l'affichage local sera géré par les helpers

require_once GS_ROOT . '/app/includes/db.php';

foreach (['html', 'settings', 'session', 'csrf', 'security'] as $helper) {
    require_once GS_ROOT . '/app/helpers/' . $helper . '.php';
}
require_once GS_ROOT . '/app/services/site_repository.php';
require_once GS_ROOT . '/app/includes/navigation.php';
require_once GS_ROOT . '/app/includes/view.php';

/** Toute exception non gérée : journalisation + page 500 générique (aucun détail SQL/chemin affiché). */
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
    render_error(500);
});
