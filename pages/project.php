<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array $project  @var list<array> $related */
$images = $project['images'];
// Image de couverture : utilisée en premier si elle n'est pas déjà dans la galerie
$cover = media_src($project['stored_name'] ?? null);
$gallery = [];
if ($cover !== null) {
    $gallery[] = ['src' => $cover, 'alt' => (trim((string) $project['alt_text']) !== '' ? $project['alt_text'] : $project['title']),
                  'caption' => '', 'w' => $project['width'], 'h' => $project['height']];
}
foreach ($images as $im) {
    $src = media_src($im['stored_name']);
    if ($src === null || $src === $cover) { continue; }
    $gallery[] = ['src' => $src, 'alt' => (trim((string) $im['alt_text']) !== '' ? $im['alt_text'] : ($im['caption'] ?: $project['title'])),
                  'caption' => (string) $im['caption'], 'w' => $im['width'], 'h' => $im['height']];
}
?>
<?php partial('page_header', [
    'title' => $project['title'], 'lead' => $project['summary'] ?: null,
    'crumbs' => [['Réalisations', '/realisations'], [$project['title'], null]],
]); ?>
<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <p class="section-kicker">Le projet en images</p>
<?php if ($gallery !== []): ?>
                <div class="project-gallery" role="list" aria-label="Photos du projet">
<?php foreach ($gallery as $i => $g): ?>
                    <a class="gallery-item" role="listitem" href="<?= e($g['src']) ?>" data-lightbox="projet"
                       data-caption="<?= e($g['caption']) ?>" data-alt="<?= e($g['alt']) ?>">
                        <img src="<?= e($g['src']) ?>" alt="<?= e($g['alt']) ?>"<?= ($g['w'] && $g['h']) ? ' width="' . (int) $g['w'] . '" height="' . (int) $g['h'] . '"' : '' ?><?= $i > 0 ? ' loading="lazy" decoding="async"' : '' ?>>
                        <span class="visually-hidden">Agrandir l’image<?= $g['caption'] !== '' ? ' : ' . e($g['caption']) : '' ?></span>
                    </a>
<?php endforeach; ?>
                </div>
<?php endif; ?>
<?php if (!empty($project['description'])): ?>
                <div class="rich-text mt-4"><?= sanitize_html($project['description']) ?></div>
<?php endif; ?>
            </div>
            <div class="col-lg-4">
                <aside class="card-gs detail-panel p-4" aria-labelledby="infos-title">
                    <h2 class="h5" id="infos-title">Informations</h2>
                    <dl class="meta-list mb-0">
                        <dt>Service</dt>
                        <dd><a href="/services/<?= e(rawurlencode($project['service_slug'])) ?>"><?= e($project['service_name']) ?></a></dd>
<?php if (!empty($project['category_name'])): ?>
                        <dt>Catégorie</dt>
                        <dd><?= e($project['category_name']) ?></dd>
<?php endif; ?>
<?php if (!empty($project['client_name'])): ?>
                        <dt>Client</dt>
                        <dd><?= e($project['client_name']) ?></dd>
<?php endif; ?>
<?php if (!empty($project['completed_on'])): ?>
                        <dt>Réalisé en</dt>
                        <dd><?= e(format_date_fr($project['completed_on'])) ?></dd>
<?php endif; ?>
                    </dl>
                    <a class="btn btn-accent w-100 mt-4" href="/demande-de-devis?service=<?= e(rawurlencode($project['service_slug'])) ?>">Un projet similaire ?</a>
                </aside>
            </div>
        </div>
    </div>
</section>

<?php if ($related !== []): ?>
<section class="section section-alt" aria-labelledby="related-title">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'À explorer', 'title' => 'Autres réalisations', 'text' => 'Découvrez d’autres projets menés par notre équipe.']); ?>
        <div class="row g-4">
<?php foreach ($related as $p): ?>
            <div class="col-md-6 col-lg-4"><?php partial('project_card', ['p' => $p]); ?></div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
