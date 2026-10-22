<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

render_page('faq', ['faqs' => repo_faqs()], [
    'title'          => 'Questions fréquentes — ' . setting('company_name', 'Génie Solutions'),
    'description'    => 'Les réponses aux questions les plus courantes : devis, délais, personnalisation, fichiers et suivi de commande.',
    'canonical_path' => '/questions-frequentes',
]);
