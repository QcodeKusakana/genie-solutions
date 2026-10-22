<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Contact : GET = formulaire + coordonnées ; POST = CSRF → débit → anti-robots → validation → enregistrement → e-mail → 303. */
$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => '', 'consent' => ''];
$status = 200;
$sent = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    session_boot();
    if (post_too_large()) {
        $errors['_form'] = 'Votre message est trop volumineux.';
        $status = 413;
    } else {
        if (!csrf_verify($_POST['_csrf'] ?? null)) {
            render_error(403);
        }
        if (rate_limit_exceeded('contact_form', 8, 3600)) {
            render_error(429);
        }
        rate_limit_hit('contact_form');

        $guard = form_guard_check('contact');
        if ($guard === 'bot') {
            flash_add('success', 'Votre message a bien été envoyé. Merci de nous avoir contactés.');
            redirect('/contact', 303);
        }
        $v = contact_validate();
        $errors = $v['errors'];
        $old = $v['old'];
        if ($guard !== 'ok') {
            $errors['_form'] = 'Le formulaire a expiré ou a été envoyé trop rapidement. Vérifiez vos informations puis renvoyez-le.';
        }
        if ($errors === []) {
            try {
                contact_create($old);
                try {
                    contact_notify_staff($old);
                } catch (Throwable $e) {
                    error_log('contact_notify_staff: ' . $e->getMessage());
                }
                unset($_SESSION['csrf_token']);
                flash_add('success', 'Votre message a bien été envoyé. Notre équipe pourra l’examiner et vous répondre à partir des coordonnées fournies.');
                redirect('/contact', 303);
            } catch (Throwable $e) {
                error_log('contact_create: ' . $e->getMessage());
                $errors['_form'] = 'Votre message n’a pas pu être enregistré. Vérifiez les informations saisies et réessayez. Si le problème persiste, contactez-nous par téléphone ou par e-mail.';
                $status = 500;
            }
        } else {
            $status = 422;
        }
    }
} else {
    form_guard_start('contact');
}

render_page('contact', ['errors' => $errors, 'old' => $old], [
    'title' => 'Contact — ' . setting('company_name', 'Génie Solutions'),
    'description' => 'Une question, un besoin particulier ou un projet à réaliser ? Contactez ' . setting('company_name', 'Génie Solutions') . '.',
    'canonical_path' => '/contact',
    'status' => $status,
]);
