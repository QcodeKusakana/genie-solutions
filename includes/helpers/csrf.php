<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Jeton CSRF lié à la session (généré à la demande). */
function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Champ caché à placer dans tout formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Vérifie le jeton reçu (comparaison à temps constant). */
function csrf_verify(mixed $token): bool   // mixed : un attaquant peut envoyer un tableau (_csrf[]=x)
{
    session_boot();
    $expected = $_SESSION['csrf_token'] ?? '';

    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

/** À appeler au début de tout traitement POST : stoppe la requête (403) si le jeton est invalide. */
function csrf_require(): void
{
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        render_error(403);
    }
}
