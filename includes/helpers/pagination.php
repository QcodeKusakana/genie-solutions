<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Calcule la pagination. La page demandée est bornée à [1, total de pages].
 *
 * @return array{page:int,pages:int,per:int,offset:int,total:int}
 */
function paginate(int $total, int $requestedPage, int $per = 12): array
{
    $per   = max(1, $per);
    $pages = max(1, (int) ceil($total / $per));
    $page  = min(max(1, $requestedPage), $pages);

    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total];
}

/** Numéro de page lu dans $_GET['page'] (entier ≥ 1 ; toute valeur étrange → 1). */
function requested_page(): int
{
    $p = $_GET['page'] ?? '1';

    return is_string($p) && ctype_digit($p) && strlen($p) < 7 ? max(1, (int) $p) : 1;
}

/** URL de pagination conservant les autres paramètres de requête. */
function page_url(string $path, array $query, int $page): string
{
    unset($query['page']);
    if ($page > 1) {
        $query['page'] = $page;
    }

    return $path . ($query === [] ? '' : '?' . http_build_query($query));
}

/** Fenêtre de numéros de pages : 1 … 4 5 [6] 7 8 … 20 (null = points de suspension). */
function page_window(int $page, int $pages, int $radius = 2): array
{
    $items = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= $radius) {
            $items[] = $i;
        } elseif (end($items) !== null) {
            $items[] = null;
        }
    }

    return $items;
}
