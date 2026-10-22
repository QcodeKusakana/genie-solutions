<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Assainisseur HTML par liste blanche, pour le contenu riche édité depuis l'administration.
 * Appliqué à l'enregistrement (étape 5) ET à l'affichage (défense en profondeur).
 * Tout ce qui n'est pas explicitement autorisé est supprimé : balises, attributs, schémas d'URL.
 */
function sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    // Balises autorisées → attributs autorisés
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'hr' => [], 'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
    ];
    // Éléments supprimés avec leur contenu
    $dropWithContent = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'link', 'meta', 'base', 'noscript',
        'template', 'audio', 'video', 'source', 'canvas', 'head', 'title'];

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="gs-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('gs-root');
    if ($root === null) {
        return '';
    }

    $safeUrl = static function (string $value, bool $isImage): ?string {
        $value = trim(preg_replace('/[\x00-\x20]+/', '', $value) ?? '');
        if ($value === '' || strlen($value) > 2000) {
            return null;
        }
        if ($isImage) {
            return preg_match('#^(/uploads/[A-Za-z0-9._%-]+|https://[^\s"\'<>]+)$#', $value) ? $value : null;
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $value) || preg_match('#^/(?!/)[^\s"\'<>]*$#', $value) || preg_match('/^#[\w-]+$/', $value)) {
            return $value;
        }

        return null;
    };

    $clean = static function (DOMNode $node) use (&$clean, $allowed, $dropWithContent, $safeUrl): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction || $child instanceof DOMCdataSection) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue; // nœud texte : conservé tel quel (sera échappé à la sérialisation)
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropWithContent, true)) {
                $node->removeChild($child);
                continue;
            }
            $clean($child); // nettoyer d'abord les descendants

            if (!isset($allowed[$tag])) { // balise inconnue mais inoffensive : on garde son contenu
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $safe = $safeUrl($attr->value, $name === 'src');
                    if ($safe === null) {
                        $child->removeAttribute($attr->name);
                    } else {
                        $child->setAttribute($name, $safe);
                    }
                } elseif (($name === 'width' || $name === 'height') && !ctype_digit($attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                if ($child->hasAttribute('href') && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                if (!$child->hasAttribute('href')) { // lien sans destination valide : devient du texte
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
            if ($tag === 'img') {
                if (!$child->hasAttribute('src')) {
                    $node->removeChild($child);
                } else {
                    $child->setAttribute('loading', 'lazy');
                    if (!$child->hasAttribute('alt')) {
                        $child->setAttribute('alt', '');
                    }
                }
            }
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }

    return trim($out);
}
