<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

render_page('services', ['services' => repo_published_services()], [
    'title'          => 'Nos services — ' . setting('company_name', 'Génie Solutions'),
    'description'    => 'Imprimerie digitale et offset, couture industrielle et broderie professionnelle : découvrez nos services.',
    'canonical_path' => '/services',
]);
