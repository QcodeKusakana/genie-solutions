/* Déclenche l’écran d’accueil avant le rendu du contenu, sans code inline (CSP). */
(function () {
    'use strict';
    try {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || sessionStorage.getItem('gs-intro-seen')) {
            return;
        }
        document.documentElement.classList.add('gs-intro-pending');
        sessionStorage.setItem('gs-intro-seen', '1');
        // Repli au cas où le script d’interactions principal ne se chargerait pas.
        window.setTimeout(function () {
            document.documentElement.classList.remove('gs-intro-pending', 'gs-intro-leaving');
        }, 2200);
    } catch (error) {
        // Le site reste immédiatement visible si le stockage de session est indisponible.
    }
}());
