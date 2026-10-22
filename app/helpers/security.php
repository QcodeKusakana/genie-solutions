<?php
declare(strict_types=1);

/**
 * En-têtes de sécurité HTTP. La CSP est stricte : aucun script/style en ligne,
 * aucune ressource externe (Bootstrap, icônes et polices sont servis en local).
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By');

    if (is_https() && config('app.env') === 'production') {
        header('Strict-Transport-Security: max-age=31536000'); // sans includeSubDomains / preload par prudence
    }
}

/** Adresse IP du client (REMOTE_ADDR uniquement : les en-têtes de proxy sont falsifiables). */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Hachage salé de l'IP : permet la limitation de débit sans stocker l'adresse. */
function ip_hash(?string $ip = null): string
{
    $secret = (string) config('app.secret');
    if ($secret === '') {
        throw new RuntimeException('APP_SECRET doit être défini dans .env.');
    }

    return hash_hmac('sha256', $ip ?? client_ip(), $secret);
}
