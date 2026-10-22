<?php defined('GS_ROOT') || exit; ?>
<?php /** @var string $title  @var ?string $eyebrow  @var ?string $text  @var bool $center */ ?>
<?php $eyebrow ??= null; $text ??= null; $center ??= false; ?>
<div class="section-intro<?= $center ? ' section-intro-centered' : '' ?>">
<?php if ($eyebrow): ?><p class="section-kicker"><?= e($eyebrow) ?></p><?php endif; ?>
    <h2 class="section-title"><?= e($title) ?></h2>
<?php if ($text): ?><p class="section-intro-text"><?= e($text) ?></p><?php endif; ?>
</div>
