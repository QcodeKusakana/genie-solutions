<?php
declare(strict_types=1);

/** Tests unitaires CLI (session, CSRF, flash, helpers). Usage : php tests/step2_php.php */
require dirname(__DIR__) . '/includes/bootstrap.php';

$pass = $fail = 0;
function t(string $name, bool $cond): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    printf("  [%s] %s\n", $cond ? 'OK' : 'ÉCHEC', $name);
}

$_SERVER['HTTPS'] = 'on'; // simule HTTPS pour vérifier le drapeau Secure
session_boot();
$p = session_get_cookie_params();
t('cookie de session : HttpOnly', $p['httponly'] === true);
t('cookie de session : Secure sous HTTPS', $p['secure'] === true);
t('cookie de session : SameSite=Lax', $p['samesite'] === 'Lax');
t('nom de session personnalisé', session_name() === 'gs_session');

$tok = csrf_token();
t('jeton CSRF : 64 caractères hexadécimaux', (bool) preg_match('/^[0-9a-f]{64}$/', $tok));
t('jeton CSRF stable pendant la session', csrf_token() === $tok);
t('csrf_verify : bon jeton accepté', csrf_verify($tok));
t('csrf_verify : mauvais jeton refusé', !csrf_verify(str_repeat('a', 64)));
t('csrf_verify : null refusé', !csrf_verify(null));
t('csrf_verify : chaîne vide refusée', !csrf_verify(''));
t('csrf_field échappe et contient le jeton', str_contains(csrf_field(), 'value="' . $tok . '"'));

flash_add('success', 'Test <b>flash</b>');
$msgs = flash_pull();
t('flash : message restitué', count($msgs) === 1 && $msgs[0]['type'] === 'success');
t('flash : vidé après lecture', flash_pull() === []);

t('e() échappe < > " \' &', e('<a href="x" onclick=\'y\'>&</a>') === '&lt;a href=&quot;x&quot; onclick=&#039;y&#039;&gt;&amp;&lt;/a&gt;');
t('e(null) → chaîne vide', e(null) === '');
t('tel_href : numéro valide', tel_href('+243 81 234 5678') === 'tel:+243812345678');
t('tel_href : numéro trop court → null', tel_href('12') === null);
t('tel_href : injection neutralisée', tel_href('javascript:alert(1)') === null);
t('whatsapp_href : valide', whatsapp_href('+243 81 234 5678') === 'https://wa.me/243812345678');
t('whatsapp_href : invalide → null', whatsapp_href('abc') === null);
t('safe_external_url : https accepté', safe_external_url('https://exemple.invalid/x') === 'https://exemple.invalid/x');
t('safe_external_url : javascript: refusé', safe_external_url('javascript:alert(1)') === null);
t('safe_external_url : data: refusé', safe_external_url('data:text/html,x') === null);
t('nav_is_active : accueil exact seulement', nav_is_active('/', '/') && !nav_is_active('/', '/contact'));
t('nav_is_active : sous-page d’une rubrique', nav_is_active('/services', '/services/broderie'));
t('nav_is_active : préfixe partiel refusé', !nav_is_active('/services', '/services-autres'));
t('ip_hash : 64 caractères, dépend du sel', strlen(ip_hash('1.2.3.4')) === 64 && ip_hash('1.2.3.4') !== ip_hash('1.2.3.5'));
t('ip_hash : ne contient pas l’IP', !str_contains(ip_hash('1.2.3.4'), '1.2.3.4'));

// Contraste WCAG 2.1 (AA : 4,5 pour le texte normal, 3 pour le grand texte)
function lum(string $hex): float
{
    $c = array_map(static fn($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
        sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function contrast(string $a, string $b): float
{
    [$l1, $l2] = [max(lum($a), lum($b)), min(lum($a), lum($b))];

    return ($l1 + 0.05) / ($l2 + 0.05);
}
$pairs = [
    ['texte #253247 sur blanc', '#253247', '#FFFFFF', 4.5],
    ['bleu profond #12345A sur blanc', '#12345A', '#FFFFFF', 4.5],
    ['blanc sur bleu profond (en-tête, hero, pied)', '#FFFFFF', '#12345A', 4.5],
    ['blanc sur rouge soutenu #C92A38 (boutons)', '#FFFFFF', '#C92A38', 4.5],
    ['bleu clair #39A9F4 sur bleu profond (icônes/étiquettes)', '#39A9F4', '#12345A', 4.5],
    ['gris atténué #55657A sur blanc', '#55657A', '#FFFFFF', 4.5],
    ['rouge soutenu #C92A38 sur blanc (étiquettes)', '#C92A38', '#FFFFFF', 4.5],
    ['[info] blanc sur bleu clair #39A9F4', '#FFFFFF', '#39A9F4', 0.0],
    ['[info] blanc sur rouge de marque #E63946', '#FFFFFF', '#E63946', 0.0],
];
foreach ($pairs as [$label, $fg, $bg, $min]) {
    $r = contrast($fg, $bg);
    if ($min > 0) {
        t(sprintf('contraste %.2f:1 — %s', $r, $label), $r >= $min);
    } else {
        printf("  [INFO] contraste %.2f:1 — %s%s\n", $r, $label, $r < 4.5 ? ' (sous AA : à ne pas utiliser pour du texte normal)' : '');
    }
}

printf("\nRésultat : %d réussis, %d échecs\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
