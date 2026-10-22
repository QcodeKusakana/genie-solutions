<?php /** @var int $code @var string $title @var string $text */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> — Génie Solutions</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="error-page">
<main id="contenu" class="container py-5 text-center">
    <p class="error-code" aria-hidden="true"><?= (int) $code ?></p>
    <h1 class="h2 mb-3"><?= e($title) ?></h1>
    <p class="lead text-muted mx-auto error-text"><?= e($text) ?></p>
    <a class="btn btn-primary btn-lg mt-3" href="/">Retour à l’accueil</a>
</main>
</body>
</html>
