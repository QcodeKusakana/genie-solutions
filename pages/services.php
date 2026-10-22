<?php defined('GS_ROOT') || exit; ?>
<?php /** @var list<array> $services */ ?>
<?php partial('page_header', [
    'title' => 'Nos services',
    'lead'  => 'De l’impression à la confection textile, en passant par la broderie personnalisée.',
    'crumbs' => [['Services', null]],
]); ?>
<section class="section">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'Nos expertises', 'title' => 'Un savoir-faire au service de vos idées', 'text' => 'Découvrez nos solutions en impression, confection textile et broderie, pensées pour répondre à vos besoins professionnels et personnels.']); ?>
<?php if ($services === []): ?>
        <p class="text-muted">Aucun service n’est publié pour le moment.</p>
<?php else: ?>
        <div class="row g-4">
<?php foreach ($services as $svc): ?>
            <div class="col-md-6 col-lg-4"><?php partial('service_card', ['svc' => $svc]); ?></div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</section>
<?php partial('cta_band', ['title' => 'Vous avez un projet en tête ?', 'label' => 'Demander un devis', 'href' => '/demande-de-devis']); ?>
