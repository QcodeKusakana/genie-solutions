<?php defined('GS_ROOT') || exit; ?>
<?php
/** Pied de page commun. Variables : $company, $services, $email, $phone, $hours (définies par header.php). */
$address  = setting('address');
$social   = [
    'facebook_url'  => ['Facebook',  'bi-facebook'],
    'instagram_url' => ['Instagram', 'bi-instagram'],
    'linkedin_url'  => ['LinkedIn',  'bi-linkedin'],
];
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4 py-5">
            <div class="col-lg-4">
                <a class="footer-brand-lockup" href="/" aria-label="Accueil — <?= e($company) ?>"><img src="<?= e($logo['url'] ?? asset('img/brand/logo-genie-solutions.jpg')) ?>" alt="<?= e($company) ?>"<?= isset($logo['width'], $logo['height']) && $logo['width'] ? ' width="' . (int) $logo['width'] . '" height="' . (int) $logo['height'] . '"' : ' width="1033" height="265"' ?> loading="lazy" decoding="async"></a>
                <p class="footer-text"><?= e(setting('footer_text')) ?></p>
<?php $socialLinks = []; foreach ($social as $key => [$label, $icon]) { $u = safe_external_url(setting($key)); if ($u !== null) { $socialLinks[] = [$label, $icon, $u]; } } ?>
<?php if ($socialLinks !== []): ?>
                <ul class="list-unstyled d-flex gap-2 mb-0 footer-social">
<?php foreach ($socialLinks as [$label, $icon, $u]): ?>
                    <li><a href="<?= e($u) ?>" rel="noopener noreferrer" target="_blank" aria-label="<?= e($label) ?> (nouvel onglet)"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="footer-title">Navigation</h2>
                <ul class="list-unstyled footer-links">
<?php foreach (nav_items() as $item): ?>
                    <li><a href="<?= e($item['path']) ?>"><?= e($item['label']) ?></a></li>
<?php endforeach; ?>
                    <li><a href="/demande-de-devis">Demander un devis</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="footer-title">Services</h2>
                <ul class="list-unstyled footer-links">
<?php foreach ($services as $svc): ?>
                    <li><a href="/services/<?= e(rawurlencode($svc['slug'])) ?>"><?= e($svc['name']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
            <div class="col-lg-3">
                <h2 class="footer-title">Contact</h2>
                <ul class="list-unstyled footer-links">
<?php if ($address !== ''): ?>
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($address) ?></li>
<?php endif; ?>
<?php if ($telHref !== null): ?>
                    <li><a href="<?= e($telHref) ?>"><i class="bi bi-telephone" aria-hidden="true"></i> <?= e($phone) ?></a></li>
<?php endif; ?>
<?php if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)): ?>
                    <li><a href="mailto:<?= e($email) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> <?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($hours !== ''): ?>
                    <li><i class="bi bi-clock" aria-hidden="true"></i> <?= e($hours) ?></li>
<?php endif; ?>
                    <li><a href="/contact">Nous écrire</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2 py-3">
            <p class="mb-0">&copy; <?= e(gmdate('Y')) ?> <?= e($company) ?>. Tous droits réservés.</p>
            <button type="button" class="cookie-manage" data-cookie-action="manage"><i class="bi bi-shield-lock" aria-hidden="true"></i> Gérer les cookies</button>
<?php // Pages légales : lien affiché uniquement si la page est publiée (jamais de lien mort, jamais de texte juridique inventé)
$legalLinks = [];
foreach (['politique-de-confidentialite' => 'Politique de confidentialité', 'mentions-legales' => 'Mentions légales'] as $slug => $label) {
    if (in_array($slug, repo_published_page_slugs(), true)) { $legalLinks[$slug] = $label; }
} ?>
<?php if ($legalLinks !== []): ?>
            <ul class="list-unstyled d-flex gap-3 mb-0">
<?php foreach ($legalLinks as $slug => $label): ?>
                <li><a href="/<?= e($slug) ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </div>
    </div>
</footer>

<button type="button" class="back-to-top" id="back-to-top" hidden aria-label="Retour en haut de la page">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>

<section class="cookie-banner" id="cookie-banner" aria-labelledby="cookie-title" aria-describedby="cookie-description" hidden>
    <div class="cookie-banner-copy">
        <span class="cookie-eyebrow"><i class="bi bi-shield-check" aria-hidden="true"></i> Respect de votre vie privée</span>
        <h2 id="cookie-title">Votre choix concernant les cookies</h2>
        <p id="cookie-description">Ce site utilise uniquement les éléments nécessaires à son fonctionnement. Aucun outil d’analyse ou de publicité n’est actuellement activé.</p>
    </div>
    <div class="cookie-actions" aria-label="Choix des cookies">
        <button type="button" class="btn btn-cookie-secondary" data-cookie-action="reject">Tout refuser</button>
        <button type="button" class="btn btn-cookie-secondary" data-cookie-action="customize">Personnaliser</button>
        <button type="button" class="btn btn-cookie-primary" data-cookie-action="accept">Tout accepter</button>
    </div>
</section>

<dialog class="cookie-dialog" id="cookie-dialog" aria-labelledby="cookie-dialog-title" aria-describedby="cookie-dialog-description">
    <form method="dialog" class="cookie-dialog-card">
        <div class="cookie-dialog-heading">
            <span class="cookie-eyebrow"><i class="bi bi-sliders" aria-hidden="true"></i> Préférences de confidentialité</span>
            <button type="submit" class="cookie-close" aria-label="Fermer" value="close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            <h2 id="cookie-dialog-title">Gérer vos préférences</h2>
            <p id="cookie-dialog-description">Choisissez les catégories que vous souhaitez autoriser. Votre choix peut être modifié à tout moment depuis le pied de page.</p>
        </div>
        <div class="cookie-category">
            <div><h3>Nécessaires</h3><p>Fonctionnement du site, sécurité et mémorisation de votre préférence.</p></div>
            <label class="cookie-switch"><input type="checkbox" checked disabled aria-label="Cookies nécessaires toujours actifs"><span aria-hidden="true"></span><b>Toujours actifs</b></label>
        </div>
        <p class="cookie-honesty"><i class="bi bi-info-circle" aria-hidden="true"></i> Aucun cookie d’analyse, de publicité ou de personnalisation n’est utilisé actuellement.</p>
        <div class="cookie-dialog-actions">
            <button type="button" class="btn btn-cookie-secondary" data-cookie-action="reject">Tout refuser</button>
            <button type="submit" class="btn btn-cookie-primary" value="save" data-cookie-action="save">Enregistrer mon choix</button>
        </div>
    </form>
</dialog>
<p class="cookie-status visually-hidden" id="cookie-status" role="status" aria-live="polite"></p>

<script src="<?= e(asset('lib/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('js/cookie-consent.js')) ?>" defer></script>
<script src="<?= e(asset('js/hero-carousel.js')) ?>" defer></script>
</body>
</html>
