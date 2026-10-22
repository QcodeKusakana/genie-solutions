<?php
/**
 * En-tête commun. Variables attendues : $meta (array), $currentPath (string).
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
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu principal</a>

<?php if ($hasTopbar): ?>
<div class="topbar" role="region" aria-label="Informations pratiques">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="topbar-message mb-0"><?= $announce !== '' ? e($announce) : '' ?></p>
        <ul class="topbar-links list-unstyled mb-0 d-flex flex-wrap gap-3">
<?php if ($telHref !== null): ?>
            <li><a href="<?= e($telHref) ?>"><i class="bi bi-telephone" aria-hidden="true"></i> <?= e($phone) ?></a></li>
<?php endif; ?>
<?php if ($wa !== null): ?>
            <li><a href="<?= e($wa) ?>" rel="noopener" target="_blank"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp<span class="visually-hidden"> (nouvel onglet)</span></a></li>
<?php endif; ?>
<?php if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)): ?>
            <li><a href="mailto:<?= e($email) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> <?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($hours !== ''): ?>
            <li><span><i class="bi bi-clock" aria-hidden="true"></i> <?= e($hours) ?></span></li>
<?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<header class="site-header" id="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="Navigation principale">
        <div class="container">
            <a class="navbar-brand" href="/">
<?php if ($logo): ?>
                <img src="<?= e($logo['url']) ?>" alt="<?= e($company) ?>" class="brand-logo"<?= $logo['width'] ? ' width="' . (int) $logo['width'] . '" height="' . (int) $logo['height'] . '"' : '' ?>>
<?php else: ?>
                <span class="brand-mark" aria-hidden="true">GS</span>
                <span class="brand-name"><?= e($company) ?></span>
<?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                    aria-controls="menu-principal" aria-expanded="false" aria-label="Ouvrir le menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="collapse navbar-collapse" id="menu-principal">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
<?php foreach (nav_items() as $item):
    $active = nav_is_active($item['path'], $currentPath);
    if (!empty($item['children']) && $services !== []): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= $active ? ' active' : '' ?>" href="<?= e($item['path']) ?>" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false"><?= e($item['label']) ?></a>
                        <ul class="dropdown-menu">
<?php foreach ($services as $svc): ?>
                            <li><a class="dropdown-item" href="/services/<?= e(rawurlencode($svc['slug'])) ?>"><?= e($svc['name']) ?></a></li>
<?php endforeach; ?>
                        </ul>
                    </li>
<?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                    </li>
<?php endif; endforeach; ?>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-accent" href="/demande-de-devis">Demander un devis</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main id="contenu" tabindex="-1">
<?php foreach (flash_pull() as $flash): ?>
    <div class="container pt-3">
        <div class="alert alert-<?= e(in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info') ?> mb-0" role="status">
            <?= e($flash['message']) ?>
        </div>
    </div>
<?php endforeach; ?>
