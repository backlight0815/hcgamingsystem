"""Exercise actual website forms and API against the disposable QA database."""
import json, pathlib, re, sqlite3, uuid
import requests
ROOT=pathlib.Path(__file__).resolve().parents[2]
OUT=ROOT/'output/journal-repair-20261013'
fixture=json.loads((OUT/'private/fixture.json').read_text())
with sqlite3.connect(OUT/'private/journal.sqlite') as db:
    db.execute('UPDATE users SET role_id=750 WHERE id=?',(fixture['website_user_id'],))
    if not db.execute('SELECT id FROM trader_onboarding_applications WHERE user_id=?',(fixture['website_user_id'],)).fetchone():
        db.execute("INSERT INTO trader_onboarding_applications(user_id,status,is_client,has_deposit,discord_username,broker_uid,broker_email) VALUES(?, 'approved', 1, 1, 'journal-qa', 'qa-only', 'journal-qa@example.test')", (fixture['website_user_id'],))
base='http://127.0.0.1:8765'
session=requests.Session()
result=[]
def record(name, response, **extra):
    result.append({'scenario':name,'status':response.status_code,**extra})
    print(json.dumps(result[-1]),flush=True)
page=session.get(base+'/login')
record('website login form',page)
csrf=re.search(r'name="_token"\s+value="([^"]+)"',page.text).group(1)
response=session.post(base+'/login',data={'_token':csrf,'username':'journalqa','password':fixture['password']},allow_redirects=False)
record('website credential login',response)
page=session.get(base+'/all/trading-journals')
record('website journal page',page,seed_visible='Website QA seed' in page.text)
(OUT/'website-journal.html').write_text(re.sub(r'(name="_token"\s+value=")[^"]+',r'\1[redacted]',page.text),encoding='utf-8')
if page.status_code==200:
    csrf=re.search(r'name="_token"\s+value="([^"]+)"',page.text).group(1)
    # Seed the legacy pair reference table only in the disposable SQLite file.
    with sqlite3.connect(OUT/'private/journal.sqlite') as db:
        if not db.execute("select id from trading_pairs where symbol='EURUSD'").fetchone():
            db.execute("insert into trading_pairs(symbol,pip_factor,pip_decimal) values('EURUSD',0.0001,4)")
    payload={'_token':csrf,'status':'closed','open_date':'2026-10-05T08:00','close_date':'2026-10-05T09:00',
        'pair':'EURUSD','direction':1,'entry_price':1.1234,'exit_price':1.1244,'lot_size':0.1,
        'result':1,'profit_loss':10,'notes':'Website HTTP QA create'}
    response=session.post(base+'/store/trading-journal',data=payload,allow_redirects=False)
    with sqlite3.connect(OUT/'private/journal.sqlite') as db:
        row=db.execute("select id,profit_loss from trading_journals where notes='Website HTTP QA create'").fetchone()
    record('website form create persisted',response,persisted=row is not None)
    if row:
        payload['notes']='Website HTTP QA update'
        response=session.post(base+f'/update/trading-journal/{row[0]}',data=payload,allow_redirects=False)
        record('website form update',response)
        api=session.get(base+'/api/mobile/v1/journal-sync?symbol=EURUSD',headers={'Authorization':'Bearer '+fixture['token'],'Accept':'application/json'})
        record('website form update visible in sync API',api,visible='Website HTTP QA update' in api.text)
        response=session.get(base+f'/delete/trading-journal/{row[0]}',allow_redirects=False)
        record('website form delete',response)
    for path in ['/trading-journal/report/pdf','/all/trading-journals']:
        response=session.get(base+path)
        record(path,response,content_type=response.headers.get('Content-Type'))
        if response.headers.get('Content-Type','').startswith('application/pdf'):
            (OUT/'website-journal-report.pdf').write_bytes(response.content)
    headers={'Authorization':'Bearer '+fixture['token'],'Accept':'application/json'}
    api=base+'/api/mobile/v1'
    # Existing withdrawal policy needs three profitable days and >=2% gross profit.
    temporary=[]
    run_id=uuid.uuid4().hex[:12]
    for day in (7,8):
        response=session.post(api+'/journal-sync',headers=headers,json={
            'mobile_id':f'capital-policy-qa-{run_id}-{day}', 'fields':{
                'status':'closed','open_date':f'2026-10-{day:02} 08:00:00','close_date':f'2026-10-{day:02} 09:00:00',
                'pair':'XAUUSD','direction':1,'entry_price':2350,'exit_price':2365,'lot_size':1,
                'pips':15,'profit_loss':145,'result':1,'notes':'Capital policy QA'}})
        if response.ok: temporary.append(response.json()['data']['record'])
    response=session.post(api+'/capitals',headers=headers,json={'type':1,'depositAmount':0,'deposit_date':'2026-10-09'})
    record('zero deposit rejected',response)
    capital_ids=[]
    for kind,amount in [(1,100),(2,20)]:
        response=session.post(api+'/capitals',headers=headers,json={'type':kind,'depositAmount':amount,'deposit_date':'2026-10-09','notes':'Capital HTTP QA'})
        record('deposit' if kind==1 else 'withdrawal',response)
        if response.status_code==201:
            capital_ids.append(response.json()['data']['id'])
    with sqlite3.connect(OUT/'private/journal.sqlite') as db:
        amounts=[row[0] for row in db.execute("SELECT amount FROM capitals WHERE notes='Capital HTTP QA'")]
    result.append({'scenario':'cash flow signs persisted','amounts':amounts,'passed':sorted(amounts)==[-20,100]})
    for capital_id in capital_ids: session.delete(api+f'/capitals/{capital_id}',headers=headers)
    for item in temporary:
        session.delete(api+f"/journal-sync/{item['sync_id']}",headers=headers,json={
            'mobile_id':item['mobile_id'],'base_revision':item['revision']})
(OUT/'website-smoke.json').write_text(json.dumps(result,indent=2))
