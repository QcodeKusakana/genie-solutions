#!/usr/bin/env bash
# Tests de la page 503 (panne de base) et de bin/check.php. Utilise GS_ENV_FILE pour simuler des configurations cassées.
# Usage : bash tests/step_diag.sh        (compte root MariaDB local requis pour le scénario « base installée »)
set -u
cd "$(dirname "$0")/.."
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  [OK]   $1"; }
ko(){ FAIL=$((FAIL+1)); echo "  [ÉCHEC] $1 — $2"; }
has(){ grep -q -- "$2" <<<"$3" && ok "$1" || ko "$1" "absent : $2"; }
hasnt(){ grep -q -- "$2" <<<"$3" && ko "$1" "présent : $2" || ok "$1"; }
T=/tmp/tdiag; rm -rf $T; mkdir -p $T; PIDS=()
trap 'for p in "${PIDS[@]:-}"; do kill $p 2>/dev/null; done' EXIT
good=$(grep -h '^DB_' /tmp/env.app 2>/dev/null)

serve(){ # serve PORT ENVFILE
  GS_ENV_FILE=$2 php -S 127.0.0.1:$1 -t . index.php >$T/ps$1.log 2>&1 & PIDS+=($!); sleep 1; }
fetch(){ # fetch PORT HOST → $CODE, $B
  CODE=$(curl -s -o $T/b.html -w '%{http_code}' -H "Host: $2" http://127.0.0.1:$1/); B=$(cat $T/b.html); }

echo "A. Mauvais mot de passe (cause : auth)"
{ echo "$good" | sed 's/^DB_PASS=.*/DB_PASS=mauvais/'; echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')"; } > $T/bad.env
serve 8101 $T/bad.env
fetch 8101 localhost
[ "$CODE" = 503 ] && ok "503 sur un nom local" || ko "code" "$CODE"
has "titre exact (plus « maintenance »)" "Service momentanément indisponible" "$B"
hasnt "ne parle plus de maintenance" "en maintenance" "$B"
has "aide au dépannage affichée en local" "Aide au dépannage" "$B"
has "cause précise : identifiants MySQL" "MySQL refuse l’utilisateur ou le mot de passe" "$B"
has "renvoie vers php bin/check.php" "php bin/check.php" "$B"
hasnt "jamais de détail SQL / mot de passe / chemin" 'SQLSTATE\|mauvais\|Access denied\|/home/claude' "$B"
fetch 8101 genie-solutions.test; has "aussi sur un domaine *.test (Laragon)" "Aide au dépannage" "$B"
fetch 8101 127.0.0.1:8101;        has "aussi sur 127.0.0.1" "Aide au dépannage" "$B"
fetch 8101 www.exemple-public.com
[ "$CODE" = 503 ] && ok "503 aussi pour un nom public" || ko "code" "$CODE"
hasnt "AUCUNE aide pour un nom de domaine public" "Aide au dépannage" "$B"
hasnt "…ni la cause (auth/MySQL)" "MySQL" "$B"
has "…mais le message générique est affiché" "Merci de réessayer dans quelques instants" "$B"
fetch 8101 "localhost.exemple-public.com"; hasnt "« localhost » en sous-domaine d'un nom public : pas d'aide" "Aide au dépannage" "$B"
CODE2=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: localhost" http://127.0.0.1:8101/inexistant); [ "$CODE2" = 404 ] && ok "une 404 reste une 404 (la panne ne masque pas le routage)" || ko "404" "$CODE2"
curl -sI -H "Host: localhost" http://127.0.0.1:8101/ | grep -qi '^retry-after' && ok "Retry-After présent" || ko "Retry-After" "absent"
curl -sI -H "Host: localhost" http://127.0.0.1:8101/ | grep -qi '^x-robots-tag: noindex' && ok "noindex (les moteurs ne retiennent pas la panne)" || ko "noindex" "absent"
tail -3 storage/logs/php-error.log | grep -q 'Access denied' && ok "la cause technique est bien dans le journal" || ko "journal" "rien"

echo "B. Base inexistante (cause : database)"
{ echo "$good" | sed 's/^DB_NAME=.*/DB_NAME=base_qui_nexiste_pas/'; echo "APP_SECRET=x"; } > $T/nodb.env; serve 8102 $T/nodb.env; fetch 8102 localhost
has "cause : base introuvable" "n’existe pas, ou cet utilisateur n’y a pas accès" "$B"

echo "C. Base vide, tables absentes (cause : schema)"
mariadb -e "CREATE DATABASE IF NOT EXISTS vide_t5 CHARACTER SET utf8mb4; GRANT ALL ON vide_t5.* TO 'genie_app'@'127.0.0.1'; FLUSH PRIVILEGES" 2>/dev/null
{ echo "$good" | sed 's/^DB_NAME=.*/DB_NAME=vide_t5/'; echo "APP_SECRET=x"; } > $T/empty.env; serve 8103 $T/empty.env; fetch 8103 localhost
has "cause : tables absentes → install.php" "php bin/install.php" "$B"
mariadb -e "DROP DATABASE IF EXISTS vide_t5" 2>/dev/null

echo "D. MySQL arrêté / mauvais port (cause : server_down)"
{ echo "$good" | grep -v '^DB_PORT'; echo "DB_PORT=3999"; echo "APP_SECRET=x"; } > $T/down.env; serve 8104 $T/down.env; fetch 8104 localhost
has "cause : serveur MySQL injoignable" "Le serveur MySQL ne répond pas" "$B"

echo "E. Aucune configuration (cause : not_configured) — copie du projet sans fichier d'environnement"
rm -rf $T/clean && mkdir $T/clean && cp -a . $T/clean/genie-solutions && rm -f $T/clean/genie-solutions/.env
( cd $T/clean/genie-solutions && GS_ENV_FILE=/nonexistent php -S 127.0.0.1:8105 -t . index.php >$T/ps8105.log 2>&1 & echo $! > $T/pid105 ); PIDS+=($(cat $T/pid105)); sleep 1
fetch 8105 localhost; [ "$CODE" = 503 ] && ok "503" || ko "code" "$CODE"
has "aide : copier .env.example vers .env" "Copiez le fichier .env.example vers .env" "$B"

echo "F. Page d'accueil saine (référence) : aucune aide, pas de 503"
serve 8106 ../genie-solutions.env; fetch 8106 localhost
[ "$CODE" = 200 ] && ok "200 avec une configuration correcte" || ko "code" "$CODE"
hasnt "aucune aide parasite" "Aide au dépannage" "$B"

echo; echo "Résultat : $PASS réussis, $FAIL échecs"; [ "$FAIL" = 0 ]
