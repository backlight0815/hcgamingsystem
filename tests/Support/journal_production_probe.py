"""Interactive production check; --send-verification requires account-owner approval.

Password and tokens stay in memory and are never written to the report.
"""
import argparse, getpass, json, pathlib, requests
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, HTTPServer

ROOT = pathlib.Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--send-verification', action='store_true')
parser.add_argument('--serve-android-fixture', action='store_true')
args = parser.parse_args()
cfg = json.loads((ROOT/'mobile/hc_trader/android/app/google-services.json').read_text())
key = cfg['client'][0]['api_key'][0]['current_key']
email = input('QA email: ')
password = getpass.getpass('QA password (hidden): ')
response = requests.post('https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    params={'key':key},json={'email':email,'password':password,'returnSecureToken':True},timeout=25)
if response.status_code != 200:
    print('Firebase login failed:', response.status_code, response.json().get('error',{}).get('message'))
    raise SystemExit(1)
identity = response.json()
headers = {'Authorization':'Bearer '+identity['idToken'], 'Accept':'application/json'}
lookup = requests.post('https://identitytoolkit.googleapis.com/v1/accounts:lookup',
    params={'key':key}, json={'idToken':identity['idToken']}, timeout=25)
users = lookup.json().get('users', []) if lookup.ok else []
report = {'observed_at_utc':datetime.now(timezone.utc).isoformat(),
    'firebase_login_status':response.status_code,
    'firebase_lookup_status':lookup.status_code,
    'email_verified':users[0].get('emailVerified', False) if users else None,
    'endpoints':[]}
print('Firebase email verified:', report['email_verified'], flush=True)
if args.send_verification:
    verification = requests.post('https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode',
        params={'key':key}, json={'requestType':'VERIFY_EMAIL','idToken':identity['idToken']}, timeout=25)
    report['verification_email_status'] = verification.status_code
    print('Verification email request status:', verification.status_code, flush=True)
for path in ['journal-sync/changes?cursor=0&limit=100','journal-sync/statistics']:
    result = requests.get('https://www.hctradinghub.com/api/mobile/v1/'+path,headers=headers,timeout=30)
    try: payload=result.json()
    except ValueError: payload={}
    item = {'path':path,'status':result.status_code,'content_type':result.headers.get('Content-Type'),
        'error_code':payload.get('error_code'),'message':payload.get('message')}
    if 'changes' in payload.get('data',{}):
        item['record_count']=len(payload['data']['changes'])
        item['has_more']=payload['data']['has_more']
    if 'statistics' in path and result.ok: item['statistics']=payload.get('data')
    report['endpoints'].append(item)
    print(json.dumps(item),flush=True)
(ROOT/'output/journal-repair-20261013/production-authenticated.json').write_text(json.dumps(report,indent=2))

if not args.serve_android_fixture:
    raise SystemExit(0)

class Handler(BaseHTTPRequestHandler):
    def log_message(self,*_): pass
    def do_GET(self):
        if self.path != '/fixture': self.send_error(404);return
        self.send_response(200);self.send_header('Content-Type','application/json');self.end_headers()
        self.wfile.write(json.dumps({'email':email,'password':password,'uid':identity['localId']}).encode())
print('Read-only Android QA fixture available on loopback port 8767; credentials are in memory only.',flush=True)
HTTPServer(('127.0.0.1',8767),Handler).serve_forever()
