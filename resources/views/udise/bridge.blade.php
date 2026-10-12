<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HPC Assist — UDISE+ Auto-Sync</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1e293b;border:1px solid #334155;border-radius:20px;padding:36px 32px;max-width:520px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.5)}
.logo{font-size:38px;text-align:center;margin-bottom:10px}
h1{font-size:18px;font-weight:800;color:#f8fafc;text-align:center;margin-bottom:4px}
.sub{font-size:12px;color:#94a3b8;text-align:center;margin-bottom:24px}
.sbox{border-radius:12px;padding:16px 18px;margin-bottom:18px;border:1px solid transparent}
.sbox.waiting{background:#451a03;border-color:#92400e}
.sbox.working{background:#0c1a2e;border-color:#1d4ed8}
.sbox.success{background:#052e16;border-color:#166534}
.sbox.error  {background:#3b0000;border-color:#991b1b}
.sicon{font-size:24px;margin-bottom:6px}
.stitle{font-size:14px;font-weight:700;margin-bottom:4px}
.smsg{font-size:12px;color:#cbd5e1;line-height:1.5}
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
.btn-open{background:#0369a1;color:#fff}.btn-open:hover{background:#0284c7}
.btn-retry{background:#334155;color:#cbd5e1;display:none}.btn-retry:hover{background:#475569}
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
    <div class="smsg" id="smsg">A UDISE+ login tab is opening. Please sign in with your school credentials. Once logged in, this page automatically fetches all students.</div>
    <div class="progress"><div class="pbar anim" id="pbar" style="width:20%;background:#f59e0b"></div></div>
  </div>

  <ul class="steps">
    <li class="active" id="s1"><span class="dot"></span>Opening UDISE+ login tab</li>
    <li id="s2"><span class="dot"></span>Waiting for you to log in</li>
    <li id="s3"><span class="dot"></span>Detecting login success</li>
    <li id="s4"><span class="dot"></span>Fetching student records &amp; APAAR IDs</li>
    <li id="s5"><span class="dot"></span>Saving to HPC Assist</li>
    <li id="s6"><span class="dot"></span>Redirecting to dashboard</li>
  </ul>

  <div class="badges" id="badges"></div>
  <button class="btn btn-open" id="openBtn" onclick="openUdise()">🌐 Open UDISE+ Tab Again</button>
  <button class="btn btn-retry" id="retryBtn" onclick="retry()">🔄 Retry Sync</button>
</div>

<script>
// ── Config injected by server ────────────────────────────────────────────────
const SCHOOL_ID  = {{ $schoolId }};
const TOKEN      = '{{ addslashes($token) }}';
const UDISE_CODE = '{{ addslashes($udiseCode) }}';
const INGEST_URL = '{{ $ingestUrl }}';
const DASH_URL   = '{{ $dashUrl }}';

// ── State ───────────────────────────────────────────────────────────────────
let udiseTab  = null;
let pollTimer = null;
let fetching  = false;
let done      = false;

// ── UI helpers ───────────────────────────────────────────────────────────────
function ui(type,icon,title,msg,pct) {
  document.getElementById('sbox').className = 'sbox '+type;
  document.getElementById('sicon').textContent = icon;
  document.getElementById('stitle').textContent = title;
  document.getElementById('smsg').textContent = msg;
  const bar = document.getElementById('pbar');
  bar.style.width = pct+'%';
  const colors = {waiting:'#f59e0b',working:'#3b82f6',success:'#22c55e',error:'#ef4444'};
  bar.style.background = colors[type] || '#3b82f6';
  bar.className = 'pbar' + (pct < 100 ? ' anim' : '');
}
const step = (id, cls) => { const el = document.getElementById(id); if(el) el.className = cls; };

// ── Open UDISE tab ───────────────────────────────────────────────────────────
function openUdise() {
  udiseTab = window.open('https://sdms.udiseplus.gov.in/', 'udise_sync_tab');
  step('s1','done'); step('s2','active');
  ui('waiting','⏳','Waiting for UDISE+ Login…',
    'Please log in with your school credentials in the UDISE+ tab. This page will auto-detect login and pull students immediately.',
    35);
}

// ── Poll the UDISE tab URL (same-origin trick: throws when cross-origin) ─────
function pollTab() {
  if (done || fetching) return;
  if (!udiseTab || udiseTab.closed) {
    ui('error','❌','Tab Closed','The UDISE+ tab was closed. Click "Open UDISE+ Tab Again" to restart.',0);
    stop(); return;
  }
  let url = '';
  try { url = udiseTab.location.href; } catch(e) { url = 'cross-origin'; }

  if (url === 'about:blank') return; // still loading
  if (url.includes('auth.udiseplus') || url.includes('/login')) {
    step('s2','active');
    ui('waiting','⏳','Waiting for Login…','Sign in to UDISE+ SDMS in the other tab. This page auto-detects login.',38);
    return;
  }
  // If cross-origin OR sdms.udiseplus.gov.in — user is logged in
  if (url === 'cross-origin' || url.includes('sdms.udiseplus.gov.in')) {
    step('s2','done'); step('s3','done'); step('s4','active');
    ui('working','⚡','Login Detected — Fetching Students…','Reading student roster, APAAR IDs and PEN numbers from UDISE+ SDMS…',60);
    fetching = true;
    stop();
    injectAndFetch();
  }
}

function stop() { if(pollTimer) { clearInterval(pollTimer); pollTimer=null; } }

// ── Inject fetch script into UDISE tab and collect result via postMessage ─────
function injectAndFetch() {
  const origin = window.location.origin;
  // Script runs inside UDISE tab, posts students back to THIS window (opener)
  const script = `
(async function(){
  try {
    let sId = location.href.match(/school\\/(\\d+)/)?.[1];
    if (!sId) {
      for(const a of document.querySelectorAll('a[href*="/school/"]')){
        const m = a.href.match(/school\\/(\\d+)/);
        if(m){sId=m[1];break;}
      }
    }
    if (!sId && typeof angular !== 'undefined') {
      try {
        const sc = angular.element(document.querySelector('[ng-app]')).injector().get('$state');
        sId = sc.params && sc.params.schoolId;
      } catch(e){}
    }
    opener && opener.postMessage({t:'status',m:'School ID: '+(sId||'detecting...')+'. Calling UDISE student API…'},'${origin}');
    let students = [];
    const apis = sId ? [
      '/p0/api/cy/snapshot/students/all/'+sId,
      '/p0/api/cy/students/all/'+sId,
    ] : [];
    for(const api of apis){
      try{
        const r = await fetch(api,{credentials:'include'});
        if(!r.ok) continue;
        const j = await r.json();
        const arr = j.data||j.students||j.result||(Array.isArray(j)?j:[]);
        if(arr.length>0){students=arr;break;}
      }catch(e){continue;}
    }
    if(!students.length && !sId){
      opener && opener.postMessage({t:'error',m:'Cannot find school ID. Please navigate to the student list: sdms.udiseplus.gov.in/g0/#/school/YOUR_ID/listAllStudentCy then click Retry Sync.'},'${origin}');
      return;
    }
    if(!students.length){
      opener && opener.postMessage({t:'error',m:'UDISE returned 0 students for school '+sId+'. Navigate to the student list page first, then Retry.'},'${origin}');
      return;
    }
    opener && opener.postMessage({t:'students',count:students.length,data:JSON.stringify(students)},'${origin}');
  } catch(e) {
    opener && opener.postMessage({t:'error',m:'Error: '+e.message},'${origin}');
  }
})();
`;
  try {
    // Try direct eval (works when tab is on same origin — shouldn't be, but try)
    udiseTab.eval(script);
  } catch(e1) {
    try {
      // Try script-tag injection
      const s = udiseTab.document.createElement('script');
      s.textContent = script;
      udiseTab.document.head.appendChild(s);
    } catch(e2) {
      // Browser blocked both — UDISE has strict CSP / cross-origin isolation.
      // Fall back: use BroadcastChannel relay via the UDISE tab's URL
      // This means we need the user to manually run the bookmarklet / console.
      ui('error','❌','Auto-Inject Blocked by UDISE+ Security',
        'UDISE+ uses strict cross-origin isolation which blocks automatic script injection. ' +
        'Please open the browser console on the UDISE tab (F12 → Console), type: allow pasting ← press Enter, ' +
        'then paste the sync snippet shown on the dashboard. This is a one-time step.',
        100,'#ef4444');
      document.getElementById('retryBtn').style.display='block';
      fetching=false;
    }
  }
}

// ── Listen for postMessage from the UDISE tab ────────────────────────────────
window.addEventListener('message', function(ev) {
  if (!ev.data || !ev.data.t) return;
  const {t, m, count, data} = ev.data;

  if (t === 'status') { document.getElementById('smsg').textContent = m; return; }
  if (t === 'error')  { ui('error','❌','Fetch Error', m, 100,'#ef4444'); document.getElementById('retryBtn').style.display='block'; fetching=false; return; }
  if (t === 'students') {
    let students;
    try { students = JSON.parse(data); } catch(e) { ui('error','❌','Parse Error','Could not read student data.',100,'#ef4444'); return; }
    if (!students.length) { ui('error','❌','No Students','UDISE returned an empty list.',100,'#ef4444'); return; }
    sendToHpc(students);
  }
});

// ── POST to /udise/ingest ────────────────────────────────────────────────────
async function sendToHpc(students) {
  done = true;
  step('s4','done'); step('s5','active');
  ui('working','📤','Saving to HPC Assist…',
    'Transferring '+students.length+' student records with APAAR IDs to your school account…', 82);

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
        r.total+' students and '+(r.apaar_count||0)+' APAAR IDs saved. Redirecting to dashboard…', 100);
      const b = document.getElementById('badges');
      b.style.display='flex';
      b.innerHTML='<span class="badge">👥 '+r.total+' Students</span><span class="badge">🆔 '+(r.apaar_count||0)+' APAAR IDs</span>';
      try { udiseTab && udiseTab.close(); } catch(e) {}
      setTimeout(()=>{ window.location.href = DASH_URL; }, 2000);
    } else {
      ui('error','❌','Save Failed', r.error||'Could not save to HPC Assist.', 100,'#ef4444');
      document.getElementById('retryBtn').style.display='block'; done=false;
    }
  } catch(e) {
    ui('error','❌','Network Error','Could not reach HPC Assist server: '+e.message, 100,'#ef4444');
    document.getElementById('retryBtn').style.display='block'; done=false;
  }
}

function retry() {
  fetching=false; done=false;
  document.getElementById('retryBtn').style.display='none';
  ['s1','s2','s3','s4','s5','s6'].forEach(id=>step(id,''));
  openUdise();
  pollTimer = setInterval(pollTab, 1500);
}

// ── Boot ─────────────────────────────────────────────────────────────────────
openUdise();
pollTimer = setInterval(pollTab, 1500);
</script>
</body>
</html>
