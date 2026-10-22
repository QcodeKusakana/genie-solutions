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
                <p class="footer-brand"><?= e($company) ?></p>
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
            <ul class="list-unstyled d-flex gap-3 mb-0">
                <li><a href="/politique-de-confidentialite">Politique de confidentialité</a></li>
                <li><a href="/mentions-legales">Mentions légales</a></li>
            </ul>
        </div>
    </div>
</footer>

<button type="button" class="back-to-top" id="back-to-top" hidden aria-label="Retour en haut de la page">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
