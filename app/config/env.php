<?php
declare(strict_types=1);

/**
 * Chargeur de fichier .env minimaliste (sans dépendance).
 * Le fichier .env se trouve à la racine du projet, hors du répertoire public.
 */

/**
 * Charge le fichier .env une seule fois et retourne les valeurs.
 *
 * @return array<string,string>
 */
function env_load(?string $path = null): array
{
    static $values = null;
    if ($values !== null && $path === null) {
        return $values;
    }

    $values = [];
    $path ??= dirname(__DIR__, 2) . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }
        if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
            $quote = $val[0];
            $end = strpos($val, $quote, 1);
            $val = $end === false ? substr($val, 1) : substr($val, 1, $end - 1);
        } else {
            // commentaire en fin de ligne : « valeur   # commentaire »
            $val = trim((string) preg_replace('/\s+#.*$/', '', $val));
        }
        $values[$key] = $val;
    }

    return $values;
}

/** Lit une variable d'environnement (fichier .env, puis environnement système). */
function env(string $key, ?string $default = null): ?string
{
    $values = env_load();
    if (array_key_exists($key, $values)) {
        return $values[$key];
    }
    $sys = getenv($key);

    return $sys !== false ? $sys : $default;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = env($key);
    if ($v === null || $v === '') {
        return $default;
    }

    return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}
