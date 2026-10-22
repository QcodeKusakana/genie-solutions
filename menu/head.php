<?php defined('GS_ROOT') || exit; ?>
<?php
/**
 * <head> + variables partagées par menu.php et footer.php (même portée que render_page()).
 * Variables attendues : $meta (array), $currentPath (string).
 */
$company   = setting('company_name', 'Génie Solutions');
$metaTitle = $meta['title'] !== null && $meta['title'] !== ''
    ? $meta['title']
    : setting('seo_default_title', $company);
$metaDesc  = $meta['description'] ?? setting('seo_default_description');
$canonical = $meta['canonical_path'] !== null ? absolute_url((string) $meta['canonical_path']) : '';
$ogImage   = $meta['og_image'] ? absolute_url((string) $meta['og_image']) : '';

$phone     = setting('phone');
$email     = setting('email');
$hours     = setting('opening_hours');
$announce  = setting('announcement_message');
$wa        = whatsapp_href(setting('whatsapp'));
$telHref   = $phone !== '' ? tel_href($phone) : null;
$hasTopbar = $announce !== '' || $phone !== '' || $email !== '' || $hours !== '' || $wa !== null;

$logo     = repo_media_url((int) setting('logo_media_id', '0') ?: null);
$favicon  = repo_media_url((int) setting('favicon_media_id', '0') ?: null);
$services = repo_published_services();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($metaTitle) ?></title>
<?php if ($metaDesc): ?>
    <meta name="description" content="<?= e($metaDesc) ?>">
<?php endif; ?>
    <meta name="robots" content="<?= e($meta['robots']) ?>">
<?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="<?= e($company) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
<?php if ($metaDesc): ?>
    <meta property="og:description" content="<?= e($metaDesc) ?>">
<?php endif; ?>
<?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
    <meta name="theme-color" content="#12345A">
<?php if ($favicon): ?>
    <link rel="icon" href="<?= e($favicon['url']) ?>" type="image/webp">
<?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('lib/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('lib/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php if ($currentPath === '/'): ?>
    <script src="<?= e(asset('js/intro.js')) ?>"></script>
<?php endif; ?>
</head>
<body>
<?php if ($currentPath === '/'): ?>
<div class="site-intro" id="site-intro" role="region" aria-label="Écran de bienvenue">
    <div class="site-intro-card">
        <img src="<?= e(asset('img/brand/logo-genie-solutions.jpg')) ?>" alt="" width="1033" height="265">
        <span class="site-intro-rule" aria-hidden="true"><span></span></span>
        <p>Imprimerie · Couture · Broderie</p>
        <button type="button" class="site-intro-skip" data-dismiss-intro>Accéder au site</button>
    </div>
</div>
<?php endif; ?>
<a class="skip-link" href="#contenu">Aller au contenu principal</a>
