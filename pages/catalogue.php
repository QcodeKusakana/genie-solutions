<?php defined('GS_ROOT') || exit; ?>
<?php /** @var list<array> $products  @var array $pg  @var ?string $serviceFilter  @var list<array> $services  @var array $query */ ?>
<?php partial('page_header', [
    'title' => 'Catalogue',
    'lead'  => 'Produits et prestations proposés. Pour une commande personnalisée, demandez un devis.',
    'crumbs' => [['Catalogue', null]],
]); ?>
<section class="section">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'Catalogue', 'title' => 'Des solutions adaptées à chaque projet', 'text' => 'Explorez nos produits et prestations. Chaque réalisation peut être adaptée selon vos quantités, formats et finitions.']); ?>
<?php if (count($services) > 1): ?>
        <nav class="filter-pills mb-4" aria-label="Filtrer par service">
            <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0">
                <li><a class="pill<?= $serviceFilter === null ? ' is-active' : '' ?>" href="/catalogue"<?= $serviceFilter === null ? ' aria-current="true"' : '' ?>>Tous</a></li>
<?php foreach ($services as $svc): ?>
                <li><a class="pill<?= $serviceFilter === $svc['slug'] ? ' is-active' : '' ?>" href="/catalogue?service=<?= e(rawurlencode($svc['slug'])) ?>"<?= $serviceFilter === $svc['slug'] ? ' aria-current="true"' : '' ?>><?= e($svc['name']) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>

<?php if ($products === []): ?>
        <div class="empty-state">
            <p class="mb-2"><strong>Aucun produit n’est publié pour le moment.</strong></p>
            <p class="text-muted mb-3">Découvrez nos services ou présentez-nous directement votre besoin.</p>
            <a class="btn btn-primary" href="/services">Voir les services</a>
        </div>
<?php else: ?>
        <p class="text-muted small" role="status"><?= (int) $pg['total'] ?> résultat<?= $pg['total'] > 1 ? 's' : '' ?></p>
        <div class="row g-4">
<?php foreach ($products as $pr): ?>
            <div class="col-md-6 col-lg-4">
                <article class="card-gs product-card reveal">
                    <div class="project-thumb">
<?php $img = media_img($pr, (string) $pr['name']); ?>
<?php if ($img !== ''): ?>
                        <?= $img ?>
<?php else: ?>
                        <div class="media-placeholder" aria-hidden="true"><i class="bi <?= e(safe_icon($pr['service_icon'] ?? null, 'bi-box')) ?>"></i></div>
<?php endif; ?>
                    </div>
                    <div class="p-3 p-lg-4">
                        <p class="project-meta mb-1"><?= e($pr['service_name']) ?></p>
                        <h2 class="h5 mb-2"><a class="stretched-link text-decoration-none" href="/catalogue/<?= e(rawurlencode($pr['slug'])) ?>"><?= e($pr['name']) ?></a></h2>
                        <p class="price mb-0"><?= e(format_price($pr['price_amount'], $pr['price_note'])) ?></p>
                    </div>
                </article>
            </div>
<?php endforeach; ?>
        </div>
<?php partial('pagination', ['pg' => $pg, 'basePath' => '/catalogue', 'query' => $query]); ?>
<?php endif; ?>
    </div>
</section>
<?php partial('cta_band', ['title' => 'Un besoin particulier ?', 'text' => 'Décrivez votre projet et recevez une proposition adaptée.', 'label' => 'Demander un devis', 'href' => '/demande-de-devis']); ?>
