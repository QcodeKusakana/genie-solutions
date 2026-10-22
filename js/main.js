/* Génie Solutions — interactions communes (JavaScript natif, aucune dépendance).
   Tout le contenu reste accessible si ce script ne se charge pas. */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Écran d’accueil bref, uniquement lors de la première visite de l’onglet.
    var intro = document.getElementById('site-intro');
    if (intro && document.documentElement.classList.contains('gs-intro-pending')) {
        var introTimer;
        var dismissIntro = function () {
            window.clearTimeout(introTimer);
            document.documentElement.classList.add('gs-intro-leaving');
            window.setTimeout(function () {
                document.documentElement.classList.remove('gs-intro-pending', 'gs-intro-leaving');
            }, 420);
        };
        var skipIntro = intro.querySelector('[data-dismiss-intro]');
        if (skipIntro) { skipIntro.addEventListener('click', dismissIntro); }
        introTimer = window.setTimeout(dismissIntro, 950);
    }

    // 1. En-tête : ombre légère après défilement
    var header = document.getElementById('site-header');
    // 2. Bouton de retour en haut
    var toTop = document.getElementById('back-to-top');

    function onScroll() {
        var y = window.pageYOffset || document.documentElement.scrollTop;
        if (header) { header.classList.toggle('is-scrolled', y > 8); }
        if (toTop) { toTop.hidden = y < 600; }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (toTop) {
        toTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
            var main = document.getElementById('contenu');
            if (main) { main.focus({ preventScroll: true }); }
        });
    }

    // 3. Visionneuse d'images : <dialog> natif (focus piégé, Échap et fond gérés par le navigateur).
    //    Sans JavaScript, chaque lien [data-lightbox] ouvre simplement l'image.
    var triggers = Array.prototype.slice.call(document.querySelectorAll('a[data-lightbox]'));
    if (triggers.length && typeof HTMLDialogElement === 'function') {
        var dlg = document.createElement('dialog');
        dlg.className = 'lightbox';
        dlg.setAttribute('aria-label', 'Visionneuse d\u2019images');
        dlg.innerHTML =
            '<div class="lightbox-inner">' +
            '<p class="lightbox-count" aria-live="polite"></p>' +
            '<button type="button" class="lightbox-btn lightbox-close" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>' +
            '<button type="button" class="lightbox-btn lightbox-prev" aria-label="Image pr\u00e9c\u00e9dente"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>' +
            '<figure class="lightbox-figure"><img alt=""><figcaption class="lightbox-caption"></figcaption></figure>' +
            '<button type="button" class="lightbox-btn lightbox-next" aria-label="Image suivante"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' +
            '</div>';
        document.body.appendChild(dlg);

        var imgEl = dlg.querySelector('img'), capEl = dlg.querySelector('figcaption'), countEl = dlg.querySelector('.lightbox-count');
        var group = [], index = 0;

        var show = function (i) {
            if (!group.length) { return; }
            index = (i + group.length) % group.length;
            var a = group[index];
            imgEl.src = a.getAttribute('href');
            imgEl.alt = a.getAttribute('data-alt') || '';
            capEl.textContent = a.getAttribute('data-caption') || '';
            capEl.hidden = !capEl.textContent;
            countEl.textContent = group.length > 1 ? (index + 1) + ' / ' + group.length : '';
            dlg.querySelector('.lightbox-prev').hidden = dlg.querySelector('.lightbox-next').hidden = group.length < 2;
        };

        triggers.forEach(function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                var name = a.getAttribute('data-lightbox');
                group = triggers.filter(function (t) { return t.getAttribute('data-lightbox') === name; });
                show(group.indexOf(a));
                dlg.showModal();
            });
        });
        dlg.querySelector('.lightbox-close').addEventListener('click', function () { dlg.close(); });
        dlg.querySelector('.lightbox-prev').addEventListener('click', function () { show(index - 1); });
        dlg.querySelector('.lightbox-next').addEventListener('click', function () { show(index + 1); });
        dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { dlg.close(); } }); // clic sur le fond
        dlg.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(index - 1); }
            if (ev.key === 'ArrowRight') { ev.preventDefault(); show(index + 1); }
        });
        dlg.addEventListener('close', function () { imgEl.removeAttribute('src'); });
    }

    // 4. Formulaires : champs conditionnels du devis, anti double-clic, focus sur le résumé d'erreurs.
    //    Sans JavaScript : tous les blocs restent visibles et le serveur ne retient que ceux du service choisi.
    var svcSelect = document.getElementById('f-service');
    if (svcSelect) {
        var profileStatus = document.getElementById('quote-profile-status');
        var syncProfile = function () {
            var opt = svcSelect.options[svcSelect.selectedIndex];
            var profile = opt ? opt.getAttribute('data-profile') : null;
            Array.prototype.forEach.call(document.querySelectorAll('[data-profile-fields]'), function (fs) {
                var show = !!profile && fs.getAttribute('data-profile-fields') === profile;
                fs.hidden = !show;
                fs.disabled = !show; // un <fieldset> désactivé n'est pas envoyé : pas de champs fantômes
            });
            if (profileStatus) {
                var activeFields = profile ? document.querySelector('[data-profile-fields="' + profile + '"]') : null;
                if (!profile) {
                    profileStatus.textContent = 'Choisissez un service pour afficher les précisions correspondantes.';
                } else if (activeFields) {
                    var legend = activeFields.querySelector('legend');
                    profileStatus.textContent = 'Les champs « ' + (legend ? legend.childNodes[0].textContent.trim() : 'Informations complémentaires') + ' » sont affichés ci-dessous. Ils sont facultatifs.';
                } else {
                    profileStatus.textContent = 'Aucun champ complémentaire pour ce service. Décrivez votre besoin dans la description du projet.';
                }
            }
        };
        svcSelect.addEventListener('change', syncProfile);
        syncProfile();
    }
    Array.prototype.forEach.call(document.querySelectorAll('form[data-submit-once]'), function (form) {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) { return; }
        var label = btn.textContent;
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) { return; }
            btn.disabled = true;
            btn.textContent = btn.getAttribute('data-busy-label') || label;
        });
        // retour arrière du navigateur (page restaurée depuis le cache) : on réactive le bouton
        window.addEventListener('pageshow', function () { btn.disabled = false; btn.textContent = label; });
    });
    var errorBox = document.getElementById('form-errors');
    if (errorBox) { errorBox.focus(); }

    // 5. Apparition progressive des éléments .reveal (désactivée si réduction des animations)
    var items = document.querySelectorAll('.reveal');
    if (!items.length || reduceMotion || !('IntersectionObserver' in window)) { return; }

    document.documentElement.classList.add('reveal-ready');
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

    items.forEach(function (el) { observer.observe(el); });
})();
