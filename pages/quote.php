<?php defined('GS_ROOT') || exit; ?>
<?php /** @var list<array> $services  @var array<string,string> $errors  @var array<string,string> $old  @var bool $attachmentNote  @var ?array $product */
$selected = null;
foreach ($services as $svc) { if ($svc['slug'] === ($old['service'] ?? '')) { $selected = $svc; } }
$activeProfile = $selected['quote_profile'] ?? null;
$labels = ['full_name' => 'Nom complet', 'organization' => 'Organisation', 'phone' => 'Téléphone', 'email' => 'Adresse e-mail', 'service' => 'Service demandé',
           'quantity' => 'Quantité estimée', 'desired_date' => 'Date souhaitée', 'delivery_mode' => 'Livraison ou retrait', 'description' => 'Description du besoin',
           'attachment' => 'Pièce jointe', 'consent' => 'Consentement'];
foreach (quote_profiles() as $p) { foreach ($p['fields'] as $f) { $labels['d_' . $f['key']] = $f['label']; } }
$privacy = in_array('politique-de-confidentialite', repo_published_page_slugs(), true);
?>
<?php partial('page_header', [
    'title' => 'Parlons de votre projet',
    'lead'  => 'Décrivez votre besoin afin que notre équipe puisse comprendre votre demande et préparer une proposition adaptée.',
    'crumbs' => [['Demande de devis', null]],
]); ?>
<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
<?php partial('section_intro', ['eyebrow' => 'Demande personnalisée', 'title' => 'Donnez-nous les détails de votre projet', 'text' => 'Ces informations nous aideront à mieux comprendre votre besoin et à préparer une proposition pertinente.']); ?>
                <p class="text-muted">Veuillez renseigner les informations disponibles concernant votre projet. Plus votre description sera précise, plus il sera facile d’évaluer les prestations nécessaires.
                   Les champs marqués d’un astérisque (<span class="req">*</span>) sont obligatoires.</p>

<?php partial('form_errors', ['errors' => $errors, 'labels' => $labels]); ?>

                <form method="post" action="/demande-de-devis" enctype="multipart/form-data" class="form-card" data-submit-once>
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true"><label>Ne pas remplir ce champ <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
<?php if ($product): ?>
                    <input type="hidden" name="product" value="<?= e($product['slug']) ?>">
                    <p class="alert alert-info py-2">Produit concerné : <strong><?= e($product['name']) ?></strong></p>
<?php endif; ?>

                    <fieldset class="mb-4">
                        <legend>Vos coordonnées</legend>
                        <div class="row">
                            <div class="col-md-6"><?= form_field(['key' => 'full_name', 'label' => 'Nom complet', 'required' => true, 'max' => 120, 'autocomplete' => 'name'], $old, $errors) ?></div>
                            <div class="col-md-6"><?= form_field(['key' => 'organization', 'label' => 'Entreprise ou organisation (facultatif)', 'max' => 150, 'autocomplete' => 'organization'], $old, $errors) ?></div>
                            <div class="col-md-6"><?= form_field(['key' => 'phone', 'label' => 'Téléphone', 'type' => 'tel', 'required' => true, 'max' => 25, 'autocomplete' => 'tel'], $old, $errors) ?></div>
                            <div class="col-md-6"><?= form_field(['key' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'max' => 190, 'autocomplete' => 'email'], $old, $errors) ?></div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend>Votre projet</legend>
                        <div class="mb-3">
                            <label class="form-label" for="f-service">Service demandé<span class="req" aria-hidden="true"> *</span><span class="visually-hidden"> (obligatoire)</span></label>
                            <select class="form-select<?= isset($errors['service']) ? ' is-invalid' : '' ?>" id="f-service" name="service" required aria-controls="quote-profile-fields"<?= isset($errors['service']) ? ' aria-invalid="true" aria-describedby="f-service-err"' : '' ?>>
                                <option value="">— Choisir un service —</option>
<?php foreach ($services as $svc): ?>
                                <option value="<?= e($svc['slug']) ?>" data-profile="<?= e($svc['quote_profile']) ?>"<?= $svc['slug'] === ($old['service'] ?? '') ? ' selected' : '' ?>><?= e($svc['name']) ?></option>
<?php endforeach; ?>
                            </select>
<?php if (isset($errors['service'])): ?><div class="invalid-feedback d-block" id="f-service-err"><?= e($errors['service']) ?></div><?php endif; ?>
                            <p class="form-text mb-0">Les champs complémentaires s’adaptent au service choisi. Vous pouvez laisser vides les précisions que vous ne connaissez pas encore.</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><?= form_field(['key' => 'quantity', 'label' => 'Quantité estimée', 'max' => 60, 'help' => 'Ex. 500 flyers, 40 polos'], $old, $errors) ?></div>
                            <div class="col-md-6"><?= form_field(['key' => 'desired_date', 'label' => 'Date souhaitée', 'type' => 'date'], $old, $errors) ?></div>
                            <div class="col-md-6"><?= form_field(['key' => 'delivery_mode', 'label' => 'Livraison ou retrait', 'type' => 'select', 'options' => QUOTE_DELIVERY_MODES], $old, $errors) ?></div>
                        </div>
                        <?= form_field(['key' => 'description', 'label' => 'Description de votre besoin', 'type' => 'textarea', 'required' => true, 'rows' => 6, 'max' => 5000,
                                        'help' => 'Usage prévu, contraintes, éléments déjà disponibles… (10 caractères minimum)'], $old, $errors) ?>
                    </fieldset>

<div id="quote-profile-fields">
<?php foreach (quote_profiles() as $profileKey => $profile): ?>
                    <fieldset class="mb-4 profile-fields" data-profile-fields="<?= e($profileKey) ?>"<?= ($activeProfile !== null && $activeProfile !== $profileKey) ? ' hidden disabled' : '' ?>>
                        <legend><?= e($profile['legend']) ?> <span class="profile-optional">Facultatif</span></legend>
                        <div class="row">
<?php foreach ($profile['fields'] as $f): $f['key'] = 'd_' . $f['key']; ?>
                            <div class="<?= ($f['type'] ?? '') === 'textarea' ? 'col-12' : 'col-md-6' ?>"><?= form_field($f, $old, $errors) ?></div>
<?php endforeach; ?>
                        </div>
                    </fieldset>
<?php endforeach; ?>
                    </div>
                    <p class="quote-profile-status" id="quote-profile-status" role="status" aria-live="polite">Choisissez un service pour afficher les précisions correspondantes.</p>

                    <fieldset class="mb-4">
                        <legend>Pièce jointe <span class="text-muted fw-normal fs-6">(facultatif)</span></legend>
<?php if ($attachmentNote): ?>
                        <p class="alert alert-warning py-2" role="status">Votre fichier n’a pas été conservé : veuillez le sélectionner à nouveau.</p>
<?php endif; ?>
                        <div class="mb-0">
                            <label class="form-label" for="f-attachment">Logo, modèle ou document</label>
                            <input class="form-control<?= isset($errors['attachment']) ? ' is-invalid' : '' ?>" type="file" id="f-attachment" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   aria-describedby="f-attachment-help<?= isset($errors['attachment']) ? ' f-attachment-err' : '' ?>"<?= isset($errors['attachment']) ? ' aria-invalid="true"' : '' ?>>
                            <div class="form-text" id="f-attachment-help">JPG, PNG, WebP ou PDF — <?= e(format_bytes(attachment_max_bytes())) ?> maximum. Sa compatibilité avec la production sera vérifiée par notre équipe.</div>
<?php if (isset($errors['attachment'])): ?><div class="invalid-feedback d-block" id="f-attachment-err"><?= e($errors['attachment']) ?></div><?php endif; ?>
                        </div>
                    </fieldset>

                    <div class="form-check mb-4">
                        <input class="form-check-input<?= isset($errors['consent']) ? ' is-invalid' : '' ?>" type="checkbox" id="f-consent" name="consent" value="1" required<?= ($old['consent'] ?? '') === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="f-consent-err"' : '' ?>>
                        <label class="form-check-label" for="f-consent">J’accepte que les informations saisies soient utilisées pour traiter ma demande<?php if ($privacy): ?> (<a href="/politique-de-confidentialite">politique de confidentialité</a>)<?php endif; ?>.<span class="req" aria-hidden="true"> *</span><span class="visually-hidden"> (obligatoire)</span></label>
<?php if (isset($errors['consent'])): ?><div class="invalid-feedback d-block" id="f-consent-err"><?= e($errors['consent']) ?></div><?php endif; ?>
                    </div>

                    <button class="btn btn-accent btn-lg" type="submit" data-busy-label="Envoi en cours…">Envoyer ma demande</button>
                </form>
            </div>
        </div>
    </div>
</section>
