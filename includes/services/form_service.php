<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Protections communes aux formulaires publics : limitation de débit, anti-robots,
 * nettoyage des saisies et téléversement sécurisé d'une pièce jointe.
 */

/* ------------------------------------------------------------------ Limitation de débit */

/** Vrai si cette IP (hachée) a déjà atteint $max tentatives dans la fenêtre. */
function rate_limit_exceeded(string $bucket, int $max, int $windowSeconds): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND ip_hash = :ip AND created_at > DATE_SUB(NOW(), INTERVAL :s SECOND)');
    $st->bindValue('b', $bucket);
    $st->bindValue('ip', ip_hash());
    $st->bindValue('s', $windowSeconds, PDO::PARAM_INT);
    $st->execute();

    return (int) $st->fetchColumn() >= $max;
}

/** Enregistre une tentative ; purge occasionnellement les anciennes lignes. */
function rate_limit_hit(string $bucket): void
{
    db()->prepare('INSERT INTO rate_limits (bucket, ip_hash) VALUES (:b, :ip)')->execute(['b' => $bucket, 'ip' => ip_hash()]);
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    }
}

/* ------------------------------------------------------------------ Anti-robots */

/** À appeler à l'AFFICHAGE du formulaire : mémorise l'heure (délai humain minimal). */
function form_guard_start(string $form): void
{
    session_boot();
    $_SESSION['form_started'][$form] = time();
}

/**
 * Contrôles à l'envoi.
 *
 * @return string 'ok' | 'bot' (champ piège rempli : on simule un succès sans rien enregistrer) | 'too_fast' | 'expired'
 */
function form_guard_check(string $form, string $honeypotField = 'website'): string
{
    if (!empty($_POST[$honeypotField])) {
        return 'bot';
    }
    $started = $_SESSION['form_started'][$form] ?? null;
    if (!is_int($started)) {
        return 'expired';
    }
    $elapsed = time() - $started;
    if ($elapsed < (int) config('app.form_min_seconds')) {
        return 'too_fast';
    }
    if ($elapsed > 6 * 3600) {
        return 'expired';
    }

    return 'ok';
}

/* ------------------------------------------------------------------ Saisies */

/** Lit un champ POST texte (jamais un tableau), le normalise et retire les caractères de contrôle. */
function post_text(string $key, bool $multiline = false): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) { // octets invalides : rejet plutôt que corruption silencieuse
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = (string) preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/\p{C}+/u', $multiline ? '' : ' ', $v);

    return trim($v);
}

/** Convertit une valeur php.ini (« 2M », « 8M »…) en octets. */
function ini_bytes(string $name): int
{
    $v = trim((string) ini_get($name));
    if ($v === '' || $v === '-1') {
        return PHP_INT_MAX;
    }
    $n = (int) $v;

    return match (strtolower(substr($v, -1))) { 'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n };
}

/** Taille maximale réellement acceptée pour une pièce jointe (paramètre du site ET limites PHP). */
function attachment_max_bytes(): int
{
    $setting = max(1, (int) setting('quote_max_attachment_mb', '5')) * 1024 * 1024;

    return min($setting, ini_bytes('upload_max_filesize'), ini_bytes('post_max_size'));
}

function format_bytes(int $n): string
{
    return $n >= 1048576 ? rtrim(rtrim(number_format($n / 1048576, 1, ',', ''), '0'), ',') . "\u{00A0}Mo" : max(1, (int) round($n / 1024)) . "\u{00A0}Ko";
}

/** La requête dépasse-t-elle post_max_size ? (PHP vide alors $_POST et $_FILES.) */
function post_too_large(): bool
{
    return empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
        && (int) $_SERVER['CONTENT_LENGTH'] > ini_bytes('post_max_size');
}

/* ------------------------------------------------------------------ Pièce jointe */

/**
 * Contrôle une pièce jointe (sans la déplacer).
 *
 * Types acceptés : JPEG, PNG, WebP, PDF. Le type est déterminé par le CONTENU (finfo), jamais par
 * le nom ou le type MIME annoncé par le navigateur, et doit être cohérent avec l'extension.
 * SVG et HTML sont volontairement exclus (scripts).
 *
 * @return array{status:string,message:?string,ext?:string,mime?:string,size?:int,tmp?:string,original?:string}
 *         status : none | ok | error
 */
function attachment_inspect(?array $file): array
{
    if ($file === null || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => 'none', 'message' => null];
    }
    $max = attachment_max_bytes();
    $err = match ($file['error']) {
        UPLOAD_ERR_OK => null,
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux (maximum ' . format_bytes($max) . ').',
        UPLOAD_ERR_PARTIAL => 'Le fichier n’a été que partiellement reçu. Veuillez réessayer.',
        default => 'Le fichier n’a pas pu être reçu. Veuillez réessayer.',
    };
    if ($err !== null) {
        return ['status' => 'error', 'message' => $err];
    }

    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['status' => 'error', 'message' => 'Le fichier n’a pas pu être reçu. Veuillez réessayer.'];
    }
    $size = (int) filesize($tmp);
    if ($size <= 0) {
        return ['status' => 'error', 'message' => 'Le fichier est vide.'];
    }
    if ($size > $max) {
        return ['status' => 'error', 'message' => 'Le fichier est trop volumineux (maximum ' . format_bytes($max) . ').'];
    }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'application/pdf' => ['pdf']];
    if (!isset($allowed[$mime])) {
        return ['status' => 'error', 'message' => 'Format non accepté. Formats autorisés : JPG, PNG, WebP et PDF.'];
    }
    $origExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($origExt, $allowed[$mime], true)) {
        return ['status' => 'error', 'message' => 'L’extension du fichier ne correspond pas à son contenu.'];
    }
    if ($mime === 'application/pdf') {
        if (file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
            return ['status' => 'error', 'message' => 'Le fichier PDF est invalide.'];
        }
    } else {
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000) {
            return ['status' => 'error', 'message' => 'L’image est invalide ou trop grande (12 000 px maximum par côté).'];
        }
    }

    // Nom d'origine conservé uniquement pour l'AFFICHAGE : caractères de contrôle et chemins retirés.
    $original = mb_substr((string) preg_replace('/[\p{C}\/\\\\]+/u', '', basename(str_replace('\\', '/', (string) $file['name']))), 0, 200);

    return ['status' => 'ok', 'message' => null, 'ext' => $allowed[$mime][0], 'mime' => $mime, 'size' => $size, 'tmp' => $tmp, 'original' => $original !== '' ? $original : 'fichier.' . $allowed[$mime][0]];
}

/**
 * Déplace un fichier validé dans le stockage PRIVÉ (hors de toute URL), sous un nom aléatoire.
 *
 * @return ?string nom stocké, ou null en cas d'échec
 */
function attachment_store_private(array $inspected, string $subdir = 'quotes'): ?string
{
    $dir = (string) config('paths.private') . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        error_log('attachment_store_private: dossier non créable');

        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
    if (!@move_uploaded_file($inspected['tmp'], $dir . '/' . $name)) {
        error_log('attachment_store_private: déplacement impossible');

        return null;
    }
    @chmod($dir . '/' . $name, 0640);

    return $name;
}
