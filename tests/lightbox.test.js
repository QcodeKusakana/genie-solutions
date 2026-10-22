/* Test de comportement de la visionneuse (js/main.js) avec jsdom.
 * jsdom n'implémente pas showModal()/close() : on les simule (ouverture = attribut "open").
 * Usage : NODE_PATH=<dossier node_modules> node tests/lightbox.test.js */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const html = `<!DOCTYPE html><body>
<a id="a1" href="/uploads/a.jpg" data-lightbox="projet" data-caption="Première" data-alt="Alt A"><img src="/uploads/a.jpg" alt="Alt A"></a>
<a id="a2" href="/uploads/b.jpg" data-lightbox="projet" data-caption="" data-alt="Alt B"><img src="/uploads/b.jpg" alt="Alt B"></a>
<a id="a3" href="/uploads/c.jpg" data-lightbox="projet" data-caption="Troisième" data-alt="Alt C"><img src="/uploads/c.jpg" alt="Alt C"></a>
<a id="x1" href="/uploads/z.jpg" data-lightbox="autre" data-alt="Z"><img src="/uploads/z.jpg" alt="Z"></a>
</body>`;
const dom = new JSDOM(html, { runScripts: 'outside-only', pretendToBeVisual: true });
const { window } = dom;
window.matchMedia = () => ({ matches: false });
window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new window.Event('close')); };
window.scrollTo = () => {};
window.eval(fs.readFileSync(path.join(__dirname, '..', 'js', 'main.js'), 'utf8'));

let pass = 0, fail = 0;
const t = (name, cond) => { cond ? pass++ : fail++; console.log(`  [${cond ? 'OK' : 'ÉCHEC'}] ${name}`); };
const doc = window.document, dlg = doc.querySelector('dialog.lightbox');
const img = () => dlg.querySelector('img'), cap = () => dlg.querySelector('figcaption'), cnt = () => dlg.querySelector('.lightbox-count');
const click = (el) => el.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));

t('une boîte de dialogue est créée', !!dlg);
t('aria-label présent sur le dialog', !!dlg.getAttribute('aria-label'));
t('boutons avec libellé accessible', [...dlg.querySelectorAll('button')].every(b => b.getAttribute('aria-label')));

const ev = new window.MouseEvent('click', { bubbles: true, cancelable: true });
doc.getElementById('a2').dispatchEvent(ev);
t('le clic est intercepté (preventDefault)', ev.defaultPrevented);
t('le dialog s\'ouvre', dlg.hasAttribute('open'));
t('image affichée = celle cliquée', img().getAttribute('src') === '/uploads/b.jpg');
t('texte alternatif repris', img().alt === 'Alt B');
t('compteur 2 / 3 (groupe « projet » uniquement)', cnt().textContent === '2 / 3');
t('légende vide masquée', cap().hidden === true);

click(dlg.querySelector('.lightbox-next'));
t('suivant → image 3, légende « Troisième »', img().getAttribute('src') === '/uploads/c.jpg' && cap().textContent === 'Troisième' && cap().hidden === false);
click(dlg.querySelector('.lightbox-next'));
t('suivant après la dernière → boucle sur la 1re', img().getAttribute('src') === '/uploads/a.jpg' && cnt().textContent === '1 / 3');
click(dlg.querySelector('.lightbox-prev'));
t('précédent depuis la 1re → boucle sur la dernière', img().getAttribute('src') === '/uploads/c.jpg');

dlg.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true, cancelable: true }));
t('flèche gauche → image précédente (b)', img().getAttribute('src') === '/uploads/b.jpg');
dlg.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));
t('flèche droite → image suivante (c)', img().getAttribute('src') === '/uploads/c.jpg');

click(dlg.querySelector('.lightbox-close'));
t('bouton fermer → dialog fermé', !dlg.hasAttribute('open'));
t('image vidée à la fermeture (pas de chargement résiduel)', !img().hasAttribute('src'));

click(doc.getElementById('x1'));
t('groupe « autre » : une seule image → pas de précédent/suivant', dlg.querySelector('.lightbox-prev').hidden && dlg.querySelector('.lightbox-next').hidden && cnt().textContent === '');
click(dlg); // clic sur le fond (cible = dialog)
t('clic sur le fond → ferme', !dlg.hasAttribute('open'));

console.log(`\nRésultat : ${pass} réussis, ${fail} échecs`);
process.exit(fail ? 1 : 0);
