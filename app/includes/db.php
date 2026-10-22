<?php
declare(strict_types=1);

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
