#!/usr/bin/env bash
# Tests du client SMTP (includes/services/mail_service.php) contre un faux serveur (tests/fake_smtp.py).
# Prérequis : base installée, python3, openssl (certificat auto-signé généré dans /tmp/smtpcert).
# Usage : bash tests/step5_mail.sh
set -u
cd "$(dirname "$0")/.."
OUT=/tmp/smtp.jsonl; CERT=/tmp/smtpcert; PASS=0; FAIL=0; SPID=""
mkdir -p $CERT; [ -f $CERT/c.pem ] || openssl req -x509 -newkey rsa:2048 -nodes -keyout $CERT/k.pem -out $CERT/c.pem -days 2 -subj "/CN=localhost" 2>/dev/null
ok()  { PASS=$((PASS+1)); echo "  [OK]   $1"; }
ko()  { FAIL=$((FAIL+1)); echo "  [ÉCHEC] $1 — $2"; }
stop(){ [ -n "$SPID" ] && kill "$SPID" 2>/dev/null; SPID=""; sleep .3; }
start(){ stop; rm -f $OUT; python3 tests/fake_smtp.py --port 2525 --out $OUT "$@" >/tmp/fs.log 2>&1 & SPID=$!; sleep 1; }
trap 'stop; mariadb genie_solutions -e "UPDATE site_settings SET setting_value=\"\" WHERE setting_key=\"mail_from_address\"" 2>/dev/null' EXIT
send(){ env MAIL_HOST=127.0.0.1 MAIL_PORT=2525 "$@"; }
res() { echo "$1" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['ok'], d['error'])"; }
mariadb genie_solutions -e "UPDATE site_settings SET setting_value='site@exemple.invalid' WHERE setting_key='mail_from_address'"

echo "1. Envoi simple sans chiffrement (boucle locale)"
start --mode plain
R=$(send MAIL_ENCRYPTION=none php tests/step5_mail.php "Nouvelle demande é" "client@exemple.invalid")
[ "$(res "$R")" = "True None" ] && ok "envoi confirmé (250 après DATA)" || ko "envoi" "$R"
python3 - <<'PY' && ok "message décodé : sujet UTF-8, corps base64 (accents, ligne commençant par un point), Reply-To, MIME, Message-ID" || ko "contenu du message" "voir détail"
import json, email, email.policy
m = json.loads(open('/tmp/smtp.jsonl').read().splitlines()[-1])
msg = email.message_from_string(m['data'], policy=email.policy.default)
assert msg['Subject'] == 'Nouvelle demande é', msg['Subject']
assert msg['Reply-To'] == 'client@exemple.invalid', msg['Reply-To']
assert 'site@exemple.invalid' in msg['From'], msg['From']
body = msg.get_content()
assert 'é à ç' in body and '.ligne commençant par un point' in body, body
assert 'utf-8' in msg['Content-Type'].lower() and msg['Content-Type'].startswith('text/plain')
assert '@exemple.invalid>' in msg['Message-ID']
PY

echo "2. Injection d'en-têtes (CRLF dans le sujet et dans Reply-To)"
send MAIL_ENCRYPTION=none php tests/step5_mail.php $'Sujet\r\nBcc: pirate@exemple.invalid\r\nX-Evil: 1' $'a@exemple.invalid\r\nBcc: pirate2@exemple.invalid' >/dev/null
python3 - <<'PY' && ok "aucun en-tête injecté : le sujet reste sur UNE ligne, pas de ligne Bcc:/X-Evil:, Reply-To invalide écarté, un seul destinataire" || ko "injection d'en-têtes" "en-tête injecté"
import json
m = json.loads(open('/tmp/smtp.jsonl').read().splitlines()[-1])
head = m['data'].split('\r\n\r\n')[0]
names = [l.split(':', 1)[0].lower() for l in head.split('\r\n') if not l.startswith((' ', '\t'))]
assert 'bcc' not in names and 'x-evil' not in names, names
assert names.count('subject') == 1 and 'reply-to' not in names, names
assert set(names) == {'date', 'from', 'to', 'subject', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding', 'x-auto-response-suppress'}, names
assert len(m['to']) == 1
PY

echo "3. Serveur injoignable"
stop
R=$(send MAIL_PORT=2599 MAIL_ENCRYPTION=none php tests/step5_mail.php "x" 2>/dev/null)
[[ "$(res "$R")" == False\ smtp_connect_failed* ]] && ok "échec détecté : $(res "$R")" || ko "serveur injoignable" "$R"

echo "4. STARTTLS avec certificat auto-signé"
start --mode starttls --cert $CERT/c.pem --key $CERT/k.pem
R=$(send MAIL_ENCRYPTION=tls MAIL_VERIFY_PEER=true php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" = "False smtp_tls_handshake_failed" ] && ok "certificat NON approuvé refusé par défaut (vérification activée)" || ko "vérification TLS" "$R"
[ ! -s $OUT ] && ok "aucun message remis au serveur non vérifié" || ko "message remis malgré TLS invalide" "oui"
R=$(send MAIL_ENCRYPTION=tls MAIL_VERIFY_PEER=false php tests/step5_mail.php "Via STARTTLS" 2>/dev/null)
[ "$(res "$R")" = "True None" ] && ok "STARTTLS réussi si la vérification est désactivée (test local)" || ko "STARTTLS" "$R"
python3 -c "import json;m=json.loads(open('/tmp/smtp.jsonl').read().splitlines()[-1]);assert m['tls'] is True" && ok "le message a bien transité chiffré" || ko "chiffrement" "tls=False"

echo "5. SSL implicite"
start --mode ssl --cert $CERT/c.pem --key $CERT/k.pem
R=$(send MAIL_ENCRYPTION=ssl MAIL_VERIFY_PEER=false php tests/step5_mail.php "Via SSL" 2>/dev/null)
[ "$(res "$R")" = "True None" ] && ok "SSL implicite réussi" || ko "SSL" "$R"
R=$(send MAIL_ENCRYPTION=ssl MAIL_VERIFY_PEER=true php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" != "True None" ] && ok "SSL + certificat non approuvé : refusé" || ko "SSL non vérifié" "$R"

echo "6. Authentification"
start --mode starttls --cert $CERT/c.pem --key $CERT/k.pem --require-auth
R=$(send MAIL_ENCRYPTION=tls MAIL_VERIFY_PEER=false MAIL_USER=smtp-user@exemple.invalid MAIL_PASS=secret php tests/step5_mail.php "Avec auth" 2>/dev/null)
[ "$(res "$R")" = "True None" ] && ok "AUTH LOGIN sur canal chiffré" || ko "auth" "$R"
python3 -c "import json;m=json.loads(open('/tmp/smtp.jsonl').read().splitlines()[-1]);assert m['auth_user']=='smtp-user@exemple.invalid'" && ok "identifiant transmis" || ko "auth_user" ""
R=$(send MAIL_ENCRYPTION=tls MAIL_VERIFY_PEER=false php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" != "True None" ] && ok "serveur exigeant l'authentification, sans identifiants : échec détecté ($(res "$R"))" || ko "auth requise" "$R"
R=$(env MAIL_HOST=127.0.0.2 MAIL_PORT=2525 MAIL_ENCRYPTION=none MAIL_USER=u@exemple.invalid MAIL_PASS=secret php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" = "False insecure_auth_refused" ] && ok "identifiants JAMAIS envoyés en clair hors boucle locale (refus avant connexion)" || ko "auth en clair" "$R"

echo "7. Réponses d'échec du serveur"
start --mode plain --reject-rcpt
R=$(send MAIL_ENCRYPTION=none php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" = "False smtp_unexpected_reply_550" ] && ok "destinataire refusé (550) → échec" || ko "550" "$R"
start --mode plain --drop-after-data
R=$(send MAIL_ENCRYPTION=none php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" != "True None" ] && ok "connexion coupée avant le « 250 » final → PAS considéré comme envoyé ($(res "$R"))" || ko "faux positif d'envoi" "$R"

echo "8. Non configuré"
R=$(env -u MAIL_HOST php tests/step5_mail.php "x" 2>/dev/null)
[ "$(res "$R")" = "False smtp_not_configured" ] && ok "SMTP non configuré → échec explicite" || ko "non configuré" "$R"

echo; echo "Résultat : $PASS réussis, $FAIL échecs"; [ "$FAIL" = 0 ]
