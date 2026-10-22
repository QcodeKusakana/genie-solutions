<?php
declare(strict_types=1);

/**
 * Installation de la base de données.
 *
 *   php bin/install.php [--create-db] [--demo]
 *
 * - Lit la configuration dans .env (DB_*).
 * - --create-db : tente CREATE DATABASE (nécessite le privilège correspondant ;
 *                 sur un mutualisé, créer la base depuis le panneau d'hébergement).
 * - --demo      : importe aussi database/demo.sql (jamais en production).
 * - Refuse de s'exécuter si la base contient déjà des tables : AUCUNE donnée existante
 *   n'est écrasée.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

/**
 * Découpe un script SQL en instructions (gère chaînes, échappements et commentaires « -- »).
 *
 * @return list<string>
 */
function sql_split(string $sql): array
{
    $statements = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $i + 1 < $len) {
                $buf .= $sql[++$i];
            } elseif ($c === $quote) {
                if ($i + 1 < $len && $sql[$i + 1] === $quote) { // '' échappé
                    $buf .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($c === '-' && ($sql[$i + 1] ?? '') === '-') { // commentaire de ligne
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            $buf .= "\n";
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $tail = trim($buf);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

$opts = array_slice($argv, 1);
$createDb = in_array('--create-db', $opts, true);
$withDemo = in_array('--demo', $opts, true);

$cfg = config('db');
if ($cfg['name'] === '' || $cfg['user'] === '') {
    fwrite(STDERR, "Erreur : DB_NAME et DB_USER doivent être définis dans .env\n");
    exit(1);
}
if ($withDemo && config('app.env') === 'production') {
    fwrite(STDERR, "Refus : --demo est interdit avec APP_ENV=production.\n");
    exit(1);
}

try {
    if ($createDb) {
        $server = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']),
            (string) $cfg['user'],
            (string) $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $server->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '', (string) $cfg['name'])
        ));
        echo "Base « {$cfg['name']} » prête.\n";
    }

    $pdo = db_connect($cfg);

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    if ($existing > 0) {
        fwrite(STDERR, "Refus : la base contient déjà {$existing} table(s). Aucune donnée n'a été modifiée.\n");
        exit(1);
    }

    $files = ['schema.sql', 'seed.sql'];
    if ($withDemo) {
        $files[] = 'demo.sql';
    }

    foreach ($files as $file) {
        $path = config('paths.sql') . '/' . $file;
        $statements = sql_split((string) file_get_contents($path));
        foreach ($statements as $n => $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                fwrite(STDERR, "Erreur dans {$file}, instruction " . ($n + 1) . " : " . $e->getMessage() . "\n");
                exit(1);
            }
        }
        printf("%-12s %d instructions exécutées\n", $file, count($statements));
    }

    echo "Installation terminée. Étape suivante : php bin/create-admin.php\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur de connexion ou d’exécution : ' . $e->getMessage() . "\n");
    exit(1);
}
