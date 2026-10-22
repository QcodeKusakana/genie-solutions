<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

$perPage = 12;
$svc = isset($_GET['service']) && is_string($_GET['service']) && is_valid_slug($_GET['service']) ? $_GET['service'] : null;

$data = repo_products_page($svc, $perPage, requested_page());
$pg   = $data['pg'];

render_page('catalogue', [
    'products' => $data['items'], 'pg' => $pg, 'serviceFilter' => $svc, 'services' => repo_published_services(),
    'query' => $svc !== null ? ['service' => $svc] : [],
], [
    'title'          => 'Catalogue — ' . setting('company_name', 'Génie Solutions'),
    'description'    => 'Les produits et prestations proposés par ' . setting('company_name', 'Génie Solutions') . '.',
    'canonical_path' => page_url('/catalogue', $svc !== null ? ['service' => $svc] : [], $pg['page']),
]);
