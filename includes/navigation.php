<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Menu principal : défini en un seul endroit, partagé par l'en-tête et le pied de page. */
function nav_items(): array
{
    $items = [
        ['label' => 'Accueil',      'path' => '/'],
        ['label' => 'À propos',     'path' => '/a-propos'],
        ['label' => 'Services',     'path' => '/services', 'children' => true],
    ];
    if (repo_has_published_products()) {
        $items[] = ['label' => 'Catalogue', 'path' => '/catalogue'];
    }

    return array_merge($items, [
        ['label' => 'Réalisations', 'path' => '/realisations'],
        ['label' => 'FAQ',          'path' => '/questions-frequentes'],
        ['label' => 'Contact',      'path' => '/contact'],
    ]);
}

/** Un lien est « actif » pour sa propre page et, pour les rubriques, ses sous-pages. */
function nav_is_active(string $itemPath, string $currentPath): bool
{
    return $itemPath === '/'
        ? $currentPath === '/'
        : ($currentPath === $itemPath || str_starts_with($currentPath, $itemPath . '/'));
}
