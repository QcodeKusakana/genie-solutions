<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Demandes de devis : champs conditionnels, validation serveur, enregistrement transactionnel.
 * Les valeurs saisies sont stockées telles quelles (texte) et ÉCHAPPÉES à l'affichage.
 */

const QUOTE_DELIVERY_MODES = ['' => '— À préciser —', 'Retrait sur place' => 'Retrait sur place', 'Livraison' => 'Livraison', 'À discuter' => 'À discuter'];

/** Champs spécifiques à chaque profil de service (affichés seulement si le service les concerne). */
function quote_profiles(): array
{
    $none = ['' => '—'];

    return [
        'print' => ['legend' => 'Détails d’impression', 'fields' => [
            ['key' => 'format',     'label' => 'Format', 'type' => 'text', 'max' => 80, 'help' => 'Ex. A5, A4, 85 × 55 mm'],
            ['key' => 'support',    'label' => 'Support ou papier', 'type' => 'text', 'max' => 80, 'help' => 'Ex. papier couché 135 g, carton, bâche'],
            ['key' => 'print_type', 'label' => 'Type d’impression', 'type' => 'select', 'options' => $none + ['Numérique' => 'Numérique', 'Offset' => 'Offset', 'Grand format' => 'Grand format', 'À conseiller' => 'À conseiller']],
            ['key' => 'colors',     'label' => 'Couleurs', 'type' => 'select', 'options' => $none + ['Couleur' => 'Couleur', 'Noir et blanc' => 'Noir et blanc', 'À préciser' => 'À préciser']],
            ['key' => 'finishes',   'label' => 'Finitions', 'type' => 'text', 'max' => 120, 'help' => 'Ex. pelliculage, vernis, reliure, découpe'],
        ]],
        'textile' => ['legend' => 'Détails de confection', 'fields' => [
            ['key' => 'garment_type',    'label' => 'Type de vêtement', 'type' => 'text', 'max' => 100, 'help' => 'Ex. uniforme scolaire, blouse, combinaison'],
            ['key' => 'fabric',          'label' => 'Tissu', 'type' => 'text', 'max' => 100],
            ['key' => 'sizes',           'label' => 'Tailles et répartition', 'type' => 'textarea', 'max' => 400, 'help' => 'Ex. S : 10, M : 20, L : 15'],
            ['key' => 'colors_text',     'label' => 'Couleurs', 'type' => 'text', 'max' => 120],
            ['key' => 'personalization', 'label' => 'Personnalisation', 'type' => 'select', 'options' => $none + ['Aucune' => 'Aucune', 'Broderie' => 'Broderie', 'Impression' => 'Impression', 'À discuter' => 'À discuter']],
        ]],
        'embroidery' => ['legend' => 'Détails de broderie', 'fields' => [
            ['key' => 'garment_type', 'label' => 'Support à broder', 'type' => 'text', 'max' => 100, 'help' => 'Ex. polo, chemise, casquette, sac'],
            ['key' => 'logo_size',    'label' => 'Dimensions du motif', 'type' => 'text', 'max' => 80, 'help' => 'Ex. 8 × 5 cm'],
            ['key' => 'thread_colors', 'label' => 'Couleurs des fils', 'type' => 'text', 'max' => 120],
            ['key' => 'placement',    'label' => 'Emplacement', 'type' => 'text', 'max' => 100, 'help' => 'Ex. poitrine gauche, dos, manche'],
            ['key' => 'sizes',        'label' => 'Tailles et répartition', 'type' => 'textarea', 'max' => 400],
        ]],
    ];
}

/** Retourne la ligne de service publiée pour un slug (avec son profil), ou null. */
function quote_find_service(string $slug): ?array
{
    foreach (repo_published_services() as $svc) {
        if ($svc['slug'] === $slug) {
            return $svc;
        }
    }

    return null;
}

/**
 * Valide la soumission du formulaire de devis.
 *
 * @return array{errors:array<string,string>,data:array<string,mixed>,details:array<string,string>,old:array<string,string>}
 */
function quote_validate(): array
{
    $err = [];
    $old = [];
    foreach (['full_name', 'organization', 'phone', 'email', 'service', 'product', 'quantity', 'desired_date', 'delivery_mode'] as $k) {
        $old[$k] = post_text($k);
    }
    $old['description'] = post_text('description', true);
    $old['consent'] = !empty($_POST['consent']) ? '1' : '';

    // --- Identité et contact
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 120 || !preg_match('/\p{L}/u', $old['full_name'])) {
        $err['full_name'] = 'Indiquez votre nom complet (2 à 120 caractères).';
    }
    if (mb_strlen($old['organization']) > 150) {
        $err['organization'] = 'Le nom de l’organisation est trop long (150 caractères maximum).';
    }
    if (!preg_match('/^[0-9 +().\-]{6,25}$/', $old['phone']) || strlen((string) preg_replace('/\D/', '', $old['phone'])) < 6) {
        $err['phone'] = 'Indiquez un numéro de téléphone valide (chiffres, espaces, +, -).';
    }
    if ($old['email'] === '' || strlen($old['email']) > 190 || filter_var($old['email'], FILTER_VALIDATE_EMAIL) === false) {
        $err['email'] = 'Indiquez une adresse e-mail valide.';
    }

    // --- Service et produit (résolus côté serveur : on ne fait jamais confiance à un identifiant du navigateur)
    $service = $old['service'] !== '' && is_valid_slug($old['service']) ? quote_find_service($old['service']) : null;
    $product = null;
    if ($old['product'] !== '' && is_valid_slug($old['product'])) {
        $product = repo_product_by_slug($old['product']);
        if ($product !== null && $service === null) {
            $service = quote_find_service((string) $product['service_slug']);
        }
        if ($product !== null && $service !== null && (int) $product['service_id'] !== (int) $service['id']) {
            $product = null; // produit d'un autre service : ignoré
        }
    }
    if ($service === null) {
        $err['service'] = 'Choisissez le service concerné.';
    }

    // --- Projet
    if (mb_strlen($old['quantity']) > 60) {
        $err['quantity'] = 'La quantité est trop longue (60 caractères maximum).';
    }
    $len = mb_strlen($old['description']);
    if ($len < 10) {
        $err['description'] = 'Décrivez votre besoin (10 caractères minimum).';
    } elseif ($len > 5000) {
        $err['description'] = 'La description est trop longue (5 000 caractères maximum).';
    }
    $date = null;
    if ($old['desired_date'] !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $old['desired_date']);
        $bad = $d === false || $d->format('Y-m-d') !== $old['desired_date'];
        if ($bad || $old['desired_date'] < gmdate('Y-m-d', time() - 86400) || $old['desired_date'] > gmdate('Y-m-d', strtotime('+3 years'))) {
            $err['desired_date'] = 'Indiquez une date valide, à venir et dans les 3 ans.';
        } else {
            $date = $old['desired_date'];
        }
    }
    if (!array_key_exists($old['delivery_mode'], QUOTE_DELIVERY_MODES)) {
        $err['delivery_mode'] = 'Choisissez un mode proposé.';
    }
    if ($old['consent'] !== '1') {
        $err['consent'] = 'Vous devez accepter le traitement de vos informations pour envoyer la demande.';
    }

    // --- Champs spécifiques : uniquement ceux du profil du service choisi, les autres sont ignorés
    $details = [];
    $profile = $service['quote_profile'] ?? 'generic';
    foreach (quote_profiles()[$profile]['fields'] ?? [] as $f) {
        $v = post_text('d_' . $f['key'], $f['type'] === 'textarea');
        $old['d_' . $f['key']] = $v;
        if ($v === '') {
            continue;
        }
        if ($f['type'] === 'select') {
            if (!array_key_exists($v, $f['options'])) {
                $err['d_' . $f['key']] = 'Choisissez une valeur proposée.';
                continue;
            }
        } elseif (mb_strlen($v) > $f['max']) {
            $err['d_' . $f['key']] = 'Texte trop long (' . $f['max'] . ' caractères maximum).';
            continue;
        }
        $details[$f['key']] = $v;
    }

    return [
        'errors'  => $err,
        'old'     => $old,
        'details' => $details,
        'data'    => ['service' => $service, 'product' => $product, 'desired_date' => $date],
    ];
}

/** Référence lisible et non devinable : GS-AAMM-XXXXXX (alphabet sans caractères ambigus). */
function quote_generate_reference(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return 'GS-' . gmdate('ym') . '-' . $code;
}

/**
 * Enregistre la demande (transaction) et retourne sa référence.
 *
 * @param array<string,string> $old  valeurs validées
 * @param ?array $attachment résultat de attachment_inspect() (status ok)
 * @throws RuntimeException si l'enregistrement échoue (rien n'est conservé, fichier supprimé)
 */
function quote_create(array $old, array $data, array $details, ?array $attachment): array
{
    $pdo = db();
    $stored = null;
    try {
        if ($attachment !== null && $attachment['status'] === 'ok') {
            $stored = attachment_store_private($attachment);
            if ($stored === null) {
                throw new RuntimeException('Stockage de la pièce jointe impossible.');
            }
        }

        $pdo->beginTransaction();
        $reference = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = quote_generate_reference();
            try {
                $pdo->prepare(
                    'INSERT INTO quote_requests (reference, full_name, organization, phone, email, service_id, product_id, quantity,
                            description, desired_date, delivery_mode, consent_at, ip_hash)
                     VALUES (:ref, :name, :org, :phone, :email, :sid, :pid, :qty, :descr, :date, :mode, UTC_TIMESTAMP(), :ip)'
                )->execute([
                    'ref' => $reference, 'name' => $old['full_name'], 'org' => $old['organization'] ?: null, 'phone' => $old['phone'],
                    'email' => $old['email'], 'sid' => (int) $data['service']['id'], 'pid' => $data['product'] ? (int) $data['product']['id'] : null,
                    'qty' => $old['quantity'] ?: null, 'descr' => $old['description'], 'date' => $data['desired_date'],
                    'mode' => $old['delivery_mode'] ?: null, 'ip' => ip_hash(),
                ]);
                break;
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) { // 1062 : doublon de référence → on retente
                    throw $e;
                }
            }
        }
        $id = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO quote_request_details (quote_id, field_key, field_value) VALUES (:q, :k, :v)');
        foreach ($details as $k => $v) {
            $ins->execute(['q' => $id, 'k' => $k, 'v' => mb_substr($v, 0, 500)]);
        }
        if ($stored !== null) {
            $pdo->prepare('INSERT INTO quote_attachments (quote_id, stored_name, original_name, mime_type, size_bytes) VALUES (:q,:s,:o,:m,:z)')
                ->execute(['q' => $id, 's' => $stored, 'o' => $attachment['original'], 'm' => $attachment['mime'], 'z' => $attachment['size']]);
        }
        $pdo->prepare('INSERT INTO quote_status_history (quote_id, old_status, new_status, changed_by) VALUES (:q, NULL, :s, NULL)')
            ->execute(['q' => $id, 's' => 'nouvelle']);
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_hash) VALUES (NULL, 'quote.created', 'quote', :id, :d, :ip)")
            ->execute(['id' => $id, 'd' => 'Référence ' . $reference, 'ip' => ip_hash()]);
        $pdo->commit();

        return ['id' => $id, 'reference' => $reference];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($stored !== null) {
            @unlink((string) config('paths.private') . '/quotes/' . $stored); // pas de fichier orphelin
        }
        error_log('quote_create: ' . $e->getMessage());
        throw new RuntimeException('quote_create_failed', 0, $e);
    }
}

/**
 * Prévient l'équipe par e-mail. N'écrit `notified_at` QUE si le serveur SMTP a confirmé l'envoi.
 * Un échec d'e-mail n'annule jamais la demande (elle reste consultable dans l'administration).
 */
function quote_notify_staff(int $quoteId, string $reference, array $old, array $data, array $details): bool
{
    $to = trim(setting('mail_notify_address'));
    if ($to === '') {
        $to = trim(setting('email'));
    }
    if ($to === '') {
        return false;
    }
    $lines = [
        'Nouvelle demande de devis ' . $reference, '',
        'Nom : ' . $old['full_name'], 'Organisation : ' . ($old['organization'] ?: '—'),
        'Téléphone : ' . $old['phone'], 'E-mail : ' . $old['email'],
        'Service : ' . $data['service']['name'], 'Produit : ' . ($data['product']['name'] ?? '—'),
        'Quantité : ' . ($old['quantity'] ?: '—'), 'Date souhaitée : ' . ($data['desired_date'] ?: '—'),
        'Livraison : ' . ($old['delivery_mode'] ?: '—'), '',
    ];
    foreach ($details as $k => $v) {
        $lines[] = ucfirst(str_replace('_', ' ', $k)) . ' : ' . $v;
    }
    $lines[] = '';
    $lines[] = 'Description :';
    $lines[] = mb_substr($old['description'], 0, 3000);

    $res = mail_send($to, 'Nouvelle demande de devis ' . $reference, implode("\n", $lines), $old['email']);
    if ($res['ok']) {
        db()->prepare('UPDATE quote_requests SET notified_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['id' => $quoteId]);
    }

    return $res['ok'];
}
