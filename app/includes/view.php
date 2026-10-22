<?php
declare(strict_types=1);

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

    $viewFile = GS_ROOT . '/app/views/' . $template . '.php';
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
    require GS_ROOT . '/app/views/layout/header.php';
    echo $content;
    require GS_ROOT . '/app/views/layout/footer.php';
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
        503 => ['Site en maintenance', 'Le site est momentanément indisponible. Merci de revenir bientôt.'],
    ];
    [$title, $text] = $messages[$code] ?? $messages[500];

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
    require GS_ROOT . '/app/views/errors/error.php';
    exit;
}
