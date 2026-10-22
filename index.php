<?php
declare(strict_types=1);

/**
 * Contrôleur frontal : toutes les requêtes dynamiques passent ici.
 * Le routeur est une table explicite (aucune inclusion de fichier déduite de l'URL).
 */

// Seul point d'entrée public : les autres fichiers PHP refusent d'être appelés directement.
define('GS_ENTRY', true);

// Serveur de développement PHP (php -S) : il n'applique pas le .htaccess. On reproduit donc ici
// la même règle : seuls css/, js/, img/, lib/ et uploads/ sont servis comme fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $urlPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($urlPath === '/admin/index.php') {
        require __DIR__ . '/admin/index.php';
        return true;
    }
    $static  = __DIR__ . $urlPath;
    $public  = (bool) preg_match('#^/(css|js|img|lib|uploads|admin/assets)/#', $urlPath) && !str_contains($urlPath, '..');
    if ($public && is_file($static) && !preg_match('#\.(php[0-9s]?|phtml|phar|html?)$#i', $urlPath)) {
        return false;
    }
}

require __DIR__ . '/includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    render_error(405);
}

// URL avec slash final → version canonique sans slash (301)
$rawPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($rawPath !== '/' && str_ends_with($rawPath, '/') && $method !== 'POST') {
    $query = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    redirect(rtrim($rawPath, '/') . ($query !== '' ? '?' . $query : ''), 301);
}

/**
 * Table de routage : [expression du chemin, méthodes autorisées, page].
 * Les groupes nommés (?<slug>…) deviennent $routeParams. Les slugs sont revalidés par les pages.
 */
const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';
$routes = [
    ['#^/$#',                                  ['GET', 'HEAD'], 'home'],
    ['#^/(?<slug>a-propos|politique-de-confidentialite|mentions-legales)$#', ['GET', 'HEAD'], 'static_page'],
    ['#^/services$#',                          ['GET', 'HEAD'], 'services'],
    ['#^/services/(?<slug>' . SLUG . ')$#',    ['GET', 'HEAD'], 'service'],
    ['#^/catalogue$#',                         ['GET', 'HEAD'], 'catalogue'],
    ['#^/catalogue/(?<slug>' . SLUG . ')$#',   ['GET', 'HEAD'], 'product'],
    ['#^/realisations$#',                      ['GET', 'HEAD'], 'projects'],
    ['#^/realisations/(?<slug>' . SLUG . ')$#', ['GET', 'HEAD'], 'project'],
    ['#^/questions-frequentes$#',              ['GET', 'HEAD'], 'faq'],
    ['#^/contact$#',                           ['GET', 'HEAD', 'POST'], 'contact'],
    ['#^/demande-de-devis$#',                  ['GET', 'HEAD', 'POST'], 'quote'],
    ['#^/demande-de-devis/merci$#',            ['GET', 'HEAD'], 'quote_thanks'],
];

$path = request_path();
$routeParams = [];
$matched = null;
$allowedForPath = [];

foreach ($routes as [$pattern, $methods, $page]) {
    if (preg_match($pattern, $path, $m) === 1) {
        if (in_array($method, $methods, true)) {
            $matched = $page;
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $routeParams[$k] = $v;
                }
            }
            break;
        }
        $allowedForPath = array_merge($allowedForPath, $methods);
    }
}

if ($matched === null) {
    if ($allowedForPath !== []) {
        header('Allow: ' . implode(', ', array_unique($allowedForPath)));
        render_error(405);
    }
    render_error(404);
}

require GS_ROOT . '/php/' . $matched . '.php';   // logique de la page (base : php/) → rend pages/<nom>.php


