<?php
declare(strict_types=1);

defined('GS_ENTRY') || define('GS_ENTRY', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
send_security_headers();
header('Cache-Control: no-store, private');
$adminMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($adminMethod, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    render_error(405);
}
session_boot();

const ADMIN_STATUSES = [
    'nouvelle' => 'Nouvelle',
    'en_etude' => 'En étude',
    'devis_envoye' => 'Devis envoyé',
    'acceptee' => 'Acceptée',
    'refusee' => 'Refusée',
    'en_production' => 'En production',
    'terminee' => 'Terminée',
    'annulee' => 'Annulée',
];

function admin_actor(): ?array
{
    static $actor = false;
    if ($actor !== false) return $actor;
    $uid = filter_var($_SESSION['admin_uid'] ?? null, FILTER_VALIDATE_INT);
    if (!$uid) return $actor = null;
    $stmt = db()->prepare(
        'SELECT u.id, u.full_name, u.email, u.role_id, r.slug AS role_slug, r.name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.is_active = 1 LIMIT 1'
    );
    $stmt->execute(['id' => $uid]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['admin_uid']);
        return $actor = null;
    }
    $stmt = db()->prepare(
        'SELECT p.code FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = :role'
    );
    $stmt->execute(['role' => $user['role_id']]);
    $user['permissions'] = array_fill_keys(array_column($stmt->fetchAll(), 'code'), true);
    return $actor = $user;
}

function admin_can(string $permission): bool
{
    $actor = admin_actor();
    return $actor !== null && isset($actor['permissions'][$permission]);
}

function admin_require(string $permission): void
{
    if (admin_actor() === null) redirect('/admin/index.php?page=login');
    if (!admin_can($permission)) render_error(403);
}

function admin_log(?int $uid, string $action, ?string $type = null, ?int $entityId = null, ?string $details = null): void
{
    db()->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_hash)
         VALUES (:uid, :action, :type, :entity, :details, :ip)'
    )->execute([
        'uid' => $uid, 'action' => $action, 'type' => $type, 'entity' => $entityId,
        'details' => $details, 'ip' => ip_hash(),
    ]);
}

/** Nom public déterministe : un import répété ne crée pas de copie supplémentaire. */
function admin_import_stored_name(string $relative): string
{
    return 'imp-' . substr(hash('sha256', $relative), 0, 32) . '.webp';
}

function admin_header(string $title, array $actor): void
{
    $company = setting('company_name', 'Génie Solutions');
    $nav = [
        ['dashboard.view', 'dashboard', 'Vue d’ensemble', 'bi-grid-1x2'],
        ['quotes.view', 'quotes', 'Demandes de devis', 'bi-inbox'],
        ['messages.manage', 'messages', 'Messages', 'bi-chat-left-text'],
        ['media.manage', 'media', 'Médiathèque', 'bi-images'],
        ['pages.manage', 'site-pages', 'Pages du site', 'bi-file-earmark-text'],
        ['pages.manage', 'partners-admin', 'Partenaires', 'bi-people'],
        ['faqs.manage', 'faqs', 'Questions fréquentes', 'bi-question-circle'],
        ['services.manage', 'services-admin', 'Services', 'bi-printer'],
        ['projects.manage', 'projects-admin', 'Réalisations', 'bi-images'],
        ['products.manage', 'products-admin', 'Catalogue', 'bi-tags'],
        ['users.manage', 'users-admin', 'Comptes administrateurs', 'bi-people'],
        ['pages.manage', 'home-content-admin', 'Contenu de l’accueil', 'bi-house-gear'],
        ['testimonials.manage', 'testimonials-admin', 'Témoignages', 'bi-chat-quote'],
    ];
    $page = (string) ($_GET['page'] ?? 'dashboard');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' · Administration · ' . e($company) . '</title>';
    echo '<link rel="stylesheet" href="/lib/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/lib/bootstrap-icons/bootstrap-icons.min.css">';
    echo '<link rel="stylesheet" href="/admin/assets/admin.css"></head><body class="admin-body">';
    echo '<aside class="admin-sidebar"><a class="admin-brand" href="/admin/index.php"><img src="/img/brand/logo-genie-solutions.jpg" alt=""><span>Administration</span></a>';
    echo '<nav aria-label="Navigation administration">';
    foreach ($nav as [$perm, $slug, $label, $icon]) {
        if (!admin_can($perm)) continue;
        echo '<a class="admin-nav-link' . ($page === $slug || ($slug === 'quotes' && $page === 'quote') ? ' active' : '') . '" href="/admin/index.php?page=' . e($slug) . '"><i class="bi ' . e($icon) . '" aria-hidden="true"></i><span>' . e($label) . '</span></a>';
    }
    echo '</nav></aside>';
    echo '<main class="admin-main"><header class="admin-topbar"><div><span class="admin-eyebrow">' . e($company) . '</span><h1>' . e($title) . '</h1></div><details class="admin-user-menu"><summary class="admin-user-menu-trigger"><span class="admin-user-avatar">' . e(mb_strtoupper(mb_substr($actor['full_name'], 0, 1))) . '</span><span class="admin-user-menu-label"><strong>' . e($actor['full_name']) . '</strong><small>' . e($actor['role_name']) . '</small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="admin-user-menu-panel"><div class="admin-user-menu-heading"><strong>' . e($actor['full_name']) . '</strong><small>' . e($actor['role_name']) . '</small></div><a href="/admin/index.php?page=account"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Mon compte</span></a>' . (admin_can('settings.manage') ? '<a href="/admin/index.php?page=settings-admin"><i class="bi bi-gear" aria-hidden="true"></i><span>Paramètres</span></a>' : '') . '<a href="/" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Voir le site</span></a><form method="post" action="/admin/index.php" class="admin-logout">' . csrf_field() . '<input type="hidden" name="action" value="logout"><button type="submit"><i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>Déconnexion</span></button></form></div></details></header>';
    foreach (flash_pull() as $flash) {
        $type = in_array($flash['type'], ['success','danger','warning','info'], true) ? $flash['type'] : 'info';
        echo '<div class="alert alert-' . e($type) . '" role="status">' . e($flash['message']) . '</div>';
    }
}

function admin_footer(): void
{
    echo '<script src="/admin/assets/admin.js" defer></script></main></body></html>';
}

function admin_login(): never
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        csrf_require();
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (strlen($password) > 4096) $password = '';
        $email = mb_substr($email, 0, 190);
        $ip = ip_hash();
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE was_success = 0 AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE
             AND ( (email = :email1) OR (ip_hash = :ip1) )'
        );
        $stmt->execute(['email1' => $email, 'ip1' => $ip]);
        $blocked = (int) $stmt->fetchColumn() >= 8;

        $find = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = :email LIMIT 1');
        $find->execute(['email' => $email]);
        $user = $find->fetch();
        static $dummyHash = null;
        $dummyHash ??= password_hash('not-a-real-account-password', PASSWORD_DEFAULT);
        $passwordOk = password_verify($password, $user ? (string) $user['password_hash'] : $dummyHash);
        $valid = !$blocked && $user && (int) $user['is_active'] === 1 && $passwordOk;

        if (!$blocked) {
            db()->prepare(
                'INSERT INTO login_attempts (email, ip_hash, was_success) VALUES (:email, :ip, :ok)'
            )->execute(['email' => $email !== '' ? $email : 'invalid@invalid', 'ip' => $ip, 'ok' => $valid ? 1 : 0]);
        }

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_uid'] = (int) $user['id'];
            db()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['id' => $user['id']]);
            admin_log((int) $user['id'], 'login.success', 'user', (int) $user['id']);
            flash_add('success', 'Connexion réussie.');
            redirect('/admin/index.php');
        }
        if (!$blocked) admin_log($user ? (int) $user['id'] : null, 'login.failed', 'user', $user ? (int) $user['id'] : null, 'Échec de connexion');
        flash_add('danger', $blocked ? 'Trop de tentatives. Réessayez dans quelques minutes.' : 'Adresse e-mail ou mot de passe incorrect.');
        redirect('/admin/index.php?page=login');
    }

    http_response_code(200);
    $flashes = flash_pull();
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connexion · Génie Solutions</title>';
    echo '<link rel="stylesheet" href="/lib/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/lib/bootstrap-icons/bootstrap-icons.min.css"><link rel="stylesheet" href="/admin/assets/admin.css"></head><body class="login-body">';
    echo '<main class="login-card"><a href="/" class="login-logo"><img src="/img/brand/logo-genie-solutions.jpg" alt="Génie Solutions"></a><div class="login-heading"><span class="admin-eyebrow">Espace sécurisé</span><h1>Bienvenue</h1><p>Connectez-vous pour gérer votre site.</p></div>';
    foreach ($flashes as $f) echo '<div class="alert alert-' . e($f['type']) . '" role="alert">' . e($f['message']) . '</div>';
    echo '<form method="post" action="/admin/index.php?page=login" class="login-form">' . csrf_field() . '<input type="hidden" name="action" value="login">';
    echo '<label for="email">Adresse e-mail</label><input id="email" name="email" type="email" maxlength="190" autocomplete="username" required autofocus>';
    echo '<label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required>';
    echo '<button type="submit" class="btn btn-admin-primary w-100">Se connecter <i class="bi bi-arrow-right"></i></button></form>';
    echo '<a href="/" class="login-back"><i class="bi bi-arrow-left"></i> Retour au site</a></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'login') admin_login();
    csrf_require();
    $actor = admin_actor();
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        if ($actor) admin_log((int) $actor['id'], 'logout', 'user', (int) $actor['id']);
        session_destroy();
        redirect('/admin/index.php?page=login');
    }
    if ($action === 'admin_user_create') {
        admin_require('users.manage');
        $name = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
        $roleSlug = is_string($_POST['role_slug'] ?? null) ? $_POST['role_slug'] : '';
        $allowedRoles = $actor['role_slug'] === 'super_admin' ? ['super_admin', 'admin', 'content_manager', 'sales_manager'] : ['admin', 'content_manager', 'sales_manager'];
        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190
            || strlen($password) < 12 || strlen($password) > 4096 || $password !== $confirm || !in_array($roleSlug, $allowedRoles, true)) {
            flash_add('danger', 'Vérifiez le nom, l’e-mail, le rôle et le mot de passe (12 caractères minimum, confirmation identique).');
            redirect('/admin/index.php?page=users-admin');
        }
        $roleStmt = db()->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleStmt->execute(['slug' => $roleSlug]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) { flash_add('danger', 'Le rôle sélectionné est introuvable.'); redirect('/admin/index.php?page=users-admin'); }
        try {
            $stmt = db()->prepare('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)');
            $stmt->execute(['role' => $roleId, 'name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $newId = (int) db()->lastInsertId();
            admin_log((int) $actor['id'], 'user.created', 'user', $newId, 'Compte créé avec le rôle ' . $roleSlug);
            flash_add('success', 'Le compte a été créé. Transmettez les identifiants à son titulaire par un canal sûr.');
        } catch (PDOException $error) {
            flash_add('danger', (string) $error->getCode() === '23000' ? 'Cette adresse e-mail est déjà utilisée.' : 'Impossible de créer le compte.');
        }
        redirect('/admin/index.php?page=users-admin');
    }
    if ($action === 'admin_user_toggle') {
        admin_require('users.manage');
        $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = db()->prepare('SELECT u.id, u.is_active, r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id');
        $stmt->execute(['id' => $id ?: 0]); $target = $stmt->fetch();
        if (!$target || (int) $target['id'] === (int) $actor['id'] || ($target['slug'] === 'super_admin' && $actor['role_slug'] !== 'super_admin')) {
            flash_add('danger', 'Ce compte ne peut pas être modifié depuis votre session.'); redirect('/admin/index.php?page=users-admin');
        }
        if ((int) $target['is_active'] === 1) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM users u JOIN role_permissions rp ON rp.role_id=u.role_id JOIN permissions p ON p.id=rp.permission_id WHERE u.is_active=1 AND p.code=:permission');
            $stmt->execute(['permission' => 'users.manage']);
            if ((int) $stmt->fetchColumn() <= 1) { flash_add('danger', 'Impossible de désactiver le dernier compte autorisé à gérer les utilisateurs.'); redirect('/admin/index.php?page=users-admin'); }
            if ($target['slug'] === 'super_admin') {
                $count = db()->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.slug='super_admin'")->fetchColumn();
                if ((int) $count <= 1) { flash_add('danger', 'Le dernier super administrateur actif ne peut pas être désactivé.'); redirect('/admin/index.php?page=users-admin'); }
            }
        }
        $newState = (int) $target['is_active'] === 1 ? 0 : 1;
        db()->prepare('UPDATE users SET is_active=:active WHERE id=:id')->execute(['active'=>$newState,'id'=>$id]);
        admin_log((int)$actor['id'],$newState?'user.activated':'user.deactivated','user',(int)$id);
        flash_add('success',$newState?'Le compte a été activé.':'Le compte a été désactivé.');
        redirect('/admin/index.php?page=users-admin');
    }
    if ($action === 'password_change') {
        if ($actor === null) redirect('/admin/index.php?page=login');
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=:id AND is_active=1');
        $stmt->execute(['id'=>(int)$actor['id']]); $hash=(string)$stmt->fetchColumn();
        if ($current === '' || !password_verify($current,$hash) || strlen($password)<12 || strlen($password)>4096 || $password!==$confirm || password_verify($password,$hash)) {
            flash_add('danger','Vérifiez le mot de passe actuel et choisissez un nouveau mot de passe de 12 caractères minimum, confirmé à l’identique.');
            redirect('/admin/index.php?page=account');
        }
        db()->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>(int)$actor['id']]);
        session_regenerate_id(true); admin_log((int)$actor['id'],'password.changed','user',(int)$actor['id']);
        flash_add('success','Votre mot de passe a été modifié.'); redirect('/admin/index.php?page=account');
    }
    if ($action === 'quote_status') {
        admin_require('quotes.manage');
        $id = filter_var($_POST['quote_id'] ?? null, FILTER_VALIDATE_INT);
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if (!$id || !isset(ADMIN_STATUSES[$status])) {
            flash_add('danger', 'La demande ou le statut est invalide.');
            redirect('/admin/index.php?page=quotes');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM quote_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            $pdo->rollBack();
            flash_add('danger', 'Cette demande est introuvable.');
            redirect('/admin/index.php?page=quotes');
        }
        if ($old !== $status) {
            $pdo->prepare('UPDATE quote_requests SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
            $pdo->prepare('INSERT INTO quote_status_history (quote_id, old_status, new_status, changed_by) VALUES (:id,:old,:new,:uid)')
                ->execute(['id' => $id, 'old' => $old, 'new' => $status, 'uid' => $actor['id']]);
            admin_log((int) $actor['id'], 'quote.status_changed', 'quote', (int) $id, $old . ' → ' . $status);
        }
        $pdo->commit();
        flash_add('success', 'Le statut de la demande a été mis à jour.');
        redirect('/admin/index.php?page=quote&id=' . (int) $id);
    }
    if ($action === 'quote_note') {
        admin_require('quotes.manage');
        $id = filter_var($_POST['quote_id'] ?? null, FILTER_VALIDATE_INT);
        $note = trim(is_string($_POST['note'] ?? null) ? $_POST['note'] : '');
        if (!$id || $note === '' || mb_strlen($note) > 4000) {
            flash_add('danger', 'La note doit contenir entre 1 et 4 000 caractères.');
            redirect('/admin/index.php?page=quotes');
        }
        $check = db()->prepare('SELECT 1 FROM quote_requests WHERE id = :id');
        $check->execute(['id' => $id]);
        if (!$check->fetchColumn()) {
            flash_add('danger', 'Cette demande est introuvable.');
            redirect('/admin/index.php?page=quotes');
        }
        db()->prepare('INSERT INTO quote_notes (quote_id, user_id, note) VALUES (:id,:uid,:note)')
            ->execute(['id' => $id, 'uid' => $actor['id'], 'note' => $note]);
        admin_log((int) $actor['id'], 'quote.note_added', 'quote', (int) $id);
        flash_add('success', 'Note interne enregistrée.');
        redirect('/admin/index.php?page=quote&id=' . (int) $id);
    }
    if ($action === 'quote_delete') {
        admin_require('quotes.manage');
        $id = filter_var($_POST['quote_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            flash_add('danger', 'La demande sélectionnée est invalide.');
            redirect('/admin/index.php?page=quotes');
        }
        if (($_POST['confirmed'] ?? '') !== '1') {
            flash_add('warning', 'Confirmez la suppression depuis la fenêtre de confirmation.');
            redirect('/admin/index.php?page=quote&id=' . (int) $id);
        }
        $pdo = db();
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT reference FROM quote_requests WHERE id=:id FOR UPDATE');
        $find->execute(['id' => $id]);
        $reference = $find->fetchColumn();
        if ($reference === false) {
            $pdo->rollBack();
            flash_add('danger', 'Cette demande est introuvable ou a déjà été supprimée.');
            redirect('/admin/index.php?page=quotes');
        }
        $filesQuery = $pdo->prepare('SELECT stored_name FROM quote_attachments WHERE quote_id=:id');
        $filesQuery->execute(['id' => $id]);
        $storedFiles = $filesQuery->fetchAll(PDO::FETCH_COLUMN);
        admin_log((int) $actor['id'], 'quote.deleted', 'quote', (int) $id, 'Demande ' . (string) $reference . ' supprimée');
        $pdo->prepare('DELETE FROM quote_requests WHERE id=:id')->execute(['id' => $id]);
        $pdo->commit();

        $privateQuotes = realpath(config('paths.private') . '/quotes');
        if ($privateQuotes !== false) {
            foreach ($storedFiles as $storedName) {
                if (!preg_match('/^[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|pdf)$/i', (string) $storedName)) continue;
                $realFile = realpath($privateQuotes . DIRECTORY_SEPARATOR . $storedName);
                if ($realFile && str_starts_with($realFile, $privateQuotes . DIRECTORY_SEPARATOR) && is_file($realFile)) @unlink($realFile);
            }
        }
        flash_add('success', 'La demande ' . (string) $reference . ' et ses informations associées ont été supprimées.');
        redirect('/admin/index.php?page=quotes');
    }
    if ($action === 'message_status') {
        admin_require('messages.manage');
        $id = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT);
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if (!$id || !in_array($status, ['new','read','archived'], true)) {
            flash_add('danger', 'Le message ou son statut est invalide.');
            redirect('/admin/index.php?page=messages');
        }
        $stmt = db()->prepare('UPDATE contact_messages SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        admin_log((int) $actor['id'], 'message.status_changed', 'contact_message', (int) $id, $status);
        flash_add('success', 'Le statut du message a été enregistré.');
        redirect('/admin/index.php?page=messages');
    }
    if ($action === 'partner_save') {
        admin_require('pages.manage');
        $id = filter_var($_POST['partner_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $url = trim(is_string($_POST['website_url'] ?? null) ? $_POST['website_url'] : '');
        $mediaId = filter_var($_POST['media_id'] ?? null, FILTER_VALIDATE_INT);
        $order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $validUrl = $url === '' || (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https');
        $file = $_FILES['logo_file'] ?? null;
        $hasUpload = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($name === '' || mb_strlen($name) > 150 || !$validUrl || mb_strlen($url) > 255 || $order === false || $order < 0 || $order > 32767) {
            flash_add('danger', 'Vérifiez le nom, le lien HTTPS et l’ordre d’affichage.');
            redirect('/admin/index.php?page=partners-admin' . ($id ? '&edit=' . (int) $id : ''));
        }

        if ($hasUpload) {
            $inspected = attachment_inspect($file);
            if ($inspected['status'] !== 'ok' || !str_starts_with((string) ($inspected['mime'] ?? ''), 'image/')) {
                flash_add('danger', $inspected['status'] === 'error' ? (string) $inspected['message'] : 'Choisissez un logo au format JPG, PNG ou WebP.');
                redirect('/admin/index.php?page=partners-admin' . ($id ? '&edit=' . (int) $id : ''));
            }
            $uploadedStored = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
            $destination = rtrim((string) config('paths.uploads'), '/\\') . DIRECTORY_SEPARATOR . $uploadedStored;
            if (!@move_uploaded_file($inspected['tmp'], $destination)) {
                flash_add('danger', 'Le logo n’a pas pu être enregistré. Vérifiez les droits du dossier des images.');
                redirect('/admin/index.php?page=partners-admin' . ($id ? '&edit=' . (int) $id : ''));
            }
            @chmod($destination, 0640);
            $dimensions = @getimagesize($destination);
            try {
                db()->prepare('INSERT INTO media (stored_name,original_name,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES (:name,:original,:mime,:size,:width,:height,:alt,:uid)')
                    ->execute([
                        'name' => $uploadedStored, 'original' => $inspected['original'], 'mime' => $inspected['mime'],
                        'size' => $inspected['size'], 'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null,
                        'alt' => $name, 'uid' => (int) $actor['id'],
                    ]);
                $mediaId = (int) db()->lastInsertId();
            } catch (Throwable $error) {
                @unlink($destination);
                error_log('partner logo media insert: ' . $error->getMessage());
                flash_add('danger', 'Le logo n’a pas pu être ajouté à la Médiathèque.');
                redirect('/admin/index.php?page=partners-admin' . ($id ? '&edit=' . (int) $id : ''));
            }
        } else {
            $image = $mediaId ? db()->prepare("SELECT 1 FROM media WHERE id = :id AND mime_type LIKE 'image/%'") : null;
            if ($image) { $image->execute(['id' => $mediaId]); }
            if (!$mediaId || !($image && $image->fetchColumn())) {
                flash_add('danger', 'Choisissez un logo sur votre ordinateur ou dans la Médiathèque.');
                redirect('/admin/index.php?page=partners-admin' . ($id ? '&edit=' . (int) $id : ''));
            }
        }

        $values = ['name' => $name, 'url' => $url !== '' ? $url : null, 'media' => $mediaId, 'sort' => $order, 'active' => isset($_POST['is_active']) ? 1 : 0];
        if ($id) {
            $values['id'] = $id;
            db()->prepare('UPDATE partners SET name=:name, website_url=:url, media_id=:media, sort_order=:sort, is_active=:active WHERE id=:id')->execute($values);
            admin_log((int) $actor['id'], 'partner.updated', 'partner', (int) $id, $name);
        } else {
            db()->prepare('INSERT INTO partners (name, website_url, media_id, sort_order, is_active) VALUES (:name,:url,:media,:sort,:active)')->execute($values);
            $id = (int) db()->lastInsertId();
            admin_log((int) $actor['id'], 'partner.created', 'partner', $id, $name);
        }
        flash_add('success', $hasUpload ? 'Le partenaire a été enregistré et le logo ajouté à la Médiathèque.' : 'Le partenaire a été enregistré.');
        redirect('/admin/index.php?page=partners-admin');
    }
    if ($action === 'partner_toggle') {
        admin_require('pages.manage');
        $id = filter_var($_POST['partner_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) { flash_add('danger', 'Partenaire invalide.'); redirect('/admin/index.php?page=partners-admin'); }
        $stmt = db()->prepare('UPDATE partners SET is_active=1-is_active WHERE id=:id');
        $stmt->execute(['id' => $id]);
        if (!$stmt->rowCount()) { flash_add('warning', 'Partenaire introuvable.'); redirect('/admin/index.php?page=partners-admin'); }
        admin_log((int) $actor['id'], 'partner.visibility_changed', 'partner', (int) $id);
        flash_add('success', 'Visibilité du partenaire mise à jour.');
        redirect('/admin/index.php?page=partners-admin');
    }
    if ($action === 'page_save') {
        admin_require('pages.manage');
        $id = filter_var($_POST['page_id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim(is_string($_POST['title'] ?? null) ? $_POST['title'] : '');
        $content = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
        $seoTitle = trim(is_string($_POST['seo_title'] ?? null) ? $_POST['seo_title'] : '');
        $seoDescription = trim(is_string($_POST['seo_description'] ?? null) ? $_POST['seo_description'] : '');
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if (!$id || $title === '' || mb_strlen($title) > 190 || mb_strlen($content) > 500000 || mb_strlen($seoTitle) > 190 || mb_strlen($seoDescription) > 320 || !in_array($status, ['draft','published'], true)) {
            flash_add('danger', 'Vérifiez le titre, le contenu et les champs SEO.');
            redirect('/admin/index.php?page=site-pages' . ($id ? '&edit=' . (int)$id : ''));
        }
        $allowedSlugs = ['a-propos','politique-de-confidentialite','mentions-legales'];
        $stmt = db()->prepare('SELECT slug FROM pages WHERE id=:id'); $stmt->execute(['id'=>$id]); $slug=$stmt->fetchColumn();
        if (!$slug || !in_array($slug, $allowedSlugs, true)) { flash_add('danger', 'Cette page n’est pas modifiable depuis cet écran.'); redirect('/admin/index.php?page=site-pages'); }
        db()->prepare('UPDATE pages SET title=:title, content=:content, seo_title=:seo_title, seo_description=:seo_description, status=:status WHERE id=:id')
            ->execute(['title'=>$title,'content'=>sanitize_html($content),'seo_title'=>$seoTitle !== '' ? $seoTitle : null,'seo_description'=>$seoDescription !== '' ? $seoDescription : null,'status'=>$status,'id'=>$id]);
        admin_log((int)$actor['id'], 'page.updated', 'page', (int)$id, $slug . ' · ' . $status);
        flash_add('success', 'La page a été mise à jour.');
        redirect('/admin/index.php?page=site-pages&edit=' . (int)$id);
    }
    if ($action === 'faq_save') {
        admin_require('faqs.manage');
        $id=filter_var($_POST['faq_id']??null,FILTER_VALIDATE_INT);
        $question=trim(is_string($_POST['question']??null)?$_POST['question']:'');
        $answer=trim(is_string($_POST['answer']??null)?$_POST['answer']:'');
        $order=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT);
        if ($question==='' || mb_strlen($question)>255 || $answer==='' || mb_strlen($answer)>10000 || $order===false || $order<0 || $order>32767) { flash_add('danger','Vérifiez la question, la réponse et l’ordre.'); redirect('/admin/index.php?page=faqs'.($id?'&edit='.(int)$id:'')); }
        $values=['q'=>$question,'a'=>$answer,'o'=>$order,'active'=>isset($_POST['is_active'])?1:0];
        if ($id) { $values['id']=$id; $stmt=db()->prepare('UPDATE faqs SET question=:q,answer=:a,sort_order=:o,is_active=:active WHERE id=:id'); $stmt->execute($values); $check=db()->prepare('SELECT 1 FROM faqs WHERE id=:id'); $check->execute(['id'=>$id]); if (!$check->fetchColumn()) { flash_add('danger','Question introuvable.'); redirect('/admin/index.php?page=faqs'); } admin_log((int)$actor['id'],'faq.updated','faq',(int)$id); }
        else { db()->prepare('INSERT INTO faqs (question,answer,sort_order,is_active) VALUES (:q,:a,:o,:active)')->execute($values); $id=(int)db()->lastInsertId(); admin_log((int)$actor['id'],'faq.created','faq',$id); }
        flash_add('success','La question fréquente a été enregistrée.'); redirect('/admin/index.php?page=faqs&edit='.(int)$id);
    }
    if ($action === 'faq_toggle') {
        admin_require('faqs.manage'); $id=filter_var($_POST['faq_id']??null,FILTER_VALIDATE_INT);
        if (!$id) { flash_add('danger','Question invalide.'); redirect('/admin/index.php?page=faqs'); }
        db()->prepare('UPDATE faqs SET is_active=1-is_active WHERE id=:id')->execute(['id'=>$id]);
        admin_log((int)$actor['id'],'faq.visibility_changed','faq',(int)$id); flash_add('success','Visibilité mise à jour.'); redirect('/admin/index.php?page=faqs');
    }
    if ($action === 'service_save') {
        admin_require('services.manage');
        $id=filter_var($_POST['service_id']??null,FILTER_VALIDATE_INT); $slug=trim(is_string($_POST['slug']??null)?$_POST['slug']:'');
        $name=trim(is_string($_POST['name']??null)?$_POST['name']:''); $short=trim(is_string($_POST['short_description']??null)?$_POST['short_description']:'');
        $description=is_string($_POST['description']??null)?$_POST['description']:''; $approach=is_string($_POST['approach_text']??null)?$_POST['approach_text']:'';
        $cta=trim(is_string($_POST['cta_text']??null)?$_POST['cta_text']:''); $icon=trim(is_string($_POST['icon']??null)?$_POST['icon']:'');
        $profile=is_string($_POST['quote_profile']??null)?$_POST['quote_profile']:''; $order=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT);
        $status=is_string($_POST['status']??null)?$_POST['status']:''; $seoTitle=trim(is_string($_POST['seo_title']??null)?$_POST['seo_title']:''); $seoDesc=trim(is_string($_POST['seo_description']??null)?$_POST['seo_description']:'');
        $offerText=is_string($_POST['offerings']??null)?$_POST['offerings']:''; $offers=array_values(array_filter(array_map('trim',preg_split('/\R/u',$offerText)?:[]),static fn($v)=>$v!==''));
        if (!is_valid_slug($slug)||strlen($slug)>120||$name===''||mb_strlen($name)>150||$short===''||mb_strlen($short)>500||mb_strlen($description)>500000||mb_strlen($approach)>100000||mb_strlen($cta)>255||($icon!==''&&!preg_match('/^bi-[a-z0-9-]{1,40}$/',$icon))||!in_array($profile,['generic','print','textile','embroidery'],true)||$order===false||$order<0||$order>32767||!in_array($status,['draft','published'],true)||mb_strlen($seoTitle)>190||mb_strlen($seoDesc)>320||count($offers)>50||array_filter($offers,static fn($v)=>mb_strlen($v)>190)) { flash_add('danger','Vérifiez les champs du service et des prestations (50 lignes maximum).'); redirect('/admin/index.php?page=services-admin'.($id?'&edit='.(int)$id:'')); }
        $dupe=db()->prepare('SELECT id FROM services WHERE slug=:slug AND id<>:id'); $dupe->execute(['slug'=>$slug,'id'=>$id?:0]);
        if ($dupe->fetchColumn()) { flash_add('danger','Cette URL est déjà utilisée.'); redirect('/admin/index.php?page=services-admin'.($id?'&edit='.(int)$id:'')); }
        $pdo=db(); $pdo->beginTransaction();
        $data=['slug'=>$slug,'name'=>$name,'short'=>$short,'description'=>sanitize_html($description),'approach'=>sanitize_html($approach),'cta'=>$cta?:null,'icon'=>$icon?:null,'profile'=>$profile,'seo_title'=>$seoTitle?:null,'seo_description'=>$seoDesc?:null,'sort'=>$order,'status'=>$status];
        if ($id) { $data['id']=$id; $pdo->prepare('UPDATE services SET slug=:slug,name=:name,short_description=:short,description=:description,approach_text=:approach,cta_text=:cta,icon=:icon,quote_profile=:profile,seo_title=:seo_title,seo_description=:seo_description,sort_order=:sort,status=:status WHERE id=:id')->execute($data); $exists=$pdo->prepare('SELECT 1 FROM services WHERE id=:id'); $exists->execute(['id'=>$id]); if (!$exists->fetchColumn()) { $pdo->rollBack(); flash_add('danger','Service introuvable.'); redirect('/admin/index.php?page=services-admin'); } $serviceId=(int)$id; }
        else { $pdo->prepare('INSERT INTO services (slug,name,short_description,description,approach_text,cta_text,icon,quote_profile,seo_title,seo_description,sort_order,status) VALUES (:slug,:name,:short,:description,:approach,:cta,:icon,:profile,:seo_title,:seo_description,:sort,:status)')->execute($data); $serviceId=(int)$pdo->lastInsertId(); }
        $pdo->prepare('DELETE FROM service_offerings WHERE service_id=:id')->execute(['id'=>$serviceId]); $insert=$pdo->prepare('INSERT INTO service_offerings (service_id,label,sort_order) VALUES (:id,:label,:sort)'); foreach($offers as $i=>$label) $insert->execute(['id'=>$serviceId,'label'=>$label,'sort'=>$i+1]);
        $pdo->commit(); admin_log((int)$actor['id'],$id?'service.updated':'service.created','service',$serviceId); flash_add('success','Service enregistré.'); redirect('/admin/index.php?page=services-admin&edit='.$serviceId);
    }
    if ($action === 'project_save') {
        admin_require('projects.manage');
        $id=filter_var($_POST['project_id']??null,FILTER_VALIDATE_INT); $slug=trim(is_string($_POST['slug']??null)?$_POST['slug']:'');
        $title=trim(is_string($_POST['title']??null)?$_POST['title']:''); $summary=trim(is_string($_POST['summary']??null)?$_POST['summary']:''); $description=is_string($_POST['description']??null)?$_POST['description']:'';
        $serviceId=filter_var($_POST['service_id']??null,FILTER_VALIDATE_INT); $categoryId=filter_var($_POST['category_id']??null,FILTER_VALIDATE_INT); $mediaId=filter_var($_POST['cover_media_id']??null,FILTER_VALIDATE_INT);
        $client=trim(is_string($_POST['client_name']??null)?$_POST['client_name']:''); $date=trim(is_string($_POST['completed_on']??null)?$_POST['completed_on']:''); $status=is_string($_POST['status']??null)?$_POST['status']:'';
        $galleryRaw=is_array($_POST['gallery_media_ids']??null)?$_POST['gallery_media_ids']:[];
        $galleryIds=[]; foreach($galleryRaw as $rawId){$galleryId=filter_var($rawId,FILTER_VALIDATE_INT);if($galleryId&&$galleryId>0)$galleryIds[]=(int)$galleryId;}
        $galleryIds=array_values(array_unique($galleryIds));
        $galleryUploads=[]; $galleryUploadError=null; $uploadFiles=$_FILES['gallery_files']??null;
        if (is_array($uploadFiles) && is_array($uploadFiles['name']??null)) {
            foreach (array_keys($uploadFiles['name']) as $fileKey) {
                $error=$uploadFiles['error'][$fileKey]??UPLOAD_ERR_NO_FILE;
                if ($error===UPLOAD_ERR_NO_FILE) continue;
                $file=['name'=>$uploadFiles['name'][$fileKey]??'','type'=>$uploadFiles['type'][$fileKey]??'','tmp_name'=>$uploadFiles['tmp_name'][$fileKey]??'','error'=>$error,'size'=>$uploadFiles['size'][$fileKey]??0];
                $inspected=attachment_inspect($file);
                if ($inspected['status']!=='ok' || !str_starts_with((string)($inspected['mime']??''),'image/')) {
                    $galleryUploadError=$inspected['status']==='error'?(string)$inspected['message']:'Choisissez des photos au format JPG, PNG ou WebP.';
                    break;
                }
                $galleryUploads[]=$inspected;
            }
        } elseif ($uploadFiles !== null) {
            $galleryUploadError='Le format des fichiers reçus est invalide.';
        }
        if ($galleryUploadError!==null) { flash_add('danger',$galleryUploadError); redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:'')); }
        $seoTitle=trim(is_string($_POST['seo_title']??null)?$_POST['seo_title']:''); $seoDesc=trim(is_string($_POST['seo_description']??null)?$_POST['seo_description']:'');
        $dateValid=true; if ($date!=='') { $parts=array_map('intval',explode('-',$date)); $dateValid=count($parts)===3&&checkdate($parts[1]??0,$parts[2]??0,$parts[0]??0); }
        if (!is_valid_slug($slug)||$title===''||mb_strlen($title)>190||mb_strlen($summary)>500||mb_strlen($description)>500000||mb_strlen($client)>150||mb_strlen($seoTitle)>190||mb_strlen($seoDesc)>320||!$serviceId||!in_array($status,['draft','published'],true)||!$dateValid||count($galleryIds)+count($galleryUploads)>20) { flash_add('danger','Vérifiez les champs et choisissez au maximum 20 photos au total.'); redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:'')); }
        $pdo=db(); $svc=$pdo->prepare('SELECT 1 FROM services WHERE id=:id'); $svc->execute(['id'=>$serviceId]);
        if (!$svc->fetchColumn()) { flash_add('danger','Sélectionnez un service existant.'); redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:'')); }
        if ($categoryId) { $cat=$pdo->prepare("SELECT 1 FROM categories WHERE id=:id AND scope='project'"); $cat->execute(['id'=>$categoryId]); if (!$cat->fetchColumn()) $categoryId=null; }
        if ($mediaId) { $med=$pdo->prepare("SELECT 1 FROM media WHERE id=:id AND mime_type LIKE 'image/%'"); $med->execute(['id'=>$mediaId]); if (!$med->fetchColumn()) $mediaId=null; }
        if($galleryIds){$in=implode(',',array_fill(0,count($galleryIds),'?'));$med=$pdo->prepare("SELECT id FROM media WHERE mime_type LIKE 'image/%' AND id IN ($in)");$med->execute($galleryIds);if(count($med->fetchAll(PDO::FETCH_COLUMN))!==count($galleryIds)){flash_add('danger','Une ou plusieurs images sélectionnées ne sont plus disponibles.');redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:''));}}
        $dupe=$pdo->prepare('SELECT id FROM projects WHERE slug=:slug AND id<>:id'); $dupe->execute(['slug'=>$slug,'id'=>$id?:0]); if ($dupe->fetchColumn()) { flash_add('danger','Cette URL est déjà utilisée.'); redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:'')); }
        $pub=$status==='published'?gmdate('Y-m-d H:i:s'):null; $demo=isset($_POST['is_demo'])?1:0;
        $params=['slug'=>$slug,'title'=>$title,'summary'=>$summary?:null,'description'=>sanitize_html($description),'service'=>$serviceId,'category'=>$categoryId?:null,'client'=>$client?:null,'date'=>$date?:null,'media'=>$mediaId?:null,'seo_title'=>$seoTitle?:null,'seo_description'=>$seoDesc?:null,'demo'=>$demo,'status'=>$status,'pub'=>$pub];
        $uploadedGalleryPaths=[];
        $pdo->beginTransaction();
        try {
            foreach($galleryUploads as $upload){
                $stored=bin2hex(random_bytes(16)).'.'.$upload['ext'];
                $destination=rtrim((string)config('paths.uploads'),'/\\').DIRECTORY_SEPARATOR.$stored;
                if(!@move_uploaded_file($upload['tmp'],$destination)) throw new RuntimeException('gallery_upload_move_failed');
                @chmod($destination,0640); $uploadedGalleryPaths[]=$destination;
                $dimensions=@getimagesize($destination);
                $pdo->prepare('INSERT INTO media (stored_name,original_name,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES (:name,:original,:mime,:size,:width,:height,:alt,:uid)')
                    ->execute(['name'=>$stored,'original'=>$upload['original'],'mime'=>$upload['mime'],'size'=>$upload['size'],'width'=>$dimensions[0]??null,'height'=>$dimensions[1]??null,'alt'=>$upload['original'],'uid'=>(int)$actor['id']]);
                $galleryIds[]=(int)$pdo->lastInsertId();
            }
            if ($id) { $params['id']=$id; $pdo->prepare('UPDATE projects SET slug=:slug,title=:title,summary=:summary,description=:description,service_id=:service,category_id=:category,client_name=:client,completed_on=:date,cover_media_id=:media,seo_title=:seo_title,seo_description=:seo_description,is_demo=:demo,status=:status,published_at=CASE WHEN :status_pub=\'published\' THEN COALESCE(published_at,:pub) ELSE NULL END WHERE id=:id')->execute($params+['status_pub'=>$status]); }
            else { $pdo->prepare('INSERT INTO projects (slug,title,summary,description,service_id,category_id,client_name,completed_on,cover_media_id,seo_title,seo_description,is_demo,status,published_at) VALUES (:slug,:title,:summary,:description,:service,:category,:client,:date,:media,:seo_title,:seo_description,:demo,:status,:pub)')->execute($params); $id=(int)$pdo->lastInsertId(); }
            $pdo->prepare('DELETE FROM project_images WHERE project_id=:id')->execute(['id'=>$id]);
            if($galleryIds){$galleryInsert=$pdo->prepare('INSERT INTO project_images (project_id,media_id,caption,sort_order) SELECT :project_id,id,alt_text,:sort_order FROM media WHERE id=:media_id');foreach($galleryIds as $position=>$galleryMediaId)$galleryInsert->execute(['project_id'=>$id,'sort_order'=>$position,'media_id'=>$galleryMediaId]);}
            $pdo->commit();
        } catch (Throwable $error) {
            if($pdo->inTransaction())$pdo->rollBack();
            foreach($uploadedGalleryPaths as $uploadedPath) @unlink($uploadedPath);
            if($error instanceof RuntimeException && $error->getMessage()==='gallery_upload_move_failed'){
                flash_add('danger','Une photo n’a pas pu être enregistrée. Vérifiez les droits du dossier des images.');
                redirect('/admin/index.php?page=projects-admin'.($id?'&edit='.(int)$id:''));
            }
            throw $error;
        }
        admin_log((int)$actor['id'],$id?'project.saved':'project.saved','project',(int)$id); flash_add('success',count($galleryUploads)?'Réalisation et photos enregistrées. Les photos ajoutées sont aussi disponibles dans la Médiathèque.':'Réalisation enregistrée.'); redirect('/admin/index.php?page=projects-admin&edit='.(int)$id);
    }
    if ($action === 'product_save') {
        admin_require('products.manage');
        $id=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT); $slug=trim(is_string($_POST['slug']??null)?$_POST['slug']:'');
        $name=trim(is_string($_POST['name']??null)?$_POST['name']:''); $description=is_string($_POST['description']??null)?$_POST['description']:'';
        $serviceId=filter_var($_POST['service_id']??null,FILTER_VALIDATE_INT); $categoryId=filter_var($_POST['category_id']??null,FILTER_VALIDATE_INT); $mediaId=filter_var($_POST['media_id']??null,FILTER_VALIDATE_INT);
        $priceRaw=trim(is_string($_POST['price_amount']??null)?$_POST['price_amount']:''); $priceNote=trim(is_string($_POST['price_note']??null)?$_POST['price_note']:'');
        $status=is_string($_POST['status']??null)?$_POST['status']:''; $sort=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT);
        $seoTitle=trim(is_string($_POST['seo_title']??null)?$_POST['seo_title']:''); $seoDesc=trim(is_string($_POST['seo_description']??null)?$_POST['seo_description']:'');
        if(!is_valid_slug($slug)||$name===''||mb_strlen($name)>190||mb_strlen($description)>10000||!$serviceId||($priceRaw!==''&&!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$priceRaw))||mb_strlen($priceNote)>120||!in_array($status,['draft','published'],true)||$sort===false||$sort<0||$sort>32767||mb_strlen($seoTitle)>190||mb_strlen($seoDesc)>320){flash_add('danger','Vérifiez les champs du produit et le format du prix.');redirect('/admin/index.php?page=products-admin'.($id?'&edit='.(int)$id:''));}
        $pdo=db(); $svc=$pdo->prepare('SELECT 1 FROM services WHERE id=:id');$svc->execute(['id'=>$serviceId]);if(!$svc->fetchColumn()){flash_add('danger','Sélectionnez un service existant.');redirect('/admin/index.php?page=products-admin'.($id?'&edit='.(int)$id:''));}
        if($categoryId){$cat=$pdo->prepare("SELECT 1 FROM categories WHERE id=:id AND scope='product'");$cat->execute(['id'=>$categoryId]);if(!$cat->fetchColumn())$categoryId=null;}
        if($mediaId){$med=$pdo->prepare('SELECT 1 FROM media WHERE id=:id');$med->execute(['id'=>$mediaId]);if(!$med->fetchColumn())$mediaId=null;}
        $dup=$pdo->prepare('SELECT id FROM products WHERE slug=:slug AND id<>:id');$dup->execute(['slug'=>$slug,'id'=>$id?:0]);if($dup->fetchColumn()){flash_add('danger','Cette URL est déjà utilisée.');redirect('/admin/index.php?page=products-admin'.($id?'&edit='.(int)$id:''));}
        $data=['slug'=>$slug,'name'=>$name,'description'=>sanitize_html($description),'service'=>$serviceId,'category'=>$categoryId?:null,'price'=>$priceRaw!==''?$priceRaw:null,'note'=>$priceNote?:null,'media'=>$mediaId?:null,'seo_title'=>$seoTitle?:null,'seo_description'=>$seoDesc?:null,'sort'=>$sort,'status'=>$status];
        if($id){$data['id']=$id;$pdo->prepare('UPDATE products SET slug=:slug,name=:name,description=:description,service_id=:service,category_id=:category,price_amount=:price,price_note=:note,media_id=:media,seo_title=:seo_title,seo_description=:seo_description,sort_order=:sort,status=:status WHERE id=:id')->execute($data);$ex=$pdo->prepare('SELECT 1 FROM products WHERE id=:id');$ex->execute(['id'=>$id]);if(!$ex->fetchColumn()){flash_add('danger','Produit introuvable.');redirect('/admin/index.php?page=products-admin');}}
        else{$pdo->prepare('INSERT INTO products (slug,name,description,service_id,category_id,price_amount,price_note,media_id,seo_title,seo_description,sort_order,status) VALUES (:slug,:name,:description,:service,:category,:price,:note,:media,:seo_title,:seo_description,:sort,:status)')->execute($data);$id=(int)$pdo->lastInsertId();}
        admin_log((int)$actor['id'],'product.saved','product',(int)$id);flash_add('success','Produit enregistré.');redirect('/admin/index.php?page=products-admin&edit='.(int)$id);
    }
    if ($action === 'media_upload') {
        admin_require('media.manage');
        $alt=trim(is_string($_POST['alt_text']??null)?$_POST['alt_text']:'');
        $file=is_array($_FILES['media_file']??null)?$_FILES['media_file']:null;
        $inspected=attachment_inspect($file);
        if($alt===''||mb_strlen($alt)>255||$inspected['status']!=='ok'||!str_starts_with((string)($inspected['mime']??''),'image/')){
            $message=$inspected['status']==='error'?(string)$inspected['message']:'Choisissez une image JPG, PNG ou WebP et renseignez son texte alternatif (255 caractères maximum).';
            flash_add('danger',$message);redirect('/admin/index.php?page=media');
        }
        $stored=bin2hex(random_bytes(16)).'.'.$inspected['ext'];
        $destination=rtrim((string)config('paths.uploads'),'/\\\\').DIRECTORY_SEPARATOR.$stored;
        if(!@move_uploaded_file($inspected['tmp'],$destination)){flash_add('danger','L’image n’a pas pu être enregistrée. Vérifiez les droits du dossier des images.');redirect('/admin/index.php?page=media');}
        @chmod($destination,0640);$dimensions=@getimagesize($destination);
        try{
            $pdo=db();$pdo->prepare('INSERT INTO media (stored_name,original_name,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES (:name,:original,:mime,:size,:width,:height,:alt,:uid)')->execute(['name'=>$stored,'original'=>$inspected['original'],'mime'=>$inspected['mime'],'size'=>(int)$inspected['size'],'width'=>$dimensions[0]??null,'height'=>$dimensions[1]??null,'alt'=>$alt,'uid'=>(int)$actor['id']]);
            $mediaId=(int)$pdo->lastInsertId();admin_log((int)$actor['id'],'media.uploaded','media',$mediaId);flash_add('success','Image ajoutée à la Médiathèque. Elle est disponible dans les formulaires du site.');
        }catch(Throwable $error){@unlink($destination);error_log('media_upload: '.$error->getMessage());flash_add('danger','Impossible d’enregistrer cette image dans la Médiathèque.');redirect('/admin/index.php?page=media');}
        redirect('/admin/index.php?page=media&library_p=1');
    }
    if ($action === 'media_alt_save') {
        admin_require('media.manage');
        $id=filter_var($_POST['media_id']??null,FILTER_VALIDATE_INT);$alt=trim(is_string($_POST['alt_text']??null)?$_POST['alt_text']:'');$returnPage=max(1,min(1000,(int)($_POST['library_p']??1)));
        if(!$id||$alt===''||mb_strlen($alt)>255){flash_add('danger','Le texte alternatif est obligatoire et doit contenir au plus 255 caractères.');redirect('/admin/index.php?page=media&library_p='.$returnPage);}
        $pdo=db();$find=$pdo->prepare("SELECT id FROM media WHERE id=:id AND mime_type LIKE 'image/%'");$find->execute(['id'=>$id]);
        if(!$find->fetchColumn()){flash_add('danger','Cette image est introuvable dans la Médiathèque.');redirect('/admin/index.php?page=media&library_p='.$returnPage);}
        $pdo->prepare('UPDATE media SET alt_text=:alt WHERE id=:id')->execute(['alt'=>$alt,'id'=>$id]);admin_log((int)$actor['id'],'media.alt_updated','media',(int)$id);flash_add('success','Texte alternatif mis à jour.');redirect('/admin/index.php?page=media&library_p='.$returnPage);
    }
    if ($action === 'media_import') {
        admin_require('media.manage');
        $sourceName=is_string($_POST['source_name']??null)?$_POST['source_name']:'';
        $alt=trim(is_string($_POST['alt_text']??null)?$_POST['alt_text']:'');
        if(!preg_match('/^[a-z0-9-]+-p[0-9]{2}-[a-f0-9]{8}\.webp$/',$sourceName)||$alt===''||mb_strlen($alt)>255){flash_add('danger','Ajoutez un texte alternatif (255 caractères maximum) et vérifiez le fichier.');redirect('/admin/index.php?page=media');}
        $manifestPath=config('paths.private').'/media-imports/manifest.json';$data=is_file($manifestPath)?json_decode((string)file_get_contents($manifestPath),true):null;$relative='';$asset=null;
        foreach(($data['assets']??[]) as $item){$candidate=(string)($item['path']??'');if(basename($candidate)===$sourceName&&preg_match('#^storage/private/media-imports/(?:catalogue-pages|realisations-photos)/[a-z0-9-]+-p[0-9]{2}-[a-f0-9]{8}\.webp$#',$candidate)){$relative=substr($candidate,strlen('storage/private/media-imports/'));$asset=$item;break;}}
        $root=realpath(config('paths.private').'/media-imports');$relativeFs=str_replace(['/','\\'],DIRECTORY_SEPARATOR,$relative);$source=$root&&$relative!==''?realpath($root.DIRECTORY_SEPARATOR.$relativeFs):false;
        if(!$source||!str_starts_with($source,$root.DIRECTORY_SEPARATOR)||!is_file($source)||filesize($source)>10*1024*1024){flash_add('danger','Le fichier est introuvable ou dépasse 10 Mo.');redirect('/admin/index.php?page=media');}
        $image=@getimagesize($source);if(!$image||($image['mime']??'')!=='image/webp'||$image[0]<1||$image[1]<1||$image[0]>65535||$image[1]>65535){flash_add('danger','Le fichier importé n’est pas une image WebP valide.');redirect('/admin/index.php?page=media');}
        $storedName=admin_import_stored_name($relative);$destination=rtrim(config('paths.uploads'),'/\\').DIRECTORY_SEPARATOR.$storedName;$copied=false;
        try {
            if(!is_file($destination)){if(!copy($source,$destination))throw new RuntimeException('copy failed');$copied=true;}
            $pdo=db();$existing=$pdo->prepare('SELECT id FROM media WHERE stored_name=:name');$existing->execute(['name'=>$storedName]);$existingId=$existing->fetchColumn();
            if($existingId){$pdo->prepare('UPDATE media SET alt_text=:alt WHERE id=:id')->execute(['alt'=>$alt,'id'=>$existingId]);$mediaId=(int)$existingId;}
            else{$original=mb_substr(trim((string)($asset['source_pdf']??'Image importée')).' · page '.(int)($asset['source_page']??0),0,255);$pdo->prepare('INSERT INTO media (stored_name,original_name,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES (:name,:original,\'image/webp\',:size,:width,:height,:alt,:uid)')->execute(['name'=>$storedName,'original'=>$original,'size'=>filesize($destination),'width'=>$image[0],'height'=>$image[1],'alt'=>$alt,'uid'=>(int)$actor['id']]);$mediaId=(int)$pdo->lastInsertId();}
            admin_log((int)$actor['id'],'media.imported','media',$mediaId,$sourceName);flash_add('success','Image validée et ajoutée à la médiathèque. Elle est maintenant disponible pour les réalisations.');
        } catch(Throwable $error){if($copied&&is_file($destination))@unlink($destination);flash_add('danger','Impossible d’ajouter cette image à la médiathèque.');}
        redirect('/admin/index.php?page=media');
    }
    if ($action === 'settings_save') {
        admin_require('settings.manage');
        $fields=[
            'company_name'=>['Identité',190,'text'],'tagline'=>['Identité',500,'text'],'footer_text'=>['Identité',1000,'text'],
            'logo_media_id'=>['Identité',20,'media'],'favicon_media_id'=>['Identité',20,'media'],
            'announcement_message'=>['Contact',500,'text'],'phone'=>['Contact',40,'text'],'whatsapp'=>['Contact',40,'text'],'email'=>['Contact',190,'email'],'address'=>['Contact',500,'text'],'opening_hours'=>['Contact',500,'text'],
            'facebook_url'=>['Réseaux sociaux',255,'url'],'instagram_url'=>['Réseaux sociaux',255,'url'],'linkedin_url'=>['Réseaux sociaux',255,'url'],
            'currency_code'=>['Commerce',3,'currency'],
        ];
        if (admin_can('seo.manage')) { $fields['seo_default_title']=['Référencement',190,'text']; $fields['seo_default_description']=['Référencement',320,'text']; }
        $submitted=is_array($_POST['settings']??null)?$_POST['settings']:[]; $errors=[]; $clean=[];
        foreach($fields as $key=>[$group,$max,$type]) {
            $value=trim(is_string($submitted[$key]??null)?$submitted[$key]:'');
            if(mb_strlen($value)>$max){$errors[]=$key;continue;}
            if($type==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL)){$errors[]=$key;continue;}
            if($type==='url'&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||strtolower((string)parse_url($value,PHP_URL_SCHEME))!=='https')){$errors[]=$key;continue;}
            if($type==='currency'&&$value!==''&&!preg_match('/^[A-Z]{3}$/',strtoupper($value))){$errors[]=$key;continue;}
            if($type==='media'&&$value!==''&&(!ctype_digit($value)||(int)$value<1)){$errors[]=$key;continue;}
            $clean[$key]=$type==='currency'?strtoupper($value):$value;
        }
        $mediaValues=array_filter([$clean['logo_media_id']??'', $clean['favicon_media_id']??''],static fn($v)=>$v!=='');
        if($mediaValues){$ids=array_values(array_unique(array_map('intval',$mediaValues)));$in=implode(',',array_fill(0,count($ids),'?'));$check=db()->prepare("SELECT id FROM media WHERE mime_type LIKE 'image/%' AND id IN ($in)");$check->execute($ids);if(count($check->fetchAll(PDO::FETCH_COLUMN))!==count($ids))$errors[]='media';}
        if($errors){flash_add('danger','Certains paramètres sont invalides ou trop longs. Vérifiez l’e-mail, les URL HTTPS et la devise (3 lettres).');redirect('/admin/index.php?page=settings-admin');}
        $pdo=db();$stmt=$pdo->prepare('UPDATE site_settings SET setting_value=:value WHERE setting_key=:key');
        foreach($clean as $key=>$value)$stmt->execute(['value'=>$value,'key'=>$key]);
        admin_log((int)$actor['id'],'settings.updated','settings',null,'Paramètres du site mis à jour');flash_add('success','Paramètres enregistrés.');redirect('/admin/index.php?page=settings-admin');
    }
    if ($action === 'home_section_save') {
        admin_require('pages.manage');
        $id=filter_var($_POST['section_id']??null,FILTER_VALIDATE_INT);$title=trim(is_string($_POST['title']??null)?$_POST['title']:'');$subtitle=trim(is_string($_POST['subtitle']??null)?$_POST['subtitle']:'');$body=trim(is_string($_POST['body']??null)?$_POST['body']:'');
        $cta=trim(is_string($_POST['cta_label']??null)?$_POST['cta_label']:'');$url=trim(is_string($_POST['cta_url']??null)?$_POST['cta_url']:'');$cta2=trim(is_string($_POST['cta2_label']??null)?$_POST['cta2_label']:'');$url2=trim(is_string($_POST['cta2_url']??null)?$_POST['cta2_url']:'');$sort=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT);
        $safeUrl=static function(string $v):bool{return $v===''||(str_starts_with($v,'/')&&!str_starts_with($v,'//')&&!preg_match('/[\x00-\x20]/',$v))||(filter_var($v,FILTER_VALIDATE_URL)&&strtolower((string)parse_url($v,PHP_URL_SCHEME))==='https');};
        if(!$id||mb_strlen($title)>190||mb_strlen($subtitle)>5000||mb_strlen($body)>5000||mb_strlen($cta)>80||mb_strlen($url)>255||mb_strlen($cta2)>80||mb_strlen($url2)>255||!$safeUrl($url)||!$safeUrl($url2)||$sort===false||$sort<0||$sort>32767){flash_add('danger','Vérifiez les textes, les liens (chemin interne ou URL HTTPS) et l’ordre.');redirect('/admin/index.php?page=home-content-admin');}
        $lookup=db()->prepare('SELECT section_key FROM home_sections WHERE id=:id');$lookup->execute(['id'=>$id]);$sectionKey=$lookup->fetchColumn();
        $values=['title'=>$title?:null,'subtitle'=>$subtitle?:null,'body'=>$body?:null,'cta'=>$cta?:null,'url'=>$url?:null,'cta2'=>$cta2?:null,'url2'=>$url2?:null,'sort'=>$sort,'active'=>isset($_POST['is_active'])?1:0,'id'=>$id];
        if($sectionKey==='hero'){
            $rawMedia=trim(is_string($_POST['media_id']??null)?$_POST['media_id']:'');
            $mediaId=$rawMedia===''?null:filter_var($rawMedia,FILTER_VALIDATE_INT);
            if($rawMedia!==''&&(!$mediaId||$mediaId<1)){flash_add('danger','Choisissez une image valide dans la médiathèque.');redirect('/admin/index.php?page=home-content-admin');}
            if($mediaId){$validMedia=db()->prepare("SELECT 1 FROM media WHERE id=:id AND mime_type LIKE 'image/%'");$validMedia->execute(['id'=>$mediaId]);if(!$validMedia->fetchColumn()){flash_add('danger','Cette image n’existe plus dans la médiathèque.');redirect('/admin/index.php?page=home-content-admin');}}
            $values['media']=$mediaId;
            $stmt=db()->prepare('UPDATE home_sections SET title=:title,subtitle=:subtitle,body=:body,media_id=:media,cta_label=:cta,cta_url=:url,cta2_label=:cta2,cta2_url=:url2,sort_order=:sort,is_active=:active WHERE id=:id');
        }else{
            $stmt=db()->prepare('UPDATE home_sections SET title=:title,subtitle=:subtitle,body=:body,cta_label=:cta,cta_url=:url,cta2_label=:cta2,cta2_url=:url2,sort_order=:sort,is_active=:active WHERE id=:id');
        }
        $stmt->execute($values);
        admin_log((int)$actor['id'],'home_section.updated','home_section',(int)$id);flash_add('success','Section d’accueil enregistrée.');redirect('/admin/index.php?page=home-content-admin');
    }
    if ($action === 'testimonial_status') {
        admin_require('testimonials.manage');$id=filter_var($_POST['testimonial_id']??null,FILTER_VALIDATE_INT);$status=is_string($_POST['status']??null)?$_POST['status']:'';
        if(!$id||!in_array($status,['pending','approved','rejected'],true)){flash_add('danger','Témoignage ou statut invalide.');redirect('/admin/index.php?page=testimonials-admin');}
        $stmt=db()->prepare('UPDATE testimonials SET status=:status,reviewed_by=:uid,reviewed_at=CASE WHEN :reviewed_status=\'pending\' THEN NULL ELSE UTC_TIMESTAMP() END WHERE id=:id');$stmt->execute(['status'=>$status,'uid'=>$status==='pending'?null:(int)$actor['id'],'reviewed_status'=>$status,'id'=>$id]);
        admin_log((int)$actor['id'],'testimonial.reviewed','testimonial',(int)$id,$status);flash_add('success','Décision enregistrée.');redirect('/admin/index.php?page=testimonials-admin');
    }
    flash_add('warning', 'Cette action n’est pas reconnue.');
    redirect('/admin/index.php');
}

if (($_GET['page'] ?? '') === 'login' && admin_actor() === null) admin_login();
$actor = admin_actor();
if ($actor === null) admin_login();


if (isset($_GET['quote_pdf'])) {
    $quotePdfId = filter_var($_GET['quote_pdf'], FILTER_VALIDATE_INT);
    if (!$quotePdfId || $quotePdfId < 1) render_error(404);
    quote_pdf_download($quotePdfId);
}

if (isset($_GET['download'])) {
    admin_require('quotes.view');
    $attachmentId = filter_var($_GET['download'], FILTER_VALIDATE_INT);
    $stmt = db()->prepare('SELECT stored_name FROM quote_attachments WHERE id = :id');
    $stmt->execute(['id' => $attachmentId ?: 0]);
    $file = $stmt->fetch();
    if (!$file || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$/i', (string) $file['stored_name'])) render_error(404);
    $path = config('paths.private') . '/quotes/' . $file['stored_name'];
    $real = realpath($path);
    $base = realpath(config('paths.private') . '/quotes');
    if (!$real || !$base || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) render_error(404);
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="fichier-transmis"');
    header('Content-Length: ' . filesize($real));
    readfile($real);
    exit;
}
if (isset($_GET['preview'])) {
    admin_require('media.manage');
    $name = is_string($_GET['preview']) ? $_GET['preview'] : '';
    if (!preg_match('/^[a-z0-9-]+-p[0-9]{2}-[a-f0-9]{8}\.webp$/', $name)) render_error(404);
    $manifestPath = config('paths.private') . '/media-imports/manifest.json';
    $data = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
    $relative = '';
    foreach (($data['assets'] ?? []) as $item) {
        $candidate = (string) ($item['path'] ?? '');
        if (basename($candidate) === $name && preg_match('#^storage/private/media-imports/(?:catalogue-pages|realisations-photos)/[a-z0-9-]+-p[0-9]{2}-[a-f0-9]{8}\.webp$#', $candidate)) {
            $relative = substr($candidate, strlen('storage/private/media-imports/'));
            break;
        }
    }
    if ($relative === '') render_error(404);
    $root = realpath(config('paths.private') . '/media-imports');
    $relativeFs = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $real = $root ? realpath($root . DIRECTORY_SEPARATOR . $relativeFs) : false;
    if (!$root || !$real || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) render_error(404);
    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($real));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
}
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
if ($page === 'quotes' || $page === 'quote') admin_require('quotes.view');
elseif ($page === 'messages') admin_require('messages.manage');
elseif ($page === 'media') admin_require('media.manage');
elseif ($page === 'site-pages') admin_require('pages.manage');
elseif ($page === 'partners-admin') admin_require('pages.manage');
elseif ($page === 'faqs') admin_require('faqs.manage');
elseif ($page === 'services-admin') admin_require('services.manage');
elseif ($page === 'projects-admin') admin_require('projects.manage');
elseif ($page === 'products-admin') admin_require('products.manage');
elseif ($page === 'settings-admin') admin_require('settings.manage');
elseif ($page === 'users-admin') admin_require('users.manage');
elseif ($page === 'account') { }
elseif ($page === 'home-content-admin') admin_require('pages.manage');
elseif ($page === 'testimonials-admin') admin_require('testimonials.manage');
else {
    $page = 'dashboard';
    admin_require('dashboard.view');
}

$titles = ['dashboard' => 'Vue d’ensemble', 'users-admin'=>'Comptes administrateurs', 'account'=>'Mon compte', 'quotes' => 'Demandes de devis', 'quote' => 'Détail de la demande', 'messages' => 'Messages', 'media' => 'Médiathèque', 'site-pages' => 'Pages du site', 'partners-admin' => 'Partenaires', 'faqs'=>'Questions fréquentes', 'services-admin'=>'Services', 'projects-admin'=>'Réalisations', 'products-admin'=>'Catalogue', 'settings-admin'=>'Paramètres', 'home-content-admin'=>'Contenu de l’accueil','testimonials-admin'=>'Témoignages'];
admin_header($titles[$page], $actor);

if ($page === 'account') {
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Sécurité du compte</span><h2>Changer mon mot de passe</h2></div></div><p class="media-note"><i class="bi bi-shield-lock"></i> Utilisez un mot de passe unique de 12 caractères minimum. Le mot de passe actuel est demandé pour confirmer cette opération.</p><form method="post" class="admin-form account-password-form">'.csrf_field().'<input type="hidden" name="action" value="password_change"><label for="current-password">Mot de passe actuel</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required><label for="new-password">Nouveau mot de passe</label><input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="4096" required><label for="new-password-confirm">Confirmer le nouveau mot de passe</label><input id="new-password-confirm" type="password" name="new_password_confirm" autocomplete="new-password" minlength="12" maxlength="4096" required><button class="btn btn-admin-primary" type="submit">Mettre à jour le mot de passe</button></form></section>';
}
if ($page === 'users-admin') {
    $roles=$actor['role_slug']==='super_admin'?db()->query('SELECT slug,name FROM roles ORDER BY name')->fetchAll():db()->query("SELECT slug,name FROM roles WHERE slug IN ('admin','content_manager','sales_manager') ORDER BY name")->fetchAll();
    $users=db()->query('SELECT u.id,u.full_name,u.email,u.is_active,u.created_at,u.last_login_at,r.slug AS role_slug,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.created_at DESC,u.id DESC')->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Équipe</span><h2>Ajouter un compte</h2></div></div><p class="media-note"><i class="bi bi-info-circle"></i> Le compte sera immédiatement actif. Le mot de passe ne sera plus visible après création : communiquez-le à son titulaire par un canal sûr.</p><form method="post" class="admin-form admin-user-create-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="admin_user_create"><div class="admin-user-create-grid"><div><label for="new-user-name">Nom complet</label><input id="new-user-name" name="full_name" maxlength="120" autocomplete="name" required></div><div><label for="new-user-email">Adresse e-mail</label><input id="new-user-email" name="email" type="email" maxlength="190" autocomplete="email" required></div><div><label for="new-user-role">Rôle</label><select id="new-user-role" name="role_slug" required>';
    foreach($roles as $role) echo '<option value="'.e($role['slug']).'">'.e($role['name']).'</option>';
    echo '</select></div><div><label for="new-user-password">Mot de passe initial</label><input id="new-user-password" type="password" name="password" minlength="12" maxlength="4096" autocomplete="new-password" required><small>12 caractères minimum.</small></div><div><label for="new-user-password-confirm">Confirmer le mot de passe</label><input id="new-user-password-confirm" type="password" name="password_confirm" minlength="12" maxlength="4096" autocomplete="new-password" required></div></div><button class="btn btn-admin-primary" type="submit"><i class="bi bi-person-plus"></i> Créer le compte</button></form></section>';
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Accès à l’administration</span><h2>Comptes existants</h2></div><span class="media-count">'.count($users).' compte(s)</span></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th>État</th><th>Action</th></tr></thead><tbody>';
    foreach($users as $user) {
        $active=(int)$user['is_active']===1;
        echo '<tr><td><strong>'.e($user['full_name']).'</strong><small class="d-block">'.e($user['email']).'</small></td><td>'.e($user['role_name']).'</td><td>'.e($user['last_login_at']?:'Jamais connecté').'</td><td><span class="status-pill '.($active?'status-approved':'status-rejected').'">'.($active?'Actif':'Désactivé').'</span></td><td>';
        if((int)$user['id']!==(int)$actor['id']) echo '<form method="post" class="inline-admin-action">'.csrf_field().'<input type="hidden" name="action" value="admin_user_toggle"><input type="hidden" name="user_id" value="'.(int)$user['id'].'"><button class="btn btn-sm '.($active?'btn-outline-admin':'btn-admin-primary').'" type="submit">'.($active?'Désactiver':'Activer').'</button></form>';
        else echo '<span class="admin-muted-inline">Votre compte</span>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

if ($page === 'dashboard') {
    $pdo = db();
    $stats = [
        'new' => (int) $pdo->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'nouvelle'")->fetchColumn(),
        'study' => (int) $pdo->query("SELECT COUNT(*) FROM quote_requests WHERE status IN ('en_etude','devis_envoye')")->fetchColumn(),
        'done' => (int) $pdo->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'terminee'")->fetchColumn(),
        'services' => (int) $pdo->query("SELECT COUNT(*) FROM services WHERE status = 'published'")->fetchColumn(),
        'projects' => (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'published'")->fetchColumn(),
        'messages' => admin_can('messages.manage') ? (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn() : 0,
    ];
    echo '<section class="admin-welcome"><div><span class="admin-eyebrow">Votre activité</span><h2>Bonjour ' . e(explode(' ', $actor['full_name'])[0]) . '</h2><p>Suivez les demandes et les contenus de Génie Solutions.</p></div>';
    if (admin_can('quotes.view')) echo '<a class="btn btn-admin-primary" href="/admin/index.php?page=quotes"><i class="bi bi-inbox"></i> Voir les demandes</a>';
    echo '</section>';
    echo '<section class="stat-grid" aria-label="Indicateurs du site">';
    foreach ([['Nouvelles demandes',$stats['new'],'bi-inbox','stat-blue'],['En cours d’étude',$stats['study'],'bi-hourglass-split','stat-red'],['Demandes terminées',$stats['done'],'bi-check2-circle','stat-green'],['Services actifs',$stats['services'],'bi-printer','stat-navy'],['Réalisations publiées',$stats['projects'],'bi-images','stat-violet']] as [$label,$value,$icon,$class]) {
        echo '<article class="stat-card"><span class="stat-icon ' . e($class) . '"><i class="bi ' . e($icon) . '"></i></span><span class="stat-label">' . e($label) . '</span><strong>' . (int) $value . '</strong></article>';
    }
    echo '</section>';
    if (admin_can('messages.manage')) echo '<a class="admin-notice-link" href="/admin/index.php?page=messages"><i class="bi bi-chat-square-text"></i><span><strong>' . (int) $stats['messages'] . ' nouveau(x) message(s)</strong><small>Consulter la boîte de contact</small></span><i class="bi bi-arrow-right"></i></a>';
    if (admin_can('quotes.view')) {
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Activité récente</span><h2>Dernières demandes</h2></div><a href="/admin/index.php?page=quotes">Tout afficher <i class="bi bi-arrow-right"></i></a></div>';
    $recent = $pdo->query('SELECT q.id,q.reference,q.full_name,q.status,q.created_at,s.name AS service_name FROM quote_requests q LEFT JOIN services s ON s.id=q.service_id ORDER BY q.created_at DESC LIMIT 6')->fetchAll();
    if (!$recent) echo '<p class="empty-state">Aucune demande pour le moment.</p>';
    else {
        echo '<div class="table-responsive"><table class="table admin-table"><thead><tr><th>Référence</th><th>Client</th><th>Service</th><th>Statut</th><th>Date</th></tr></thead><tbody>';
        foreach ($recent as $q) echo '<tr><td><a class="ref-link" href="/admin/index.php?page=quote&id=' . (int)$q['id'] . '">' . e($q['reference']) . '</a></td><td>' . e($q['full_name']) . '</td><td>' . e($q['service_name'] ?: '—') . '</td><td><span class="status-pill status-' . e($q['status']) . '">' . e(ADMIN_STATUSES[$q['status']] ?? $q['status']) . '</span></td><td>' . e($q['created_at']) . '</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '</section>';
    }
}

if ($page === 'quotes') {
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    if ($status !== '' && !isset(ADMIN_STATUSES[$status])) $status = '';
    $search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $pageNo = max(1, min(100000, (int) ($_GET['p'] ?? 1)));
    $where = []; $params = [];
    if ($status !== '') { $where[] = 'q.status = :status'; $params['status'] = $status; }
    if ($search !== '') { $where[] = '(q.reference LIKE :q1 OR q.full_name LIKE :q2 OR q.email LIKE :q3)'; $like = '%' . $search . '%'; $params += ['q1'=>$like,'q2'=>$like,'q3'=>$like]; }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = db()->prepare('SELECT COUNT(*) FROM quote_requests q' . $sqlWhere); $count->execute($params); $total = (int)$count->fetchColumn();
    $perPage = 25; $pages = max(1, (int)ceil($total / $perPage)); $pageNo = min($pageNo, $pages);
    $stmt = db()->prepare('SELECT q.id,q.reference,q.full_name,q.email,q.phone,q.status,q.created_at,s.name AS service_name
        FROM quote_requests q LEFT JOIN services s ON s.id=q.service_id' . $sqlWhere . ' ORDER BY q.created_at DESC LIMIT :lim OFFSET :off');
    foreach ($params as $key=>$value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT); $stmt->bindValue(':off', ($pageNo-1)*$perPage, PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
    echo '<section class="admin-panel"><form method="get" class="filters-form"><input type="hidden" name="page" value="quotes"><label class="visually-hidden" for="quote-search">Rechercher</label><input id="quote-search" type="search" name="q" value="' . e($search) . '" placeholder="Référence, nom ou e-mail"><label class="visually-hidden" for="quote-status">Statut</label><select id="quote-status" name="status"><option value="">Tous les statuts</option>';
    foreach (ADMIN_STATUSES as $code=>$label) echo '<option value="' . e($code) . '"' . ($status===$code?' selected':'') . '>' . e($label) . '</option>';
    echo '</select><button class="btn btn-admin-primary" type="submit"><i class="bi bi-search"></i> Filtrer</button></form>';
    if (!$rows) echo '<p class="empty-state">Aucune demande ne correspond à votre recherche.</p>';
    else {
        echo '<div class="table-responsive"><table class="table admin-table"><thead><tr><th>Référence</th><th>Client</th><th>Contact</th><th>Service</th><th>Statut</th><th>Date</th><th></th></tr></thead><tbody>';
        foreach ($rows as $q) echo '<tr><td><a class="ref-link" href="/admin/index.php?page=quote&id='.(int)$q['id'].'">'.e($q['reference']).'</a></td><td>'.e($q['full_name']).'</td><td><a href="mailto:'.e($q['email']).'">'.e($q['email']).'</a><small class="d-block">'.e($q['phone']).'</small></td><td>'.e($q['service_name'] ?: '—').'</td><td><span class="status-pill status-'.e($q['status']).'">'.e(ADMIN_STATUSES[$q['status']]??$q['status']).'</span></td><td>'.e($q['created_at']).'</td><td><a class="icon-link" aria-label="Ouvrir la demande '.e($q['reference']).'" href="/admin/index.php?page=quote&id='.(int)$q['id'].'"><i class="bi bi-arrow-up-right"></i></a></td></tr>';
        echo '</tbody></table></div>';
    }
    if ($pages>1) {
        echo '<nav class="admin-pagination" aria-label="Pagination">';
        for ($n=max(1,$pageNo-2);$n<=min($pages,$pageNo+2);$n++) echo '<a class="' . ($n===$pageNo?'active':'') . '" href="/admin/index.php?page=quotes&p='.$n.'&status='.rawurlencode($status).'&q='.rawurlencode($search).'">'.$n.'</a>';
        echo '</nav>';
    }
    echo '</section>';
}

if ($page === 'quote') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = db()->prepare('SELECT q.*,s.name AS service_name,p.name AS product_name FROM quote_requests q LEFT JOIN services s ON s.id=q.service_id LEFT JOIN products p ON p.id=q.product_id WHERE q.id=:id');
    $stmt->execute(['id'=>$id ?: 0]); $quote=$stmt->fetch();
    if (!$quote) render_error(404);
    echo '<div class="detail-back"><a href="/admin/index.php?page=quotes"><i class="bi bi-arrow-left"></i> Retour aux demandes</a><span class="status-pill status-'.e($quote['status']).'">'.e(ADMIN_STATUSES[$quote['status']]??$quote['status']).'</span><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?quote_pdf='.(int)$id.'"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Télécharger la fiche PDF</a>';
    if (admin_can('quotes.manage')) echo '<form method="post" id="quote-delete-form" class="quote-delete-form">'.csrf_field().'<input type="hidden" name="action" value="quote_delete"><input type="hidden" name="quote_id" value="'.(int)$id.'"><input type="hidden" name="confirmed" value="0"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i> Supprimer la demande</button></form><dialog class="admin-confirm-dialog" id="quote-delete-dialog" aria-labelledby="quote-delete-title"><form method="dialog"><h2 id="quote-delete-title">Supprimer cette demande ?</h2><p>La demande '.e($quote['reference']).', ses notes, son historique et ses fichiers seront supprimés définitivement.</p><div class="admin-confirm-actions"><button class="btn btn-sm btn-outline-admin" value="cancel">Annuler</button><button class="btn btn-sm btn-danger" id="quote-delete-confirm" value="confirm">Supprimer définitivement</button></div></form></dialog>';
    echo '</div>';
    echo '<div class="detail-grid"><section class="admin-panel"><span class="admin-eyebrow">Demande '.e($quote['reference']).'</span><h2>'.e($quote['full_name']).'</h2><p class="detail-meta">Reçue le '.e($quote['created_at']).'</p><dl class="quote-info">';
    foreach (['organization'=>'Organisation','email'=>'E-mail','phone'=>'Téléphone','service_name'=>'Service','product_name'=>'Prestation','quantity'=>'Quantité estimée','desired_date'=>'Date souhaitée','delivery_mode'=>'Livraison / retrait'] as $key=>$label) if (!empty($quote[$key])) echo '<div><dt>'.e($label).'</dt><dd>'.($key==='email'?'<a href="mailto:'.e($quote[$key]).'">'.e($quote[$key]).'</a>':e($quote[$key])).'</dd></div>';
    echo '<div class="quote-description"><dt>Besoin exprimé</dt><dd>'.nl2br(e($quote['description'])).'</dd></div></dl>';
    $extra=db()->prepare('SELECT field_key,field_value FROM quote_request_details WHERE quote_id=:id ORDER BY field_key'); $extra->execute(['id'=>$id]); $details=$extra->fetchAll();
    if ($details) { echo '<h3>Précisions</h3><dl class="quote-info">'; foreach($details as $d) echo '<div><dt>'.e($d['field_key']).'</dt><dd>'.e($d['field_value']).'</dd></div>'; echo '</dl>'; }
    $files=db()->prepare('SELECT id,original_name,mime_type,size_bytes FROM quote_attachments WHERE quote_id=:id'); $files->execute(['id'=>$id]); $attachments=$files->fetchAll();
    if ($attachments) { echo '<h3>Fichiers transmis</h3><ul class="attachment-list">'; foreach($attachments as $f) echo '<li><a href="/admin/index.php?download='.(int)$f['id'].'"><i class="bi bi-paperclip"></i> '.e($f['original_name']).'</a><small>'.number_format((int)$f['size_bytes']/1024,0,',',' ').' Ko</small></li>'; echo '</ul>'; }
    echo '</section><aside class="admin-panel"><span class="admin-eyebrow">Traitement interne</span><h2>Suivi</h2>';
    if (admin_can('quotes.manage')) {
        echo '<form method="post" class="admin-form">'.csrf_field().'<input type="hidden" name="action" value="quote_status"><input type="hidden" name="quote_id" value="'.(int)$id.'"><label for="status">Statut</label><select id="status" name="status">';
        foreach(ADMIN_STATUSES as $code=>$label) echo '<option value="'.e($code).'"'.($quote['status']===$code?' selected':'').'>'.e($label).'</option>';
        echo '</select><button class="btn btn-admin-primary" type="submit">Enregistrer le statut</button></form><hr><form method="post" class="admin-form">'.csrf_field().'<input type="hidden" name="action" value="quote_note"><input type="hidden" name="quote_id" value="'.(int)$id.'"><label for="note">Note interne</label><textarea id="note" name="note" rows="4" maxlength="4000" required></textarea><small>Visible uniquement par l’équipe.</small><button class="btn btn-outline-admin" type="submit">Ajouter la note</button></form>';
    }
    $notes=db()->prepare('SELECT n.note,n.created_at,u.full_name FROM quote_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.quote_id=:id ORDER BY n.created_at DESC'); $notes->execute(['id'=>$id]); $allNotes=$notes->fetchAll();
    echo '<div class="internal-notes"><h3>Notes</h3>';
    if (!$allNotes) echo '<p class="text-muted">Aucune note interne.</p>';
    foreach($allNotes as $n) echo '<article><p>'.nl2br(e($n['note'])).'</p><small>'.e($n['full_name'] ?: 'Équipe').' · '.e($n['created_at']).'</small></article>';
    echo '</div></aside></div>';
}

if ($page === 'messages') {
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    if (!in_array($status,['','new','read','archived'],true)) $status='';
    $stmt=db()->prepare('SELECT * FROM contact_messages' . ($status!==''?' WHERE status=:status':'') . ' ORDER BY created_at DESC LIMIT 100');
    if ($status!=='') $stmt->execute(['status'=>$status]); else $stmt->execute();
    $rows=$stmt->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Boîte de contact</span><h2>Messages reçus</h2></div><form method="get"><input type="hidden" name="page" value="messages"><select name="status" aria-label="Filtrer par statut"><option value="">Tous</option><option value="new"'.($status==='new'?' selected':'').'>Nouveaux</option><option value="read"'.($status==='read'?' selected':'').'>Lus</option><option value="archived"'.($status==='archived'?' selected':'').'>Archivés</option></select><button type="submit" class="btn btn-sm btn-outline-admin">Filtrer</button></form></div>';
    if(!$rows) echo '<p class="empty-state">Aucun message dans cette vue.</p>';
    foreach($rows as $m) {
        echo '<article class="message-card"><div class="message-card-head"><div><span class="status-pill status-'.e($m['status']).'">'.e(['new'=>'Nouveau','read'=>'Lu','archived'=>'Archivé'][$m['status']]??$m['status']).'</span><h3>'.e($m['subject']).'</h3><p>'.e($m['full_name']).' · <a href="mailto:'.e($m['email']).'">'.e($m['email']).'</a>'.($m['phone']?' · '.e($m['phone']):'').'</p></div><small>'.e($m['created_at']).'</small></div><p class="message-content">'.nl2br(e($m['message'])).'</p><form method="post" class="message-status-form">'.csrf_field().'<input type="hidden" name="action" value="message_status"><input type="hidden" name="message_id" value="'.(int)$m['id'].'"><label class="visually-hidden" for="m-status-'.(int)$m['id'].'">Statut du message</label><select id="m-status-'.(int)$m['id'].'" name="status"><option value="new"'.($m['status']==='new'?' selected':'').'>Nouveau</option><option value="read"'.($m['status']==='read'?' selected':'').'>Lu</option><option value="archived"'.($m['status']==='archived'?' selected':'').'>Archivé</option></select><button class="btn btn-sm btn-outline-admin" type="submit">Enregistrer</button></form></article>';
    }
    echo '</section>';
}

if ($page === 'media') {
    $libraryPage=max(1,min(1000,(int)($_GET['library_p']??1)));$libraryPerPage=24;
    $libraryCount=(int)db()->query("SELECT COUNT(*) FROM media WHERE mime_type LIKE 'image/%'")->fetchColumn();
    $libraryPages=max(1,(int)ceil($libraryCount/$libraryPerPage));$libraryPage=min($libraryPage,$libraryPages);
    $libraryStmt=db()->prepare("SELECT id,stored_name,original_name,mime_type,size_bytes,width,height,alt_text FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC,id DESC LIMIT :limit OFFSET :offset");
    $libraryStmt->bindValue(':limit',$libraryPerPage,PDO::PARAM_INT);$libraryStmt->bindValue(':offset',($libraryPage-1)*$libraryPerPage,PDO::PARAM_INT);$libraryStmt->execute();$libraryRows=$libraryStmt->fetchAll();
    echo '<section id="media-library-section" class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Images disponibles pour le site</span><h2>Médiathèque</h2></div><span class="media-count">'.$libraryCount.' images</span></div>';
    echo '<p class="media-note"><i class="bi bi-images" aria-hidden="true"></i> Ces images peuvent être sélectionnées dans les formulaires des réalisations, partenaires et réglages du site.</p>';
    echo '<form method="post" enctype="multipart/form-data" class="admin-form admin-modal-form media-upload-form">'.csrf_field().'<input type="hidden" name="action" value="media_upload"><label for="media-file">Image JPG, PNG ou WebP</label><input id="media-file" type="file" name="media_file" accept="image/jpeg,image/png,image/webp" required><label for="media-alt-upload">Texte alternatif</label><input id="media-alt-upload" name="alt_text" maxlength="255" required placeholder="Décrivez brièvement l’image"><small>Le texte alternatif est visible dans le code du site et facilite l’accessibilité.</small><button class="btn btn-admin-primary" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Ajouter l’image</button></form>';
    if(!$libraryRows)echo '<p class="empty-state">Aucune image dans la médiathèque pour le moment.</p>';
    else{echo '<div class="media-grid">';foreach($libraryRows as $row){$src='/uploads/'.rawurlencode((string)$row['stored_name']);$altId='library-alt-'.(int)$row['id'];$size=number_format(((int)$row['size_bytes'])/1024,0,',',' ');echo '<article class="media-card"><img src="'.e($src).'" alt="'.e($row['alt_text']?:$row['original_name']).'" loading="lazy"><div class="media-card-caption"><strong>'.e($row['original_name']).'</strong><span>'.(int)($row['width']??0).' × '.(int)($row['height']??0).' px · '.e(strtoupper(str_replace('image/','',(string)$row['mime_type']))).' · '.$size.' Ko</span><form method="post" class="media-alt-edit">'.csrf_field().'<input type="hidden" name="action" value="media_alt_save"><input type="hidden" name="media_id" value="'.(int)$row['id'].'"><input type="hidden" name="library_p" value="'.$libraryPage.'"><label for="'.e($altId).'">Texte alternatif</label><input id="'.e($altId).'" name="alt_text" maxlength="255" value="'.e($row['alt_text']).'" required><button class="btn btn-sm btn-outline-admin" type="submit">Enregistrer</button></form></div></article>'; } echo '</div>';if($libraryPages>1){echo '<nav class="admin-pagination" aria-label="Pagination des images">';for($n=max(1,$libraryPage-2);$n<=min($libraryPages,$libraryPage+2);$n++)echo '<a class="'.($n===$libraryPage?'active':'').'" href="/admin/index.php?page=media&library_p='.$n.'#media-library-section">'.$n.'</a>';echo '</nav>';}}
    echo '</section>';
    $manifestPath = config('paths.private') . '/media-imports/manifest.json';
    $data = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
    $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];
    $mediaPage = max(1, min(1000, (int) ($_GET['p'] ?? 1)));
    $perPage = 24;
    $totalPages = max(1, (int) ceil(count($assets) / $perPage));
    $mediaPage = min($mediaPage, $totalPages);
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Contenus visuels</span><h2>Médiathèque importée</h2></div><span class="media-count">' . count($assets) . ' fichiers</span></div>';
    echo '<p class="media-note"><i class="bi bi-shield-lock"></i> Les aperçus restent privés jusqu’à leur validation. Ajoutez un texte alternatif puis choisissez « Ajouter à la médiathèque » : l’image sera alors proposée dans le formulaire Réalisations, pour la couverture et la galerie.</p><div class="media-grid">';
    $visibleAssets=array_slice($assets, ($mediaPage - 1) * $perPage, $perPage);
    foreach ($visibleAssets as $item) {
        $relative=(string)($item['path']??'');if(preg_match('#^storage/private/media-imports/(?:catalogue-pages|realisations-photos)/([a-z0-9-]+-p[0-9]{2}-[a-f0-9]{8}\.webp)$#',$relative,$match))$importName=admin_import_stored_name(substr($relative,strlen('storage/private/media-imports/')));else $importName='';
        $importStmt=$importName!==''?db()->prepare('SELECT id FROM media WHERE stored_name=:name'):null;if($importStmt)$importStmt->execute(['name'=>$importName]);$alreadyImported=$importStmt?(int)$importStmt->fetchColumn():0;
        $name = basename((string) ($item['path'] ?? ''));
        $src = '/admin/index.php?preview=' . rawurlencode($name);
        echo '<article class="media-card"><img src="' . e($src) . '" alt="Aperçu interne à valider" loading="lazy"><div class="media-card-caption"><strong>' . e($item['category'] ?? 'Image') . '</strong><span>PDF : ' . e($item['source_pdf'] ?? '') . ' · page ' . (int) ($item['source_page'] ?? 0) . '</span><small>' . e(number_format(((int) ($item['bytes'] ?? 0)) / 1024, 0, ',', ' ')) . ' Ko</small>';
        if($alreadyImported){echo '<span class="status-pill">Ajoutée à la médiathèque</span><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=projects-admin">Associer à une réalisation</a>';}
        elseif($importName!==''){echo '<form method="post" class="media-import-form">'.csrf_field().'<input type="hidden" name="action" value="media_import"><input type="hidden" name="source_name" value="'.e($match[1]).'"><label for="media-alt-'.e(substr(hash('sha256',$relative),0,10)).'">Texte alternatif</label><input id="media-alt-'.e(substr(hash('sha256',$relative),0,10)).'" name="alt_text" maxlength="255" required placeholder="Décrivez ce que montre l’image"><button class="btn btn-sm btn-admin-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Ajouter à la médiathèque</button></form>';}
        echo '</div></article>';
    }
    echo '</div>';
    if ($totalPages > 1) {
        echo '<nav class="admin-pagination" aria-label="Pagination des médias">';
        for ($n = max(1, $mediaPage - 2); $n <= min($totalPages, $mediaPage + 2); $n++) echo '<a class="' . ($n === $mediaPage ? 'active' : '') . '" href="/admin/index.php?page=media&p=' . $n . '">' . $n . '</a>';
        echo '</nav>';
    }
    echo '</section>';
}

if ($page === 'partners-admin') {
    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    $editing = null;
    if ($editId) {
        $st = db()->prepare('SELECT * FROM partners WHERE id=:id');
        $st->execute(['id' => $editId]);
        $editing = $st->fetch() ?: null;
    }
    $images = db()->query("SELECT id, stored_name, original_name, alt_text FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC")->fetchAll();
    $partners = db()->query('SELECT p.*, m.stored_name, m.original_name FROM partners p JOIN media m ON m.id=p.media_id ORDER BY p.sort_order,p.name')->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Identité & collaborations</span><h2>'.($editing ? 'Modifier un partenaire' : 'Ajouter un partenaire').'</h2></div><span class="media-count">'.count($partners).' partenaire(s)</span></div>';
    echo '<form method="post" enctype="multipart/form-data" class="admin-form partner-admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="partner_save"><input type="hidden" name="partner_id" value="'.(int)($editing['id'] ?? 0).'"><div class="partner-form-grid"><div><label for="partner-name">Nom du partenaire <span aria-hidden="true">*</span></label><input id="partner-name" name="name" maxlength="150" required value="'.e($editing['name'] ?? '').'" placeholder="Ex. Organisation partenaire"></div><div><label for="partner-url">Lien du site web</label><input id="partner-url" name="website_url" type="url" inputmode="url" maxlength="255" value="'.e($editing['website_url'] ?? '').'" placeholder="https://exemple.org"><small>Facultatif. Seuls les liens HTTPS sont acceptés.</small></div><div class="partner-image-field"><label for="partner-logo">Logo depuis votre ordinateur</label><input id="partner-logo" name="logo_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small>Formats JPG, PNG ou WebP. La taille maximale dépend de la limite de téléversement PHP configurée.</small></div><div class="partner-image-field"><label for="partner-media">Ou choisir un logo déjà importé</label><select id="partner-media" name="media_id"><option value="">Choisir dans la Médiathèque…</option>';
    foreach ($images as $image) echo '<option value="'.(int)$image['id'].'"'.((int)($editing['media_id'] ?? 0)===(int)$image['id']?' selected':'').'>'.e($image['alt_text'] ?: $image['original_name']).'</option>';
    echo '</select><small>Si un nouveau fichier est sélectionné, il sera utilisé à la place de ce choix.</small></div><div><label for="partner-order">Ordre d’affichage</label><input id="partner-order" type="number" min="0" max="32767" name="sort_order" value="'.(int)($editing['sort_order'] ?? 0).'"></div><label class="admin-check-label"><input type="checkbox" name="is_active" value="1"'.(!$editing || (int)$editing['is_active'] === 1 ? ' checked' : '').'> Afficher sur la page d’accueil</label></div><button type="submit" class="btn btn-admin-primary">'.($editing ? 'Enregistrer les modifications' : 'Ajouter le partenaire').'</button>'.($editing ? ' <a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=partners-admin">Annuler</a>' : '').'</form>';
    echo '</section><section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Page d’accueil</span><h2>Partenaires enregistrés</h2></div></div>';
    if (!$partners) echo '<div class="empty-state"><i class="bi bi-people"></i><p>Aucun partenaire pour le moment. Ajoutez un logo depuis le formulaire ci-dessus.</p></div>';
    else {
        echo '<div class="table-responsive"><table class="table admin-table"><thead><tr><th>Logo</th><th>Partenaire</th><th>Lien</th><th>Ordre</th><th>Visibilité</th><th>Actions</th></tr></thead><tbody>';
        foreach ($partners as $partner) {
            $logo = '/uploads/' . rawurlencode((string) $partner['stored_name']);
            echo '<tr><td><img class="partner-admin-thumb" src="'.e($logo).'" alt="" loading="lazy"></td><td><strong>'.e($partner['name']).'</strong><small class="d-block">'.e($partner['original_name']).'</small></td><td>'.(!empty($partner['website_url']) ? '<a href="'.e($partner['website_url']).'" target="_blank" rel="noopener noreferrer">Visiter <i class="bi bi-box-arrow-up-right"></i></a>' : '<span class="text-muted">Aucun lien</span>').'</td><td>'.(int)$partner['sort_order'].'</td><td><span class="status-pill">'.((int)$partner['is_active'] ? 'Visible' : 'Masqué').'</span></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=partners-admin&edit='.(int)$partner['id'].'">Modifier</a><form method="post" class="inline-admin-action">'.csrf_field().'<input type="hidden" name="action" value="partner_toggle"><input type="hidden" name="partner_id" value="'.(int)$partner['id'].'"><button class="btn btn-sm btn-outline-admin" type="submit">'.((int)$partner['is_active'] ? 'Masquer' : 'Afficher').'</button></form></div></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

if ($page === 'site-pages') {
    $allowedSlugs = ['a-propos','politique-de-confidentialite','mentions-legales'];
    $marks = implode(',', array_fill(0, count($allowedSlugs), '?'));
    $list=db()->prepare('SELECT id,slug,title,status,updated_at FROM pages WHERE slug IN (' . $marks . ') ORDER BY FIELD(slug,\'a-propos\',\'politique-de-confidentialite\',\'mentions-legales\')');
    $list->execute($allowedSlugs); $rows=$list->fetchAll();
    $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT); $editing=null;
    if ($editId) { $stmt=db()->prepare('SELECT * FROM pages WHERE id=? AND slug IN (' . $marks . ')'); $stmt->execute(array_merge([$editId],$allowedSlugs)); $editing=$stmt->fetch()?:null; }
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Contenu éditorial</span><h2>Pages statiques</h2></div><span class="media-count">'.count($rows).' pages</span></div><p class="media-note"><i class="bi bi-info-circle"></i> Les adresses URL restent fixes. Le contenu HTML est filtré avant publication. Les textes juridiques doivent être fournis et validés par l’entreprise.</p><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Page</th><th>Adresse</th><th>État</th><th>Dernière modification</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $preview='/'.$row['slug'];
        echo '<tr><td>'.e($row['title']).'</td><td><a href="'.e($preview).'">'.e($preview).'</a></td><td><span class="status-pill status-'.e($row['status']).'">'.($row['status']==='published'?'Publiée':'Brouillon').'</span></td><td>'.e($row['updated_at']).'</td><td><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=site-pages&edit='.(int)$row['id'].'">Modifier</a></td></tr>';
    }
    echo '</tbody></table></div></section>';
    if ($editing) {
        echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">'.e('/'.$editing['slug']).'</span><h2>Modifier « '.e($editing['title']).' »</h2></div><a href="/'.e($editing['slug']).'" target="_blank" rel="noopener">Prévisualiser <i class="bi bi-box-arrow-up-right"></i></a></div>';
        echo '<form method="post" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="page_save"><input type="hidden" name="page_id" value="'.(int)$editing['id'].'"><label for="page-title">Titre de la page</label><input id="page-title" name="title" maxlength="190" required value="'.e($editing['title']).'"><label for="page-content">Contenu (HTML simple)</label><textarea id="page-content" name="content" rows="14" maxlength="500000">'.e($editing['content']??'').'</textarea><small>Paragraphes, titres, listes, liens et emphase sont acceptés. Les balises non autorisées sont supprimées.</small><hr><h3>Référencement</h3><label for="page-seo-title">Titre SEO</label><input id="page-seo-title" name="seo_title" maxlength="190" value="'.e($editing['seo_title']??'').'" aria-describedby="seo-title-help"><small id="seo-title-help">Laissez vide pour reprendre le titre de la page.</small><label for="page-seo-description">Description SEO</label><textarea id="page-seo-description" name="seo_description" rows="3" maxlength="320">'.e($editing['seo_description']??'').'</textarea><label for="page-status">Publication</label><select id="page-status" name="status"><option value="draft"'.($editing['status']==='draft'?' selected':'').'>Brouillon</option><option value="published"'.($editing['status']==='published'?' selected':'').'>Publiée</option></select><button type="submit" class="btn btn-admin-primary">Enregistrer la page</button></form></section>';
    }
}

if ($page === 'faqs') {
    $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT); $editing=null;
    if ($editId) { $st=db()->prepare('SELECT * FROM faqs WHERE id=:id'); $st->execute(['id'=>$editId]); $editing=$st->fetch()?:null; }
    $rows=db()->query('SELECT id,question,sort_order,is_active,updated_at FROM faqs ORDER BY sort_order,id')->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Contenu du site</span><h2>'.($editing?'Modifier une question':'Ajouter une question').'</h2></div></div><form method="post" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="faq_save"><input type="hidden" name="faq_id" value="'.(int)($editing['id']??0).'"><label for="faq-question">Question</label><input id="faq-question" name="question" maxlength="255" required value="'.e($editing['question']??'').'"><label for="faq-answer">Réponse</label><textarea id="faq-answer" name="answer" rows="6" maxlength="10000" required>'.e($editing['answer']??'').'</textarea><label for="faq-order">Ordre d’affichage</label><input id="faq-order" type="number" min="0" max="32767" name="sort_order" value="'.(int)($editing['sort_order']??0).'"><label><input type="checkbox" name="is_active" value="1"'.(!$editing||(int)$editing['is_active']===1?' checked':'').'> Visible sur le site</label><button type="submit" class="btn btn-admin-primary">Enregistrer</button></form></section>';
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Questions et réponses</span><h2>'.count($rows).' élément(s)</h2></div></div>';
    foreach($rows as $row) echo '<article class="message-card"><div class="message-card-head"><strong>'.e($row['question']).'</strong><span class="status-pill">'.((int)$row['is_active']?'Visible':'Masquée').' · ordre '.(int)$row['sort_order'].'</span></div><div class="d-flex gap-2 mt-2"><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=faqs&edit='.(int)$row['id'].'">Modifier</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="faq_toggle"><input type="hidden" name="faq_id" value="'.(int)$row['id'].'"><button class="btn btn-sm btn-outline-admin" type="submit">'.((int)$row['is_active']?'Masquer':'Afficher').'</button></form></div></article>';
    if (!$rows) echo '<p class="empty-state">Aucune FAQ pour le moment.</p>';
    echo '</section>';
}

if ($page === 'services-admin') {
    $rows=db()->query('SELECT id,slug,name,status,sort_order FROM services ORDER BY sort_order,name')->fetchAll();
    $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT); $editing=null; $offerings=[];
    if ($editId) { $st=db()->prepare('SELECT * FROM services WHERE id=:id'); $st->execute(['id'=>$editId]); $editing=$st->fetch()?:null; if($editing){$st=db()->prepare('SELECT label FROM service_offerings WHERE service_id=:id ORDER BY sort_order,id');$st->execute(['id'=>$editId]);$offerings=array_column($st->fetchAll(),'label');} }
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Contenu du site</span><h2>Services</h2></div><a class="btn btn-admin-primary" href="/admin/index.php?page=services-admin">Nouveau service</a></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Service</th><th>URL</th><th>Publication</th><th></th></tr></thead><tbody>';
    foreach($rows as $row) echo '<tr><td>'.e($row['name']).'</td><td>/services/'.e($row['slug']).'</td><td><span class="status-pill">'.($row['status']==='published'?'Publié':'Brouillon').'</span></td><td><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=services-admin&edit='.(int)$row['id'].'">Modifier</a></td></tr>';
    if(!$rows) echo '<tr><td colspan="4">Aucun service enregistré.</td></tr>';
    echo '</tbody></table></div></section>';
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">'.($editing?'Modifier':'Créer').'</span><h2>'.e($editing['name']??'Nouveau service').'</h2></div></div><form method="post" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="service_save"><input type="hidden" name="service_id" value="'.(int)($editing['id']??0).'"><label for="svc-name">Nom</label><input id="svc-name" name="name" maxlength="150" required value="'.e($editing['name']??'').'"><label for="svc-slug">URL courte (minuscules, chiffres et tirets)</label><input id="svc-slug" name="slug" maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required value="'.e($editing['slug']??'').'"><label for="svc-short">Résumé</label><textarea id="svc-short" name="short_description" rows="3" maxlength="500" required>'.e($editing['short_description']??'').'</textarea><label for="svc-description">Description (HTML simple)</label><textarea id="svc-description" name="description" rows="8" maxlength="500000">'.e($editing['description']??'').'</textarea><label for="svc-approach">Notre approche</label><textarea id="svc-approach" name="approach_text" rows="4" maxlength="100000">'.e($editing['approach_text']??'').'</textarea><label for="svc-offerings">Prestations, une par ligne</label><textarea id="svc-offerings" name="offerings" rows="6" maxlength="10000">'.e(implode("\n",$offerings)).'</textarea><label for="svc-cta">Texte d’appel à l’action</label><input id="svc-cta" name="cta_text" maxlength="255" value="'.e($editing['cta_text']??'').'"><label for="svc-icon">Icône Bootstrap (ex. bi-printer)</label><input id="svc-icon" name="icon" maxlength="43" value="'.e($editing['icon']??'').'">';
    echo '<label for="svc-profile">Profil du formulaire de devis</label><select id="svc-profile" name="quote_profile">'; foreach(['generic'=>'Général','print'=>'Impression','textile'=>'Couture / textile','embroidery'=>'Broderie'] as $v=>$label) echo '<option value="'.e($v).'"'.(($editing['quote_profile']??'generic')===$v?' selected':'').'>'.e($label).'</option>';
    echo '</select><label for="svc-sort">Ordre</label><input id="svc-sort" name="sort_order" type="number" min="0" max="32767" value="'.(int)($editing['sort_order']??0).'"><label for="svc-status">Publication</label><select id="svc-status" name="status"><option value="draft"'.(($editing['status']??'draft')==='draft'?' selected':'').'>Brouillon</option><option value="published"'.(($editing['status']??'')==='published'?' selected':'').'>Publié</option></select><label for="svc-seo-title">Titre SEO</label><input id="svc-seo-title" name="seo_title" maxlength="190" value="'.e($editing['seo_title']??'').'"> <label for="svc-seo-desc">Description SEO</label><textarea id="svc-seo-desc" name="seo_description" rows="2" maxlength="320">'.e($editing['seo_description']??'').'</textarea><button class="btn btn-admin-primary" type="submit">Enregistrer le service</button></form></section>';
}

if ($page === 'projects-admin') {
    $rows=db()->query('SELECT p.id,p.slug,p.title,p.status,p.is_demo,s.name AS service_name FROM projects p JOIN services s ON s.id=p.service_id ORDER BY p.updated_at DESC LIMIT 200')->fetchAll();
    $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT); $editing=null;
    if($editId){$st=db()->prepare('SELECT * FROM projects WHERE id=:id');$st->execute(['id'=>$editId]);$editing=$st->fetch()?:null;}
    $services=db()->query('SELECT id,name FROM services ORDER BY sort_order,name')->fetchAll();
    $categories=db()->query("SELECT id,name FROM categories WHERE scope='project' ORDER BY sort_order,name")->fetchAll();
    $media=db()->query("SELECT id,original_name,alt_text FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC")->fetchAll();
    $gallerySelected=[];if($editing){$galleryQuery=db()->prepare('SELECT media_id FROM project_images WHERE project_id=:id ORDER BY sort_order,id');$galleryQuery->execute(['id'=>$editing['id']]);$gallerySelected=array_map('intval',$galleryQuery->fetchAll(PDO::FETCH_COLUMN));}
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Portfolio</span><h2>Réalisations</h2></div><a class="btn btn-admin-primary" href="/admin/index.php?page=projects-admin">Nouvelle réalisation</a></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Titre</th><th>Service</th><th>État</th><th></th></tr></thead><tbody>';
    foreach($rows as $row) echo '<tr><td>'.e($row['title']).($row['is_demo']?' <span class="status-pill">Démo</span>':'').'</td><td>'.e($row['service_name']).'</td><td><span class="status-pill">'.($row['status']==='published'?'Publiée':'Brouillon').'</span></td><td><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=projects-admin&edit='.(int)$row['id'].'">Modifier</a></td></tr>';
    if(!$rows) echo '<tr><td colspan="4">Aucune réalisation enregistrée.</td></tr>';
    echo '</tbody></table></div><p class="media-note"><i class="bi bi-info-circle"></i> Les contenus de démonstration restent marqués. N’affichez le nom d’un client qu’avec son accord. Les photos importées des PDF sont privées tant qu’elles ne sont pas validées et ajoutées à la médiathèque.</p></section>';
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">'.($editing?'Modifier':'Créer').'</span><h2>'.e($editing['title']??'Nouvelle réalisation').'</h2></div></div><form method="post" enctype="multipart/form-data" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="project_save"><input type="hidden" name="project_id" value="'.(int)($editing['id']??0).'"><label for="proj-title">Titre</label><input id="proj-title" name="title" maxlength="190" required value="'.e($editing['title']??'').'"><label for="proj-slug">URL courte</label><input id="proj-slug" name="slug" maxlength="140" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required value="'.e($editing['slug']??'').'"><label for="proj-service">Service</label><select id="proj-service" name="service_id" required><option value="">Choisir</option>'; foreach($services as $s) echo '<option value="'.(int)$s['id'].'"'.((int)($editing['service_id']??0)===(int)$s['id']?' selected':'').'>'.e($s['name']).'</option>';
    echo '</select><label for="proj-category">Catégorie</label><select id="proj-category" name="category_id"><option value="">Aucune</option>'; foreach($categories as $c) echo '<option value="'.(int)$c['id'].'"'.((int)($editing['category_id']??0)===(int)$c['id']?' selected':'').'>'.e($c['name']).'</option>';
    echo '</select><label for="proj-summary">Résumé</label><textarea id="proj-summary" name="summary" rows="3" maxlength="500">'.e($editing['summary']??'').'</textarea><label for="proj-description">Description (HTML simple)</label><textarea id="proj-description" name="description" rows="7" maxlength="500000">'.e($editing['description']??'').'</textarea><label for="proj-client">Client (avec accord)</label><input id="proj-client" name="client_name" maxlength="150" value="'.e($editing['client_name']??'').'"><label for="proj-date">Date de réalisation</label><input id="proj-date" type="date" name="completed_on" value="'.e($editing['completed_on']??'').'">';
    echo '<label for="proj-cover">Image de couverture</label><select id="proj-cover" name="cover_media_id"><option value="">Aucune</option>'; foreach($media as $m) echo '<option value="'.(int)$m['id'].'"'.((int)($editing['cover_media_id']??0)===(int)$m['id']?' selected':'').'>'.e($m['original_name']).($m['alt_text']?' · '.e($m['alt_text']):'').'</option>';
    echo '</select><div class="project-gallery-upload"><label for="proj-gallery-files">Ajouter des photos depuis votre ordinateur</label><input id="proj-gallery-files" name="gallery_files[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple aria-describedby="proj-gallery-files-help"><small id="proj-gallery-files-help">JPG, PNG ou WebP. Sélectionnez plusieurs fichiers à la fois si nécessaire.</small></div><label for="proj-gallery">Ou choisir des photos déjà importées <span class="admin-muted-inline">(20 maximum au total)</span></label><select id="proj-gallery" name="gallery_media_ids[]" multiple size="8" aria-describedby="proj-gallery-help">';foreach($media as $m)echo '<option value="'.(int)$m['id'].'"'.(in_array((int)$m['id'],$gallerySelected,true)?' selected':'').'>'.e($m['original_name']).($m['alt_text']?' · '.e($m['alt_text']):'').'</option>';echo '</select><small id="proj-gallery-help">Pour sélectionner plusieurs images importées, maintenez Ctrl (Windows) ou ⌘ (Mac). Les photos téléversées et sélectionnées sont réunies ; au prochain enregistrement elles remplacent la galerie actuelle. Maximum : 20 photos au total.</small><label for="proj-seo-title">Titre SEO</label><input id="proj-seo-title" name="seo_title" maxlength="190" value="'.e($editing['seo_title']??'').'"> <label for="proj-seo-desc">Description SEO</label><textarea id="proj-seo-desc" name="seo_description" rows="2" maxlength="320">'.e($editing['seo_description']??'').'</textarea><label for="proj-status">Publication</label><select id="proj-status" name="status"><option value="draft"'.(($editing['status']??'draft')==='draft'?' selected':'').'>Brouillon</option><option value="published"'.(($editing['status']??'')==='published'?' selected':'').'>Publiée</option></select><label class="admin-check-label"><input type="checkbox" name="is_demo" value="1"'.(!$editing||(int)$editing['is_demo']===1?' checked':'').'> Contenu de démonstration</label><button class="btn btn-admin-primary" type="submit">Enregistrer la réalisation</button></form></section>';
}

if ($page === 'products-admin') {
    $rows=db()->query('SELECT p.id,p.slug,p.name,p.status,p.price_amount,s.name AS service_name FROM products p JOIN services s ON s.id=p.service_id ORDER BY p.sort_order,p.name LIMIT 250')->fetchAll();
    $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT);$editing=null;if($editId){$st=db()->prepare('SELECT * FROM products WHERE id=:id');$st->execute(['id'=>$editId]);$editing=$st->fetch()?:null;}
    $services=db()->query('SELECT id,name FROM services ORDER BY sort_order,name')->fetchAll();$categories=db()->query("SELECT id,name FROM categories WHERE scope='product' ORDER BY sort_order,name")->fetchAll();$media=db()->query('SELECT id,original_name,alt_text FROM media ORDER BY created_at DESC')->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Offre commerciale</span><h2>Catalogue de produits</h2></div><a class="btn btn-admin-primary" href="/admin/index.php?page=products-admin">Nouveau produit</a></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Produit</th><th>Service</th><th>Prix</th><th>État</th><th></th></tr></thead><tbody>';
    foreach($rows as $row)echo '<tr><td>'.e($row['name']).'</td><td>'.e($row['service_name']).'</td><td>'.e(format_price($row['price_amount']!==null?(string)$row['price_amount']:null)).'</td><td><span class="status-pill">'.($row['status']==='published'?'Publié':'Brouillon').'</span></td><td><a class="btn btn-sm btn-outline-admin" href="/admin/index.php?page=products-admin&edit='.(int)$row['id'].'">Modifier</a></td></tr>';
    if(!$rows)echo '<tr><td colspan="5">Aucun produit. Les montants ne seront visibles que si la devise du site est configurée.</td></tr>';
    echo '</tbody></table></div></section><section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">'.($editing?'Modifier':'Créer').'</span><h2>'.e($editing['name']??'Nouveau produit').'</h2></div></div><form method="post" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="product_save"><input type="hidden" name="product_id" value="'.(int)($editing['id']??0).'"><label for="product-name">Nom</label><input id="product-name" name="name" maxlength="190" required value="'.e($editing['name']??'').'"><label for="product-slug">URL courte</label><input id="product-slug" name="slug" maxlength="140" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required value="'.e($editing['slug']??'').'"><label for="product-service">Service</label><select id="product-service" name="service_id" required><option value="">Choisir</option>';foreach($services as $s)echo '<option value="'.(int)$s['id'].'"'.((int)($editing['service_id']??0)===(int)$s['id']?' selected':'').'>'.e($s['name']).'</option>';
    echo '</select><label for="product-category">Catégorie</label><select id="product-category" name="category_id"><option value="">Aucune</option>';foreach($categories as $c)echo '<option value="'.(int)$c['id'].'"'.((int)($editing['category_id']??0)===(int)$c['id']?' selected':'').'>'.e($c['name']).'</option>';
    echo '</select><label for="product-description">Description</label><textarea id="product-description" name="description" rows="6" maxlength="10000">'.e($editing['description']??'').'</textarea><label for="product-price">Prix (laisser vide pour « sur devis »)</label><input id="product-price" name="price_amount" inputmode="decimal" pattern="\d{1,10}(\.\d{1,2})?" value="'.e($editing['price_amount']??'').'"><label for="product-price-note">Précision sur le prix</label><input id="product-price-note" name="price_note" maxlength="120" value="'.e($editing['price_note']??'').'"><label for="product-media">Image</label><select id="product-media" name="media_id"><option value="">Aucune</option>';foreach($media as $m)echo '<option value="'.(int)$m['id'].'"'.((int)($editing['media_id']??0)===(int)$m['id']?' selected':'').'>'.e($m['original_name']).($m['alt_text']?' · '.e($m['alt_text']):'').'</option>';
    echo '</select><label for="product-sort">Ordre</label><input id="product-sort" name="sort_order" type="number" min="0" max="32767" value="'.(int)($editing['sort_order']??0).'"><label for="product-status">Publication</label><select id="product-status" name="status"><option value="draft"'.(($editing['status']??'draft')==='draft'?' selected':'').'>Brouillon</option><option value="published"'.(($editing['status']??'')==='published'?' selected':'').'>Publié</option></select><label for="product-seo-title">Titre SEO</label><input id="product-seo-title" name="seo_title" maxlength="190" value="'.e($editing['seo_title']??'').'"> <label for="product-seo-desc">Description SEO</label><textarea id="product-seo-desc" name="seo_description" rows="2" maxlength="320">'.e($editing['seo_description']??'').'</textarea><button class="btn btn-admin-primary" type="submit">Enregistrer le produit</button></form></section>';
}

if ($page === 'settings-admin') {
    $currentLogo = repo_media_url((int) setting('logo_media_id', '0') ?: null);
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Identité visuelle</span><h2>Logo affiché sur le site</h2></div></div><div class="admin-logo-guide"><div class="admin-logo-preview"><span>Aperçu actuel</span><img src="'.e($currentLogo['url'] ?? asset('img/brand/logo-genie-solutions.jpg')).'" alt="Logo actuellement affiché sur le site"></div><ol><li>Ouvrez la <a href="/admin/index.php?page=media">Médiathèque</a> et importez le nouveau logo comme image.</li><li>Revenez dans Paramètres, choisissez l’image dans « Logo du site », puis enregistrez.</li><li>Rechargez le site pour voir le changement.</li></ol><p>Un fichier PNG transparent ou WebP horizontal s’intègre généralement bien dans l’en-tête.</p></div></section>';
    $labels=['company_name'=>'Nom de l’entreprise','tagline'=>'Signature','footer_text'=>'Texte du pied de page','announcement_message'=>'Annonce du site','phone'=>'Téléphone','whatsapp'=>'WhatsApp','email'=>'Adresse e-mail publique','address'=>'Adresse','opening_hours'=>'Horaires','facebook_url'=>'Facebook','instagram_url'=>'Instagram','linkedin_url'=>'LinkedIn','currency_code'=>'Devise (code ISO à 3 lettres)','seo_default_title'=>'Titre SEO par défaut','seo_default_description'=>'Description SEO par défaut','logo_media_id'=>'Logo du site','favicon_media_id'=>'Icône du site'];
    $groups=['identity'=>'Identité','contact'=>'Contact','social'=>'Réseaux sociaux','commerce'=>'Commerce','seo'=>'Référencement'];
    $allowed=['company_name','tagline','footer_text','logo_media_id','favicon_media_id','announcement_message','phone','whatsapp','email','address','opening_hours','facebook_url','instagram_url','linkedin_url','currency_code'];
    if(admin_can('seo.manage')){$allowed[]='seo_default_title';$allowed[]='seo_default_description';}
    $marks=implode(',',array_fill(0,count($allowed),'?'));$st=db()->prepare('SELECT setting_key,setting_value,setting_group FROM site_settings WHERE setting_key IN ('.$marks.') ORDER BY setting_group,setting_key');$st->execute($allowed);$settings=$st->fetchAll();$grouped=[];foreach($settings as $item)$grouped[$item['setting_group']][]=$item;
    $siteMedia=db()->query("SELECT id,stored_name,original_name,alt_text FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC")->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Configuration du site</span><h2>Informations et préférences</h2></div></div><p class="media-note"><i class="bi bi-shield-lock"></i> Les identifiants SMTP et secrets techniques restent dans le fichier d’environnement et ne sont pas accessibles ici. Les URL des réseaux sociaux doivent utiliser HTTPS. Laissez les coordonnées vides tant qu’elles ne sont pas confirmées.</p><form method="post" class="admin-form admin-settings-form">'.csrf_field().'<input type="hidden" name="action" value="settings_save">';
    foreach($groups as $groupKey=>$groupLabel){if(empty($grouped[$groupKey]))continue;echo '<fieldset class="admin-settings-group"><legend>'.e($groupLabel).'</legend><div class="admin-settings-grid">';foreach($grouped[$groupKey] as $item){$key=(string)$item['setting_key'];$value=(string)($item['setting_value']??'');$type=match($key){'email'=>'email','facebook_url','instagram_url','linkedin_url'=>'url',default=>'text'};$id='setting-'.e($key);echo '<div class="admin-settings-field"><label for="'.$id.'">'.e($labels[$key]??$key).'</label>';if(in_array($key,['logo_media_id','favicon_media_id'],true)){echo '<select id="'.$id.'" name="settings['.e($key).']"><option value="">Utiliser le logo intégré au site</option>';foreach($siteMedia as $m)echo '<option value="'.(int)$m['id'].'"'.((string)$m['id']===$value?' selected':'').'>'.e($m['original_name']).($m['alt_text']?' · '.e($m['alt_text']):'').'</option>';echo '</select><small>'.($key==='logo_media_id'?'Sélectionnez le nouveau logo importé depuis la Médiathèque, puis enregistrez.':'Choisissez une image déjà validée dans la médiathèque.').'</small>';}elseif(in_array($key,['footer_text','address','opening_hours','announcement_message','seo_default_description'],true))echo '<textarea id="'.$id.'" name="settings['.e($key).']" rows="'.($key==='footer_text'?3:2).'" maxlength="'.($key==='seo_default_description'?320:500).'">'.e($value).'</textarea>';else echo '<input id="'.$id.'" type="'.e($type).'" name="settings['.e($key).']" maxlength="'.($key==='currency_code'?3:($key==='seo_default_title'?190:255)).'" value="'.e($value).'">';echo '</div>';}echo '</div></fieldset>';}
    echo '<button class="btn btn-admin-primary" type="submit">Enregistrer les paramètres</button></form></section>';
}

if ($page === 'home-content-admin') {
    $sections=db()->query('SELECT * FROM home_sections ORDER BY sort_order,id')->fetchAll();
    $homeMedia=db()->query("SELECT id,original_name,alt_text FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC")->fetchAll();
    $labels=['hero'=>'Bandeau principal','intro'=>'Présentation','gallery'=>'Réalisations mises en avant','cta'=>'Appel à l’action'];
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Page d’accueil</span><h2>Sections du site</h2></div></div><p class="media-note"><i class="bi bi-info-circle"></i> Les textes sont affichés comme du texte brut. Les liens doivent être des chemins internes (ex. /contact) ou des URL HTTPS.</p></section>';
    foreach($sections as $section){
        $key=(string)$section['section_key'];$id=(int)$section['id'];
        echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">'.e($key).'</span><h2>'.e($labels[$key]??$key).'</h2></div></div><form method="post" class="admin-form admin-modal-form">'.csrf_field().'<input type="hidden" name="action" value="home_section_save"><input type="hidden" name="section_id" value="'.$id.'"><label for="home-title-'.$id.'">Titre</label><input id="home-title-'.$id.'" name="title" maxlength="190" value="'.e($section['title']??'').'">';
        echo '<label for="home-subtitle-'.$id.'">Texte secondaire</label><textarea id="home-subtitle-'.$id.'" name="subtitle" rows="3" maxlength="5000">'.e($section['subtitle']??'').'</textarea><label for="home-body-'.$id.'">Texte principal</label><textarea id="home-body-'.$id.'" name="body" rows="3" maxlength="5000">'.e($section['body']??'').'</textarea>';
        if($key==='hero'){
            echo '<label for="home-media-'.$id.'">Photo principale</label><select id="home-media-'.$id.'" name="media_id"><option value="">Aucune photo</option>';
            foreach($homeMedia as $media){$selected=(int)($section['media_id']??0)===(int)$media['id']?' selected':'';echo '<option value="'.(int)$media['id'].'"'.$selected.'>'.e($media['original_name']).($media['alt_text']?' · '.e($media['alt_text']):'').'</option>';}
            echo '</select><small>Choisissez une photo déjà importée dans la Médiathèque.</small>';
        }
        echo '<div class="row g-3"><div class="col-md-6"><label for="home-cta-'.$id.'">Bouton principal</label><input id="home-cta-'.$id.'" name="cta_label" maxlength="80" value="'.e($section['cta_label']??'').'" ></div><div class="col-md-6"><label for="home-url-'.$id.'">Lien du bouton principal</label><input id="home-url-'.$id.'" name="cta_url" maxlength="255" value="'.e($section['cta_url']??'').'" ></div><div class="col-md-6"><label for="home-cta2-'.$id.'">Bouton secondaire</label><input id="home-cta2-'.$id.'" name="cta2_label" maxlength="80" value="'.e($section['cta2_label']??'').'" ></div><div class="col-md-6"><label for="home-url2-'.$id.'">Lien secondaire</label><input id="home-url2-'.$id.'" name="cta2_url" maxlength="255" value="'.e($section['cta2_url']??'').'" ></div></div><label for="home-order-'.$id.'">Ordre d’affichage</label><input id="home-order-'.$id.'" type="number" min="0" max="32767" name="sort_order" value="'.(int)$section['sort_order'].'"><label><input type="checkbox" name="is_active" value="1"'.((int)$section['is_active']===1?' checked':'').'> Section visible sur le site</label><button class="btn btn-admin-primary" type="submit">Enregistrer cette section</button></form></section>';
    }
}

if ($page === 'testimonials-admin') {
    $status=is_string($_GET['status']??null)?$_GET['status']:'';if(!in_array($status,['','pending','approved','rejected'],true))$status='';
    $sql='SELECT t.*,u.full_name AS reviewer FROM testimonials t LEFT JOIN users u ON u.id=t.reviewed_by'.($status!==''?' WHERE t.status=:status':'').' ORDER BY t.submitted_at DESC LIMIT 100';$st=db()->prepare($sql);if($status!=='')$st->execute(['status'=>$status]);else$st->execute();$rows=$st->fetchAll();
    echo '<section class="admin-panel"><div class="panel-heading"><div><span class="admin-eyebrow">Avis clients</span><h2>Modération des témoignages</h2></div><form method="get"><input type="hidden" name="page" value="testimonials-admin"><label class="visually-hidden" for="testimonial-filter">Filtrer</label><select id="testimonial-filter" name="status"><option value="">Tous</option><option value="pending"'.($status==='pending'?' selected':'').'>En attente</option><option value="approved"'.($status==='approved'?' selected':'').'>Publiés</option><option value="rejected"'.($status==='rejected'?' selected':'').'>Refusés</option></select><button class="btn btn-sm btn-outline-admin" type="submit">Filtrer</button></form></div><p class="media-note"><i class="bi bi-shield-check"></i> Un témoignage n’apparaît sur le site qu’après approbation.</p>';
    if(!$rows)echo '<p class="empty-state">Aucun témoignage dans cette vue.</p>';
    foreach($rows as $item){$statusLabel=['pending'=>'En attente','approved'=>'Publié','rejected'=>'Refusé'][$item['status']]??$item['status'];echo '<article class="message-card"><div class="message-card-head"><div><span class="status-pill">'.e($statusLabel).'</span><h3>'.e($item['author_name']).'</h3><p>'.e($item['author_role']??'').'</p></div><small>'.e($item['submitted_at']).'</small></div><p class="message-content">'.nl2br(e($item['content'])).'</p><form method="post" class="message-status-form">'.csrf_field().'<input type="hidden" name="action" value="testimonial_status"><input type="hidden" name="testimonial_id" value="'.(int)$item['id'].'"><label class="visually-hidden" for="testimonial-status-'.(int)$item['id'].'">Décision</label><select id="testimonial-status-'.(int)$item['id'].'" name="status"><option value="pending"'.($item['status']==='pending'?' selected':'').'>En attente</option><option value="approved"'.($item['status']==='approved'?' selected':'').'>Approuver et publier</option><option value="rejected"'.($item['status']==='rejected'?' selected':'').'>Refuser</option></select><button class="btn btn-sm btn-outline-admin" type="submit">Enregistrer</button></form></article>';}
    echo '</section>';
}

admin_footer();



