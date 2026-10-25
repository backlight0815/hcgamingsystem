"""Actual HTTP journal contract checks against the disposable localhost QA server."""
import json, pathlib, re, sqlite3, uuid
import requests

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/journal-repair-20261013'
fixture = json.loads((OUT/'private/fixture.json').read_text())
base = 'http://127.0.0.1:8765'
api = base + '/api/mobile/v1'
headers = {'Authorization':'Bearer '+fixture['token'], 'Accept':'application/json'}
results = []

def check(method, path, expected, **kwargs):
    response = requests.request(method, api+path, headers=kwargs.pop('headers', headers), timeout=30, **kwargs)
    item = {'method':method, 'path':path, 'status':response.status_code,
        'expected':expected, 'json':response.headers.get('Content-Type','').startswith('application/json')}
    results.append(item)
    assert response.status_code == expected and item['json'], item
    return response.json()

check('GET','/journal-sync/changes',401,headers={'Accept':'application/json'})
check('GET','/journal-sync/changes?cursor=-1',422)
check('GET','/journal-sync/999999999',404)
check('GET','/journal-sync/statistics',200)
trading_data=check('GET','/trading-data',200)
assert 'capitals' in trading_data['data']
mobile_id='http-contract-'+uuid.uuid4().hex
payload={'mobile_id':mobile_id,'fields':{'status':'closed','open_date':'2026-10-12T08:00:00',
    'close_date':'2026-10-12T09:00:00','pair':'EURUSD','direction':1,'entry_price':1.1234,
    'exit_price':1.1244,'lot_size':0.1,'profit_loss':10,'pips':10,'result':1,
    'notes':mobile_id,'mobile_trading_account_id':'mobile-only-account'}}
created=check('POST','/journal-sync',201,json=payload)['data']['record']
sync_id=created['sync_id']
try:
    retry=check('POST','/journal-sync',200,json=payload)
    assert retry['data']['status']=='unchanged'
    check('GET',f'/journal-sync/{sync_id}',200)
    check('GET',f'/journal-sync/{sync_id}',404,headers={**headers,'Authorization':'Bearer '+fixture['other_token']})
    index=check('GET','/journal-sync?symbol=EURUSD',200)
    assert sum(r['mobile_id']==mobile_id for r in index['data']['journals'])==1
    with sqlite3.connect(OUT/'private/journal.sqlite') as db:
        assert db.execute('select count(*) from trading_journals where notes=?',(mobile_id,)).fetchone()[0]==1
    # Same database row is rendered by the existing website after API creation.
    session=requests.Session()
    login=session.get(base+'/login')
    csrf=re.search(r'name="_token"\s+value="([^"]+)"',login.text).group(1)
    session.post(base+'/login',data={'_token':csrf,'username':'journalqa','password':fixture['password']})
    website=session.get(base+'/all/trading-journals')
    visible=website.status_code==200 and mobile_id in website.text
    results.append({'scenario':'API-created persisted row appears on website','passed':visible})
    assert visible
    updated=check('PATCH',f'/journal-sync/{sync_id}',200,json={
        'mobile_id':mobile_id,'base_revision':created['revision'],'fields':{'notes':'Updated '+mobile_id}})['data']['record']
    check('PATCH',f'/journal-sync/{sync_id}',409,json={
        'mobile_id':mobile_id,'base_revision':created['revision'],'fields':{'notes':'Stale '+mobile_id}})
    check('PATCH',f'/journal-sync/{sync_id}',422,json={
        'mobile_id':mobile_id,'base_revision':updated['revision'],'fields':{'lot_size':-1}})
finally:
    current=check('GET',f'/journal-sync/{sync_id}',200)['data']
    check('DELETE',f'/journal-sync/{sync_id}',200,json={
        'mobile_id':mobile_id,'base_revision':current['revision']})
check('GET',f'/journal-sync/{sync_id}',404)
check('POST','/journal-sync',409,json=payload)
(OUT/'http-contract.json').write_text(json.dumps(results,indent=2))
print(json.dumps({'checks':len(results),'passed':True}))
