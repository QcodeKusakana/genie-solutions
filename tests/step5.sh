#!/usr/bin/env bash
# Tests HTTP des formulaires (contact + devis). Crée et supprime ses propres données de test.
# Usage : bash tests/step5.sh            → lance 3 serveurs PHP (ports 8091-8093) + un faux SMTP (2525)
#         bash tests/step5.sh http://h:p → teste un serveur déjà lancé (Apache) ; les scénarios exigeant un serveur dédié sont ignorés.
#           Ce serveur doit avoir : FORM_MIN_SECONDS=0, MAIL_HOST=127.0.0.1, MAIL_PORT=2525, MAIL_ENCRYPTION=none
# ATTENTION : utilise le compte root MariaDB local (socket) pour inspecter la base et simuler une panne. Jamais en production.
set -u
cd "$(dirname "$0")/.."
ARG="${1:-}"; EXT=0; [[ "$ARG" == http* ]] && EXT=1
PASS=0; FAIL=0; SKIP=0
ok(){ PASS=$((PASS+1)); echo "  [OK]   $1"; }
ko(){ FAIL=$((FAIL+1)); echo "  [ÉCHEC] $1 — $2"; }
sk(){ SKIP=$((SKIP+1)); echo "  [SKIP] $1"; }
q(){ mariadb --default-character-set=utf8mb4 -N -e "$1" genie_solutions; }
is(){ [ "$2" = "$3" ] && ok "$1" || ko "$1" "attendu « $3 », obtenu « $2 »"; }
has(){ grep -q -- "$2" <<<"$3" && ok "$1" || ko "$1" "absent : $2"; }
hasnt(){ grep -q -- "$2" <<<"$3" && ko "$1" "présent (ne devrait pas) : $2" || ok "$1"; }

T=/tmp/t5; rm -rf $T; mkdir -p $T; BODY=$T/body.html; HDR=$T/hdr.txt; PIDS=()
cleanup(){ for p in "${PIDS[@]:-}"; do [ -n "$p" ] && kill "$p" 2>/dev/null; done
  q "UPDATE site_settings SET setting_value='' WHERE setting_key IN ('mail_from_address','mail_notify_address')" 2>/dev/null
  q "RENAME TABLE quote_requests_x TO quote_requests" 2>/dev/null
  q "DELETE FROM quote_requests WHERE email LIKE '%@t5.invalid'" 2>/dev/null; q "DELETE FROM contact_messages WHERE email LIKE '%@t5.invalid'" 2>/dev/null
  q "DELETE FROM rate_limits" 2>/dev/null; php tests/step3_fixtures.php down >/dev/null 2>&1
  rm -f storage/private/quotes/*.png storage/private/quotes/*.jpg storage/private/quotes/*.pdf 2>/dev/null; }
trap cleanup EXIT

# ---------- fichiers de test
php -r '
$im=imagecreatetruecolor(300,200); imagefill($im,0,0,imagecolorallocate($im,18,52,90)); imagepng($im,"'$T'/logo.png"); imagejpeg($im,"'$T'/photo.jpg");
copy("'$T'/photo.jpg","'$T'/polyglot.jpg"); file_put_contents("'$T'/polyglot.jpg","<?php echo \"POLYGLOT-EXEC\"; ?>",FILE_APPEND);
copy("'$T'/logo.png","'$T'/png-as.jpg");
$big=imagecreatetruecolor(1400,1400); for($y=0;$y<1400;$y++)for($x=0;$x<1400;$x++)imagesetpixel($big,$x,$y,imagecolorallocate($big,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255))); imagepng($big,"'$T'/big.png",0);'
printf '%%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%%%EOF\n' > $T/doc.pdf
printf '<?php echo "PWNED"; ?>' > $T/shell.php; cp $T/shell.php $T/shell.jpg
printf '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script></svg>' > $T/x.svg
printf '<html><script>alert(3)</script></html>' > $T/x.html; : > $T/empty.png; printf 'texte simple' > $T/fake.pdf
head -c 9500000 /dev/urandom > $T/huge.png
printf 'Jean\xff\xfeTest' > $T/badutf8.txt

# ---------- serveurs
q "UPDATE site_settings SET setting_value='site@t5.invalid' WHERE setting_key='mail_from_address'"
q "UPDATE site_settings SET setting_value='equipe@t5.invalid' WHERE setting_key='mail_notify_address'"
php tests/step3_fixtures.php up >/dev/null
SM=/tmp/smtp5.jsonl; rm -f $SM
python3 tests/fake_smtp.py --port 2525 --out $SM >/tmp/fs5.log 2>&1 & PIDS+=($!)
if [ $EXT = 0 ]; then
  env FORM_MIN_SECONDS=0 MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_ENCRYPTION=none php -S 127.0.0.1:8091 -t . index.php >/tmp/ps91.log 2>&1 & PIDS+=($!)
  env MAIL_HOST= php -S 127.0.0.1:8092 -t . index.php >/tmp/ps92.log 2>&1 & PIDS+=($!)
  env FORM_MIN_SECONDS=0 MAIL_HOST=127.0.0.1 MAIL_PORT=2599 MAIL_ENCRYPTION=none php -S 127.0.0.1:8093 -t . index.php >/tmp/ps93.log 2>&1 & PIDS+=($!)
  sleep 1.5; BASE=http://127.0.0.1:8091
else BASE="${ARG%/}"; fi
LOG=storage/logs/php-error.log; : >> $LOG; LOG0=$(wc -l < $LOG)

# ---------- utilitaires HTTP
token(){ curl -s -c "$1" -b "$1" "$2$3" | sed -n 's/.*name="_csrf" value="\([0-9a-f]*\)".*/\1/p' | head -1; }   # token JAR BASE PATH
post(){ local jar=$1 base=$2 path=$3; shift 3; [ "${NOCLEAR:-0}" = 1 ] || q "DELETE FROM rate_limits"
  CODE=$(curl -s -b "$jar" -c "$jar" -o $BODY -D $HDR -w '%{http_code}' -X POST "$base$path" "$@"); }
loc(){ grep -i '^location:' $HDR | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }
FUT=$(date -u -d '+10 days' +%F)
qv(){ QV=(-F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=$1@t5.invalid" -F "service=${2:-broderie}" -F "description=Logo à broder sur 40 polos bleu marine" -F "quantity=40" -F "delivery_mode=Livraison" -F "desired_date=$FUT" -F "consent=1"); }

echo "A. Affichage des formulaires"
J=$T/ja; curl -s -c $J -D $HDR -o $BODY $BASE/demande-de-devis; P=$(cat $BODY)
is "GET /demande-de-devis → 200" "$(curl -s -o /dev/null -w '%{http_code}' $BASE/demande-de-devis)" 200
has "jeton CSRF présent" 'name="_csrf" value="[0-9a-f]\{64\}"' "$P"
has "champ piège (honeypot) présent et masqué aux lecteurs d'écran" 'class="hp-field" aria-hidden="true"' "$P"
is "3 blocs de champs conditionnels" "$(grep -o 'data-profile-fields=' <<<"$P" | wc -l | tr -d ' ')" 3
has "options de service avec profil" 'value="broderie" data-profile="embroidery"' "$P"
has "enctype multipart (pièce jointe)" 'enctype="multipart/form-data"' "$P"
has "champs obligatoires marqués (required)" 'id="f-email" name="email" required' "$P"
has "type=email / tel / date" 'type="email"' "$P"; has "…tel" 'type="tel"' "$P"; has "…date" 'type="date"' "$P"
has "taille maximale annoncée" 'maximum' "$P"
grep -qi 'set-cookie: gs_session=.*httponly' $HDR && ok "cookie de session HttpOnly" || ko "cookie HttpOnly" "absent"
grep -qi 'set-cookie:.*samesite=lax' $HDR && ok "cookie SameSite=Lax" || ko "SameSite" "absent"
grep -qi '^cache-control:.*no-store' $HDR && ok "page de formulaire non mise en cache (no-store)" || ko "Cache-Control" "$(grep -i '^cache-control' $HDR)"
is "aucun script/style en ligne (CSP)" "$(grep -Eio '<script[^>]*>' <<<"$P" | grep -vc 'src=')$(grep -ci 'style="' <<<"$P")" "00"
P=$(curl -s "$BASE/demande-de-devis?service=broderie"); has "?service= présélectionne" 'value="broderie" data-profile="embroidery" selected' "$P"
has "blocs des autres services masqués" 'data-profile-fields="print" hidden disabled' "$P"
P=$(curl -s "$BASE/demande-de-devis?service=inconnu"); hasnt "?service inconnu ignoré" ' selected>' "$(grep 'data-profile=' <<<"$P")"
P=$(curl -s "$BASE/demande-de-devis?service=%3Cscript%3E"); hasnt "?service piégé non réfléchi" '<script>' "$P"
P=$(curl -s "$BASE/demande-de-devis?produit=test-produit"); has "?produit= → champ caché" 'name="product" value="test-produit"' "$P"; has "…service déduit du produit" 'value="broderie" data-profile="embroidery" selected' "$P"
P=$(curl -s "$BASE/demande-de-devis?produit=nexiste-pas"); hasnt "produit inconnu ignoré" 'name="product"' "$P"
P=$(curl -s "$BASE/demande-de-devis?produit=test-produit-brouillon"); hasnt "produit en brouillon ignoré" 'name="product"' "$P"
is "GET /contact → 200" "$(curl -s -o /dev/null -w '%{http_code}' $BASE/contact)" 200
is "HEAD /demande-de-devis → 200" "$(curl -s -o /dev/null -w '%{http_code}' -I $BASE/demande-de-devis)" 200
is "PUT /contact → 405" "$(curl -s -o /dev/null -w '%{http_code}' -X PUT $BASE/contact)" 405
is "/demande-de-devis/merci sans session → 303 vers le formulaire" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' $BASE/demande-de-devis/merci | sed 's#http://[^/]*##')" "303 /demande-de-devis"

echo "B. CSRF"
J=$T/jb; TOK=$(token $J $BASE /demande-de-devis); qv csrf
post $J $BASE /demande-de-devis "${QV[@]}"; is "POST sans jeton → 403" "$CODE" 403
post $J $BASE /demande-de-devis -F "_csrf=$(printf 'a%.0s' {1..64})" "${QV[@]}"; is "POST avec faux jeton → 403" "$CODE" 403
post $J $BASE /demande-de-devis -F "_csrf=${TOK:0:63}" "${QV[@]}"; is "POST avec jeton tronqué → 403" "$CODE" 403
post $T/jamais-vu $BASE /demande-de-devis -F "_csrf=$TOK" "${QV[@]}"; is "POST avec un jeton valide mais SANS la session correspondante → 403" "$CODE" 403
post $J $BASE /demande-de-devis -F "_csrf[]=x" "${QV[@]}"; is "jeton envoyé sous forme de tableau → 403" "$CODE" 403
is "aucune demande enregistrée par ces essais" "$(q "SELECT COUNT(*) FROM quote_requests WHERE email='csrf@t5.invalid'")" 0
has "page 403 sans fuite technique" "Accès refusé" "$(cat $BODY)"
J=$T/jb2; TOK=$(token $J $BASE /contact); post $J $BASE /contact -F "full_name=X Y" ; is "contact : POST sans jeton → 403" "$CODE" 403

echo "C. Demande de devis valide (service broderie, champs conditionnels, e-mail)"
J=$T/jc; TOK=$(token $J $BASE /demande-de-devis); qv ok1; rm -f $SM
N0=$(q "SELECT COUNT(*) FROM quote_requests")
post $J $BASE /demande-de-devis -F "_csrf=$TOK" "${QV[@]}" -F "d_garment_type=Polo" -F "d_logo_size=8 x 5 cm" -F "d_fabric=TISSU-LEURRE" -F "d_print_type=Offset" -F "website="
is "POST valide → 303" "$CODE" 303; is "redirection vers la page de confirmation" "$(loc | sed 's#http://[^/]*##')" "/demande-de-devis/merci"
THX=$(curl -s -b $J -c $J $BASE/demande-de-devis/merci); REF=$(grep -o 'GS-[0-9]\{4\}-[A-Z2-9]\{6\}' <<<"$THX" | head -1)
[ -n "$REF" ] && ok "référence affichée au format GS-AAMM-XXXXXX : $REF" || ko "référence" "absente"
has "message de confirmation (texte fourni)" "Votre demande a bien été enregistrée" "$THX"
has "page de confirmation en noindex" 'content="noindex,nofollow"' "$THX"
is "refresh de la page de confirmation → même référence" "$(curl -s -b $J $BASE/demande-de-devis/merci | grep -o 'GS-[0-9]\{4\}-[A-Z2-9]\{6\}' | head -1)" "$REF"
is "1 ligne ajoutée en base" "$(( $(q "SELECT COUNT(*) FROM quote_requests") - N0 ))" 1
Q="$(q "SELECT id, status, service_id=(SELECT id FROM services WHERE slug='broderie'), CHAR_LENGTH(ip_hash), consent_at IS NOT NULL, delivery_mode, quantity, desired_date FROM quote_requests WHERE reference='$REF'")"
[ "$(cut -f2- <<<"$Q")" = "$(printf 'nouvelle\t1\t64\t1\tLivraison\t40\t%s' $FUT)" ] && ok "statut « nouvelle », bon service, IP hachée (64), consentement horodaté, champs enregistrés" || ko "contenu de la ligne" "$Q"
QID=$(cut -f1 <<<"$Q")
is "IP brute jamais stockée" "$(q "SELECT COUNT(*) FROM quote_requests WHERE ip_hash LIKE '%127.0.0.1%'")" 0
is "détails du profil broderie enregistrés (2)" "$(q "SELECT GROUP_CONCAT(field_key ORDER BY field_key) FROM quote_request_details WHERE quote_id=$QID")" "garment_type,logo_size"
is "champs d'un AUTRE profil ignorés (leurre textile/impression)" "$(q "SELECT COUNT(*) FROM quote_request_details WHERE quote_id=$QID AND field_key IN ('fabric','print_type')")" 0
is "historique de statut initial" "$(q "SELECT CONCAT(IFNULL(old_status,'∅'),'→',new_status) FROM quote_status_history WHERE quote_id=$QID")" "∅→nouvelle"
is "journal d'activité créé" "$(q "SELECT COUNT(*) FROM activity_logs WHERE action='quote.created' AND entity_id=$QID")" 1
is "notified_at renseigné (SMTP a confirmé)" "$(q "SELECT notified_at IS NOT NULL FROM quote_requests WHERE id=$QID")" 1
if [ $EXT = 0 ] || [ -s $SM ]; then python3 - "$REF" <<'PY' && ok "e-mail reçu par le serveur : référence dans le sujet, destinataire équipe, Reply-To = client" || ko "e-mail de notification" "contenu inattendu"
import json, sys, email, email.policy
m = json.loads(open('/tmp/smtp5.jsonl').read().splitlines()[-1])
msg = email.message_from_string(m['data'], policy=email.policy.default)
assert sys.argv[1] in msg['Subject'], msg['Subject']
assert 'equipe@t5.invalid' in m['to'][0]
assert 'ok1@t5.invalid' in msg['Reply-To']
b = msg.get_content(); assert 'Jean Test' in b and 'garment type : Polo' in b.lower().replace('Garment','garment') or 'Garment type : Polo' in b, b
assert 'TISSU-LEURRE' not in b
PY
fi
echo "   Rejeu et jeton à usage unique"
post $J $BASE /demande-de-devis -F "_csrf=$TOK" "${QV[@]}"; is "rejeu du même formulaire (jeton consommé) → 403" "$CODE" 403
is "aucun doublon créé" "$(( $(q "SELECT COUNT(*) FROM quote_requests") - N0 ))" 1

echo "D. Validation côté serveur (aucune ligne ne doit être créée)"
J=$T/jd; TOK=$(token $J $BASE /demande-de-devis); N0=$(q "SELECT COUNT(*) FROM quote_requests")
bad(){ local name=$1 expect=$2; shift 2; post $J $BASE /demande-de-devis -F "_csrf=$TOK" "$@"; [ "$CODE" = 422 ] && grep -q -- "$expect" $BODY && ok "$name" || ko "$name" "HTTP $CODE ; message « $expect » ?"; }
base=(-F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1")
bad "tout vide → erreurs multiples" "Veuillez corriger les" -F "x=1"
bad "nom trop court" "Indiquez votre nom complet" -F "full_name=A" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
bad "nom sans lettre (12345)" "Indiquez votre nom complet" -F "full_name=12345" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
bad "nom de 121 caractères" "Indiquez votre nom complet" -F "full_name=$(printf 'a%.0s' {1..121})" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
for em in "pas-un-email" "a@b" "@t5.invalid" "a b@t5.invalid" "a@@t5.invalid"; do
  bad "e-mail invalide « $em »" "adresse e-mail valide" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" --form-string "email=$em" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"; done
bad "e-mail avec CRLF (injection d'en-tête)" "adresse e-mail valide" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=$(printf 'a@t5.invalid\r\nBcc: x@t5.invalid')" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
for ph in "abc" "12" "<script>" "+243 81 234 5678 poste 12 bis bis bis bis"; do
  bad "téléphone invalide « $ph »" "téléphone valide" -F "full_name=Jean Test" --form-string "phone=$ph" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"; done
bad "service absent" "Choisissez le service" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "description=Une description suffisante" -F "consent=1"
bad "service inconnu" "Choisissez le service" "${base[@]}" -F "service=nexiste-pas"
bad "service en brouillon refusé" "Choisissez le service" "${base[@]}" -F "service=test-service-brouillon"
bad "service par injection SQL" "Choisissez le service" "${base[@]}" -F "service=x' OR '1'='1"
bad "description trop courte" "10 caractères minimum" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=court" -F "consent=1"
bad "description de 5001 caractères" "trop longue" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=$(printf 'a%.0s' {1..5001})" -F "consent=1"
bad "quantité de 61 caractères" "quantité est trop longue" "${base[@]}" -F "quantity=$(printf '9%.0s' {1..61})"
for d in "2020-01-01" "2026-02-30" "abc" "2035-01-01" "10/12/2026"; do bad "date invalide « $d »" "date valide" "${base[@]}" -F "desired_date=$d"; done
bad "mode de livraison hors liste" "mode proposé" "${base[@]}" -F "delivery_mode=Drone"
bad "consentement absent" "accepter le traitement" -F "full_name=Jean Test" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante"
bad "champ de profil : liste déroulante hors options (imprimerie)" "valeur proposée" "${base[@]}" -F "service=imprimerie-digitale-offset" -F "d_print_type=PirateOffset"
bad "champ de profil trop long" "Texte trop long" "${base[@]}" -F "d_logo_size=$(printf 'z%.0s' {1..81})"
bad "champ sous forme de tableau (full_name[]) rejeté" "nom complet" -F "full_name[]=Jean" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
bad "octets UTF-8 invalides rejetés" "nom complet" -F "full_name=<$T/badutf8.txt" -F "phone=+243 81 234 5678" -F "email=v@t5.invalid" -F "service=broderie" -F "description=Une description suffisante" -F "consent=1"
is "AUCUNE ligne créée par les $((PASS)) essais invalides ci-dessus" "$(( $(q "SELECT COUNT(*) FROM quote_requests") - N0 ))" 0
echo "   Ré-affichage sûr après erreur"
post $J $BASE /demande-de-devis -F "_csrf=$TOK" -F 'full_name="><script>alert(1)</script>' -F "email=x"; B=$(cat $BODY)
hasnt "saisie piégée NON exécutable dans la page d'erreur" '<script>alert(1)' "$B"
has "…ré-affichée échappée dans le champ" 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"' "$B"
has "résumé d'erreurs accessible (role=alert, focusable)" 'id="form-errors" role="alert" tabindex="-1"' "$B"
has "liens du résumé vers les champs" 'href="#f-email"' "$B"
has "champ en erreur : aria-invalid + aria-describedby" 'aria-invalid="true" aria-describedby="f-email-err"' "$B"
hasnt "aucune fuite technique (SQLSTATE, chemins, pile d'appels)" 'SQLSTATE\|/home/claude\|Stack trace\|PDOException' "$B"
grep -qi '^content-security-policy' $HDR && ok "CSP aussi sur la réponse d'erreur" || ko "CSP sur 422" "absente"
printf 'Ligne\x07avec\x00contr\x1bôle de description' > $T/ctl.txt
post $J $BASE /demande-de-devis -F "_csrf=$TOK" "${base[@]}" -F "description=<$T/ctl.txt" -F "email=ctl@t5.invalid"
is "caractères de contrôle nettoyés (le formulaire valide passe)" "$CODE" 303
is "…et absents de la base (BEL, ESC, NUL)" "$(q "SELECT INSTR(description, CHAR(7))+INSTR(description, CHAR(27))+INSTR(description, CHAR(0)) FROM quote_requests WHERE email='ctl@t5.invalid'")" 0
is "…le texte légitime est conservé" "$(q "SELECT description FROM quote_requests WHERE email='ctl@t5.invalid'")" "Ligneaveccontrôle de description"

echo "E. Pièces jointes"
sd=storage/private/quotes; mkdir -p $sd
att(){ local name=$1 file=$2 expect=$3 type=${4:-application/octet-stream} fname=${5:-$(basename $2)}; local J2=$T/je_$RANDOM; local TK=$(token $J2 $BASE /demande-de-devis); qv "att$RANDOM"; local F0=$(ls $sd | wc -l)
  post $J2 $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "attachment=@$file;type=$type;filename=$fname"
  if [ "$expect" = ok ]; then [ "$CODE" = 303 ] && ok "$name → accepté" || ko "$name" "attendu 303, obtenu $CODE ($(grep -o 'alert-link[^<]*<[^>]*>[^<]*' $BODY | head -1))"
  else [ "$CODE" = 422 ] && grep -q -- "$expect" $BODY && ok "$name → refusé" || ko "$name" "HTTP $CODE ; « $expect » ?"
       [ "$(ls $sd | wc -l)" = "$F0" ] && ok "   aucun fichier conservé" || ko "   fichier orphelin après refus" "$(ls $sd | wc -l) vs $F0"; fi; }
att "PNG valide" $T/logo.png ok image/png
att "JPEG valide" $T/photo.jpg ok image/jpeg
att "PDF valide" $T/doc.pdf ok application/pdf
att "script .php" $T/shell.php "Format non accepté" application/x-php
att "script PHP renommé .jpg (type annoncé image/jpeg)" $T/shell.jpg "Format non accepté" image/jpeg
att "SVG (vecteur XSS)" $T/x.svg "Format non accepté" image/svg+xml
att "HTML" $T/x.html "Format non accepté" text/html
att "PNG renommé en .jpg (extension ≠ contenu)" $T/png-as.jpg "ne correspond pas" image/jpeg
att "PDF factice (texte simple)" $T/fake.pdf "Format non accepté" application/pdf
att "fichier vide" $T/empty.png "vide\|Format non accepté\|n’a pas pu" image/png
att "image trop volumineuse (> limite)" $T/big.png "trop volumineux" image/png
att "double extension x.php.jpg avec vrai JPEG" $T/photo.jpg ok image/jpeg "x.php.jpg"
att "nom piégé (../../etc/passwd.png)" $T/logo.png ok image/png '../../etc/passwd.png'
att "polyglotte (vrai JPEG + code PHP ajouté)" $T/polyglot.jpg ok image/jpeg
echo "   Stockage privé"
SN=$(q "SELECT stored_name FROM quote_attachments ORDER BY id DESC LIMIT 1"); OR=$(q "SELECT original_name FROM quote_attachments ORDER BY id DESC LIMIT 1")
[[ "$SN" =~ ^[0-9a-f]{32}\.jpg$ ]] && ok "nom stocké aléatoire (32 hex) + extension déduite du CONTENU : $SN" || ko "nom stocké" "$SN"
[ -f "$sd/$SN" ] && ok "fichier présent dans storage/private/quotes (hors URL)" || ko "fichier stocké" "absent"
is "permissions 640" "$(stat -c %a $sd/$SN)" 640
case "$(curl -s -o /dev/null -w '%{http_code}' $BASE/storage/private/quotes/$SN)" in 403|404) ok "fichier inaccessible en HTTP";; *) ko "fichier accessible en HTTP" "";; esac
case "$(curl -s -o /dev/null -w '%{http_code}' $BASE/uploads/$SN)" in 403|404) ok "absent aussi de /uploads/";; *) ko "/uploads/$SN" "servi";; esac
hasnt "code PHP du polyglotte jamais exécuté via HTTP" 'POLYGLOT-EXEC' "$(curl -s $BASE/storage/private/quotes/$SN)"
is "nom d'origine « ../../etc/passwd.png » réduit à un nom sûr" "$(q "SELECT original_name FROM quote_attachments WHERE original_name LIKE '%passwd.png' LIMIT 1")" "passwd.png"
is "types MIME enregistrés = ceux détectés" "$(q "SELECT GROUP_CONCAT(DISTINCT mime_type ORDER BY mime_type) FROM quote_attachments")" "application/pdf,image/jpeg,image/png"
echo "   Requête dépassant post_max_size"
J=$T/jf; TK=$(token $J $BASE /demande-de-devis); qv huge
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "attachment=@$T/huge.png;type=image/png"
[ "$CODE" = 413 ] && ok "9,5 Mo → 413" || ko "requête trop grosse" "HTTP $CODE"
has "…message clair, sans erreur technique" "dépassent la taille autorisée" "$(cat $BODY)"

echo "F. Anti-robots, débit"
J=$T/jg; TK=$(token $J $BASE /demande-de-devis); qv bot; N0=$(q "SELECT COUNT(*) FROM quote_requests")
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "website=http://spam.example"
is "honeypot rempli → succès simulé (303)" "$CODE" 303
THX=$(curl -s -b $J $BASE/demande-de-devis/merci); hasnt "…sans référence affichée" 'GS-[0-9]' "$THX"
is "…et AUCUNE ligne enregistrée" "$(( $(q "SELECT COUNT(*) FROM quote_requests") - N0 ))" 0
J=$T/jh; TK=$(token $J $BASE /contact); post $J $BASE /contact -F "_csrf=$TK" -F "full_name=Bot Bot" -F "email=bot@t5.invalid" -F "subject=Spam spam" -F "message=Message de robot 1234" -F "consent=1" -F "website=x"
is "contact : honeypot → 303, rien enregistré" "$CODE$(q "SELECT COUNT(*) FROM contact_messages WHERE email='bot@t5.invalid'")" 3030
echo "   Session sans affichage préalable du formulaire (délai non initialisé)"
J=$T/ji; TK=$(token $J $BASE /contact); qv exp; post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}"
[ "$CODE" = 422 ] && has "formulaire jamais affiché → refusé (expiré)" "a expiré" "$(cat $BODY)" || ko "formulaire non affiché" "HTTP $CODE"
echo "   Limitation de débit (8 tentatives / heure / IP)"
J=$T/jj; TK=$(token $J $BASE /demande-de-devis); q "DELETE FROM rate_limits"; CODES=""
for i in $(seq 1 10); do NOCLEAR=1 post $J $BASE /demande-de-devis -F "_csrf=$TK" -F "x=$i"; CODES="$CODES $CODE"; done
is "8 premières tentatives acceptées (422), puis 429" "$(echo $CODES)" "422 422 422 422 422 422 422 422 429 429"
has "page 429 claire" "Trop de requêtes" "$(cat $BODY)"; grep -qi '^retry-after' $HDR && ok "en-tête Retry-After" || ko "Retry-After" "absent"
J=$T/jk; TK=$(token $J $BASE /contact); NOCLEAR=1 post $J $BASE /contact -F "_csrf=$TK" -F "x=1"; is "le compteur du CONTACT est indépendant (pas de 429)" "$CODE" 422
is "seuls des hachages d'IP sont stockés" "$(q "SELECT COUNT(*) FROM rate_limits WHERE ip_hash LIKE '%127.0.0.1%'")" 0
q "DELETE FROM rate_limits"

echo "G. Produit lié"
J=$T/jl; TK=$(token $J $BASE "/demande-de-devis?produit=test-produit"); qv prod
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "product=test-produit" -F "service=broderie"; is "devis avec produit → 303" "$CODE" 303
is "product_id renseigné" "$(q "SELECT p.slug FROM quote_requests r JOIN products p ON p.id=r.product_id WHERE r.email='prod@t5.invalid'")" "test-produit"
J=$T/jm; TK=$(token $J $BASE /demande-de-devis); qv prod2
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "product=test-produit" -F "service=imprimerie-digitale-offset"; is "produit d'un autre service → demande acceptée" "$CODE" 303
is "…mais le produit est ignoré (product_id NULL)" "$(q "SELECT product_id IS NULL FROM quote_requests WHERE email='prod2@t5.invalid'")" 1

echo "H. Formulaire de contact"
J=$T/jn; TK=$(token $J $BASE /contact); rm -f $SM
post $J $BASE /contact -F "_csrf=$TK" -F "full_name=Marie Contact" -F "email=contact@t5.invalid" -F "phone=+243 99 000 0000" -F "subject=Question sur les délais" -F "message=Bonjour, quels sont vos délais pour 200 flyers ?" -F "consent=1"
is "contact valide → 303 vers /contact" "$CODE $(loc | sed 's#http://[^/]*##')" "303 /contact"
P=$(curl -s -b $J -c $J $BASE/contact); has "message de succès (flash) affiché" "Votre message a bien été envoyé" "$P"
is "message enregistré" "$(q "SELECT CONCAT(subject,'|',status) FROM contact_messages WHERE email='contact@t5.invalid'")" "Question sur les délais|new"
P=$(curl -s -b $J -c $J $BASE/contact); hasnt "message flash affiché UNE seule fois" "Votre message a bien été envoyé" "$P"
if [ $EXT = 0 ]; then python3 - <<'PY' && ok "e-mail de notification reçu (objet, expéditeur en Reply-To)" || ko "e-mail contact" "inattendu"
import json, email, email.policy
m = json.loads(open('/tmp/smtp5.jsonl').read().splitlines()[-1])
msg = email.message_from_string(m['data'], policy=email.policy.default)
assert msg['Subject'] == 'Nouveau message : Question sur les délais', msg['Subject']
assert 'contact@t5.invalid' in msg['Reply-To']
PY
fi
post $J $BASE /contact -F "_csrf=$(token $J $BASE /contact)" -F "full_name=A" -F "email=nope" -F "subject=x" -F "message=court"
[ "$CODE" = 422 ] && ok "contact invalide → 422" || ko "contact invalide" "$CODE"
has "…erreurs listées (nom, e-mail, objet, message, consentement)" 'Veuillez corriger les 5 champs' "$(cat $BODY)"
J=$T/jo; TK=$(token $J $BASE /contact); rm -f $SM
post $J $BASE /contact -F "_csrf=$TK" -F "full_name=Inj Ection" -F "email=inj@t5.invalid" -F "subject=$(printf 'Objet\r\nBcc: pirate@t5.invalid')" -F "message=Message suffisamment long ici" -F "consent=1"
is "objet avec CRLF → accepté mais nettoyé" "$CODE" 303
is "…enregistré sur une seule ligne" "$(q "SELECT subject FROM contact_messages WHERE email='inj@t5.invalid'")" "Objet Bcc: pirate@t5.invalid"
if [ $EXT = 0 ]; then python3 - <<'PY' && ok "…et aucun en-tête Bcc dans l'e-mail réellement envoyé" || ko "injection e-mail contact" "Bcc présent"
import json
m = json.loads(open('/tmp/smtp5.jsonl').read().splitlines()[-1])
head = m['data'].split('\r\n\r\n')[0]
assert not any(l.lower().startswith('bcc:') for l in head.split('\r\n')) and len(m['to']) == 1
PY
fi

echo "I. Pannes"
if [ $EXT = 0 ]; then
  echo "   Serveur SMTP injoignable (port fermé)"
  J=$T/jp; TK=$(token $J http://127.0.0.1:8093 /demande-de-devis); qv smtpdown
  post $J http://127.0.0.1:8093 /demande-de-devis -F "_csrf=$TK" "${QV[@]}"
  is "demande acceptée malgré l'échec d'e-mail → 303" "$CODE" 303
  is "ligne enregistrée" "$(q "SELECT COUNT(*) FROM quote_requests WHERE email='smtpdown@t5.invalid'")" 1
  is "notified_at reste NULL (envoi non confirmé)" "$(q "SELECT notified_at IS NULL FROM quote_requests WHERE email='smtpdown@t5.invalid'")" 1
  grep -q 'mail_send: smtp_connect_failed' $LOG && ok "échec journalisé (code technique, sans secret)" || ko "journalisation de l'échec SMTP" "absent du journal"
  echo "   SMTP non configuré (aucun e-mail tenté)"
  J=$T/jq; TK=$(token $J http://127.0.0.1:8092 /demande-de-devis); qv nosmtp
  post $J http://127.0.0.1:8092 /demande-de-devis -F "_csrf=$TK" "${QV[@]}"; sleep 0; 
  is "sans SMTP et sans délai → refus « trop rapide » (422)" "$CODE" 422
  has "…message sans détail technique" "envoyé trop rapidement" "$(cat $BODY)"
  is "…aucune ligne créée" "$(q "SELECT COUNT(*) FROM quote_requests WHERE email='nosmtp@t5.invalid'")" 0
  sleep 3.2
  post $J http://127.0.0.1:8092 /demande-de-devis -F "_csrf=$TK" "${QV[@]}"
  is "après 3 s depuis l'affichage, le MÊME formulaire est accepté → 303" "$CODE" 303
  is "…notified_at NULL (pas de SMTP)" "$(q "SELECT notified_at IS NULL FROM quote_requests WHERE email='nosmtp@t5.invalid'")" 1
else sk "SMTP injoignable / SMTP absent / délai minimal : nécessitent des serveurs dédiés"; fi
echo "   Panne de la base au moment de l'enregistrement"
J=$T/jr; TK=$(token $J $BASE /demande-de-devis); qv dbdown; F0=$(ls $sd | wc -l)
q "RENAME TABLE quote_requests TO quote_requests_x"
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}" -F "attachment=@$T/logo.png;type=image/png"
q "RENAME TABLE quote_requests_x TO quote_requests"
is "échec d'enregistrement → 500" "$CODE" 500
has "message d'erreur officiel (texte fourni)" "n’a pas pu être enregistrée" "$(cat $BODY)"
hasnt "…sans fuite SQL ni chemin" 'SQLSTATE\|quote_requests\|/home/claude\|PDOException' "$(cat $BODY)"
is "fichier téléversé supprimé (aucun orphelin)" "$(ls $sd | wc -l)" "$F0"
is "aucune ligne partielle (transaction annulée)" "$(q "SELECT COUNT(*) FROM quote_requests WHERE email='dbdown@t5.invalid'")" 0
post $J $BASE /demande-de-devis -F "_csrf=$TK" "${QV[@]}"; is "après rétablissement, le même formulaire est accepté → 303" "$CODE" 303

echo "J. Journal PHP"
NEW=$(tail -n +$((LOG0+1)) $LOG | grep -Ei 'PHP (Warning|Notice|Fatal|Deprecated|Parse)|Uncaught' | wc -l | tr -d ' ')
is "aucun avertissement / erreur PHP pendant les tests" "$NEW" 0
[ "$NEW" != 0 ] && tail -n +$((LOG0+1)) $LOG | grep -Ei 'PHP (Warning|Notice|Fatal|Deprecated|Parse)|Uncaught' | head -3 | cut -c1-200

echo; echo "Résultat : $PASS réussis, $FAIL échecs, $SKIP ignorés"; [ "$FAIL" = 0 ]
