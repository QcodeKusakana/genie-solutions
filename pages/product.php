<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $product */ ?>
<?php partial('page_header', [
    'title' => $product['name'],
    'crumbs' => [['Catalogue', '/catalogue'], [$product['name'], null]],
]); ?>
<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6">
<?php $img = media_img($product, (string) $product['name'], 'product-feature-image', false); ?>
<?php if ($img !== ''): ?>
                <?= $img ?>
<?php else: ?>
                <div class="media-placeholder media-placeholder-lg" aria-hidden="true"><i class="bi bi-box"></i></div>
<?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="card-gs product-detail-card p-4 p-lg-5">
                <p class="project-meta"><a href="/services/<?= e(rawurlencode($product['service_slug'])) ?>"><?= e($product['service_name']) ?></a></p>
                <h2 class="h4 mb-3">À propos de cette prestation</h2>
                <p class="price price-lg"><?= e(format_price($product['price_amount'], $product['price_note'])) ?></p>
<?php if (!empty($product['description'])): ?>
                <div class="prose"><?= nl2br(e($product['description'])) ?></div>
<?php endif; ?>
                <a class="btn btn-accent btn-lg mt-4" href="/demande-de-devis?produit=<?= e(rawurlencode($product['slug'])) ?>">Demander un devis pour ce produit</a>
                </div>
            </div>
        </div>
    </div>
</section>
