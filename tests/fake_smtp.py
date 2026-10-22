#!/usr/bin/env python3
"""Faux serveur SMTP pour les tests (aucune dépendance). Enregistre les messages reçus en JSON (une ligne par message).

Usage : python3 tests/fake_smtp.py --port 2525 --out /tmp/smtp.jsonl [--mode plain|starttls|ssl] [--cert c.pem --key k.pem]
                                  [--require-auth] [--reject-rcpt] [--drop-after-data]
 --drop-after-data : coupe la connexion après DATA SANS répondre 250 (le client ne doit PAS conclure à un envoi réussi)
"""
import argparse, base64, json, socketserver, ssl, sys, threading

ap = argparse.ArgumentParser()
ap.add_argument('--port', type=int, required=True)
ap.add_argument('--out', required=True)
ap.add_argument('--mode', default='plain', choices=['plain', 'starttls', 'ssl'])
ap.add_argument('--cert'); ap.add_argument('--key')
ap.add_argument('--require-auth', action='store_true')
ap.add_argument('--reject-rcpt', action='store_true')
ap.add_argument('--drop-after-data', action='store_true')
A = ap.parse_args()
lock = threading.Lock()

class H(socketserver.StreamRequestHandler):
    def send(self, s): self.wfile.write((s + '\r\n').encode()); self.wfile.flush()
    def line(self):
        l = self.rfile.readline()
        return l.decode('utf-8', 'replace').rstrip('\r\n') if l else None
    def handle(self):
        msg = {'from': None, 'to': [], 'auth_user': None, 'tls': A.mode == 'ssl', 'data': ''}
        authed = False
        self.send('220 fake-smtp ready')
        while True:
            l = self.line()
            if l is None: return
            u = l.upper()
            if u.startswith('EHLO') or u.startswith('HELO'):
                caps = ['250-fake-smtp']
                if A.mode == 'starttls' and not msg['tls']: caps.append('250-STARTTLS')
                if (A.mode != 'starttls' or msg['tls']): caps.append('250-AUTH LOGIN')
                caps.append('250 8BITMIME')
                self.wfile.write(('\r\n'.join(caps) + '\r\n').encode()); self.wfile.flush()
            elif u == 'STARTTLS' and A.mode == 'starttls':
                self.send('220 go ahead')
                ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain(A.cert, A.key)
                try:
                    conn = ctx.wrap_socket(self.connection, server_side=True)
                except Exception: return
                self.connection = conn; self.rfile = conn.makefile('rb'); self.wfile = conn.makefile('wb'); msg['tls'] = True
            elif u == 'AUTH LOGIN':
                self.send('334 VXNlcm5hbWU6'); user = base64.b64decode(self.line() or '').decode('utf-8', 'replace')
                self.send('334 UGFzc3dvcmQ6'); self.line()
                msg['auth_user'] = user; authed = True; self.send('235 ok')
            elif u.startswith('MAIL FROM:'):
                if A.require_auth and not authed: self.send('530 auth required'); continue
                msg['from'] = l[10:].strip(); self.send('250 ok')
            elif u.startswith('RCPT TO:'):
                if A.reject_rcpt: self.send('550 no such user'); continue
                msg['to'].append(l[8:].strip()); self.send('250 ok')
            elif u == 'DATA':
                self.send('354 end with .')
                lines = []
                while True:
                    d = self.line()
                    if d is None: return
                    if d == '.': break
                    lines.append(d[1:] if d.startswith('..') else d)
                msg['data'] = '\r\n'.join(lines)
                if A.drop_after_data: return          # message reçu, mais AUCUNE confirmation 250
                with lock, open(A.out, 'a', encoding='utf-8') as f: f.write(json.dumps(msg) + '\n')
                self.send('250 queued')
            elif u == 'QUIT': self.send('221 bye'); return
            else: self.send('250 ok')

class S(socketserver.ThreadingTCPServer):
    allow_reuse_address = True; daemon_threads = True
    def get_request(self):
        sock, addr = super().get_request()
        if A.mode == 'ssl':
            ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain(A.cert, A.key)
            sock = ctx.wrap_socket(sock, server_side=True)
        return sock, addr

with S(('127.0.0.1', A.port), H) as srv:
    print('fake smtp on', A.port, A.mode, flush=True)
    srv.serve_forever()
