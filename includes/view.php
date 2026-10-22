<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Rendu des vues : le contenu est mis en tampon puis encadré par le layout commun
 * (en-tête SEO, barre d'annonce, navigation, pied de page).
 *
 * @param array<string,mixed> $vars  Variables disponibles dans la vue
 * @param array<string,mixed> $meta  title, description, robots, canonical_path, og_image, status
 */
function render_page(string $template, array $vars = [], array $meta = []): void
{
    $meta += [
        'title' => null, 'description' => null, 'robots' => 'index,follow',
        'canonical_path' => null, 'og_image' => null, 'status' => 200,
    ];

    // Base indisponible : 503 (et non une page vide en 200, mauvaise pour le référencement).
    if (!settings_available()) {
        render_error(503);
    }

    $viewFile = GS_ROOT . '/pages/' . $template . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Vue introuvable : ' . $template);
    }

    $content = (static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;

        return (string) ob_get_clean();
    })($viewFile, $vars);

    http_response_code((int) $meta['status']);
    send_security_headers();
    header('Content-Type: text/html; charset=utf-8');

    $currentPath = request_path();
    require GS_ROOT . '/menu/head.php';
    require GS_ROOT . '/menu/menu.php';
    echo $content;
    require GS_ROOT . '/menu/footer.php';
}

/**
 * Page d'erreur autonome (ne dépend ni de la base ni des paramètres : elle doit
 * pouvoir s'afficher quand tout le reste est en panne).
 */
function render_error(int $code): never
{
    $messages = [
        403 => ['Accès refusé', 'Vous n’avez pas l’autorisation d’accéder à cette ressource, ou votre session a expiré.'],
        404 => ['Page introuvable', 'La page demandée n’existe pas ou a été déplacée.'],
        405 => ['Méthode non autorisée', 'Cette action n’est pas autorisée sur cette page.'],
        429 => ['Trop de requêtes', 'Vous avez effectué trop de tentatives. Veuillez patienter quelques minutes avant de réessayer.'],
        500 => ['Erreur du serveur', 'Une erreur est survenue de notre côté. Veuillez réessayer dans quelques instants.'],
        503 => ['Service momentanément indisponible', 'Le site ne peut pas répondre pour le moment. Merci de réessayer dans quelques instants.'],
    ];
    [$title, $text] = $messages[$code] ?? $messages[500];

    // Aide au dépannage : UNIQUEMENT sur un poste de développement (boucle locale + nom local), jamais en ligne.
    $diagnostic = null;
    if ($code === 503 && is_dev_request()) {
        $reason = settings_failure() ?? ((string) config('db.name') === '' ? 'not_configured' : 'other');
        $diagnostic = db_failure_hint($reason) . ' Diagnostic complet : « php bin/check.php ».';
    }

    if (!headers_sent()) {
        http_response_code($code);
        send_security_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        if ($code === 429 || $code === 503) {
            header('Retry-After: 300');
        }
    }
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    require GS_ROOT . '/pages/error.php';
    exit;
}

/**
 * Inclut un fragment de vue réutilisable (pages/partials/<nom>.php) avec ses variables.
 *
 * @param array<string,mixed> $vars
 */
function partial(string $name, array $vars = []): void
{
    $file = GS_ROOT . '/pages/partials/' . $name . '.php';
    if (!preg_match('/^[a-z0-9_]+$/', $name) || !is_file($file)) {
        throw new RuntimeException('Fragment introuvable : ' . $name);
    }
    (static function (string $__file, array $__vars): void {
        extract($__vars, EXTR_SKIP);
        require $__file;
    })($file, $vars);
}
