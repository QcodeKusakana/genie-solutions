<?php /** @var ?array $hero @var ?array $intro */ ?>
<?php if ($hero): ?>
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero-inner">
        <span class="hero-kicker">Imprimerie · Couture · Broderie</span>
        <h1 id="hero-title"><?= e($hero['title']) ?></h1>
<?php if (!empty($hero['subtitle'])): ?>
        <p class="lead mt-3"><?= e($hero['subtitle']) ?></p>
<?php endif; ?>
        <div class="d-flex flex-wrap gap-3 mt-4">
<?php if (!empty($hero['cta_label'])): ?>
            <a class="btn btn-accent btn-lg" href="<?= e($hero['cta_url']) ?>"><?= e($hero['cta_label']) ?></a>
<?php endif; ?>
<?php if (!empty($hero['cta2_label'])): ?>
            <a class="btn btn-light-outline btn-lg" href="<?= e($hero['cta2_url']) ?>"><?= e($hero['cta2_label']) ?></a>
<?php endif; ?>
        </div>
        <div class="hero-pattern" aria-hidden="true">
            <span class="crop tl"></span><span class="crop tr"></span><span class="crop bl"></span><span class="crop br"></span>
            <span class="frame"></span><span class="dots"></span>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($intro): ?>
<section class="section" aria-labelledby="intro-title">
    <div class="container">
        <div class="prose reveal">
            <h2 id="intro-title" class="section-title"><?= e($intro['title']) ?></h2>
            <p class="mt-3"><?= e($intro['body']) ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!$hero && !$intro): ?>
<section class="section">
    <div class="container"><p class="text-muted mb-0">Le contenu de cette page n’est pas encore renseigné.</p></div>
</section>
<?php endif; ?>
