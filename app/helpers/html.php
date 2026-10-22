<?php
declare(strict_types=1);

/** Échappement HTML (contexte texte ET attributs entre guillemets). */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** La requête courante est-elle en HTTPS (direct ou derrière un proxy de confiance) ? */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    // En-tête de proxy accepté uniquement si l'APP_URL configurée est en https.
    return str_starts_with((string) config('app.url'), 'https://')
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** URL relative à la racine du site ("/contact"). */
function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

/** URL absolue : uniquement si APP_URL est configurée (sinon chaîne vide — jamais devinée). */
function absolute_url(string $path = '/'): string
{
    $base = (string) config('app.url');

    return $base === '' ? '' : $base . url($path);
}

/** URL d'un fichier statique de public/assets avec empreinte de version (cache busting). */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = config('paths.public') . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '0';

    return '/assets/' . $path . '?v=' . $v;
}

/** Chemin de la requête, normalisé (sans slash final, sans query string). */
function request_path(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rawurldecode($path);
    $path = '/' . trim($path, '/');

    return $path === '' ? '/' : $path;
}

/** Retourne un href tel: valide, ou null si le numéro est inexploitable. */
function tel_href(string $phone): ?string
{
    $clean = (string) preg_replace('/[^\d+]/', '', $phone);
    if (strlen(preg_replace('/\D/', '', $clean)) < 6) {
        return null;
    }

    return 'tel:' . $clean;
}

/** Retourne un lien wa.me valide, ou null. */
function whatsapp_href(string $number): ?string
{
    $digits = (string) preg_replace('/\D/', '', $number);
    if (strlen($digits) < 6 || strlen($digits) > 15) {
        return null;
    }

    return 'https://wa.me/' . $digits;
}

/** N'accepte que des URL http(s) pour les liens sociaux (évite javascript:, data:…). */
function safe_external_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return $url;
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}
