<?php defined('GS_ROOT') || exit; ?>
<?php
/** @var array $pg  @var string $basePath  @var array $query */
if ($pg['pages'] <= 1) { return; }
?>
<nav class="mt-5" aria-label="Pagination">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item<?= $pg['page'] <= 1 ? ' disabled' : '' ?>">
<?php if ($pg['page'] > 1): ?>
            <a class="page-link" href="<?= e(page_url($basePath, $query, $pg['page'] - 1)) ?>" rel="prev">Précédent</a>
<?php else: ?>
            <span class="page-link">Précédent</span>
<?php endif; ?>
        </li>
<?php foreach (page_window($pg['page'], $pg['pages']) as $n): ?>
<?php if ($n === null): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
<?php elseif ($n === $pg['page']): ?>
        <li class="page-item active" aria-current="page"><span class="page-link"><?= (int) $n ?></span></li>
<?php else: ?>
        <li class="page-item"><a class="page-link" href="<?= e(page_url($basePath, $query, $n)) ?>"><?= (int) $n ?><span class="visually-hidden"> (page <?= (int) $n ?>)</span></a></li>
<?php endif; ?>
<?php endforeach; ?>
        <li class="page-item<?= $pg['page'] >= $pg['pages'] ? ' disabled' : '' ?>">
<?php if ($pg['page'] < $pg['pages']): ?>
            <a class="page-link" href="<?= e(page_url($basePath, $query, $pg['page'] + 1)) ?>" rel="next">Suivant</a>
<?php else: ?>
            <span class="page-link">Suivant</span>
<?php endif; ?>
        </li>
    </ul>
</nav>
