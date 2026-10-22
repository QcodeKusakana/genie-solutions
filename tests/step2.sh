#!/usr/bin/env bash
# Tests HTTP de l'étape 2. Prérequis : base installée, .env configuré (APP_SECRET défini).
# Usage : bash tests/step2.sh [port | http://hote:port]
#   - un numéro de port : lance le serveur PHP intégré (php -S) sur ce port ;
#   - une URL           : teste un serveur déjà lancé (ex. Apache, pour valider le .htaccess).
set -u
ARG="${1:-8089}"
cd "$(dirname "$0")/.."
if [[ "$ARG" == http* ]]; then
  BASE="${ARG%/}"; SRV=""
else
  BASE="http://127.0.0.1:$ARG"
  php -S 127.0.0.1:$ARG -t . index.php >/tmp/phpserver.log 2>&1 &
  SRV=$!; trap 'kill $SRV 2>/dev/null' EXIT; sleep 1
fi
PASS=0; FAIL=0
check() { if [ "$2" = "ok" ]; then PASS=$((PASS+1)); printf "  [OK]   %s\n" "$1"; else FAIL=$((FAIL+1)); printf "  [ÉCHEC] %s — %s\n" "$1" "$2"; fi; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
hdr()    { curl -s -D - -o /dev/null "$@"; }

H=$(hdr $BASE/); B=$(curl -s $BASE/)
echo "Accueil"
[ "$(status $BASE/)" = 200 ] && check "GET / → 200" ok || check "GET / → 200" "$(status $BASE/)"
echo "$B" | grep -q '<h1 id="hero-title">Des solutions professionnelles' && check "H1 issu de la base" ok || check "H1 issu de la base" absent
[ "$(echo "$B" | grep -o '<h1' | wc -l)" = 1 ] && check "un seul H1" ok || check "un seul H1" "nombre ≠ 1"
echo "$B" | grep -q '<html lang="fr">' && check "lang=fr" ok || check "lang=fr" absent
echo "$B" | grep -q 'class="skip-link"' && check "lien d'évitement" ok || check "lien d'évitement" absent
echo "$B" | grep -q 'aria-current="page">Accueil' && check "aria-current sur l'accueil" ok || check "aria-current sur l'accueil" absent
echo "$B" | grep -q '<meta name="description"' && check "meta description" ok || check "meta description" absent
echo "$B" | grep -qi 'style="' && check "aucun style en ligne (CSP)" "style= trouvé" || check "aucun style en ligne (CSP)" ok
[ "$(echo "$B" | grep -Eio '<script[^>]*>' | grep -vc 'src=')" = 0 ] && check "aucun script en ligne (CSP)" ok || check "aucun script en ligne (CSP)" "script inline"
echo "$B" | grep -Eq 'https?://(cdn|fonts|ajax|code)\.' && check "aucune ressource externe" "URL externe" || check "aucune ressource externe" ok

echo "En-têtes de sécurité"
for h in "Content-Security-Policy: default-src 'self'" "X-Content-Type-Options: nosniff" "X-Frame-Options: SAMEORIGIN" "Referrer-Policy: strict-origin-when-cross-origin" "Permissions-Policy:"; do
  echo "$H" | grep -qi "^$h" && check "$h" ok || check "$h" absent
done
echo "$H" | grep -qi '^X-Powered-By' && check "pas de X-Powered-By" "présent" || check "pas de X-Powered-By" ok
echo "$H" | grep -qi '^Set-Cookie' && check "pas de cookie sur page publique simple" "cookie posé" || check "pas de cookie sur page publique simple" ok

echo "Routage et erreurs"
[ "$(status $BASE/inexistante)" = 404 ] && check "404 sur route inconnue" ok || check "404" "$(status $BASE/inexistante)"
hdr $BASE/inexistante | grep -qi '^X-Robots-Tag: noindex' && check "404 en noindex" ok || check "404 en noindex" absent
curl -s $BASE/inexistante | grep -q 'Page introuvable' && check "page 404 personnalisée" ok || check "page 404 personnalisée" absent
[ "$(status -X POST $BASE/)" = 405 ] && check "POST / → 405" ok || check "POST / → 405" "$(status -X POST $BASE/)"
[ "$(status -X DELETE $BASE/)" = 405 ] && check "DELETE → 405" ok || check "DELETE → 405" "$(status -X DELETE $BASE/)"
hdr -X DELETE $BASE/ | grep -qi '^Allow:' && check "en-tête Allow" ok || check "en-tête Allow" absent
[ "$(status $BASE/contact/)" = 301 ] && check "slash final → 301" ok || check "slash final → 301" "$(status $BASE/contact/)"
curl -s -D - -o /dev/null $BASE/contact/ | grep -qi '^Location: /contact' && check "Location sans slash" ok || check "Location sans slash" absent

echo "Fichiers statiques et fuites"
[ "$(status "$BASE/css/site.css")" = 200 ] && check "css/site.css servi" ok || check "css/site.css servi" "ko"
[ "$(status "$BASE/js/main.js")" = 200 ] && check "js/main.js servi" ok || check "js/main.js servi" "ko"
[ "$(status "$BASE/lib/bootstrap-icons/fonts/bootstrap-icons.woff2")" = 200 ] && check "police icônes servie" ok || check "police icônes servie" "ko"

echo "Dossiers et fichiers privés inaccessibles en HTTP (attendu : 403 ou 404, jamais 200)"
for p in /.env /.env.example /.htaccess /includes/config.php /includes/bootstrap.php /includes/helpers/csrf.php /php/home.php /pages/home.php /pages/error.php \
         /menu/menu.php /menu/footer.php /database/schema.sql /database/seed.sql /storage/logs/ /storage/private/ /bin/install.php /bin/create-admin.php \
         /tests/step2.sh /README.md /index.php.bak /includes/ /pages/ /php/ /admin/php/ /admin/pages/; do
  CODE=$(status -L --max-redirs 3 "$BASE$p")   # on suit la redirection « slash final » avant de juger
  case "$CODE" in 403|404) check "$p → $CODE" ok;; *) check "$p" "code $CODE (accessible !)";; esac
done
echo "Corps de réponse : aucune fuite de code source ni de secret"
for p in /includes/config.php /php/home.php /pages/home.php /database/schema.sql /.env /menu/menu.php; do
  if curl -s "$BASE$p" | grep -Eq '<\?php|CREATE TABLE|DB_PASS|APP_SECRET|GS_ROOT'; then check "fuite sur $p" "CODE/SECRET VISIBLE"; else check "pas de fuite sur $p" ok; fi
done
echo "Exécution de scripts dans uploads/ interdite"
printf '<?php echo "EXECUTE-OK"; ?>' > uploads/_test_exec.php; printf '<script>alert(1)</script>' > uploads/_test.html
if curl -s "$BASE/uploads/_test_exec.php" | grep -q 'EXECUTE-OK'; then check "PHP dans uploads/" "EXÉCUTÉ"; else check "PHP dans uploads/ non exécuté ($(status "$BASE/uploads/_test_exec.php"))" ok; fi
[ "$(status "$BASE/uploads/_test.html")" != 200 ] && check "HTML dans uploads/ non servi" ok || check "HTML dans uploads/" "servi en 200"
rm -f uploads/_test_exec.php uploads/_test.html
for p in "/../.env" "/..%2f.env" "/css/../.env" "/css/..%2f..%2f.env" "/lib/../includes/config.php" "/../database/schema.sql"; do
  CODE=$(status --path-as-is "$BASE$p"); BODY=$(curl -s --path-as-is "$BASE$p")
  if echo "$BODY" | grep -Eq 'DB_PASS|CREATE TABLE|APP_SECRET'; then check "traversée $p" "FUITE"; else check "traversée $p (code $CODE, pas de fuite)" ok; fi
done

echo "Échappement XSS (valeur piégée dans un paramètre)"
PAYLOAD='<script>alert(1)</script>"><img src=x onerror=alert(2)>'
php -r 'require "includes/bootstrap.php"; $s=db()->prepare("UPDATE site_settings SET setting_value=:v WHERE setting_key=\"announcement_message\""); $s->execute(["v"=>$argv[1]]);' -- "$PAYLOAD"
[ "$(php -r 'require "includes/bootstrap.php"; echo strlen((string) db()->query("SELECT setting_value FROM site_settings WHERE setting_key=\"announcement_message\"")->fetchColumn());')" -gt 20 ] && check "charge XSS posée en base" ok || check "charge XSS posée en base" "UPDATE sans effet : test invalide"
B2=$(curl -s $BASE/)
echo "$B2" | grep -q '<script>alert(1)' && check "XSS neutralisé" "SCRIPT NON ÉCHAPPÉ" || check "XSS neutralisé (balise script)" ok
echo "$B2" | grep -q '<img src=x' && check "XSS neutralisé (img)" "IMG NON ÉCHAPPÉE" || check "XSS neutralisé (img)" ok
echo "$B2" | grep -q '&lt;script&gt;alert(1)&lt;/script&gt;' && check "texte affiché échappé" ok || check "texte affiché échappé" absent
php -r 'require "includes/bootstrap.php"; db()->exec("UPDATE site_settings SET setting_value=\"[DÉMO] Numéro fictif : +000 00 000 0000\" WHERE setting_key=\"announcement_message\"");'

echo "Liens sociaux : seules les URL http(s) sont rendues"
php -r 'require "includes/bootstrap.php"; db()->exec("UPDATE site_settings SET setting_value=\"javascript:alert(1)\" WHERE setting_key=\"facebook_url\"; UPDATE site_settings SET setting_value=\"https://exemple.invalid/gs\" WHERE setting_key=\"instagram_url\"");'
B3=$(curl -s $BASE/)
echo "$B3" | grep -q 'javascript:' && check "javascript: refusé" "rendu" || check "javascript: refusé" ok
echo "$B3" | grep -q 'href="https://exemple.invalid/gs"' && check "lien https accepté" ok || check "lien https accepté" absent
php -r 'require "includes/bootstrap.php"; db()->exec("UPDATE site_settings SET setting_value=\"\" WHERE setting_key IN (\"facebook_url\",\"instagram_url\")");'

echo; echo "Résultat : $PASS réussis, $FAIL échecs"; [ "$FAIL" = 0 ]
