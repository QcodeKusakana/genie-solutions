<?php
declare(strict_types=1);

/** Page d'accueil — étape 2 : héros et présentation (les autres sections arrivent à l'étape 3). */
$hero  = repo_home_section('hero');
$intro = repo_home_section('intro');

render_page('home', ['hero' => $hero, 'intro' => $intro], [
    'title'          => null, // titre SEO par défaut (paramètre seo_default_title)
    'description'    => null,
    'canonical_path' => '/',
]);
