<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Pages éditoriales (À propos, politique de confidentialité, mentions légales). Brouillon → 404. */
$page = repo_published_page($routeParams['slug'] ?? '');
if ($page === null) {
    render_error(404);
}

render_page('static_page', ['page' => $page], [
    'title'          => $page['seo_title'] ?: $page['title'] . ' — ' . setting('company_name', 'Génie Solutions'),
    'description'    => $page['seo_description'] ?: null,
    'canonical_path' => '/' . $page['slug'],
]);
