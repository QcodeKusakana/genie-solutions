<?php
declare(strict_types=1);

/** Menu principal : défini en un seul endroit, partagé par l'en-tête et le pied de page. */
function nav_items(): array
{
    return [
        ['label' => 'Accueil',      'path' => '/'],
        ['label' => 'À propos',     'path' => '/a-propos'],
        ['label' => 'Services',     'path' => '/services', 'children' => true],
        ['label' => 'Réalisations', 'path' => '/realisations'],
        ['label' => 'FAQ',          'path' => '/questions-frequentes'],
        ['label' => 'Contact',      'path' => '/contact'],
    ];
}

/** Un lien est « actif » pour sa propre page et, pour les rubriques, ses sous-pages. */
function nav_is_active(string $itemPath, string $currentPath): bool
{
    return $itemPath === '/'
        ? $currentPath === '/'
        : ($currentPath === $itemPath || str_starts_with($currentPath, $itemPath . '/'));
}
