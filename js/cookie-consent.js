(function () {
    'use strict';

    var KEY = 'gs-cookie-consent';
    var VERSION = 1;
    var MAX_AGE = 180 * 24 * 60 * 60 * 1000;
    var banner = document.getElementById('cookie-banner');
    var dialog = document.getElementById('cookie-dialog');
    var status = document.getElementById('cookie-status');
    if (!banner || !dialog) { return; }

    var previousFocus = null;
    var storageAvailable = true;

    function readChoice() {
        try {
            var raw = window.localStorage.getItem(KEY);
            if (!raw) { return null; }
            var saved = JSON.parse(raw);
            if (!saved || saved.version !== VERSION || !saved.expiresAt || Date.now() >= saved.expiresAt) {
                window.localStorage.removeItem(KEY);
                return null;
            }
            return saved;
        } catch (err) {
            storageAvailable = false;
            return null;
        }
    }

    function setChoice(choice) {
        var now = Date.now();
        var record = {
            version: VERSION,
            choice: choice,
            categories: { necessary: true, analytics: false, advertising: false, personalization: false },
            decidedAt: new Date(now).toISOString(),
            expiresAt: now + MAX_AGE
        };
        try {
            window.localStorage.setItem(KEY, JSON.stringify(record));
            storageAvailable = true;
        } catch (err) {
            storageAvailable = false;
        }
        banner.hidden = true;
        if (status) {
            status.textContent = storageAvailable
                ? 'Votre choix a été enregistré.'
                : 'Votre choix est appliqué pour cette visite, mais le navigateur ne permet pas de le mémoriser.';
        }
        window.dispatchEvent(new CustomEvent('gs:consent-change', { detail: record }));
    }

    function openDialog() {
        if (typeof dialog.showModal !== 'function') { return; }
        previousFocus = document.activeElement;
        if (!dialog.open) { dialog.showModal(); }
        var close = dialog.querySelector('.cookie-close');
        if (close) { close.focus(); }
    }

    function showBanner() {
        banner.hidden = false;
        var firstAction = banner.querySelector('button');
        if (firstAction) { firstAction.focus(); }
    }

    document.addEventListener('click', function (event) {
        var control = event.target.closest('[data-cookie-action]');
        if (!control) { return; }
        var action = control.getAttribute('data-cookie-action');
        if (action === 'accept') { setChoice('accepted'); }
        if (action === 'reject') { setChoice('rejected'); if (dialog.open) { dialog.close('saved'); } }
        if (action === 'customize' || action === 'manage') { openDialog(); }
    });

    dialog.addEventListener('close', function () {
        if (dialog.returnValue === 'save') { setChoice('customized'); }
        if (previousFocus && typeof previousFocus.focus === 'function') { previousFocus.focus(); }
        previousFocus = null;
    });

    if (!readChoice()) { banner.hidden = false; }
})();
