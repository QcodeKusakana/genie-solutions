<?php
declare(strict_types=1);

/**
 * Création d'un compte administrateur (ligne de commande uniquement).
 *
 *   php bin/create-admin.php [--email=adresse] [--name="Nom complet"] [--role=super_admin]
 *
 * Le mot de passe n'est JAMAIS passé en argument (il apparaîtrait dans la liste des
 * processus et l'historique du shell) : il est saisi à l'invite ou lu sur l'entrée standard.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

const MIN_PASSWORD_LENGTH = 12;

function cli_read(string $prompt, bool $hidden = false): string
{
    // POSIX isatty() n'est pas fourni par PHP Windows : l'ancien code
    // lisait bien STDIN, mais n'affichait jamais les invites sous Laragon.
    fwrite(STDOUT, $prompt);
    $canHide = PHP_OS_FAMILY !== 'Windows'
        && function_exists('posix_isatty')
        && posix_isatty(STDIN)
        && function_exists('shell_exec');
    if ($hidden && $canHide) {
        shell_exec('stty -echo');
    }
    if ($hidden && !$canHide) {
        fwrite(STDOUT, "(la saisie ne sera pas masquée dans ce terminal)\n");
    }
    $line = fgets(STDIN);
    if ($hidden && $canHide) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return $line === false ? '' : rtrim($line, "\r\n");
}

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) {
        $args[$m[1]] = $m[2];
    }
}

$name  = trim($args['name']  ?? cli_read('Nom complet : '));
$email = strtolower(trim($args['email'] ?? cli_read('Adresse e-mail : ')));
$role  = $args['role'] ?? 'super_admin';

if ($name === '' || mb_strlen($name) > 120) {
    fwrite(STDERR, "Nom invalide (1 à 120 caractères).\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    fwrite(STDERR, "Adresse e-mail invalide.\n");
    exit(1);
}

$password = cli_read('Mot de passe (min. ' . MIN_PASSWORD_LENGTH . ' caractères) : ', true);
$confirm  = cli_read('Confirmer le mot de passe : ', true);

if ($password !== $confirm) {
    fwrite(STDERR, "Les mots de passe ne correspondent pas.\n");
    exit(1);
}
if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
    fwrite(STDERR, 'Mot de passe trop court (minimum ' . MIN_PASSWORD_LENGTH . " caractères).\n");
    exit(1);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
    $stmt->execute(['slug' => $role]);
    $roleId = $stmt->fetchColumn();
    if ($roleId === false) {
        fwrite(STDERR, "Rôle inconnu : {$role}\n");
        exit(1);
    }

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetchColumn()) {
        fwrite(STDERR, "Un compte existe déjà pour cette adresse.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO users (role_id, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)'
    )->execute(['role' => $roleId, 'name' => $name, 'email' => $email, 'hash' => $hash]);

    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
         VALUES (:uid, :action, :etype, :eid, :details)'
    )->execute([
        'uid' => $userId, 'action' => 'user.created_cli', 'etype' => 'user',
        'eid' => $userId, 'details' => 'Compte créé en ligne de commande (rôle ' . $role . ')',
    ]);

    echo "Compte créé : {$email} (rôle : {$role}).\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur base de données : ' . $e->getMessage() . "\n");
    exit(1);
}
