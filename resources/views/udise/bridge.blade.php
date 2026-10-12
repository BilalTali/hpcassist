<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HPC Assist — UDISE+ Auto-Sync</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1e293b;border:1px solid #334155;border-radius:20px;padding:36px 32px;max-width:540px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.5)}
.logo{font-size:38px;text-align:center;margin-bottom:10px}
h1{font-size:18px;font-weight:800;color:#f8fafc;text-align:center;margin-bottom:4px}
.sub{font-size:12px;color:#94a3b8;text-align:center;margin-bottom:24px}
.sbox{border-radius:12px;padding:16px 18px;margin-bottom:18px;border:1px solid transparent}
.sbox.waiting{background:#1a150a;border-color:#92400e}
.sbox.working{background:#0c1a2e;border-color:#1d4ed8}
.sbox.success{background:#052e16;border-color:#166534}
.sbox.error  {background:#1f0a0a;border-color:#991b1b}
.sicon{font-size:28px;margin-bottom:8px}
.stitle{font-size:14px;font-weight:700;margin-bottom:4px}
.smsg{font-size:12px;color:#cbd5e1;line-height:1.6}
.smsg code,.smsg strong{color:#7dd3fc}
.progress{height:4px;background:#334155;border-radius:2px;overflow:hidden;margin-top:14px}
.pbar{height:100%;border-radius:2px;transition:width .4s ease,background .3s}
.pbar.anim{animation:pp 1.5s ease-in-out infinite}
@keyframes pp{0%,100%{opacity:1}50%{opacity:.35}}
ul.steps{list-style:none;margin-bottom:18px}
ul.steps li{display:flex;align-items:center;gap:10px;padding:7px 12px;border-radius:8px;font-size:12px;color:#475569}
ul.steps li.done  {color:#34d399}
ul.steps li.active{color:#60a5fa;background:#1d3a5f}
ul.steps li .dot{width:7px;height:7px;border-radius:50%;background:currentColor;flex-shrink:0}
.badges{display:none;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.badge{background:#0f2d1a;border:1px solid #166534;border-radius:8px;padding:5px 14px;font-size:12px;font-weight:700;color:#4ade80}
.btn{display:block;width:100%;padding:11px;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:.2s;text-align:center;margin-top:8px}
.btn-primary{background:#0369a1;color:#fff}.btn-primary:hover{background:#0284c7}
.btn-confirm{background:#059669;color:#fff;display:none}.btn-confirm:hover{background:#047857}
.btn-retry{background:#334155;color:#cbd5e1;display:none}.btn-retry:hover{background:#475569}
.btn:disabled{opacity:.5;cursor:not-allowed}
.hint-box{background:#0f1a2e;border:1px solid #1e3a5f;border-radius:10px;padding:14px 16px;margin-top:12px;font-size:11px;color:#94a3b8;display:none;line-height:1.8}
.hint-box strong{color:#7dd3fc}
.snippet{background:#0a0f1a;border-radius:6px;padding:10px;margin-top:8px;word-break:break-all;font-size:10px;color:#34d399;font-family:monospace;border:1px solid #1e3a5f}
</style>
</head>
<body>
<div class="card">
  <div class="logo">🏫</div>
  <h1>UDISE+ Auto-Sync</h1>
  <p class="sub">Fetching your school's official student roster from UDISE+ SDMS</p>

  <div class="sbox waiting" id="sbox">
    <div class="sicon" id="sicon">⏳</div>
    <div class="stitle" id="stitle">Opening UDISE+ Login…</div>
    <div class="smsg" id="smsg">A UDISE+ tab is opening. Sign in with your school credentials, then click <strong>"I've Logged In"</strong> below.</div>
    <div class="progress"><div class="pbar anim" id="pbar" style="width:20%;background:#f59e0b"></div></div>
  </div>

  <ul class="steps">
    <li class="active" id="s1"><span class="dot"></span>Open UDISE+ tab &amp; log in</li>
    <li id="s2"><span class="dot"></span>Confirm login complete</li>
    <li id="s3"><span class="dot"></span>Navigate to student list</li>
    <li id="s4"><span class="dot"></span>Fetch student records &amp; APAAR IDs</li>
    <li id="s5"><span class="dot"></span>Save to HPC Assist</li>
    <li id="s6"><span class="dot"></span>Redirecting to dashboard</li>
  </ul>

  <div class="badges" id="badges"></div>

  <button class="btn btn-primary" id="openBtn" onclick="openUdise()">🌐 Open UDISE+ Tab Again</button>
  <button class="btn btn-confirm" id="confirmBtn" onclick="onLoginConfirmed()">✅ I've Logged In — Fetch Students Now</button>
  <button class="btn btn-retry" id="retryBtn" onclick="retry()">🔄 Retry Sync</button>

  <div class="hint-box" id="hintBox">
    <strong>If auto-fetch fails (UDISE+ security blocks injection):</strong><br>
    1. In the UDISE+ tab, press <strong>F12</strong> → Console<br>
    2. Type <code style="background:#0a0f1a;padding:2px 6px;border-radius:3px">allow pasting</code> and press Enter<br>
    3. Navigate to: <strong>UDISE+ Dashboard → Student → All Students</strong><br>
    4. Paste the snippet below and press Enter:
    <div class="snippet" id="bookmarklet"></div>
  </div>
</div>

<script>
// ── Config injected by server ────────────────────────────────────────────────
const SCHOOL_ID  = {{ $schoolId }};
const TOKEN      = '{{ addslashes($token) }}';
const UDISE_CODE = '{{ addslashes($udiseCode) }}';
const INGEST_URL = '{{ $ingestUrl }}';
const DASH_URL   = '{{ $dashUrl }}';

let udiseTab = null;
let fetching = false;
let done     = false;

// ── UI helpers ───────────────────────────────────────────────────────────────
function ui(type, icon, title, msg, pct) {
  document.getElementById('sbox').className    = 'sbox ' + type;
  document.getElementById('sicon').textContent  = icon;
  document.getElementById('stitle').textContent = title;
  document.getElementById('smsg').innerHTML     = msg;
  const bar = document.getElementById('pbar');
  bar.style.width      = pct + '%';
  bar.style.background = ({waiting:'#f59e0b',working:'#3b82f6',success:'#22c55e',error:'#ef4444'})[type] || '#3b82f6';
  bar.className = 'pbar' + (pct < 100 ? ' anim' : '');
}
const step = (id, cls) => { const el = document.getElementById(id); if (el) el.className = cls; };

// ── Open UDISE tab ───────────────────────────────────────────────────────────
function openUdise() {
  udiseTab = window.open('https://sdms.udiseplus.gov.in/', 'udise_sync_tab');
  if (!udiseTab) {
    ui('error','❌','Pop-up Blocked',
      'Your browser blocked the UDISE+ pop-up. <strong>Allow pop-ups</strong> for this site then click "Open UDISE+ Tab Again".',0);
    return;
  }
  document.getElementById('openBtn').style.display    = 'none';
  document.getElementById('confirmBtn').style.display = 'block';
  step('s1','active');
  ui('waiting','⏳','UDISE+ Tab Opened',
    'Sign in with your school credentials in the UDISE+ tab that just opened.<br>After login is complete, click <strong>"I\'ve Logged In — Fetch Students Now"</strong>.',35);
}

// ── User confirms they are logged in ─────────────────────────────────────────
function onLoginConfirmed() {
  if (fetching || done) return;
  if (!udiseTab || udiseTab.closed) {
    ui('error','❌','UDISE+ Tab Closed',
      'The UDISE+ tab was closed. Click "Retry Sync" to open it again.',0);
    document.getElementById('confirmBtn').style.display = 'none';
    document.getElementById('retryBtn').style.display   = 'block';
    return;
  }
  document.getElementById('confirmBtn').disabled = true;
  step('s1','done'); step('s2','done'); step('s3','active');
  ui('working','⚡','Detecting Student List Page…',
    'Please navigate to <strong>Student → All Students</strong> in the UDISE+ tab if not already there. Fetching will begin automatically.',55);
  fetching = true;
  injectAndFetch(0);
}

// ── Inject fetch script into UDISE tab, retrying on block ────────────────────
function injectAndFetch(attempt) {
  if (done) return;
  if (attempt > 8) { showManualFallback(); return; }

  const origin = window.location.origin;
  const script = `(async function(){
  try{
    let sId=location.href.match(/school\\/(\\d+)/)?.[1];
    if(!sId){for(const a of document.querySelectorAll('a[href*="/school/"]')){const m=a.href.match(/school\\/(\\d+)/);if(m){sId=m[1];break;}}}
    if(!sId&&typeof angular!=='undefined'){try{const sc=angular.element(document.querySelector('[ng-app]')).injector().get('$state');sId=sc.params&&sc.params.schoolId;}catch(e){}}
    if(!sId){const m2=document.body.innerHTML.match(/\\/school\\/(\\d+)\\//);if(m2)sId=m2[1];}
    opener&&opener.postMessage({t:'status',m:'School ID: '+(sId||'detecting')+'… Calling API…'},'${origin}');
    let students=[];
    const apis=sId?['/p0/api/cy/snapshot/students/all/'+sId,'/p0/api/cy/students/all/'+sId]:[];
    for(const api of apis){try{const r=await fetch(api,{credentials:'include'});if(!r.ok)continue;const j=await r.json();const arr=j.data||j.students||j.result||(Array.isArray(j)?j:[]);if(arr.length>0){students=arr;break;}}catch(e){continue;}}
    if(!students.length&&!sId){opener&&opener.postMessage({t:'no_school_id'},'${origin}');return;}
    if(!students.length){opener&&opener.postMessage({t:'no_students',schoolId:sId},'${origin}');return;}
    opener&&opener.postMessage({t:'students',count:students.length,data:JSON.stringify(students)},'${origin}');
  }catch(e){opener&&opener.postMessage({t:'error',m:'Error: '+e.message},'${origin}');}
})();`;

  let injected = false;
  try { udiseTab.eval(script); injected = true; } catch(e1) {}
  if (!injected) {
    try {
      const s = udiseTab.document.createElement('script');
      s.textContent = script;
      udiseTab.document.head.appendChild(s);
      injected = true;
    } catch(e2) {}
  }

  if (!injected) {
    const waitMsg = attempt < 3
      ? 'Waiting for UDISE+ page to load… (<strong>' + (attempt+1) + '/8</strong>)'
      : 'Please navigate to <strong>Student → All Students</strong> in UDISE+. Retrying… (<strong>' + (attempt+1) + '/8</strong>)';
    ui('working','⚡', attempt < 3 ? 'Waiting for Student List…' : 'Navigate to Student List', waitMsg, 55 + attempt*3);
    setTimeout(() => injectAndFetch(attempt + 1), 3000);
  }
}

// ── Listen for postMessage from UDISE tab ────────────────────────────────────
window.addEventListener('message', function(ev) {
  if (!ev.data || !ev.data.t) return;
  const { t, m, count, data, schoolId } = ev.data;

  if (t === 'status') { document.getElementById('smsg').innerHTML = m; return; }

  if (t === 'no_school_id') {
    ui('working','🔍','Navigate to Student List',
      'Could not detect school ID. Please go to <strong>Student → All Students</strong> in the UDISE+ tab, then click "I\'ve Logged In" again.',58);
    document.getElementById('confirmBtn').disabled = false;
    fetching = false;
    return;
  }

  if (t === 'no_students') {
    ui('error','⚠️','0 Students Found',
      'Found school <code>' + (schoolId||'?') + '</code> but no students were returned. Navigate to <strong>Students → All Students</strong> then retry.',65);
    document.getElementById('retryBtn').style.display = 'block';
    fetching = false;
    return;
  }

  if (t === 'error') {
    ui('error','❌','Fetch Error', m, 90);
    document.getElementById('retryBtn').style.display = 'block';
    document.getElementById('hintBox').style.display  = 'block';
    fetching = false;
    return;
  }

  if (t === 'students') {
    let students;
    try { students = JSON.parse(data); } catch(e) {
      ui('error','❌','Parse Error','Could not read student data.', 100);
      return;
    }
    if (!students.length) { ui('error','❌','No Students','UDISE+ returned an empty list.', 100); return; }
    sendToHpc(students);
  }
});

// ── POST students to /udise/ingest ───────────────────────────────────────────
async function sendToHpc(students) {
  done = true;
  step('s3','done'); step('s4','done'); step('s5','active');
  ui('working','📤','Saving to HPC Assist…',
    'Transferring ' + students.length + ' student records with APAAR IDs…', 82);
  try {
    const resp = await fetch(INGEST_URL, {
      method:'POST', mode:'cors',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({school_id:SCHOOL_ID, token:TOKEN, udise_code:UDISE_CODE, students})
    });
    const r = await resp.json();
    if (r.ok) {
      step('s5','done'); step('s6','active');
      ui('success','✅','Sync Complete!',
        r.total + ' students &amp; ' + (r.apaar_count||0) + ' APAAR IDs saved. Redirecting…', 100);
      const b = document.getElementById('badges');
      b.style.display = 'flex';
      b.innerHTML = '<span class="badge">👥 '+r.total+' Students</span><span class="badge">🆔 '+(r.apaar_count||0)+' APAAR IDs</span>';
      try { udiseTab && udiseTab.close(); } catch(e) {}
      setTimeout(() => { window.location.href = DASH_URL; }, 2200);
    } else {
      ui('error','❌','Save Failed', r.error||'Could not save to HPC Assist.', 100);
      document.getElementById('retryBtn').style.display = 'block';
      done = false;
    }
  } catch(e) {
    ui('error','❌','Network Error','Could not reach HPC Assist: ' + e.message, 100);
    document.getElementById('retryBtn').style.display = 'block';
    done = false;
  }
}

// ── Manual console fallback ───────────────────────────────────────────────────
function showManualFallback() {
  fetching = false;
  const snippet = "fetch('/p0/api/cy/snapshot/students/all/'+location.href.match(/\\/school\\/(\\d+)/)?.[1],{credentials:'include'}).then(r=>r.json()).then(j=>{const a=j.data||j.students||j.result||[];opener&&opener.postMessage({t:'students',count:a.length,data:JSON.stringify(a)},'" + window.location.origin + "')})";
  document.getElementById('bookmarklet').textContent = snippet;
  document.getElementById('hintBox').style.display = 'block';
  ui('error','❌','Auto-Inject Blocked',
    'UDISE+ security prevented automatic injection. See the manual instructions below.', 90);
  document.getElementById('retryBtn').style.display = 'block';
}

function retry() {
  fetching = false; done = false;
  document.getElementById('retryBtn').style.display   = 'none';
  document.getElementById('hintBox').style.display    = 'none';
  document.getElementById('confirmBtn').style.display = 'block';
  document.getElementById('confirmBtn').disabled       = false;
  document.getElementById('openBtn').style.display    = 'none';
  ['s1','s2','s3','s4','s5','s6'].forEach(id => step(id,''));
  step('s1','active');
  ui('waiting','⏳','Ready',
    'Click <strong>"I\'ve Logged In"</strong> if you are already logged in to UDISE+.', 35);
}

// ── Boot ─────────────────────────────────────────────────────────────────────
openUdise();
</script>
</body>
</html>
