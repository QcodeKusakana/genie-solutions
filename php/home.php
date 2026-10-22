<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Page d'accueil : toutes les sections sont alimentées par la base (modifiables en administration). */
render_page('home', [
    'hero'         => repo_home_section('hero'),
    'intro'        => repo_home_section('intro'),
    'gallery'      => repo_home_section('gallery'),
    'cta'          => repo_home_section('cta'),
    'services'     => repo_published_services(),
    'why'          => repo_why_arguments(),
    'steps'        => repo_order_steps(),
    'projects'     => repo_latest_projects(6),
    'testimonials' => repo_approved_testimonials(6),
], [
    'canonical_path' => '/',
]);
