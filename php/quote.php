<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Demande de devis : GET = formulaire ; POST = traitement serveur complet
 * (CSRF → limitation de débit → anti-robots → validation → pièce jointe → enregistrement → e-mail → redirection 303).
 */
const QUOTE_MSG_ERROR = 'Votre demande n’a pas pu être enregistrée. Vérifiez les informations saisies et réessayez. Si le problème persiste, contactez-nous par téléphone ou par e-mail.';

$services = repo_published_services();
$errors = [];
$old = ['service' => '', 'product' => '', 'delivery_mode' => '', 'consent' => ''];
$status = 200;
$attachmentNote = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    session_boot();
    if (post_too_large()) {
        $errors['_form'] = 'Les fichiers envoyés dépassent la taille autorisée (' . format_bytes(attachment_max_bytes()) . ' maximum).';
        $status = 413;
    } else {
        if (!csrf_verify($_POST['_csrf'] ?? null)) {
            render_error(403);
        }
        if (rate_limit_exceeded('quote_form', 8, 3600)) {
            render_error(429);
        }
        rate_limit_hit('quote_form');

        $guard = form_guard_check('quote');
        if ($guard === 'bot') { // champ piège rempli : succès simulé, rien n'est enregistré
            $_SESSION['quote_ref'] = '';
            redirect('/demande-de-devis/merci', 303);
        }

        $v = quote_validate();
        $errors = $v['errors'];
        $old = $v['old'];
        if ($guard !== 'ok') {
            $errors['_form'] = 'Le formulaire a expiré ou a été envoyé trop rapidement. Vérifiez vos informations puis renvoyez-le.';
        }

        $att = attachment_inspect($_FILES['attachment'] ?? null);
        if ($att['status'] === 'error') {
            $errors['attachment'] = (string) $att['message'];
        }

        if ($errors === []) {
            try {
                $created = quote_create($old, $v['data'], $v['details'], $att['status'] === 'ok' ? $att : null);
                try {
                    quote_notify_staff($created['id'], $created['reference'], $old, $v['data'], $v['details']);
                } catch (Throwable $e) {
                    error_log('quote_notify_staff: ' . $e->getMessage()); // l'e-mail ne doit jamais annuler la demande
                }
                unset($_SESSION['csrf_token']);           // jeton à usage unique : un renvoi du même formulaire est refusé
                $_SESSION['quote_ref'] = $created['reference'];
                redirect('/demande-de-devis/merci', 303);  // PRG : évite la double soumission par rechargement
            } catch (RuntimeException $e) {
                $errors['_form'] = QUOTE_MSG_ERROR;
                $status = 500;
            }
        } else {
            $status = 422;
            $attachmentNote = $att['status'] === 'ok'; // le navigateur ne peut pas pré-remplir un champ fichier
        }
    }
} else {
    form_guard_start('quote');
    foreach (['service', 'produit'] as $q) {
        $slug = $_GET[$q] ?? '';
        if (is_string($slug) && is_valid_slug($slug)) {
            $old[$q === 'service' ? 'service' : 'product'] = $slug;
        }
    }
    if ($old['product'] !== '' && repo_product_by_slug($old['product']) === null) {
        $old['product'] = '';
    }
    if ($old['service'] !== '' && quote_find_service($old['service']) === null) {
        $old['service'] = '';
    }
    if ($old['service'] === '' && $old['product'] !== '') {
        $pr = repo_product_by_slug($old['product']);
        $old['service'] = (string) ($pr['service_slug'] ?? '');
    }
}

render_page('quote', [
    'services' => $services, 'errors' => $errors, 'old' => $old, 'attachmentNote' => $attachmentNote,
    'product' => $old['product'] !== '' ? repo_product_by_slug($old['product']) : null,
], [
    'title' => 'Demander un devis — ' . setting('company_name', 'Génie Solutions'),
    'description' => 'Décrivez votre projet d’impression, de confection ou de broderie et recevez une proposition adaptée.',
    'canonical_path' => '/demande-de-devis',
    'status' => $status,
]);
