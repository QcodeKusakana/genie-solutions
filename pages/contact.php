<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array<string,string> $errors  @var array<string,string> $old */
$labels = ['full_name' => 'Nom complet', 'email' => 'Adresse e-mail', 'phone' => 'Téléphone', 'subject' => 'Objet', 'message' => 'Message', 'consent' => 'Consentement'];
$phone = setting('phone'); $email = setting('email'); $address = setting('address'); $hours = setting('opening_hours');
$tel = $phone !== '' ? tel_href($phone) : null; $wa = whatsapp_href(setting('whatsapp'));
$hasInfo = $tel !== null || $wa !== null || $email !== '' || $address !== '' || $hours !== '';
$privacy = in_array('politique-de-confidentialite', repo_published_page_slugs(), true);
?>
<?php partial('page_header', [
    'title' => 'Contactez ' . setting('company_name', 'Génie Solutions'),
    'lead'  => 'Une question, un besoin particulier ou un projet à réaliser ? Contactez-nous pour obtenir des informations sur nos services ou préparer une demande de devis.',
    'crumbs' => [['Contact', null]],
]); ?>
<section class="section">
    <div class="container">
<?php partial('section_intro', ['eyebrow' => 'Contact', 'title' => 'Parlons de votre besoin', 'text' => 'Écrivez-nous quelques mots. Nous vous répondrons avec les informations utiles pour faire avancer votre projet.']); ?>
        <div class="row g-5">
            <div class="col-lg-<?= $hasInfo ? '7' : '8' ?>">
<?php partial('form_errors', ['errors' => $errors, 'labels' => $labels]); ?>
                <form method="post" action="/contact" class="form-card" data-submit-once>
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true"><label>Ne pas remplir ce champ <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <p class="text-muted">Les champs marqués d’un astérisque (<span class="req">*</span>) sont obligatoires.</p>
                    <div class="row">
                        <div class="col-md-6"><?= form_field(['key' => 'full_name', 'label' => 'Nom complet', 'required' => true, 'max' => 120, 'autocomplete' => 'name'], $old, $errors) ?></div>
                        <div class="col-md-6"><?= form_field(['key' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'max' => 190, 'autocomplete' => 'email'], $old, $errors) ?></div>
                        <div class="col-md-6"><?= form_field(['key' => 'phone', 'label' => 'Téléphone (facultatif)', 'type' => 'tel', 'max' => 25, 'autocomplete' => 'tel'], $old, $errors) ?></div>
                        <div class="col-md-6"><?= form_field(['key' => 'subject', 'label' => 'Objet', 'required' => true, 'max' => 190], $old, $errors) ?></div>
                    </div>
                    <?= form_field(['key' => 'message', 'label' => 'Votre message', 'type' => 'textarea', 'required' => true, 'rows' => 6, 'max' => 5000], $old, $errors) ?>
                    <div class="form-check mb-4">
                        <input class="form-check-input<?= isset($errors['consent']) ? ' is-invalid' : '' ?>" type="checkbox" id="f-consent" name="consent" value="1" required<?= ($old['consent'] ?? '') === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="f-consent-err"' : '' ?>>
                        <label class="form-check-label" for="f-consent">J’accepte que les informations saisies soient utilisées pour répondre à mon message<?php if ($privacy): ?> (<a href="/politique-de-confidentialite">politique de confidentialité</a>)<?php endif; ?>.<span class="req" aria-hidden="true"> *</span><span class="visually-hidden"> (obligatoire)</span></label>
<?php if (isset($errors['consent'])): ?><div class="invalid-feedback d-block" id="f-consent-err"><?= e($errors['consent']) ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-accent btn-lg" type="submit" data-busy-label="Envoi en cours…">Envoyer le message</button>
                </form>
            </div>
<?php if ($hasInfo): ?>
            <div class="col-lg-5">
                <aside class="card-gs p-4" aria-labelledby="coord-title">
                    <h2 class="h5" id="coord-title">Nos coordonnées</h2>
                    <ul class="list-unstyled contact-list mb-0">
<?php if ($address !== ''): ?><li><i class="bi bi-geo-alt" aria-hidden="true"></i> <span><?= e($address) ?></span></li><?php endif; ?>
<?php if ($tel !== null): ?><li><i class="bi bi-telephone" aria-hidden="true"></i> <a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
<?php if ($wa !== null): ?><li><i class="bi bi-whatsapp" aria-hidden="true"></i> <a href="<?= e($wa) ?>" target="_blank" rel="noopener">Écrire sur WhatsApp<span class="visually-hidden"> (nouvel onglet)</span></a></li><?php endif; ?>
<?php if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)): ?><li><i class="bi bi-envelope" aria-hidden="true"></i> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
<?php if ($hours !== ''): ?><li><i class="bi bi-clock" aria-hidden="true"></i> <span><?= e($hours) ?></span></li><?php endif; ?>
                    </ul>
                </aside>
            </div>
<?php endif; ?>
        </div>
    </div>
</section>
