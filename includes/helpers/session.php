<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Session PHP sécurisée, démarrée à la demande (les pages publiques simples n'en ont
 * pas besoin : pas de cookie inutile, meilleure mise en cache).
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('gs_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Ajoute un message flash. $type : success | danger | warning | info. */
function flash_add(string $type, string $message): void
{
    session_boot();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Retourne et vide les messages flash (sans démarrer de session si aucune n'existe). */
function flash_pull(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['gs_session'])) {
        return [];
    }
    session_boot();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}
