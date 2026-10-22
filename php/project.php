<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

$project = is_valid_slug($routeParams['slug'] ?? '') ? repo_project_by_slug($routeParams['slug']) : null;
if ($project === null) {
    render_error(404);
}

render_page('project', [
    'project' => $project,
    'related' => repo_latest_projects(3, (int) $project['service_id'], (int) $project['id']),
], [
    'title'          => $project['seo_title'] ?: $project['title'] . ' — ' . setting('company_name', 'Génie Solutions'),
    'description'    => $project['seo_description'] ?: ($project['summary'] ?: null),
    'canonical_path' => '/realisations/' . $project['slug'],
    'og_image'       => media_src($project['stored_name'] ?? null),
]);
