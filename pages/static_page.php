<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $page */ ?>
<?php
$isAbout = ($page['slug'] ?? '') === 'a-propos';
$safeContent = sanitize_html($page['content'] ?? '');
$aboutIntro = '';
$aboutSections = [];
if ($isAbout && $safeContent !== '') {
    $parts = preg_split('/(?=<h2>)/i', $safeContent) ?: [$safeContent];
    foreach ($parts as $part) {
        if (preg_match('/^<h2>(.*?)<\/h2>(.*)$/is', trim($part), $match)) {
            $aboutSections[] = [
                'title' => trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'body' => trim($match[2]),
            ];
        } else {
            $aboutIntro .= $part;
        }
    }
}
?>
<?php partial('page_header', [
    'title' => $page['title'],
    'lead' => $isAbout ? 'Un accompagnement professionnel en imprimerie, couture industrielle et broderie, du besoin jusqu’aux finitions.' : null,
    'crumbs' => [[$page['title'], null]],
]); ?>

<?php if ($isAbout && $safeContent !== ''): ?>
<section class="section about-intro" aria-labelledby="about-intro-title">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-lg-7">
                <p class="section-kicker">Qui sommes-nous</p>
                <h2 class="section-title mb-4" id="about-intro-title">Un partenaire pour vos projets</h2>
                <div class="rich-text about-copy"><?= $aboutIntro !== '' ? sanitize_html($aboutIntro) : $safeContent ?></div>
            </div>
            <div class="col-lg-5">
                <aside class="about-offer-card" aria-label="Nos domaines d’activité">
                    <span class="about-offer-mark" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    <p class="about-offer-kicker">Nos domaines d’activité</p>
                    <ul class="about-offer-list list-unstyled mb-0">
                        <li><i class="bi bi-printer" aria-hidden="true"></i><span>Imprimerie digitale et offset</span></li>
                        <li><i class="bi bi-scissors" aria-hidden="true"></i><span>Couture industrielle</span></li>
                        <li><i class="bi bi-threads" aria-hidden="true"></i><span>Broderie professionnelle</span></li>
                    </ul>
                    <a href="/services" class="about-offer-link">Découvrir nos services <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </aside>
            </div>
        </div>
    </div>
</section>

<?php if ($aboutSections !== []): ?>
<section class="section section-alt about-principles" aria-label="Notre mission, notre vision et nos valeurs">
    <div class="container">
        <div class="row g-4">
<?php foreach ($aboutSections as $index => $item): ?>
<?php $isValues = mb_strtolower($item['title']) === 'nos valeurs'; ?>
            <div class="<?= $isValues ? 'col-12' : 'col-md-6' ?>">
<?php if ($isValues): ?>
                <section class="about-values-panel" aria-labelledby="about-values-title">
                    <p class="section-kicker">Ce qui nous guide</p>
                    <h2 class="section-title mb-4" id="about-values-title"><?= e($item['title']) ?></h2>
                    <?php preg_match_all('/<li>(.*?)<\/li>/is', $item['body'], $values); $valueIcons = ['bi-award','bi-lightbulb','bi-bullseye','bi-chat-heart','bi-shield-check','bi-arrow-repeat']; ?>
                    <?php if (!empty($values[1])): ?>
                    <ul class="about-values-grid list-unstyled mb-0">
<?php foreach ($values[1] as $valueIndex => $value): ?>
                        <li class="about-value-card"><span class="about-value-icon" aria-hidden="true"><i class="bi <?= e($valueIcons[$valueIndex % count($valueIcons)]) ?>"></i></span><span><?= e(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></span></li>
<?php endforeach; ?>
                    </ul>
                    <?php else: ?><div class="rich-text"><?= sanitize_html($item['body']) ?></div><?php endif; ?>
                </section>
<?php else: ?>
                <article class="about-principle-card h-100">
                    <span class="about-principle-icon" aria-hidden="true"><i class="bi <?= $index === 0 ? 'bi-compass' : 'bi-eye' ?>"></i></span>
                    <p class="section-kicker mb-2"><?= $index === 0 ? 'Notre engagement' : 'Notre ambition' ?></p>
                    <h2 class="h3 mb-3"><?= e($item['title']) ?></h2>
                    <div class="rich-text"><?= sanitize_html($item['body']) ?></div>
                </article>
<?php endif; ?>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php elseif ($safeContent !== ''): ?>
<section class="section">
    <div class="container">
        <div class="prose rich-text editorial-page-content"><?= $safeContent ?></div>
    </div>
</section>
<?php endif; ?>

<?php partial('cta_band', ['title' => 'Vous avez un projet en tête ?', 'label' => 'Demander un devis', 'href' => '/demande-de-devis', 'label2' => 'Nous contacter', 'href2' => '/contact']); ?>
