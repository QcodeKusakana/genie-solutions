<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Confirmation : la référence vient de la SESSION (jamais de l'URL → impossible à deviner ou à énumérer). */
$ref = null;
if (isset($_COOKIE['gs_session'])) {
    session_boot();
    $ref = $_SESSION['quote_ref'] ?? null;
}
if (!is_string($ref)) {
    redirect('/demande-de-devis', 303);
}

render_page('quote_thanks', ['reference' => $ref], [
    'title' => 'Demande enregistrée — ' . setting('company_name', 'Génie Solutions'),
    'robots' => 'noindex,nofollow',
]);
