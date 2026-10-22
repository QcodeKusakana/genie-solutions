<?php defined('GS_ROOT') || exit; ?>
<?php
/**
 * Bandeau de titre + fil d'Ariane.
 * @var string $title  @var ?string $lead  @var list<array{0:string,1:?string}> $crumbs  [libellé, url|null]
 */
$lead   ??= null;
$crumbs ??= [];
?>
<section class="page-header">
    <div class="container">
<?php if ($crumbs !== []): ?>
        <nav aria-label="Fil d’Ariane">
            <ol class="breadcrumb-gs">
                <li><a href="/">Accueil</a></li>
<?php foreach ($crumbs as [$label, $href]): ?>
<?php if ($href !== null): ?>
                <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
<?php else: ?>
                <li aria-current="page"><?= e($label) ?></li>
<?php endif; ?>
<?php endforeach; ?>
            </ol>
        </nav>
<?php endif; ?>
        <h1><?= e($title) ?></h1>
<?php if ($lead): ?>
        <p class="lead"><?= e($lead) ?></p>
<?php endif; ?>
    </div>
</section>
