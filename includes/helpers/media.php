<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Balise <img> pour une ligne contenant stored_name / alt_text / width / height.
 * Retourne '' si aucune image n'est associée (l'appelant affiche alors un repère visuel).
 *
 * @param array<string,mixed> $row
 */
function media_img(array $row, string $fallbackAlt, string $class = '', bool $lazy = true): string
{
    $name = (string) ($row['stored_name'] ?? '');
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $name)) {
        return '';
    }
    $alt = trim((string) ($row['alt_text'] ?? '')) !== '' ? (string) $row['alt_text'] : $fallbackAlt;
    $dim = (!empty($row['width']) && !empty($row['height']))
        ? ' width="' . (int) $row['width'] . '" height="' . (int) $row['height'] . '"' : '';

    return '<img src="/uploads/' . e(rawurlencode($name)) . '" alt="' . e($alt) . '"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '') . $dim
        . ($lazy ? ' loading="lazy" decoding="async"' : '') . '>';
}

/** URL publique d'un média à partir de son nom stocké (null si le nom est invalide). */
function media_src(?string $storedName): ?string
{
    return ($storedName !== null && preg_match('/^[A-Za-z0-9._-]{1,80}$/', $storedName))
        ? '/uploads/' . rawurlencode($storedName) : null;
}
