<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $service  @var list<array> $projects  @var list<array> $products */ ?>
<?php partial('page_header', [
    'title' => $service['name'], 'lead' => $service['short_description'],
    'crumbs' => [['Services', '/services'], [$service['name'], null]],
]); ?>
<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <p class="section-kicker">Notre savoir-faire</p>
<?php if (!empty($service['media'])): ?>
                <img class="service-feature-image mb-4" src="<?= e($service['media']['url']) ?>" alt="<?= e($service['media']['alt'] ?: $service['name']) ?>"<?= $service['media']['width'] ? ' width="' . (int) $service['media']['width'] . '" height="' . (int) $service['media']['height'] . '"' : '' ?>>
<?php endif; ?>
                <div class="rich-text"><?= sanitize_html($service['description']) ?></div>
<?php if (!empty($service['approach_text'])): ?>
                <h2 class="h4 mt-5">Notre approche</h2>
                <p><?= e($service['approach_text']) ?></p>
<?php endif; ?>
            </div>
            <div class="col-lg-5">
<?php if ($service['offerings'] !== []): ?>
                <aside class="card-gs detail-panel p-4" aria-labelledby="offerings-title">
                    <h2 class="h5" id="offerings-title">Prestations proposées</h2>
                    <p class="small text-muted">Selon disponibilité.</p>
                    <ul class="check-list">
<?php foreach ($service['offerings'] as $label): ?>
                        <li><?= e($label) ?></li>
<?php endforeach; ?>
                    </ul>
                </aside>
<?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($products !== []): ?>
<section class="section section-alt" aria-labelledby="products-title">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'À découvrir', 'title' => 'Produits et prestations', 'text' => 'Retrouvez les options associées à ce service et demandez une proposition adaptée.']); ?>
        <div class="row g-4">
<?php foreach ($products as $pr): ?>
            <div class="col-md-6 col-lg-4">
                <article class="card-gs p-4 reveal">
                    <h3 class="h5"><a class="stretched-link text-decoration-none" href="/catalogue/<?= e(rawurlencode($pr['slug'])) ?>"><?= e($pr['name']) ?></a></h3>
                    <p class="price mb-0"><?= e(format_price($pr['price_amount'], $pr['price_note'])) ?></p>
                </article>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($projects !== []): ?>
<section class="section" aria-labelledby="proj-title">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'En images', 'title' => 'Réalisations récentes', 'text' => 'Quelques exemples du travail accompli dans ce domaine.']); ?>
        <div class="row g-4">
<?php foreach ($projects as $p): ?>
            <div class="col-md-6 col-lg-4"><?php partial('project_card', ['p' => $p]); ?></div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php partial('cta_band', [
    'title' => 'Parlons de votre projet',
    'text'  => $service['cta_text'] ?: null,
    'label' => 'Demander un devis',
    'href'  => '/demande-de-devis?service=' . rawurlencode($service['slug']),
]); ?>
