<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Envoi d'e-mails par SMTP, sans dépendance.
 *
 * Principes :
 *  - un envoi n'est déclaré réussi QUE si le serveur répond « 250 » après la commande DATA ;
 *  - le certificat TLS est vérifié par défaut (MAIL_VERIFY_PEER=false réservé aux tests locaux) ;
 *  - les identifiants ne sont jamais envoyés sur une connexion non chiffrée (sauf boucle locale) ;
 *  - toute valeur placée dans un en-tête est nettoyée des retours à la ligne (injection d'en-têtes) ;
 *  - rien de secret n'est journalisé : seulement le code de réponse du serveur.
 */

/** Supprime tout caractère de contrôle (dont CR/LF) d'une valeur destinée à un en-tête. */
function mail_header_clean(string $value): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
}

/** Encode une valeur d'en-tête non ASCII (RFC 2047). */
function mail_header_encode(string $value): string
{
    $value = mail_header_clean($value);

    return preg_match('/^[\x20-\x7E]*$/', $value) ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/** Lit une réponse SMTP (éventuellement sur plusieurs lignes). @return array{0:int,1:string} */
function smtp_read($fp): array
{
    $text = '';
    $code = 0;
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        $code = (int) substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] === ' ') {
            return [$code, trim($text)];
        }
    }
    throw new RuntimeException('smtp_connection_lost');
}

/** Envoie une commande et vérifie le code de réponse attendu. @return string texte de la réponse */
function smtp_cmd($fp, ?string $command, array $expected): string
{
    if ($command !== null) {
        fwrite($fp, $command . "\r\n");
    }
    [$code, $text] = smtp_read($fp);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('smtp_unexpected_reply_' . $code);
    }

    return $text;
}

/**
 * Envoie un e-mail texte.
 *
 * @return array{ok:bool,error:?string}  error : code technique court (journal) — jamais affiché au visiteur
 */
function mail_send(string $to, string $subject, string $body, ?string $replyTo = null): array
{
    $cfg = config('mail');
    if (($cfg['host'] ?? '') === '') {
        return ['ok' => false, 'error' => 'smtp_not_configured'];
    }

    $from = trim(setting('mail_from_address'));
    if ($from === '' && filter_var($cfg['user'], FILTER_VALIDATE_EMAIL)) {
        $from = (string) $cfg['user'];
    }
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'from_address_invalid'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'recipient_invalid'];
    }
    if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = null;
    }

    $enc  = strtolower((string) $cfg['encryption']);
    $host = (string) $cfg['host'];
    $port = (int) $cfg['port'];
    $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    $hasAuth  = $cfg['user'] !== '' && $cfg['pass'] !== '';

    if ($hasAuth && $enc === 'none' && !$loopback) {
        return ['ok' => false, 'error' => 'insecure_auth_refused']; // pas d'identifiants en clair sur le réseau
    }

    $verify = (bool) $cfg['verify_peer'];
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'peer_name' => $host,
    ]]);

    $fp = null;
    try {
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr,
            (float) $cfg['timeout'], STREAM_CLIENT_CONNECT, $ctx);
        if ($fp === false) {
            throw new RuntimeException('smtp_connect_failed_' . $errno);
        }
        stream_set_timeout($fp, (int) $cfg['timeout']);

        smtp_cmd($fp, null, [220]);
        $domain = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $ehlo   = static fn(): string => smtp_cmd($fp, 'EHLO ' . $domain, [250]);
        $caps   = $ehlo();

        if ($enc === 'tls') {
            if (stripos($caps, 'STARTTLS') === false) {
                throw new RuntimeException('smtp_starttls_unsupported');
            }
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('smtp_tls_handshake_failed'); // certificat invalide, version TLS refusée…
            }
            $ehlo();
        }

        if ($hasAuth) {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode((string) $cfg['user']), [334]);
            smtp_cmd($fp, base64_encode((string) $cfg['pass']), [235]);
        }

        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $fromName = setting('mail_from_name', 'Génie Solutions');
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . mail_header_encode($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . mail_header_encode($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($from, '@') ?: '@localhost', 1)) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Auto-Response-Suppress: All',
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n"
            . chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");

        fwrite($fp, $message . ".\r\n"); // base64 : aucune ligne ne commence par « . » (pas de dot-stuffing nécessaire)
        smtp_cmd($fp, null, [250]);       // SEUL ce 250 confirme l'envoi
        try { smtp_cmd($fp, 'QUIT', [221]); } catch (Throwable $e) { /* le message est déjà accepté : une erreur de QUIT n'est pas un échec d'envoi */ }

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('mail_send: ' . $e->getMessage());

        return ['ok' => false, 'error' => $e->getMessage()];
    } finally {
        if (is_resource($fp)) {
            @fclose($fp);
        }
    }
}
