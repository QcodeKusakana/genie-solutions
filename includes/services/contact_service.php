<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Validation et enregistrement du formulaire de contact. */

/** @return array{errors:array<string,string>,old:array<string,string>} */
function contact_validate(): array
{
    $err = [];
    $old = ['full_name' => post_text('full_name'), 'email' => post_text('email'), 'phone' => post_text('phone'),
            'subject' => post_text('subject'), 'message' => post_text('message', true), 'consent' => !empty($_POST['consent']) ? '1' : ''];

    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 120 || !preg_match('/\p{L}/u', $old['full_name'])) {
        $err['full_name'] = 'Indiquez votre nom complet (2 à 120 caractères).';
    }
    if ($old['email'] === '' || strlen($old['email']) > 190 || filter_var($old['email'], FILTER_VALIDATE_EMAIL) === false) {
        $err['email'] = 'Indiquez une adresse e-mail valide.';
    }
    if ($old['phone'] !== '' && (!preg_match('/^[0-9 +().\-]{6,25}$/', $old['phone']) || strlen((string) preg_replace('/\D/', '', $old['phone'])) < 6)) {
        $err['phone'] = 'Numéro de téléphone invalide (chiffres, espaces, +, -).';
    }
    if (mb_strlen($old['subject']) < 3 || mb_strlen($old['subject']) > 190) {
        $err['subject'] = 'Indiquez un objet (3 à 190 caractères).';
    }
    $len = mb_strlen($old['message']);
    if ($len < 10 || $len > 5000) {
        $err['message'] = $len < 10 ? 'Écrivez votre message (10 caractères minimum).' : 'Le message est trop long (5 000 caractères maximum).';
    }
    if ($old['consent'] !== '1') {
        $err['consent'] = 'Vous devez accepter le traitement de vos informations pour envoyer le message.';
    }

    return ['errors' => $err, 'old' => $old];
}

/** Enregistre le message ; retourne son identifiant. */
function contact_create(array $old): int
{
    $pdo = db();
    $pdo->prepare('INSERT INTO contact_messages (full_name, email, phone, subject, message, consent_at, ip_hash)
                   VALUES (:n, :e, :p, :s, :m, UTC_TIMESTAMP(), :ip)')
        ->execute(['n' => $old['full_name'], 'e' => $old['email'], 'p' => $old['phone'] ?: null, 's' => $old['subject'], 'm' => $old['message'], 'ip' => ip_hash()]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_hash) VALUES (NULL, 'contact.created', 'contact_message', :id, NULL, :ip)")
        ->execute(['id' => $id, 'ip' => ip_hash()]);

    return $id;
}

/** Prévient l'équipe par e-mail (échec toléré : le message reste enregistré en base). */
function contact_notify_staff(array $old): bool
{
    $to = trim(setting('mail_notify_address')) ?: trim(setting('email'));
    if ($to === '') {
        return false;
    }
    $body = "Nouveau message depuis le site\n\nNom : {$old['full_name']}\nE-mail : {$old['email']}\nTéléphone : " . ($old['phone'] ?: '—')
          . "\nObjet : {$old['subject']}\n\n" . mb_substr($old['message'], 0, 3000);

    return mail_send($to, 'Nouveau message : ' . $old['subject'], $body, $old['email'])['ok'];
}
