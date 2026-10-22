<?php
declare(strict_types=1);

/**
 * Accès aux paramètres du site (table site_settings), chargés en UNE requête par page.
 * Tolérant aux pannes : si la base est indisponible, retourne la valeur par défaut.
 */
function settings_all(): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        settings_available(true);
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
            foreach ($rows as $row) {
                $all[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('settings_all: ' . $e->getMessage());
            settings_available(false);
        }
    }

    return $all;
}

/**
 * Indique si la base a répondu au chargement des paramètres.
 * Appelé avec un booléen par settings_all() ; sans argument, déclenche le chargement et retourne l'état.
 */
function settings_available(?bool $set = null): bool
{
    static $ok = true;
    if ($set !== null) {
        $ok = $set;

        return $ok;
    }
    settings_all();

    return $ok;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();

    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}
