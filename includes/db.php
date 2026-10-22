<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Connexion PDO unique (point d'accès centralisé à la base de données).
 * Requêtes préparées natives (pas d'émulation) : protection contre l'injection SQL.
 */

/** Construit une connexion PDO à partir d'un tableau de configuration. */
function db_connect(array $cfg): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['name']
    );

    return new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+00:00'",
    ]);
}

/** Retourne l'instance PDO partagée de la requête courante. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(config('db'));
    }

    return $pdo;
}

/**
 * Classe une panne de base de données en une cause lisible (sans exposer le message SQL au visiteur).
 *
 * @return string not_configured | auth | database | schema | server_down | other
 */
function db_failure_reason(Throwable $e): string
{
    if ((string) config('db.name') === '' || (string) config('db.user') === '') {
        return 'not_configured';
    }
    $code = 0;
    if ($e instanceof PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 0 && preg_match('/\[(\d{4})\]|: (\d{4}) /', $e->getMessage(), $m)) {
            $code = (int) ($m[1] !== '' ? $m[1] : $m[2]);
        }
    }

    return match (true) {
        $code === 1045 => 'auth',
        $code === 1044, $code === 1049 => 'database',
        $code === 1146, $code === 1054 => 'schema',
        $code === 2002, $code === 2003, $code === 2006 => 'server_down',
        default => 'other',
    };
}

/** Explication et correctif pour chaque cause (texte sans donnée sensible). */
function db_failure_hint(string $reason): string
{
    return match ($reason) {
        'not_configured' => 'Aucune configuration de base de données n’a été trouvée. Copiez le fichier .env.example vers .env (à la racine du projet) puis renseignez DB_NAME, DB_USER et DB_PASS.',
        'auth'           => 'MySQL refuse l’utilisateur ou le mot de passe indiqués (DB_USER / DB_PASS). Sous Laragon, l’utilisateur par défaut est « root » avec un mot de passe vide.',
        'database'       => 'La base indiquée dans DB_NAME n’existe pas, ou cet utilisateur n’y a pas accès. Créez la base (utf8mb4) puis lancez « php bin/install.php ».',
        'schema'         => 'La base existe mais ses tables sont absentes ou obsolètes. Lancez « php bin/install.php » sur une base vide, ou appliquez les fichiers de database/migrations/.',
        'server_down'    => 'Le serveur MySQL ne répond pas (DB_HOST / DB_PORT). Vérifiez qu’il est démarré.',
        default          => 'Consultez storage/logs/php-error.log pour le détail.',
    };
}
