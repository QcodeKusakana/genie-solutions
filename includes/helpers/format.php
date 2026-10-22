<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Affichage d'un prix. Aucun prix n'est jamais affiché sans devise configurée :
 * dans ce cas (ou sans montant) on renvoie « Sur devis ».
 */
function format_price(?string $amount, ?string $note = null): string
{
    $currency = trim(setting('currency_code'));
    if ($amount === null || $amount === '' || $currency === '' || !is_numeric($amount)) {
        return 'Sur devis';
    }
    $n = (float) $amount;
    $txt = (floor($n) === $n) ? number_format($n, 0, ',', "\u{202F}") : number_format($n, 2, ',', "\u{202F}");

    return trim(($note ? $note . ' ' : '') . $txt . "\u{00A0}" . $currency);
}

/** Classe d'icône Bootstrap Icons sûre (liste de caractères stricte) avec valeur de repli. */
function safe_icon(?string $icon, string $fallback = 'bi-grid'): string
{
    return $icon !== null && preg_match('/^bi-[a-z0-9-]{1,40}$/', $icon) ? $icon : $fallback;
}

/** Slug valide : minuscules, chiffres, tirets simples. */
function is_valid_slug(string $slug): bool
{
    return strlen($slug) <= 140 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

/** Date en français (ex. « 12 mars 2026 »), sans dépendre de l'extension intl. */
function format_date_fr(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    return (int) gmdate('j', $ts) . ' ' . $mois[(int) gmdate('n', $ts)] . ' ' . gmdate('Y', $ts);
}
