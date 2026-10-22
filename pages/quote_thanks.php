<?php defined('GS_ROOT') || exit; ?>
<?php /** @var string $reference */ ?>
<?php partial('page_header', ['title' => 'Demande enregistrée', 'crumbs' => [['Demande de devis', '/demande-de-devis'], ['Confirmation', null]]]); ?>
<section class="section">
    <div class="container">
        <div class="thanks-card mx-auto text-center">
            <span class="icon-badge mb-3" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            <h2 class="h3">Merci !</h2>
            <p class="lead">Votre demande a bien été enregistrée. Notre équipe pourra l’examiner et vous contacter à partir des coordonnées fournies.</p>
<?php if ($reference !== ''): ?>
            <p class="mb-1 text-muted small">Votre référence</p>
            <p class="reference" aria-label="Référence de votre demande"><?= e($reference) ?></p>
            <p class="text-muted small">Conservez cette référence : elle vous permettra de suivre votre demande auprès de l’entreprise.</p>
<?php endif; ?>
            <div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
                <a class="btn btn-primary" href="/">Retour à l’accueil</a>
                <a class="btn btn-outline-navy" href="/realisations">Voir nos réalisations</a>
            </div>
        </div>
    </div>
</section>
