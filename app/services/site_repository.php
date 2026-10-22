<?php
declare(strict_types=1);

/**
 * Accès aux données du site public (toutes les requêtes SQL du front sont ici).
 * Chaque fonction est tolérante aux pannes pour que le layout ne casse pas la page.
 */

/** @return list<array{slug:string,name:string}> */
function repo_published_services(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query(
                "SELECT slug, name, short_description FROM services WHERE status = 'published' ORDER BY sort_order, name"
            )->fetchAll();
        } catch (Throwable $e) {
            error_log('repo_published_services: ' . $e->getMessage());
            $cache = [];
        }
    }

    return $cache;
}

/** Retourne une section d'accueil active par sa clé, ou null. */
function repo_home_section(string $key): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM home_sections WHERE section_key = :k AND is_active = 1');
        $st->execute(['k' => $key]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('repo_home_section: ' . $e->getMessage());

        return null;
    }
}

/** Retourne une page publiée par son slug, ou null. */
function repo_published_page(string $slug): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM pages WHERE slug = :s AND status = 'published'");
        $st->execute(['s' => $slug]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('repo_published_page: ' . $e->getMessage());

        return null;
    }
}

/** URL publique d'un média (image raster téléversée), ou null. */
function repo_media_url(?int $mediaId): ?array
{
    if (!$mediaId) {
        return null;
    }
    try {
        $st = db()->prepare('SELECT stored_name, alt_text, width, height FROM media WHERE id = :id');
        $st->execute(['id' => $mediaId]);
        $row = $st->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'url'    => '/uploads/' . rawurlencode($row['stored_name']),
            'alt'    => (string) $row['alt_text'],
            'width'  => $row['width'],
            'height' => $row['height'],
        ];
    } catch (Throwable $e) {
        error_log('repo_media_url: ' . $e->getMessage());

        return null;
    }
}
