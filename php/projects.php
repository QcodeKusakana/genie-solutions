<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Galerie des réalisations : filtre par catégorie (?categorie=) et pagination serveur (?page=). Fonctionne sans JavaScript. */
$perPage = 12;
$cat = isset($_GET['categorie']) && is_string($_GET['categorie']) && is_valid_slug($_GET['categorie']) ? $_GET['categorie'] : null;

$data  = repo_projects_page($cat, $perPage, requested_page());
$pg    = $data['pg'];
$query = $cat !== null ? ['categorie' => $cat] : [];

render_page('projects', [
    'projects' => $data['items'], 'categories' => repo_project_categories(),
    'activeCategory' => $cat, 'pg' => $pg, 'query' => $query,
], [
    'title'          => 'Nos réalisations — ' . setting('company_name', 'Génie Solutions'),
    'description'    => 'Découvrez une sélection de réalisations en impression, confection textile et broderie.',
    'canonical_path' => page_url('/realisations', $query, $pg['page']),
]);
