@extends('admin.admin_master')
@section('admin')
<title>TradingHub AI | HC TradingHub</title>
<style>
.coach { --ink:#14253a;--muted:#637387;--line:#dbe3ea;--paper:#fff;--wash:#f4f7fa;--navy:#102b43;--teal:#117f78;color:var(--ink);padding:22px 0 34px }
.coach * { box-sizing:border-box }.coach-shell { display:grid;grid-template-columns:248px minmax(0,1fr) 275px;min-height:calc(100vh - 185px);max-height:920px;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:var(--paper);box-shadow:0 14px 42px rgba(15,35,54,.07) }
.coach button,.coach input,.coach select,.coach textarea { font:inherit }.coach button { cursor:pointer }.coach button:focus-visible,.coach input:focus-visible,.coach select:focus-visible,.coach textarea:focus-visible { outline:3px solid #34b8ae;outline-offset:2px }
.coach-sidebar { background:#f7f9fb;border-right:1px solid var(--line);padding:18px;overflow:auto }.coach-brand { display:flex;gap:10px;align-items:center;margin-bottom:23px }.coach-mark { display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:var(--navy);color:#fff;font-weight:800 }.coach-brand strong { display:block;font-size:14px;letter-spacing:.04em }.coach-brand small { color:var(--muted);font-size:11px }.coach-new,.coach-primary { border:0;border-radius:9px;background:var(--navy);color:#fff;padding:10px 14px;font-weight:700 }.coach-new { width:100%;text-align:left }.coach-search { width:100%;margin:16px 0;padding:9px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink) }.coach-side-label { color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:800;margin:14px 0 8px }.coach-list { display:grid;gap:4px }.coach-item { display:flex;align-items:center;gap:5px;border-radius:8px;padding:3px }.coach-item.active { background:#e2eeef }.coach-item button { border:0;background:none;color:var(--ink) }.coach-item-title { flex:1;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:8px 5px }.coach-item-action { width:26px;height:26px;color:var(--muted)!important }.coach-more { border:1px solid var(--line);background:#fff;border-radius:7px;padding:6px 10px;margin-top:12px;color:var(--ink) }
.coach-main { display:flex;flex-direction:column;min-width:0;min-height:0;background:var(--paper) }.coach-top { display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 24px;border-bottom:1px solid var(--line) }.coach-top h1 { margin:0;font-size:18px;font-weight:800;color:var(--ink) }.coach-top p { margin:3px 0 0;color:var(--muted);font-size:12px }.coach-badge { border:1px solid #b7dcd6;background:#eaf8f5;color:#09645e;border-radius:99px;padding:5px 9px;font-size:11px;font-weight:800;white-space:nowrap }.coach-mobile-history { display:none }.coach-scroll { flex:1;overflow:auto;padding:28px max(24px,calc((100% - 760px)/2));scroll-behavior:smooth }.coach-welcome { max-width:760px;margin:8% auto 0 }.coach-welcome .eyebrow { color:var(--teal);font-size:12px;letter-spacing:.15em;font-weight:800;text-transform:uppercase }.coach-welcome h2 { font-size:clamp(29px,3vw,42px);line-height:1.13;margin:10px 0 12px;color:var(--navy) }.coach-welcome p { font-size:15px;line-height:1.6;color:var(--muted);max-width:610px }.coach-quick { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:28px }.coach-quick button { text-align:left;border:1px solid var(--line);border-radius:11px;background:#fff;padding:15px;color:var(--ink);min-height:68px;font-weight:700;transition:border-color .15s,transform .15s }.coach-quick button:hover { border-color:var(--teal);transform:translateY(-2px) }.coach-quick span { color:var(--teal);margin-right:8px }
.coach-message { max-width:760px;margin:0 auto 22px }.coach-message-head { display:flex;gap:8px;align-items:center;color:var(--muted);font-size:12px;margin-bottom:7px }.coach-avatar { width:25px;height:25px;border-radius:7px;display:grid;place-items:center;background:#e8f3f1;color:var(--teal);font-weight:800 }.coach-user .coach-avatar { background:#e8eef6;color:var(--navy) }.coach-bubble { border:1px solid var(--line);border-radius:11px;padding:15px 17px;background:#fff;line-height:1.65;overflow-wrap:anywhere }.coach-user .coach-bubble { background:#f1f5f8 }.coach-error .coach-bubble { background:#fff3f2;border-color:#eeb9b4;color:#9a3330 }.coach-bubble p:last-child { margin-bottom:0 }.coach-bubble h2,.coach-bubble h3 { font-size:15px;margin:13px 0 6px }.coach-bubble h2:first-child,.coach-bubble h3:first-child { margin-top:0 }.coach-bubble ul,.coach-bubble ol { padding-left:22px }.coach-bubble pre { white-space:pre-wrap;background:#f0f4f7;padding:10px;border-radius:7px }.coach-table { overflow:auto;max-width:100% }.coach-table table { border-collapse:collapse;min-width:440px;width:100% }.coach-table td,.coach-table th { border:1px solid var(--line);padding:7px;text-align:left }.coach-sources { margin-top:10px;font-size:11px;color:var(--muted) }.coach-actions { display:flex;gap:10px;margin-top:7px }.coach-actions button { border:0;background:none;color:var(--teal);font-size:12px;font-weight:700;padding:2px }
.coach-composer { border-top:1px solid var(--line);padding:14px 24px 18px;background:#fff }.coach-form { display:flex;gap:10px;align-items:flex-end;max-width:760px;margin:auto }.coach-input { flex:1;min-width:0;max-height:160px;min-height:48px;resize:none;border:1px solid #cad5de;border-radius:10px;padding:12px 13px;color:var(--ink);background:#fff }.coach-primary { min-height:46px }.coach-primary:disabled { opacity:.55;cursor:not-allowed }.coach-form-note { max-width:760px;margin:7px auto 0;color:var(--muted);font-size:11px }.coach-status { max-width:760px;margin:0 auto 8px;color:#9a3330;font-size:12px;min-height:16px }
.coach-context { background:#fbfcfd;border-left:1px solid var(--line);padding:21px;overflow:auto }.coach-context h2 { font-size:14px;color:var(--ink);margin:0 0 16px }.coach-context h3 { font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin:22px 0 10px }.coach-context label { display:block;font-size:11px;font-weight:700;color:var(--muted);margin:11px 0 4px }.coach-context input,.coach-context select { width:100%;padding:8px;border:1px solid var(--line);background:#fff;border-radius:7px;color:var(--ink) }.coach-context button { margin-top:12px;border:1px solid var(--navy);background:#fff;color:var(--navy);padding:8px 11px;border-radius:8px;font-weight:700 }.coach-context .coach-facts { display:grid;gap:8px }.coach-fact { padding:10px;border:1px solid var(--line);background:#fff;border-radius:8px;font-size:12px }.coach-fact strong { display:block;margin-bottom:3px }.coach-muted { font-size:11px;color:var(--muted);line-height:1.5 }.coach-risk-result { margin-top:10px;white-space:pre-wrap;font-size:12px;line-height:1.5 }
@media(max-width:1199px) { .coach-shell { grid-template-columns:220px minmax(0,1fr) }.coach-top #coachContextButton { display:inline-block;border:1px solid var(--line);border-radius:8px;padding:6px 9px;background:#fff;color:var(--ink) }.coach-context { display:none }.coach-context.open { display:block;position:absolute;z-index:21;right:12px;top:100px;width:min(340px,calc(100vw - 24px));max-height:75dvh;border:1px solid var(--line);border-radius:12px;box-shadow:0 16px 40px #14253a30 } }
@media(max-width:767px) { .coach { padding:0 0 20px }.coach-shell { display:block;border-radius:0;min-height:calc(100dvh - 92px);max-height:none }.coach-sidebar { display:none;position:absolute;z-index:20;left:12px;right:12px;top:90px;max-height:70dvh;border:1px solid var(--line);border-radius:12px;box-shadow:0 16px 40px #14253a30 }.coach-sidebar.open { display:block }.coach-main { height:calc(100dvh - 93px);min-height:560px }.coach-top { padding:13px 16px }.coach-mobile-history { display:inline-block;border:1px solid var(--line);border-radius:8px;padding:6px 9px;background:#fff;color:var(--ink) }.coach-top h1 { font-size:15px }.coach-scroll { padding:18px 15px }.coach-welcome { margin-top:3% }.coach-quick { grid-template-columns:1fr 1fr;gap:7px }.coach-quick button { min-height:76px;padding:10px;font-size:12px }.coach-composer { padding:10px 13px max(13px,env(safe-area-inset-bottom)) }.coach-form { gap:6px }.coach-primary { padding:10px }.coach-badge { display:none } }
@media(max-width:767px){.coach-shell{min-height:0}.coach-main{min-height:0;height:calc(100dvh - 93px)}.coach-scroll{min-height:0}}
.coach-message-head{flex-wrap:wrap}.coach-answer-type{border-radius:99px;background:#edf4f6;color:#286070;padding:2px 7px;font-size:10px;font-weight:700}.coach-actions{flex-wrap:wrap}
@media(max-width:767px){.coach-top{flex-wrap:wrap}.coach-badge{display:inline-flex;font-size:10px}.coach-quick{grid-template-columns:1fr}}
.coach-welcome h3{font-size:13px;margin:24px 0 8px;color:var(--navy)}
</style>
<div class="page-content coach"><div class="container-fluid"><div class="coach-shell">
    <aside id="coachSidebar" class="coach-sidebar" aria-label="Conversations">
        <div class="coach-brand"><span class="coach-mark">HC</span><div><strong>TRADINGHUB AI</strong><small>Research & education</small></div></div>
        <button id="coachNew" class="coach-new" type="button">＋ New Chat</button>
        <input id="coachSearch" class="coach-search" type="search" placeholder="Search conversations" aria-label="Search conversations">
        <div class="coach-side-label">Your conversations</div><div id="coachList" class="coach-list"></div>
        <button id="coachMore" class="coach-more" type="button" hidden>Load more</button>
    </aside>
    <main class="coach-main"><header class="coach-top"><div><h1><button id="coachHistory" class="coach-mobile-history" type="button" aria-label="Show conversation history">☰</button> HC TradingHub AI</h1><p>Your Trading Knowledge & Market Structure Assistant</p></div><button id="coachContextButton" class="coach-mobile-history" type="button" aria-label="Show trading context and preferences">Context</button><span id="coachMode" class="coach-badge">CHECKING KNOWLEDGE</span></header>
        <div id="coachScroll" class="coach-scroll" role="log" aria-live="polite"></div>
        <div class="coach-composer"><div id="coachStatus" class="coach-status" role="alert"></div><form id="coachForm" class="coach-form"><textarea id="coachInput" class="coach-input" maxlength="2000" placeholder="Ask about structure, risk, TP / SL, psychology…" aria-label="Message" required></textarea><button id="coachSend" class="coach-primary" type="submit">Send</button><button id="coachStop" class="coach-primary" type="button" hidden>Stop</button></form><p class="coach-form-note">Enter to send · Shift+Enter for a new line · Historical outlooks are labeled with their source and date.</p></div>
    </main>
    <aside class="coach-context" aria-label="Trading context"><h2>Workspace context</h2><div class="coach-facts"><div class="coach-fact"><strong>Structure hierarchy</strong>H4 major · H1 internal · M15 execution</div><div id="coachMarket" class="coach-fact"><strong>Market data</strong>No live quote connected</div></div>
        <h3>Structure check</h3><p class="coach-muted">Educational check using your inputs. No chart data is inferred.</p><form id="coachStructure"><label for="structureH4">H4 major</label><select id="structureH4"><option value="unknown">Unknown</option><option value="bullish">Bullish</option><option value="bearish">Bearish</option><option value="ranging">Ranging</option></select><label for="structureH1">H1 internal</label><select id="structureH1"><option value="unknown">Unknown</option><option value="bullish">Bullish</option><option value="bearish">Bearish</option><option value="ranging">Ranging</option></select><label for="structureM15">M15 current</label><select id="structureM15"><option value="unknown">Unknown</option><option value="bullish">Bullish</option><option value="bearish">Bearish</option><option value="bullish_choch">Bullish CHOCH</option><option value="bearish_choch">Bearish CHOCH</option><option value="ranging">Ranging</option></select><label><input id="structureInvalid" type="checkbox" style="width:auto"> H1 protected swing invalidated</label><label><input id="structureZone" type="checkbox" style="width:auto"> At a relevant H1 zone</label><label><input id="structureReaction" type="checkbox" style="width:auto"> M15 reaction confirmed</label><label><input id="structureRange" type="checkbox" style="width:auto"> Price in range middle</label><button type="submit">Evaluate structure</button></form><div id="coachStructureResult" class="coach-risk-result" role="status"></div>
        <h3>Your preferences</h3><form id="coachPrefs"><label for="prefExperience">Experience</label><select id="prefExperience"><option>beginner</option><option>intermediate</option><option>advanced</option></select><label for="prefStyle">Trading style</label><select id="prefStyle"><option>intraday</option><option>scalping</option><option>swing</option><option>position</option></select><label for="prefTimeframe">Execution timeframe</label><select id="prefTimeframe"><option>M15</option><option>M5</option><option>M30</option><option>H1</option><option>H4</option><option>D1</option></select><label for="prefRisk">Default risk per idea (%)</label><input id="prefRisk" type="number" step="0.01" min="0.01" max="1"><label for="prefMaxRisk">Personal maximum risk (%)</label><input id="prefMaxRisk" type="number" step="0.01" min="0.01" max="1"><label for="prefRr">Preferred minimum R:R</label><input id="prefRr" type="number" step="0.1" min="1" max="10"><label for="prefInstruments">Preferred symbols</label><input id="prefInstruments" type="text" maxlength="255" placeholder="XAUUSD, EURUSD"><label for="prefLanguage">Language</label><select id="prefLanguage"><option value="en">English</option><option value="zh">Chinese</option><option value="ms">Malay</option></select><label for="prefDetail">Answer detail</label><select id="prefDetail"><option>standard</option><option>concise</option><option>detailed</option></select><button type="submit">Save preferences</button></form><p class="coach-muted">Risk across one idea stays at or below the lower personal or administrator cap, including related entries.</p>
        <h3>Risk worksheet</h3><form id="coachRisk"><label for="riskEquity">Account equity</label><input id="riskEquity" type="number" step="any" min="0.01" required><label for="riskExisting">Risk already committed to this idea</label><input id="riskExisting" type="number" step="any" min="0"><label for="riskSide">Direction</label><select id="riskSide"><option value="buy">Buy</option><option value="sell">Sell</option></select><label for="riskEntry">Entry</label><input id="riskEntry" type="number" step="any" min="0.000001"><label for="riskStop">Structural stop</label><input id="riskStop" type="number" step="any" min="0.000001"><label for="riskTarget">Nearest opposing level</label><input id="riskTarget" type="number" step="any" min="0.000001"><details><summary>Verified instrument specifications</summary><p class="coach-muted">Enter tick value in account currency, or supply a conversion factor.</p><label for="riskTickSize">Tick size</label><input id="riskTickSize" type="number" step="any" min="0.000001"><label for="riskTickValue">Tick value</label><input id="riskTickValue" type="number" step="any" min="0.000001"><label for="riskContract">Contract size (reference)</label><input id="riskContract" type="number" step="any" min="0.000001"><label for="riskConversion">Currency conversion</label><input id="riskConversion" type="number" step="any" min="0.000001" placeholder="1 if already account currency"><label for="riskCost">Estimated cost per volume unit</label><input id="riskCost" type="number" step="any" min="0"><label for="riskStep">Volume step</label><input id="riskStep" type="number" step="any" min="0.000001"><label for="riskMin">Minimum volume</label><input id="riskMin" type="number" step="any" min="0.000001"></details><button type="submit">Evaluate risk</button></form><div id="coachRiskResult" class="coach-risk-result" role="status"></div>
        <h3>Cost-adjusted break-even</h3><form id="coachBe"><label for="beEntry">Entry price</label><input id="beEntry" type="number" step="any" min="0.000001" required><label for="beCosts">Total estimated costs in account currency</label><input id="beCosts" type="number" step="any" min="0" required><label for="beUnits">Position units / lots</label><input id="beUnits" type="number" step="any" min="0.000001" required><label for="beValue">Account-currency value per 1.0 price move per unit</label><input id="beValue" type="number" step="any" min="0.000001" required><button type="submit">Calculate BE</button></form><div id="coachBeResult" class="coach-risk-result" role="status"></div>
    </aside>
</div></div></div>
<script>
(() => {
    const base = @json(route('ai.assistant.index', [], false));
    const token = @json(csrf_token());
    const el = id => document.getElementById(id);
    let current = null, nextPage = null, messagePage = null, activeRequest = null, lastQuestion = null, lastFailed = false;
    const headers = {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token};
    const parseUtc = value => { if(!value) return new Date(); const text=String(value).replace(' ','T'); return new Date(/[zZ]$|[+-]\d\d:\d\d$/.test(text)?text:text+'Z') };
    const myTime = value => new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}).format(parseUtc(value))+' MYT';
    const myDate = value => new Intl.DateTimeFormat('en-MY',{timeZone:'Asia/Kuala_Lumpur',year:'numeric',month:'short',day:'numeric'}).format(parseUtc(value));
    async function api(path, options={}) { const response=await fetch(base+path,{headers,...options}); const data=await response.json().catch(()=>({})); if(!response.ok) throw new Error(data.message || 'Request failed. Please retry.'); return data; }
    function status(message='') { el('coachStatus').textContent=message; }
    function scrollEnd() { el('coachScroll').scrollTop=el('coachScroll').scrollHeight; }
    function welcome() {
        el('coachScroll').replaceChildren();
        const section=document.createElement('section');section.className='coach-welcome';
        section.innerHTML='<div class="eyebrow">Your research desk</div><h2>Ask a trading question with confidence.</h2><p>Approved lessons answer directly. Current prices and chart images are unavailable unless a verified source is connected.</p>';
        const groups=[
            ['Basics',[['Bid, ask and spread','What are bid ask spread and slippage?'],['Asset differences','How do contract specifications differ across asset classes?']]],
            ['Market Structure',[['BOS and CHOCH','What are BOS CHOCH and a liquidity sweep?'],['H4 / H1 / M15','How do H4 H1 and M15 work together?']]],
            ['Risk, SL / TP / BE',[['Risk per idea','How do I calculate reward risk and total idea exposure?'],['Plan an exit','How should I plan entry stop loss take profit and break even?']]],
            ['Psychology',[['FOMO and revenge trading','What should I do after FOMO or revenge trading?']]],
            ['Journal',[['Interpret statistics','How should I interpret win rate expectancy and drawdown in my journal?']]],
            ['Platform Help',[['Available features','What TradingHub features can this assistant actually access?']]],
        ];
        groups.forEach(([name,prompts])=>{const heading=document.createElement('h3');heading.textContent=name;const grid=document.createElement('div');grid.className='coach-quick';prompts.forEach(([label,question])=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.addEventListener('click',()=>send(question));grid.append(button)});section.append(heading,grid)});
        el('coachScroll').append(section);
    }
    function markdown(text) { const frag=document.createDocumentFragment(); const lines=String(text).split(/\r?\n/); let list=null; for (let i=0;i<lines.length;i++) {const line=lines[i]; if(/^\|.+\|$/.test(line) && lines[i+1] && /^\|[\s:|-]+\|$/.test(lines[i+1])) {const wrap=document.createElement('div');wrap.className='coach-table';const table=document.createElement('table');const head=document.createElement('tr');line.split('|').slice(1,-1).forEach(x=>{const c=document.createElement('th');c.textContent=x.trim();head.append(c)});table.append(head);i+=2;while(i<lines.length && /^\|.+\|$/.test(lines[i])) {const tr=document.createElement('tr');lines[i].split('|').slice(1,-1).forEach(x=>{const c=document.createElement('td');c.textContent=x.trim();tr.append(c)});table.append(tr);i++}i--;wrap.append(table);frag.append(wrap);list=null;continue} const heading=line.match(/^(#{1,3})\s+(.+)/); if(heading){const h=document.createElement('h3');h.textContent=heading[2];frag.append(h);list=null;continue}const bullet=line.match(/^\s*([-*]|\d+\.)\s+(.+)/);if(bullet){const kind=/\d/.test(bullet[1])?'ol':'ul';if(!list || list.tagName.toLowerCase()!==kind){list=document.createElement(kind);frag.append(list)}const li=document.createElement('li');li.textContent=bullet[2];list.append(li);continue}list=null;if(line.trim()){const p=document.createElement('p');p.textContent=line;frag.append(p)}}return frag; }
    function message(text, role, sources=[], timestamp=null, id=null, questionId=null, answerType=null) {
        const article=document.createElement('article');article.className='coach-message coach-'+role;
        const head=document.createElement('div');head.className='coach-message-head';
        const avatar=document.createElement('span');avatar.className='coach-avatar';avatar.textContent=role==='user'?'You':'HC';
        head.append(avatar,document.createTextNode(role==='user'?'You':'TradingHub AI'));
        if(role==='assistant') {const label=document.createElement('span');label.className='coach-answer-type';label.textContent=({educational_explanation:'Educational explanation',historical_analysis:'Historical/data-backed analysis',unable_to_verify:'Unable to verify'})[answerType]||'Assistant response';head.append(label)}
        const time=document.createElement('time');time.textContent=' · '+myTime(timestamp);head.append(time);
        const bubble=document.createElement('div');bubble.className='coach-bubble';bubble.append(markdown(text));article.append(head,bubble);
        if(sources?.length){const source=document.createElement('div');source.className='coach-sources';source.textContent='Sources: '+sources.map(x=>x.title+(x.version?' v'+x.version:'')+(x.published_at?' ('+myDate(x.published_at)+')':'')).join(' · ');article.append(source)}
        if(role==='assistant'){
            const actions=document.createElement('div');actions.className='coach-actions';
            const copy=document.createElement('button');copy.type='button';copy.textContent='Copy answer';copy.addEventListener('click',()=>navigator.clipboard.writeText(text));actions.append(copy);
            if(questionId){const retry=document.createElement('button');retry.type='button';retry.textContent='Regenerate';retry.addEventListener('click',()=>retryAnswer(questionId));actions.append(retry)}
            if(id){[['Helpful','helpful'],['Not helpful','not_helpful']].forEach(([label,rating])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.addEventListener('click',async()=>{try{let issue_category=null;if(rating==='not_helpful'){issue_category=prompt('What needs review? Enter incorrect, outdated, unsafe, unclear, missing_data, or other.','other');if(issue_category===null)return;if(!['incorrect','outdated','unsafe','unclear','missing_data','other'].includes(issue_category))issue_category='other'}await api('/feedback',{method:'POST',body:JSON.stringify({message_id:id,rating,issue_category})});status('Feedback saved for review.')}catch(e){status(e.message)}});actions.append(b)})}
            article.append(actions);
        }
        el('coachScroll').append(article);scrollEnd();return article;
    }
    async function loadList(append=false) {const q=encodeURIComponent(el('coachSearch').value.trim());const page=append&&nextPage?nextPage:1;try{const data=await api('/conversations?search='+q+'&page='+page);if(!append)el('coachList').replaceChildren();const labels=el('coachList').querySelectorAll('.coach-side-label');let lastDate=labels.length?labels[labels.length-1].dataset.date:null;data.data.forEach(item=>{const date=new Date(item.updated_at).toLocaleDateString();if(date!==lastDate){const label=document.createElement('div');label.className='coach-side-label';label.dataset.date=date;label.textContent=date;el('coachList').append(label);lastDate=date}const row=document.createElement('div');row.className='coach-item'+(item.id===current?' active':'');const title=document.createElement('button');title.className='coach-item-title';title.textContent=item.title;title.addEventListener('click',()=>openConversation(item.id));const rename=document.createElement('button');rename.className='coach-item-action';rename.title='Rename';rename.textContent='✎';rename.addEventListener('click',async()=>{const name=prompt('Conversation title',item.title);if(!name?.trim())return;try{await api('/conversations/'+item.id,{method:'PATCH',body:JSON.stringify({title:name.trim()})});loadList()}catch(e){status(e.message)}});const del=document.createElement('button');del.className='coach-item-action';del.title='Delete';del.textContent='×';del.addEventListener('click',async()=>{if(!confirm('Delete this conversation?'))return;try{await api('/conversations/'+item.id,{method:'DELETE'});if(current===item.id){current=null;welcome()}loadList()}catch(e){status(e.message)}});row.append(title,rename,del);el('coachList').append(row)});nextPage=data.next_page_url?data.current_page+1:null;el('coachMore').hidden=!nextPage}catch(e){status(e.message)}}
    async function loadOlder(){if(!messagePage||!current)return;const scroll=el('coachScroll');const height=scroll.scrollHeight;try{const data=await api('/conversations/'+current+'/messages?page='+messagePage);scroll.querySelector('.coach-older')?.remove();data.data.forEach(m=>{const article=message(m.content,m.role,m.sources?JSON.parse(m.sources):[],m.created_at,m.id,m.reply_to_message_id,m.answer_type);scroll.prepend(article)});messagePage=data.next_page_url?data.current_page+1:null;if(messagePage){const button=document.createElement('button');button.type='button';button.className='coach-more coach-older';button.textContent='Load older messages';button.addEventListener('click',loadOlder);scroll.prepend(button)}scroll.scrollTop=scroll.scrollHeight-height}catch(e){status(e.message)}}
    async function openConversation(id){current=id;status();try{const data=await api('/conversations/'+id+'/messages');el('coachScroll').replaceChildren();data.data.slice().reverse().forEach(m=>message(m.content,m.role,m.sources?JSON.parse(m.sources):[],m.created_at,m.id,m.reply_to_message_id,m.answer_type));messagePage=data.next_page_url?data.current_page+1:null;if(messagePage){const button=document.createElement('button');button.type='button';button.className='coach-more coach-older';button.textContent='Load older messages';button.addEventListener('click',loadOlder);el('coachScroll').prepend(button)}if(!data.data.length)welcome();el('coachSidebar').classList.remove('open');loadList()}catch(e){status(e.message)}}
    function newClientId() {
        if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
            const random = Math.floor(Math.random() * 16);
            return (character === 'x' ? random : (random & 3 | 8)).toString(16);
        });
    }
    function retryNotice(text, clientId, saved) {
        const notice = message(saved ? 'Your question is saved. The answer is still being processed or could not be displayed.' : 'The question could not be submitted. Your text is ready to retry.', 'error');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'coach-more';
        button.textContent = 'Retry';
        button.addEventListener('click', () => { notice.remove(); send(text, clientId, false); });
        notice.append(button);
    }
    async function send(question, clientId = newClientId(), renderQuestion = true) {
        if (activeRequest) return;
        const text = question.trim();
        if (text.length < 2) { status('Please enter at least two characters.'); return; }
        status();
        lastQuestion = text;
        lastFailed = false;
        el('coachInput').value = '';
        el('coachScroll').querySelector('.coach-welcome')?.remove();
        if (renderQuestion) message(text, 'user');
        const loading = message('Checking approved knowledge…', 'assistant');
        activeRequest = new AbortController();
        el('coachSend').disabled = true;
        el('coachStop').hidden = false;
        try {
            const response = await fetch(base + '/ask', {
                method: 'POST', headers, signal: activeRequest.signal,
                body: JSON.stringify({message: text, conversation_id: current, client_message_id: clientId})
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const failure = new Error(data.message || 'Request failed. Please retry.');
                failure.saved = Boolean(data.question_id);
                failure.conversationId = data.conversation_id;
                throw failure;
            }
            loading.remove();
            current = data.conversation_id || current;
            if (response.status === 202) {
                status(data.message || 'Question saved and still processing.');
                retryNotice(text, clientId, true);
                loadList();
                return;
            }
            message(data.answer, 'assistant', data.sources, null, data.message_id, data.question_id, data.answer_type);
            if (data.unanswered) status(data.review_recorded === true ? 'Question saved for review. You can continue using approved knowledge.' : data.review_recorded === false ? 'Question saved. The review queue is unavailable.' : 'Question saved. Check the answer details.');
            if (data.market) el('coachMarket').textContent = 'Historical ' + data.market.symbol + ' outlook · ' + (data.market.published_at ? myTime(data.market.published_at) : 'date unavailable');
            if (data.insight) el('coachMarket').textContent = 'Verified REAL snapshots · ' + data.insight.symbol + ' · ' + Object.entries(data.insight.timeframes).map(([tf, frame]) => tf + ': ' + frame.status).join(' · ');
            loadList();
        } catch (error) {
            loading.remove();
            lastFailed = true;
            if (error.name === 'AbortError') {
                status('Request stopped in this browser. The question may have been saved; refresh history or retry.');
                retryNotice(text, clientId, true);
            } else {
                if (error.conversationId) current = error.conversationId;
                if (!error.saved) el('coachInput').value = text;
                status(error.message);
                retryNotice(text, clientId, Boolean(error.saved));
            }
        } finally {
            activeRequest = null;
            el('coachSend').disabled = false;
            el('coachStop').hidden = true;
            el('coachInput').focus();
        }
    }
    async function retryAnswer(questionId, attemptId = newClientId()) {
        if(activeRequest || !questionId) return;
        const loading=message('Rechecking verified knowledge…','assistant');
        activeRequest=new AbortController();el('coachSend').disabled=true;el('coachStop').hidden=false;
        try {
            const response=await fetch(base+'/questions/'+questionId+'/retry',{method:'POST',headers,
                signal:activeRequest.signal,body:JSON.stringify({attempt_id:attemptId})});
            const data=await response.json().catch(()=>({}));
            if(response.status===202){status(data.message||'The answer is still processing.');return}
            if(!response.ok) throw new Error(data.message||'The answer could not be retried.');
            loading.remove();message(data.answer,'assistant',data.sources,null,data.message_id,data.question_id,data.answer_type);
            status(data.unanswered?'The answer is still unverified; the saved question remains available.':'New answer attempt saved.');
        } catch(error) {
            loading.remove();status(error.name==='AbortError'?'Retry stopped in this browser. Refresh history to check the saved attempt.':error.message);
        } finally {loading.remove();activeRequest=null;el('coachSend').disabled=false;el('coachStop').hidden=true}
    }
    async function loadStatus() {
        try {
            const data = await api('/status');
            el('coachMode').textContent = data.knowledge_available ? 'KNOWLEDGE READY' : 'ASK OR REQUEST REVIEW';
            el('coachMode').title = data.external_generation_enabled ? 'Approved knowledge first; external generation is enabled but may be unavailable.' : 'Approved knowledge and a safe fallback are available.';
        } catch (error) {
            el('coachMode').textContent = 'ASSISTANT STATUS UNKNOWN';
        }
    }
    el('coachForm').addEventListener('submit',e=>{e.preventDefault();send(el('coachInput').value)});el('coachInput').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();el('coachForm').requestSubmit()}});el('coachInput').addEventListener('input',e=>{e.target.style.height='auto';e.target.style.height=Math.min(e.target.scrollHeight,160)+'px'});el('coachStop').addEventListener('click',()=>activeRequest?.abort());el('coachNew').addEventListener('click',async()=>{try{const data=await api('/conversations',{method:'POST',body:'{}'});current=data.id;welcome();loadList();el('coachSidebar').classList.remove('open')}catch(e){status(e.message)}});el('coachHistory').addEventListener('click',()=>el('coachSidebar').classList.toggle('open'));el('coachContextButton').addEventListener('click',()=>document.querySelector('.coach-context').classList.toggle('open'));el('coachSearch').addEventListener('input',()=>loadList());el('coachMore').addEventListener('click',()=>loadList(true));
    async function loadPrefs(){try{const p=await api('/preferences');el('prefExperience').value=p.experience;el('prefStyle').value=p.style;el('prefTimeframe').value=p.timeframe;el('prefRisk').value=p.risk_percent;el('prefMaxRisk').value=p.maximum_risk_percent;el('prefRr').value=p.preferred_rr;el('prefInstruments').value=p.preferred_instruments;el('prefLanguage').value=p.language;el('prefDetail').value=p.answer_detail}catch(e){status(e.message)}}el('coachPrefs').addEventListener('submit',async e=>{e.preventDefault();try{await api('/preferences',{method:'PUT',body:JSON.stringify({experience:el('prefExperience').value,style:el('prefStyle').value,timeframe:el('prefTimeframe').value,risk_percent:el('prefRisk').value,maximum_risk_percent:el('prefMaxRisk').value,preferred_rr:el('prefRr').value,preferred_instruments:el('prefInstruments').value,language:el('prefLanguage').value,answer_detail:el('prefDetail').value})});status('Preferences saved.')}catch(error){status(error.message)}});
    el('coachRisk').addEventListener('submit',async e=>{e.preventDefault();const fields={equity:'riskEquity',risk_percent:'prefRisk',existing_idea_risk:'riskExisting',side:'riskSide',entry:'riskEntry',stop:'riskStop',opposing_level:'riskTarget',tick_size:'riskTickSize',tick_value:'riskTickValue',contract_size:'riskContract',currency_conversion:'riskConversion',cost_per_unit:'riskCost',volume_step:'riskStep',minimum_volume:'riskMin'};const body={};Object.entries(fields).forEach(([key,id])=>{if(el(id).value!=='')body[key]=el(id).value});try{const r=await api('/risk',{method:'POST',body:JSON.stringify(body)});el('coachRiskResult').textContent='Risk budget: '+r.risk_budget+' · Classification: '+r.classification+(r.reward_ratio!==null?' · R:R 1:'+r.reward_ratio:'')+(r.position_size!==null?' · Size: '+r.position_size:'')+'\n'+r.warnings.join('\n')}catch(error){el('coachRiskResult').textContent=error.message}});
    el('coachBe').addEventListener('submit',async e=>{e.preventDefault();try{const r=await api('/break-even',{method:'POST',body:JSON.stringify({entry:el('beEntry').value,side:el('riskSide').value,total_costs:el('beCosts').value,units:el('beUnits').value,value_per_price_unit:el('beValue').value})});el('coachBeResult').textContent='Nominal BE: '+r.nominal_break_even+' · Cost-adjusted BE: '+r.cost_adjusted_break_even+'\n'+r.warning}catch(error){el('coachBeResult').textContent=error.message}});
    el('coachStructure').addEventListener('submit',async e=>{e.preventDefault();try{const r=await api('/structure',{method:'POST',body:JSON.stringify({h4:el('structureH4').value,h1:el('structureH1').value,m15:el('structureM15').value,h1_invalidated:el('structureInvalid').checked,at_h1_zone:el('structureZone').checked,m15_reaction_confirmed:el('structureReaction').checked,range_middle:el('structureRange').checked})});el('coachStructureResult').textContent=r.classification+'\n'+r.explanation+(r.warnings.length?'\n'+r.warnings.join('\n'):'')}catch(error){el('coachStructureResult').textContent=error.message}});
    welcome();loadList();loadPrefs();loadStatus();
})();
</script>
@endsection
