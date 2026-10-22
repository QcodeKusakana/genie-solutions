<?php defined('GS_ROOT') || exit; ?>
<?php /** Barre d'annonce + navigation + ouverture de <main>. Variables issues de head.php. */ ?>
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
                <img src="<?= e(asset('img/brand/logo-genie-solutions.jpg')) ?>" alt="<?= e($company) ?>" class="brand-logo" width="1033" height="265" fetchpriority="high">
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
