<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $svc */ ?>
<article class="card-gs service-card p-4 d-flex flex-column reveal">
    <span class="icon-badge" aria-hidden="true"><i class="bi <?= e(safe_icon($svc['icon'] ?? null)) ?>"></i></span>
    <h3 class="h4 mt-3"><?= e($svc['name']) ?></h3>
    <p class="text-muted flex-grow-1"><?= e($svc['short_description']) ?></p>
    <a class="stretched-link fw-semibold" href="/services/<?= e(rawurlencode($svc['slug'])) ?>">En savoir plus<span class="visually-hidden"> sur <?= e($svc['name']) ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</article>
