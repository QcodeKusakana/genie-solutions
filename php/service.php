<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

$service = is_valid_slug($routeParams['slug'] ?? '') ? repo_service_by_slug($routeParams['slug']) : null;
if ($service === null) {
    render_error(404);
}

render_page('service', [
    'service'  => $service,
    'projects' => repo_latest_projects(3, (int) $service['id']),
    'products' => repo_products_of_service((int) $service['id']),
], [
    'title'          => $service['seo_title'] ?: $service['name'] . ' — ' . setting('company_name', 'Génie Solutions'),
    'description'    => $service['seo_description'] ?: $service['short_description'],
    'canonical_path' => '/services/' . $service['slug'],
    'og_image'       => $service['media'] ? $service['media']['url'] : null,
]);
