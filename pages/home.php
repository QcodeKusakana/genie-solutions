<?php defined('GS_ROOT') || exit; ?>
<?php /** @var ?array $hero @var ?array $intro @var ?array $gallery @var ?array $cta @var list<array> $services @var list<array> $why
        * @var list<array> $steps @var list<array> $projects @var list<array> $testimonials */ ?>
<?php if ($hero): ?>
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero-inner">
        <span class="hero-kicker">Imprimerie · Couture · Broderie</span>
        <h1 id="hero-title"><?= e($hero['title']) ?></h1>
<?php if (!empty($hero['subtitle'])): ?>
        <p class="lead mt-3"><?= e($hero['subtitle']) ?></p>
<?php endif; ?>
        <div class="d-flex flex-wrap gap-3 mt-4">
<?php if (!empty($hero['cta_label'])): ?>
            <a class="btn btn-accent btn-lg" href="<?= e($hero['cta_url']) ?>"><?= e($hero['cta_label']) ?></a>
<?php endif; ?>
<?php if (!empty($hero['cta2_label'])): ?>
            <a class="btn btn-light-outline btn-lg" href="<?= e($hero['cta2_url']) ?>"><?= e($hero['cta2_label']) ?></a>
<?php endif; ?>
        </div>
        <div class="hero-pattern" aria-hidden="true">
            <span class="crop tl"></span><span class="crop tr"></span><span class="crop bl"></span><span class="crop br"></span>
            <span class="frame"></span><span class="dots"></span>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($intro): ?>
<section class="section" aria-labelledby="intro-title">
    <div class="container">
        <div class="home-intro-card reveal">
            <p class="section-kicker">Génie Solutions</p>
            <h2 id="intro-title" class="section-title"><?= e($intro['title']) ?></h2>
            <p class="mt-3 mb-0"><?= e($intro['body']) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($services !== []): ?>
<section class="section section-alt" aria-labelledby="services-title">
    <div class="container">
        <p class="section-kicker">Nos services</p>
        <h2 class="section-title mb-4" id="services-title">Trois savoir-faire, un même niveau d’exigence</h2>
        <div class="row g-4">
<?php foreach ($services as $svc): ?>
            <div class="col-md-6 col-lg-4"><?php partial('service_card', ['svc' => $svc]); ?></div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($why !== []): ?>
<section class="section" aria-labelledby="why-title">
    <div class="container">
        <p class="section-kicker">Notre approche</p>
        <h2 class="section-title mb-4" id="why-title">Pourquoi choisir <?= e(setting('company_name', 'Génie Solutions')) ?> ?</h2>
        <div class="row g-4">
<?php foreach ($why as $w): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="why-item reveal">
                    <span class="icon-badge icon-badge-sm" aria-hidden="true"><i class="bi <?= e(safe_icon($w['icon'] ?? null, 'bi-check2-circle')) ?>"></i></span>
                    <h3 class="h6 mt-3"><?= e($w['title']) ?></h3>
                    <p class="text-muted small mb-0"><?= e($w['description']) ?></p>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($projects !== []): ?>
<section class="section section-alt" aria-labelledby="gallery-title">
    <div class="container">
        <p class="section-kicker">Réalisations</p>
        <h2 class="section-title" id="gallery-title"><?= e($gallery['title'] ?? 'Découvrez notre savoir-faire en images') ?></h2>
<?php if (!empty($gallery['subtitle'])): ?>
        <p class="text-muted prose"><?= e($gallery['subtitle']) ?></p>
<?php endif; ?>
        <div class="row g-4 mt-1">
<?php foreach ($projects as $p): ?>
            <div class="col-md-6 col-lg-4"><?php partial('project_card', ['p' => $p]); ?></div>
<?php endforeach; ?>
        </div>
<?php if (!empty($gallery['cta_label'])): ?>
        <p class="mt-4 mb-0"><a class="btn btn-outline-navy" href="<?= e($gallery['cta_url']) ?>"><?= e($gallery['cta_label']) ?></a></p>
<?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($steps !== []): ?>
<section class="section" aria-labelledby="steps-title">
    <div class="container">
        <p class="section-kicker">Processus de commande</p>
        <h2 class="section-title mb-4" id="steps-title">De votre besoin à la livraison</h2>
        <ol class="steps">
<?php foreach ($steps as $i => $st): ?>
            <li class="step reveal">
                <span class="step-num" aria-hidden="true"><?= (int) $i + 1 ?></span>
                <h3 class="h6"><?= e($st['title']) ?></h3>
<?php if (!empty($st['description'])): ?>
                <p class="text-muted small mb-0"><?= e($st['description']) ?></p>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php if ($testimonials !== []): /* uniquement les témoignages validés (status = approved) */ ?>
<section class="section section-alt" aria-labelledby="testi-title">
    <div class="container">
        <p class="section-kicker">Témoignages</p>
        <h2 class="section-title mb-4" id="testi-title">Ils nous ont fait confiance</h2>
        <div class="row g-4">
<?php foreach ($testimonials as $t): ?>
            <div class="col-md-6 col-lg-4">
                <figure class="card-gs testimonial p-4 mb-0 reveal">
                    <blockquote class="mb-3"><p class="mb-0"><?= e($t['content']) ?></p></blockquote>
                    <figcaption class="small"><strong><?= e($t['author_name']) ?></strong><?php if (!empty($t['author_role'])): ?><br><span class="text-muted"><?= e($t['author_role']) ?></span><?php endif; ?></figcaption>
                </figure>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($cta): ?>
<?php partial('cta_band', [
    'title' => (string) $cta['title'], 'text' => $cta['subtitle'] ?: null,
    'label' => (string) $cta['cta_label'], 'href' => (string) $cta['cta_url'],
    'label2' => $cta['cta2_label'] ?: null, 'href2' => $cta['cta2_url'] ?: null,
]); ?>
<?php endif; ?>

<?php if (!$hero && !$intro && $services === []): ?>
<section class="section">
    <div class="container"><p class="text-muted mb-0">Le contenu de cette page n’est pas encore renseigné.</p></div>
</section>
<?php endif; ?>
