<?php
declare(strict_types=1);

/** Envoi d'un e-mail de test via mail_send() avec la configuration MAIL_* de l'environnement. Affiche le résultat en JSON.
 *  Usage : MAIL_HOST=… MAIL_PORT=… MAIL_ENCRYPTION=… php tests/step5_mail.php "<sujet>" [replyTo] */
require dirname(__DIR__) . '/includes/bootstrap.php';
$res = mail_send('equipe@exemple.invalid', $argv[1] ?? 'Test', "Bonjour é à ç — corps du message\n.ligne commençant par un point\nFin.", $argv[2] ?? null);
echo json_encode($res, JSON_UNESCAPED_UNICODE), "\n";
