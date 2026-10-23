<?php defined('GS_ROOT') || exit; ?>
<?php /** @var ?array $hero @var ?array $intro @var ?array $gallery @var ?array $cta @var list<array> $services @var list<array> $why
        * @var list<array> $steps @var list<array> $projects @var list<array> $testimonials @var list<array> $partners */ ?>
<?php
$heroPhoto = null;
if (!empty($hero['stored_name'])) {
    $heroPhoto = [
        'stored_name' => $hero['stored_name'],
        'alt_text' => trim((string) ($hero['alt_text'] ?? '')) === '' || $hero['alt_text'] === 'Impression'
            ? 'Présentation d’uniformes brodés dans l’atelier Génie Solutions'
            : $hero['alt_text'],
        'width' => $hero['media_width'] ?? null,
        'height' => $hero['media_height'] ?? null,
    ];
} else {
    foreach ($projects as $candidate) {
        if (str_contains((string) ($candidate['slug'] ?? ''), 'demo-broderie-polos')) {
            $heroPhoto = $candidate;
            $heroPhoto['alt_text'] = 'Broderie textile réalisée dans l’atelier Génie Solutions';
            break;
        }
    }
}
$heroSlides = [];
if ($heroPhoto && !empty($heroPhoto['stored_name'])) {
    $heroSlides[] = $heroPhoto;
}
foreach ($projects as $candidate) {
    if (empty($candidate['stored_name'])) {
        continue;
    }
    $alreadyAdded = false;
    foreach ($heroSlides as $slide) {
        if ((string) $slide['stored_name'] === (string) $candidate['stored_name']) {
            $alreadyAdded = true;
            break;
        }
    }
    if (!$alreadyAdded) {
        $heroSlides[] = $candidate;
    }
    if (count($heroSlides) >= 4) {
        break;
    }
}
?>
<?php $homeHeadline = (string) ($hero['title'] ?? ''); if (trim($homeHeadline) === 'Des solutions professionnelles pour donner vie à vos idées.') { $homeHeadline = 'De l’idée au textile, de l’encre au grand format.'; } ?>
<?php if ($hero): ?>
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero-inner">
        <div class="hero-copy">
            <span class="hero-kicker">Imprimerie · Couture · Broderie</span>
            <h1 id="hero-title"><?= e($homeHeadline) ?></h1>
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
        </div>
<?php if ($heroSlides !== []): ?>
        <div class="hero-photo hero-carousel reveal" data-hero-carousel data-interval="5500" role="region" aria-roledescription="carrousel" aria-label="Quelques réalisations de Génie Solutions">
            <div class="hero-carousel-slides">
<?php foreach ($heroSlides as $i => $slide): $slideAlt = trim((string) ($slide['alt_text'] ?? '')); if ($slideAlt === '' || $slideAlt === 'Impression') { $slideAlt = 'Photographie d’une réalisation de Génie Solutions'; } ?>
                <figure class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" data-hero-slide aria-roledescription="diapositive" aria-label="<?= (int) $i + 1 ?> sur <?= count($heroSlides) ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
                    <?= media_img($slide, $slideAlt, '', $i !== 0) ?>
                    <figcaption><span>Nos réalisations</span><span>Un aperçu de notre savoir-faire</span></figcaption>
                </figure>
<?php endforeach; ?>
            </div>
<?php if (count($heroSlides) > 1): ?>
            <div class="hero-carousel-controls" aria-label="Commandes du carrousel">
                <button type="button" class="hero-carousel-arrow" data-hero-prev aria-label="Image précédente"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
                <div class="hero-carousel-dots" role="group" aria-label="Choisir une image">
<?php foreach ($heroSlides as $i => $_slide): ?>
                    <button type="button" class="hero-carousel-dot<?= $i === 0 ? ' is-active' : '' ?>" data-hero-dot="<?= (int) $i ?>" aria-label="Afficher l’image <?= (int) $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
<?php endforeach; ?>
                </div>
                <button type="button" class="hero-carousel-arrow" data-hero-next aria-label="Image suivante"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </div>
<?php endif; ?>
        </div>
<?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($intro): ?>
<section class="section" aria-labelledby="intro-title">
    <div class="container">
        <div class="home-intro-layout reveal">
            <p class="section-kicker">La maison</p>
            <h2 id="intro-title" class="section-title"><?= e($intro['title']) ?></h2>
            <p class="home-intro-copy mb-0"><?= e($intro['body']) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($services !== []): ?>
<section class="section section-alt section-services" aria-labelledby="services-title">
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
        <div class="home-project-grid mt-4">
<?php foreach ($projects as $p): ?>
            <?php partial('project_card', ['p' => $p]); ?>
<?php endforeach; ?>
        </div>
<?php if (!empty($gallery['cta_label'])): ?>
        <p class="mt-4 mb-0"><a class="btn btn-outline-navy" href="<?= e($gallery['cta_url']) ?>"><?= e($gallery['cta_label']) ?></a></p>
<?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($partners !== []): ?>
<section class="section section-partners" aria-labelledby="partners-title">
    <div class="container">
        <div class="partner-heading">
            <p class="section-kicker">Ils nous font confiance</p>
            <h2 class="section-title" id="partners-title">Nos partenaires</h2>
            <p class="text-muted">Des collaborations qui nous permettent de concrétiser vos projets.</p>
        </div>
        <div class="partner-marquee" aria-label="Logos de nos partenaires">
            <div class="partner-track">
<?php foreach ($partners as $partner): $logo = '/uploads/' . rawurlencode((string) $partner['stored_name']); ?>
                <?php if (!empty($partner['website_url'])): ?>
                <a class="partner-logo" href="<?= e($partner['website_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Visiter le site de <?= e($partner['name']) ?> (nouvel onglet)">
                    <img src="<?= e($logo) ?>" alt="<?= e($partner['name']) ?>" loading="lazy" width="180" height="90">
                </a>
                <?php else: ?>
                <span class="partner-logo"><img src="<?= e($logo) ?>" alt="<?= e($partner['name']) ?>" loading="lazy" width="180" height="90"></span>
                <?php endif; ?>
<?php endforeach; ?>
            </div>
            <div class="partner-track partner-track-copy" aria-hidden="true" inert>
<?php foreach ($partners as $partner): ?>
                <span class="partner-logo"><img src="/uploads/<?= e(rawurlencode((string) $partner['stored_name'])) ?>" alt="" loading="lazy" width="180" height="90"></span>
<?php endforeach; ?>
            </div>
        </div>
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
