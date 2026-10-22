<?php defined('GS_ROOT') || exit; ?>
<?php /** @var list<array> $faqs */ ?>
<?php partial('page_header', ['title' => 'Questions fréquentes', 'lead' => 'Retrouvez les réponses aux questions les plus courantes sur nos services, les commandes et les demandes de devis.', 'crumbs' => [['Questions fréquentes', null]]]); ?>
<section class="section">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'Besoin d’informations', 'title' => 'Nous vous répondons en toute clarté', 'text' => 'Si vous ne trouvez pas la réponse à votre question, notre équipe reste disponible pour vous accompagner.', 'center' => true]); ?>
<?php if ($faqs === []): ?>
        <p class="text-muted">Aucune question n’est publiée pour le moment.</p>
<?php else: ?>
        <div class="faq-list prose">
<?php foreach ($faqs as $faq): ?>
            <details class="faq-item">
                <summary><?= e($faq['question']) ?></summary>
                <p><?= e($faq['answer']) ?></p>
            </details>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</section>
<?php partial('cta_band', ['title' => 'Une autre question ?', 'text' => 'Contactez-nous ou présentez directement votre projet.', 'label' => 'Demander un devis', 'href' => '/demande-de-devis', 'label2' => 'Nous contacter', 'href2' => '/contact']); ?>
