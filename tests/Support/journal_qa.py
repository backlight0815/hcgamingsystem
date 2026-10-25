"""Local-only journal QA services; no production data or credentials are used."""
import json, os, pathlib, secrets, sqlite3, subprocess, sys, time
from http.server import BaseHTTPRequestHandler, HTTPServer
import requests

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/journal-repair-20261013'
PRIVATE = OUT / 'private'
PRIVATE.mkdir(parents=True, exist_ok=True)
(PRIVATE / '.gitignore').write_text('*\n')
DB = PRIVATE / 'journal.sqlite'
FIXTURE = PRIVATE / 'fixture.json'
env = os.environ.copy()
env.update(APP_ENV='local', APP_DEBUG='false', DB_CONNECTION='sqlite', DB_DATABASE=str(DB),
    APP_KEY='base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode(),
    CACHE_DRIVER='array', SESSION_DRIVER='file', QUEUE_CONNECTION='sync', MAIL_MAILER='log',
    APP_CONFIG_CACHE=str(PRIVATE / 'unused-config.php'),
    FIREBASE_AUTH_EMULATOR_HOST='127.0.0.1:9099', FIREBASE_WEB_API_KEY='demo-journal',
    JOURNAL_QA_PASSWORD=secrets.token_urlsafe(24), JOURNAL_QA_FIXTURE=str(FIXTURE),
    APP_URL='http://127.0.0.1:8765')
if not DB.exists():
    DB.touch()
    with (OUT / 'qa-migrations.log').open('w') as log:
        subprocess.run(['php', 'artisan', 'migrate', '--force'], cwd=ROOT, env=env, stdout=log, stderr=log, check=True)
    subprocess.run(['php', 'tests/Support/journal_qa_seed.php'], cwd=ROOT, env=env, check=True)
fixture = json.loads(FIXTURE.read_text())
log = (OUT / 'qa-http.log').open('w')
server = subprocess.Popen(['php', '-S', '127.0.0.1:8765', '-t', 'public', 'public/index.php'],
    cwd=ROOT, env=env, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW)
time.sleep(1)
auth = 'http://127.0.0.1:9099/identitytoolkit.googleapis.com/v1/'
signup = requests.post(auth + 'accounts:signUp?key=demo-journal', json={
    'email': fixture['email'], 'password': fixture['password'], 'returnSecureToken': True}).json()
if 'localId' not in signup:
    signup = requests.post(auth + 'accounts:signInWithPassword?key=demo-journal', json={
        'email': fixture['email'], 'password': fixture['password'], 'returnSecureToken': True}).json()
uid = signup['localId']
with sqlite3.connect(DB) as db:
    # Firebase emulator resets identities on restart; this fixture DB is local only.
    db.execute('DELETE FROM trading_journal_identities WHERE user_id = ? AND firebase_uid != ?',
        (fixture['website_user_id'], uid))
requests.post(auth + 'accounts:update?key=demo-journal', headers={'Authorization': 'Bearer owner'},
    json={'localId': uid, 'emailVerified': True}).raise_for_status()
profile = {'name':'Journal QA', 'email':fixture['email'], 'username':'journalqa', 'role_id':750,
    'access_status':'approved', 'account_status':'active', 'final_exam_passed':True}
fields = {k: {('booleanValue' if isinstance(v,bool) else 'integerValue' if isinstance(v,int) else 'stringValue'): v} for k,v in profile.items()}
requests.patch(f'http://127.0.0.1:8085/v1/projects/demo-hc-journal/databases/(default)/documents/users/{uid}',
    headers={'Authorization':'Bearer owner'}, json={'fields':fields}).raise_for_status()
fixture['uid'] = uid

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *_): pass
    def do_GET(self):
        if self.path == '/fixture':
            body = fixture
        elif self.path == '/database':
            with sqlite3.connect(DB) as db:
                db.row_factory = sqlite3.Row
                body = {table: [dict(row) for row in db.execute(f'SELECT * FROM {table}')]
                    for table in ['trading_journals', 'trading_journal_sync_records', 'capitals']}
        else:
            self.send_error(404); return
        self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers()
        self.wfile.write(json.dumps(body).encode())

print('QA ready: Laravel 8765, local fixture/database evidence 8766; Firebase demo-hc-journal.', flush=True)
try: HTTPServer(('127.0.0.1',8766),Handler).serve_forever()
finally: server.terminate()
