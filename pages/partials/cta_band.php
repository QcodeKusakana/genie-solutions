<?php defined('GS_ROOT') || exit; ?>
<?php
/** @var string $title  @var ?string $text  @var string $label  @var string $href  @var ?string $label2  @var ?string $href2 */
$text ??= null; $label2 ??= null; $href2 ??= null;
?>
<section class="cta-band" aria-labelledby="cta-title">
    <div class="container text-center">
        <h2 id="cta-title"><?= e($title) ?></h2>
<?php if ($text): ?>
        <p class="lead mx-auto"><?= e($text) ?></p>
<?php endif; ?>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
            <a class="btn btn-accent btn-lg" href="<?= e($href) ?>"><?= e($label) ?></a>
<?php if ($label2 && $href2): ?>
            <a class="btn btn-light-outline btn-lg" href="<?= e($href2) ?>"><?= e($label2) ?></a>
<?php endif; ?>
        </div>
    </div>
</section>
