<?php defined('GS_ROOT') || exit; ?>
<?php /** @var list<array> $projects  @var list<array> $categories  @var ?string $activeCategory  @var array $pg  @var array $query */ ?>
<?php partial('page_header', [
    'title' => 'Nos réalisations',
    'lead'  => 'Une sélection de projets en impression, confection textile et broderie.',
    'crumbs' => [['Réalisations', null]],
]); ?>
<section class="section">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'Notre savoir-faire', 'title' => 'Des réalisations concrètes, pensées pour durer', 'text' => 'Parcourez quelques projets réalisés par notre équipe et trouvez l’inspiration pour le vôtre.']); ?>
<?php if ($categories !== []): ?>
        <nav class="filter-pills mb-4" aria-label="Filtrer les réalisations par catégorie">
            <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0">
                <li><a class="pill<?= $activeCategory === null ? ' is-active' : '' ?>" href="/realisations"<?= $activeCategory === null ? ' aria-current="true"' : '' ?>>Toutes</a></li>
<?php foreach ($categories as $cat): ?>
                <li><a class="pill<?= $activeCategory === $cat['slug'] ? ' is-active' : '' ?>" href="/realisations?categorie=<?= e(rawurlencode($cat['slug'])) ?>"<?= $activeCategory === $cat['slug'] ? ' aria-current="true"' : '' ?>><?= e($cat['name']) ?> <span class="pill-count"><?= (int) $cat['n'] ?></span></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>

<?php if ($projects === []): ?>
        <div class="empty-state">
            <p class="mb-2"><strong>Aucune réalisation à afficher<?= $activeCategory !== null ? ' dans cette catégorie' : ' pour le moment' ?>.</strong></p>
<?php if ($activeCategory !== null): ?>
            <a class="btn btn-primary" href="/realisations">Voir toutes les réalisations</a>
<?php endif; ?>
        </div>
<?php else: ?>
        <p class="text-muted small" role="status"><?= (int) $pg['total'] ?> réalisation<?= $pg['total'] > 1 ? 's' : '' ?></p>
        <div class="row g-4">
<?php foreach ($projects as $p): ?>
            <div class="col-md-6 col-lg-4"><?php partial('project_card', ['p' => $p]); ?></div>
<?php endforeach; ?>
        </div>
<?php partial('pagination', ['pg' => $pg, 'basePath' => '/realisations', 'query' => $query]); ?>
<?php endif; ?>
    </div>
</section>
<?php partial('cta_band', ['title' => 'Votre projet pourrait être le prochain', 'label' => 'Demander un devis', 'href' => '/demande-de-devis']); ?>
