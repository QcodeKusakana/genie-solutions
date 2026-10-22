<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $p */ ?>
<article class="card-gs project-card reveal">
    <div class="project-thumb">
<?php $img = media_img($p, (string) $p['title'], '', true); ?>
<?php if ($img !== ''): ?>
        <?= $img ?>
<?php else: ?>
        <div class="media-placeholder" aria-hidden="true"><i class="bi <?= e(safe_icon($p['service_icon'] ?? null, 'bi-image')) ?>"></i></div>
<?php endif; ?>
    </div>
    <div class="p-3 p-lg-4">
        <p class="project-meta mb-1"><?= e($p['category_name'] ?: $p['service_name']) ?></p>
        <h3 class="h5 mb-2"><a class="stretched-link text-decoration-none" href="/realisations/<?= e(rawurlencode($p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
<?php if (!empty($p['summary'])): ?>
        <p class="text-muted small mb-0"><?= e($p['summary']) ?></p>
<?php endif; ?>
    </div>
</article>
