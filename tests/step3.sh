#!/usr/bin/env bash
# Tests HTTP de l'étape 3 (pages publiques). Crée des données de test puis les supprime.
# Usage : bash tests/step3.sh [port | http://hote:port]
set -u
ARG="${1:-8089}"
cd "$(dirname "$0")/.."
if [[ "$ARG" == http* ]]; then BASE="${ARG%/}"; SRV=""
else BASE="http://127.0.0.1:$ARG"; php -S 127.0.0.1:$ARG -t . index.php >/tmp/phpserver3.log 2>&1 & SRV=$!; sleep 1; fi
cleanup() { php tests/step3_fixtures.php down >/dev/null 2>&1; [ -n "$SRV" ] && kill $SRV 2>/dev/null; }
trap cleanup EXIT

PASS=0; FAIL=0
check() { if [ "$2" = "ok" ]; then PASS=$((PASS+1)); printf "  [OK]   %s\n" "$1"; else FAIL=$((FAIL+1)); printf "  [ÉCHEC] %s — %s\n" "$1" "$2"; fi; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
has()    { grep -q -- "$1" <<<"$2"; }           # has "motif" "$page"
count()  { grep -o -- "$1" <<<"$2" | wc -l | tr -d ' '; }
is()     { [ "$2" = "$3" ] && check "$1" ok || check "$1" "attendu « $3 », obtenu « $2 »"; }
yes()    { has "$2" "$3" && check "$1" ok || check "$1" "absent : $2"; }
no()     { has "$2" "$3" && check "$1" "présent (ne devrait pas) : $2" || check "$1" ok; }

php tests/step3_fixtures.php up >/dev/null || { echo "échec création des données de test"; exit 1; }
TABLES_BEFORE=$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()" genie_solutions 2>/dev/null || echo "?")

echo "Pages éditoriales et services"
H=$(curl -s $BASE/); is "accueil 200" "$(status $BASE/)" 200
is "À propos 200" "$(status $BASE/a-propos)" 200
A=$(curl -s $BASE/a-propos); yes "À propos : titre issu de la base" "Génie Solutions, votre partenaire" "$A"; yes "À propos : valeurs listées" "Écoute du client" "$A"; yes "À propos : fil d'Ariane" 'aria-label="Fil d’Ariane"' "$A"
is "/services 200" "$(status $BASE/services)" 200
S=$(curl -s $BASE/services); is "3 cartes de service" "$(count 'class="card-gs service-card' "$S")" 3
SB=$(curl -s $BASE/services/broderie); is "/services/broderie 200" "$(status $BASE/services/broderie)" 200
yes "prestations affichées" "Broderie de logos" "$SB"; yes "mention « selon disponibilité »" "Selon disponibilité" "$SB"
is "6 prestations de broderie" "$(count '<li>Broderie\|<li>Personnalisation\|<li>Marquage\|<li>Commandes' "$SB")" 6
yes "CTA devis lié au service" 'href="/demande-de-devis?service=broderie"' "$SB"
yes "bloc « Notre approche »" "Le motif, les couleurs" "$SB"
is "service inconnu → 404" "$(status $BASE/services/inexistant)" 404
is "slug en majuscules → 404" "$(status $BASE/services/Broderie)" 404
is "slug avec injection → 404" "$(status "$BASE/services/x'%20OR%201=1--")" 404
is "service en brouillon → 404" "$(status $BASE/services/test-service-brouillon)" 404
no "service brouillon absent du menu" "SECRET" "$H"
F=$(curl -s $BASE/questions-frequentes); is "FAQ 200" "$(status $BASE/questions-frequentes)" 200
is "FAQ : 7 questions" "$(count '<details class="faq-item"' "$F")" 7
yes "FAQ : réponse présente dans le HTML (sans JS)" "Remplissez le formulaire de demande de devis" "$F"

echo "Pages légales : jamais de lien mort"
no "brouillon → pas de lien « Mentions légales » dans le pied" "Mentions légales" "$H"
is "mentions légales en brouillon → 404" "$(status $BASE/mentions-legales)" 404
is "politique de confidentialité en brouillon → 404" "$(status $BASE/politique-de-confidentialite)" 404
php tests/step3_fixtures.php legal-publish
L=$(curl -s $BASE/mentions-legales); is "publiée → 200" "$(status $BASE/mentions-legales)" 200
yes "contenu affiché" "Texte de test" "$L"; no "script retiré du contenu riche" "xss-legal" "$L"; no "javascript: retiré" "javascript:" "$L"
yes "lien apparaît dans le pied une fois publiée" 'href="/mentions-legales"' "$(curl -s $BASE/)"
php tests/step3_fixtures.php legal-draft

echo "Accueil : sections alimentées par la base"
H=$(curl -s $BASE/)
is "3 cartes de service" "$(count 'class="card-gs service-card' "$H")" 3
is "4 arguments « pourquoi nous choisir »" "$(count 'class="why-item' "$H")" 4
is "5 étapes de commande" "$(count 'class="step ' "$H")" 5
yes "section réalisations (projets publiés)" 'id="gallery-title"' "$H"
is "6 réalisations maximum à l'accueil" "$(count 'class="card-gs project-card' "$H")" 6
yes "témoignage approuvé affiché" "Témoignage validé de test" "$H"
no "témoignage EN ATTENTE jamais affiché" "EN ATTENTE secret" "$H"
no "témoignage REJETÉ jamais affiché" "REJETÉ secret" "$H"
yes "nom d'auteur échappé" "Directrice &lt;b&gt;test&lt;/b&gt;" "$H"
no "projet brouillon absent de l'accueil" "brouillon SECRET" "$H"
yes "appel à l'action final" "Vous avez un projet en tête" "$H"
yes "Catalogue présent dans le menu (produits publiés)" 'href="/catalogue"' "$H"

echo "Réalisations : filtre, pagination, détail"
P1=$(curl -s $BASE/realisations); is "/realisations 200" "$(status $BASE/realisations)" 200
is "page 1 : 12 cartes" "$(count 'class="card-gs project-card' "$P1")" 12
yes "pagination affichée" 'aria-label="Pagination"' "$P1"; yes "lien rel=next" 'rel="next"' "$P1"
yes "compteur de résultats (18)" "18 réalisations" "$P1"
P2=$(curl -s "$BASE/realisations?page=2"); is "page 2 : 6 cartes" "$(count 'class="card-gs project-card' "$P2")" 6
yes "page 2 : rel=prev" 'rel="prev"' "$P2"
is "page 999 → bornée, 200" "$(status "$BASE/realisations?page=999")" 200
is "page 999 affiche la dernière page (6 cartes)" "$(count 'class="card-gs project-card' "$(curl -s "$BASE/realisations?page=999")")" 6
is "page=abc → page 1 (12 cartes)" "$(count 'class="card-gs project-card' "$(curl -s "$BASE/realisations?page=abc")")" 12
is "page=-5 → page 1" "$(count 'class="card-gs project-card' "$(curl -s "$BASE/realisations?page=-5")")" 12
no "projet brouillon jamais listé" "brouillon SECRET" "$P1$P2"
C=$(curl -s "$BASE/realisations?categorie=broderie"); is "filtre catégorie : 12 cartes (page 1)" "$(count 'class="card-gs project-card' "$C")" 12
yes "filtre actif marqué (aria-current)" 'aria-current="true"' "$C"
yes "canonical conserve le filtre" 'rel="canonical" href="http://127.0.0.1:[0-9]*/realisations?categorie=broderie"' "$C"
E=$(curl -s "$BASE/realisations?categorie=test-vide"); yes "catégorie existante sans projet publié : état vide" "Aucune réalisation à afficher dans cette catégorie" "$E"; yes "…avec lien « toutes les réalisations »" "Voir toutes les réalisations" "$E"; no "…catégorie vide absente des filtres" "test-vide\"" "$P1"
Z=$(curl -s "$BASE/realisations?categorie=inconnue"); is "catégorie inconnue → 200 + état vide" "$(status "$BASE/realisations?categorie=inconnue")" 200; yes "…avec message" "Aucune réalisation" "$Z"
X=$(curl -s "$BASE/realisations?categorie=%3Cscript%3Ealert(1)%3C/script%3E"); no "paramètre piégé non réfléchi" "<script>alert(1)" "$X"; is "paramètre piégé → liste complète" "$(count 'class="card-gs project-card' "$X")" 12
Q=$(curl -s "$BASE/realisations?categorie=%27%20OR%201=1--&page=1;DROP%20TABLE%20users"); is "injection SQL tentée → 200" "$(status "$BASE/realisations?categorie=%27%20OR%201=1--")" 200
is "tables intactes après injection" "$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()" genie_solutions 2>/dev/null || echo '?')" "$TABLES_BEFORE"

D=$(curl -s $BASE/realisations/test-projet-riche); is "détail 200" "$(status $BASE/realisations/test-projet-riche)" 200
is "3 images dans la visionneuse (couverture + 2)" "$(count 'data-lightbox="projet"' "$D")" 3
yes "légende échappée" 'data-caption="Légende &lt;u&gt;un&lt;/u&gt;"' "$D"
yes "alt de la couverture" 'alt="Couverture de test"' "$D"
yes "dimensions width/height (évite le saut de mise en page)" 'width="640" height="480"' "$D"
yes "1re image chargée immédiatement, suivantes en lazy" 'loading="lazy"' "$D"
no "titre HTML non interprété" "<b>riche</b>" "$D"; yes "titre échappé" "Projet &lt;b&gt;riche&lt;/b&gt;" "$D"
no "script de la description supprimé" 'alert("xss-desc")' "$D"; no "onerror supprimé" "onerror" "$D"; no "javascript: supprimé" "javascript:" "$D"
yes "texte légitime conservé" "Texte légitime" "$D"
yes "date en français" "5 mars 2026" "$D"
yes "og:image absolue" 'property="og:image" content="http://127.0.0.1:[0-9]*/uploads/t3-cover.jpg"' "$D"
yes "réalisations liées" 'id="related-title"' "$D"
is "image servie (200)" "$(status $BASE/uploads/t3-img1.jpg)" 200
curl -sI $BASE/uploads/t3-img1.jpg | grep -qi 'content-type: image/jpeg' && check "type MIME image/jpeg" ok || check "type MIME image/jpeg" "incorrect"
is "projet brouillon → 404" "$(status $BASE/realisations/test-brouillon)" 404
is "projet inconnu → 404" "$(status $BASE/realisations/nexiste-pas)" 404

echo "Catalogue et prix"
K=$(curl -s $BASE/catalogue); is "/catalogue 200" "$(status $BASE/catalogue)" 200
is "2 produits publiés listés" "$(count 'class="card-gs product-card' "$K")" 2
no "produit brouillon absent" "brouillon SECRET" "$K"
no "aucun prix affiché sans devise configurée" "1 500" "$K"; yes "« Sur devis » sans devise" "Sur devis" "$K"
no "nom de produit non interprété" "<b>test</b>" "$K"
php tests/step3_fixtures.php currency USD
K=$(curl -s $BASE/catalogue)
if grep -Eq 'à partir de.{1,4}1.{1,4}500.{1,4}USD' <<<"$K"; then check "prix affiché avec devise configurée" ok; else check "prix affiché avec devise configurée" "absent"; fi
PR=$(curl -s $BASE/catalogue/test-produit); is "fiche produit 200" "$(status $BASE/catalogue/test-produit)" 200
no "script de la description échappé (pas exécutable)" "<script>alert(9)" "$PR"; yes "…affiché comme texte" "&lt;script&gt;alert(9)" "$PR"
yes "retour à la ligne conservé (nl2br)" "<br" "$PR"
yes "CTA devis lié au produit" 'href="/demande-de-devis?produit=test-produit"' "$PR"
is "produit brouillon → 404" "$(status $BASE/catalogue/test-produit-brouillon)" 404
is "filtre service du catalogue" "$(count 'class="card-gs product-card' "$(curl -s "$BASE/catalogue?service=couture-industrielle")")" 0
yes "filtre sans résultat : état vide" "Aucun produit" "$(curl -s "$BASE/catalogue?service=couture-industrielle")"
php tests/step3_fixtures.php currency ""

echo "Méthodes et en-têtes"
is "HEAD /realisations → 200" "$(status -I $BASE/realisations)" 200
is "POST /realisations → 405" "$(status -X POST $BASE/realisations)" 405
is "POST /services/broderie → 405" "$(status -X POST $BASE/services/broderie)" 405
curl -sI $BASE/realisations | grep -qi '^Content-Security-Policy' && check "CSP sur les nouvelles pages" ok || check "CSP sur les nouvelles pages" "absente"
for u in / /services /services/broderie /realisations /realisations/test-projet-riche /catalogue /catalogue/test-produit /questions-frequentes /a-propos; do
  b=$(curl -s "$BASE$u"); n=$(grep -Eio '<script[^>]*>' <<<"$b" | grep -vc 'src='); s=$(grep -ci 'style="' <<<"$b")
  [ "$n" = 0 ] && [ "$s" = 0 ] && check "CSP respectée (aucun script/style en ligne) : $u" ok || check "CSP : $u" "script inline=$n style=$s"
  [ "$(grep -o '<h1' <<<"$b" | wc -l | tr -d ' ')" = 1 ] && check "un seul H1 : $u" ok || check "un seul H1 : $u" "≠ 1"
done

echo; echo "Résultat : $PASS réussis, $FAIL échecs"; [ "$FAIL" = 0 ]
