<?php
declare(strict_types=1);

/** Tests unitaires CLI de l'étape 3 : assainisseur HTML, pagination, prix, slugs. Usage : php tests/step3_php.php */
require dirname(__DIR__) . '/includes/bootstrap.php';

$pass = $fail = 0;
function t(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    printf("  [%s] %s%s\n", $cond ? 'OK' : 'ÉCHEC', $name, ($cond || $detail === '') ? '' : ' — ' . $detail);
}
function absent(string $out, string ...$needles): bool { foreach ($needles as $n) { if (stripos($out, $n) !== false) { return false; } } return true; }

echo "Assainisseur HTML\n";
$cases = [
    'script supprimé avec son contenu'        => ['<p>a</p><script>alert(1)</script><p>b</p>', ['script', 'alert']],
    'gestionnaire onerror supprimé'            => ['<img src="/uploads/a.jpg" onerror="alert(1)" alt="x">', ['onerror', 'alert']],
    'onclick / onmouseover supprimés'          => ['<p onclick="x()" onmouseover="y()">t</p>', ['onclick', 'onmouseover']],
    'href javascript: supprimé'                => ['<a href="javascript:alert(1)">clic</a>', ['javascript', 'href']],
    'href javascript: masqué par espaces/casse'=> ['<a href="  JaVa' . "\t" . 'ScRiPt:alert(1)">x</a>', ['javascript', 'alert']],
    'href data: supprimé'                      => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:', 'base64']],
    'iframe supprimée'                         => ['<p>x</p><iframe src="https://evil.invalid"></iframe>', ['iframe', 'evil']],
    'object / embed supprimés'                 => ['<object data="x"></object><embed src="y">', ['object', 'embed']],
    'attribut style supprimé'                  => ['<p style="background:url(javascript:alert(1))">t</p>', ['style', 'javascript']],
    'class / id supprimés'                     => ['<p class="x" id="y">t</p>', ['class=', 'id=']],
    'formulaire et champs supprimés'           => ['<form action="/x"><input name="a"><button>ok</button></form>', ['<form', '<input', '<button']],
    'svg (vecteur XSS) supprimé'               => ['<svg onload="alert(1)"><script>1</script></svg>', ['svg', 'onload']],
    'commentaire conditionnel supprimé'        => ['<!--[if IE]><script>alert(1)</script><![endif]--><p>t</p>', ['<!--', 'alert']],
    'img src externe http refusée'             => ['<img src="http://evil.invalid/p.gif" alt="">', ['evil']],
    'img src data: refusée'                    => ['<img src="data:image/svg+xml;base64,AAA" alt="">', ['data:']],
    'img src avec traversée refusée'           => ['<img src="/uploads/../includes/config.php" alt="">', ['config.php']],
    'balise mal fermée / imbriquée neutralisée'=> ['<p><b>x</p></b><scr<script>ipt>alert(1)</scr</script>ipt>', ['<script', 'alert(1)</']],
    'meta refresh / base / link supprimés'     => ['<meta http-equiv="refresh" content="0;url=x"><base href="//evil.invalid/"><link rel="stylesheet" href="x">', ['meta', '<base', '<link']],
];
foreach ($cases as $name => [$in, $mustNotContain]) {
    $out = sanitize_html($in);
    t($name, absent($out, ...$mustNotContain), 'sortie : ' . $out);
}
$ok = sanitize_html('<h2>Titre</h2><p>Un <strong>texte</strong> avec <em>accent é</em> et <a href="https://exemple.invalid/x">lien</a>, <a href="/contact">interne</a>, <a href="mailto:a@b.invalid">mail</a>, <a href="tel:+243000">tel</a>.</p><ul><li>un</li><li>deux</li></ul><img src="/uploads/photo.jpg" alt="Photo" width="300" height="200">');
t('contenu légitime conservé (titres, gras, listes)', str_contains($ok, '<h2>Titre</h2>') && str_contains($ok, '<strong>texte</strong>') && str_contains($ok, '<li>deux</li>'));
t('accents UTF-8 préservés', str_contains($ok, 'accent é'));
t('liens https / internes / mailto / tel conservés', str_contains($ok, 'href="https://exemple.invalid/x"') && str_contains($ok, 'href="/contact"') && str_contains($ok, 'href="mailto:a@b.invalid"') && str_contains($ok, 'href="tel:+243000"'));
t('lien externe : rel="noopener noreferrer" ajouté', (bool) preg_match('/<a[^>]*href="https:\/\/exemple\.invalid\/x"[^>]*rel="noopener noreferrer"|<a[^>]*rel="noopener noreferrer"[^>]*href="https:\/\/exemple\.invalid\/x"/', $ok));
t('image /uploads/ conservée avec loading=lazy', str_contains($ok, 'src="/uploads/photo.jpg"') && str_contains($ok, 'loading="lazy"'));
t('esperluette et chevrons dans le texte échappés', str_contains(sanitize_html('<p>a & b < c</p>'), '&amp;') && str_contains(sanitize_html('<p>a & b < c</p>'), '&lt;'));
t('chaîne vide / null → chaîne vide', sanitize_html('') === '' && sanitize_html(null) === '');
t('idempotent (assainir deux fois = une fois)', sanitize_html($ok) === $ok);
t('balise inconnue dépliée, texte conservé', sanitize_html('<custom-tag>texte</custom-tag>') === 'texte');
t('<div>/<span> dépliés, texte conservé', sanitize_html('<div><span>abc</span></div>') === 'abc');

echo "Pagination\n";
$pg = paginate(0, 1, 12);   t('0 résultat → 1 page, offset 0', $pg['pages'] === 1 && $pg['offset'] === 0 && $pg['page'] === 1);
$pg = paginate(25, 1, 12);  t('25 résultats / 12 → 3 pages', $pg['pages'] === 3);
$pg = paginate(25, 3, 12);  t('page 3 → offset 24', $pg['offset'] === 24);
$pg = paginate(25, 99, 12); t('page hors bornes → bornée à la dernière', $pg['page'] === 3);
$pg = paginate(25, -5, 12); t('page négative → 1', $pg['page'] === 1);
$pg = paginate(24, 2, 12);  t('24 / 12 → exactement 2 pages', $pg['pages'] === 2);
$_GET['page'] = '3';        t('requested_page : "3" → 3', requested_page() === 3);
$_GET['page'] = '3abc';     t('requested_page : "3abc" → 1', requested_page() === 1);
$_GET['page'] = '-1';       t('requested_page : "-1" → 1', requested_page() === 1);
$_GET['page'] = ['x'];      t('requested_page : tableau → 1', requested_page() === 1);
$_GET['page'] = '99999999'; t('requested_page : très grand nombre → 1 (rejeté)', requested_page() === 1);
unset($_GET['page']);
t('page_url : page 1 sans paramètre page', page_url('/realisations', ['categorie' => 'x'], 1) === '/realisations?categorie=x');
t('page_url : page 2 avec filtre conservé', page_url('/realisations', ['categorie' => 'x'], 2) === '/realisations?categorie=x&page=2');
t('page_url : sans filtre ni page', page_url('/realisations', [], 1) === '/realisations');
t('page_window : 1 … 4 5 [6] 7 8 … 20', page_window(6, 20) === [1, null, 4, 5, 6, 7, 8, null, 20]);
t('page_window : 3 pages → 1 2 3', page_window(2, 3) === [1, 2, 3]);

echo "Prix, slugs, icônes, dates\n";
t('prix sans devise configurée → « Sur devis »', format_price('1500', null) === 'Sur devis');
t('prix NULL → « Sur devis »', format_price(null) === 'Sur devis');
t('is_valid_slug : valides', is_valid_slug('imprimerie-digitale-offset') && is_valid_slug('a1') && is_valid_slug('x'));
t('is_valid_slug : invalides', !is_valid_slug('Abc') && !is_valid_slug('a--b') && !is_valid_slug('-a') && !is_valid_slug('a-') && !is_valid_slug('a b') && !is_valid_slug("a'b") && !is_valid_slug('../x') && !is_valid_slug(''));
t('safe_icon : valide', safe_icon('bi-printer') === 'bi-printer');
t('safe_icon : injection → repli', safe_icon('bi-x" onclick="y') === 'bi-grid' && safe_icon(null, 'bi-box') === 'bi-box');
t('format_date_fr', format_date_fr('2026-03-05') === '5 mars 2026' && format_date_fr('2026-08-12') === '12 août 2026' && format_date_fr(null) === '' && format_date_fr('nimportequoi') === '');
t('media_img : nom invalide refusé', media_img(['stored_name' => '../../x.php'], 'a') === '' && media_img(['stored_name' => ''], 'a') === '');
t('media_img : alt échappé', str_contains(media_img(['stored_name' => 'a.jpg', 'alt_text' => '"><script>'], 'x'), 'alt="&quot;&gt;&lt;script&gt;"'));
t('media_src : nom invalide → null', media_src('a/b.jpg') === null && media_src('a b.jpg') === null && media_src('ok.jpg') === '/uploads/ok.jpg');

printf("\nRésultat : %d réussis, %d échecs\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
