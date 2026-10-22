<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

$product = is_valid_slug($routeParams['slug'] ?? '') ? repo_product_by_slug($routeParams['slug']) : null;
if ($product === null) {
    render_error(404);
}

render_page('product', ['product' => $product], [
    'title'          => $product['seo_title'] ?: $product['name'] . ' — ' . setting('company_name', 'Génie Solutions'),
    'description'    => $product['seo_description'] ?: null,
    'canonical_path' => '/catalogue/' . $product['slug'],
    'og_image'       => media_src($product['stored_name'] ?? null),
]);
