<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏫</span>
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 leading-tight">
                        {{ $school?->name ?? 'My School' }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                        <span class="font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700 font-semibold">
                            UDISE: {{ $school?->udise_code ?? '—' }}
                        </span>
                        <span>•</span>
                        <span>{{ $school?->district ?? 'District' }}{{ $school?->state ? ', ' . $school->state : '' }}</span>
                    </div>
                </div>
            </div>

            <!-- Header Quick Badge -->
            <div class="flex items-center gap-3">
                @php $uStat = $school?->udise_status ?? 'not_attempted'; @endphp
                @if($uStat === 'synced')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        UDISE Synced ({{ $udiseCount ?? 129 }} Students)
                    </span>
                @elseif($uStat === 'skipped')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        Manual &amp; Template Mode
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        Setup In Progress
                    </span>
                @endif
                <button onclick="openSchoolModal()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-xs transition active:scale-95">
                    ⚙️ Edit School
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-slate-50 min-h-screen" x-data="workflowApp({{ (int) request('step', ($school?->udise_status === 'synced' || $school?->udise_status === 'skipped') ? 2 : 1) }})">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- ── STEPPER PROGRESS BAR (MATCHING USER WORKFLOW PATTERN) ── -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-3 sm:p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    
                    <!-- STEP 1 TAB -->
                    <button @click="setStep(1)"
                            type="button"
                            class="flex items-center gap-3 p-3 rounded-xl transition text-left relative overflow-hidden"
                            :class="currentStep === 1 ? 'bg-indigo-50 border-2 border-indigo-600 shadow-xs' : 'bg-slate-50 hover:bg-slate-100 border border-slate-200/80'">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0"
                             :class="currentStep === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">
                            1
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Step 1</div>
                            <div class="text-xs font-bold text-slate-900 truncate">UDISE Login</div>
                            <div class="text-[10px] font-medium text-slate-500 truncate">
                                @if($uStat === 'synced')
                                    <span class="text-emerald-600 font-bold">✓ Synced</span>
                                @elseif($uStat === 'skipped')
                                    <span class="text-slate-500">Skipped</span>
                                @else
                                    <span>Decision Pending</span>
                                @endif
                            </div>
                        </div>
                    </button>

                    <!-- STEP 2 TAB: HPC SCHOOL CONNECT & RECORD SESSION -->
                    <button @click="setStep(2)"
                            type="button"
                            class="flex items-center gap-3 p-3 rounded-xl transition text-left relative overflow-hidden"
                            :class="currentStep === 2 ? 'bg-indigo-50 border-2 border-indigo-600 shadow-xs' : 'bg-slate-50 hover:bg-slate-100 border border-slate-200/80'">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0"
                             :class="currentStep === 2 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">
                            2
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Step 2</div>
                            <div class="text-xs font-bold text-slate-900 truncate">HPC School Connect</div>
                            <div class="text-[10px] font-medium text-slate-500 truncate">
                                @if(!empty($hasSchoolProfile))
                                    <span class="text-emerald-600 font-bold">✓ Session Recorded</span>
                                @else
                                    <span>Record Session</span>
                                @endif
                            </div>
                        </div>
                    </button>

                    <!-- STEP 3 TAB: STUDENT DATA & HPC SUBMISSION -->
                    <button @click="setStep(3)"
                            type="button"
                            class="flex items-center gap-3 p-3 rounded-xl transition text-left relative overflow-hidden"
                            :class="currentStep === 3 ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 border border-slate-200/80'">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0"
                             :class="currentStep === 3 ? 'bg-white text-indigo-700' : 'bg-slate-200 text-slate-700'">
                            3
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase tracking-wider" :class="currentStep === 3 ? 'text-indigo-200' : 'text-slate-400'">Step 3</div>
                            <div class="text-xs font-bold truncate" :class="currentStep === 3 ? 'text-white' : 'text-slate-900'">Student Data &amp; Submit</div>
                            <div class="text-[10px] font-medium truncate" :class="currentStep === 3 ? 'text-indigo-100' : 'text-slate-500'">Templates &amp; HPC Portal</div>
                        </div>
                    </button>

                    <!-- STEP 4 TAB: HPC TEACHER REGISTRATION & RECORD SESSION -->
                    <button @click="setStep(4)"
                            type="button"
                            class="flex items-center gap-3 p-3 rounded-xl transition text-left relative overflow-hidden"
                            :class="currentStep === 4 ? 'bg-indigo-50 border-2 border-indigo-600 shadow-xs' : 'bg-slate-50 hover:bg-slate-100 border border-slate-200/80'">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shrink-0"
                             :class="currentStep === 4 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">
                            4
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Step 4</div>
                            <div class="text-xs font-bold text-slate-900 truncate">HPC Teacher Connect</div>
                            <div class="text-[10px] font-medium text-slate-500 truncate">
                                @if(!empty($hasTeacherProfile))
                                    <span class="text-emerald-600 font-bold">✓ Session Recorded</span>
                                @else
                                    <span>Record Session</span>
                                @endif
                            </div>
                        </div>
                    </button>

                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- STEP 1 CONTENT: UDISE LOGIN & STUDENT DATA EXTRACTION -->
            <!-- ======================================================= -->
            <div x-show="currentStep === 1" x-transition class="space-y-6">

                @if($uStat === 'synced' && !empty($udiseCount) && $udiseCount > 0)
                    {{-- ── SYNCED STATE ──────────────────────────────────────────── --}}
                    <div class="bg-white rounded-2xl shadow-xs border border-emerald-300 p-6 sm:p-8">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-3 max-w-2xl">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                    ✓ UDISE+ SDMS Data Synchronized
                                </span>
                                <h3 class="text-xl font-black text-slate-900">Official Student Roster &amp; Unmasked APAAR IDs Ready</h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    All student records, dates of birth, grades, sections, and unmasked 12-digit APAAR IDs have been retrieved directly from UDISE+ SDMS.
                                </p>
                                <div class="flex items-center gap-3 pt-1 text-xs font-bold text-slate-700">
                                    <span class="bg-emerald-50 text-emerald-800 px-3 py-1 rounded-lg border border-emerald-200">
                                        👥 {{ $udiseCount }} Students Loaded
                                    </span>
                                    <span class="bg-indigo-50 text-indigo-800 px-3 py-1 rounded-lg border border-indigo-200">
                                        🆔 {{ $udiseApaarCount }} Unmasked APAAR IDs
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col gap-2.5">
                                <button @click="setStep(2)"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition active:scale-95">
                                    Proceed to Step 2: HPC School Connect ➔
                                </button>
                                <button onclick="resetUdiseSync()"
                                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition active:scale-95">
                                    🔄 Re-sync UDISE Data
                                </button>
                            </div>
                        </div>
                    </div>

                @elseif($uStat === 'skipped')
                    {{-- ── SKIPPED STATE ─────────────────────────────────────────── --}}
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-300 p-6 sm:p-8">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2 max-w-2xl">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Manual &amp; Template Mode Active (UDISE Skipped)
                                </span>
                                <h3 class="text-xl font-black text-slate-900">Preparing HPC Data via Standard Spreadsheets</h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    You chose to bypass UDISE+. You can still connect UDISE+ below to auto-populate students, or continue with manual templates.
                                </p>
                            </div>
                            <div class="shrink-0 flex flex-col gap-2.5">
                                <button @click="setStep(2)"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition active:scale-95">
                                    Proceed to Step 2 ➔
                                </button>
                                <button onclick="resetUdiseSync()"
                                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-xl font-semibold text-xs transition active:scale-95">
                                    🌐 Connect UDISE+ Instead
                                </button>
                            </div>
                        </div>
                    </div>

                @else
                    {{-- ── INITIAL STATE — two cards ─────────────────────────────── --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5"
                         x-data="{
                            syncing: false,
                            loading: false,
                            showSnippet: false,
                            pollTimer: null,
                            copied: false,
                            ingestUrl: '{{ url('/udise/ingest') }}',
                            schoolId: {{ $school?->id ?? 0 }},
                            token: '{{ $schoolToken ?? '' }}',
                            udiseCode: '{{ $school?->udise_code ?? '' }}',

                            get bookmarklet() {
                                return `(async()=>{try{` +
                                    `let id=(location.href.match(/school\\/(\\d+)/)?.[1])||(location.hash.match(/school\\/(\\d+)/)?.[1])||` +
                                    `(window.performance.getEntriesByType('resource').map(r=>r.name).find(u=>u.includes('/snapshot/students/all/'))?.match(/all\\/(\\d+)/)?.[1])||` +
                                    `(window.performance.getEntriesByType('resource').map(r=>r.name).find(u=>u.includes('/cy/school/'))?.match(/school\\/(\\d+)/)?.[1])||` +
                                    `prompt('Enter UDISE School ID (digits after /school/ in URL):');` +
                                    `if(!id){alert('School ID required.');return;}` +
                                    `let r=await fetch('/p0/api/cy/snapshot/students/all/'+id,{credentials:'include'});` +
                                    `let j=await r.json();let s=j.data||j.students||j.result||j;` +
                                    `if(!Array.isArray(s)||!s.length){` +
                                    `r=await fetch('/p0/api/cy/students/all/'+id,{credentials:'include'});` +
                                    `j=await r.json();s=j.data||j.students||j.result||j;}` +
                                    `if(!Array.isArray(s)||!s.length){alert('0 students found. Open Student->All Students in UDISE first.');return;}` +
                                    `let a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(s,null,2)],{type:'application/json'}));` +
                                    `a.download='udise_students_'+id+'.json';a.click();` +
                                    `try{await fetch('${this.ingestUrl}',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({school_id:${this.schoolId},token:'${this.token}',udise_code:'${this.udiseCode}',students:s})});` +
                                    `alert('✅ Synced '+s.length+' students to HPC Assist! Backup JSON saved to your Downloads.');}catch(e){` +
                                    `alert('Saved udise_students_'+id+'.json! Click Upload JSON on your HPC dashboard.');}` +
                                    `}catch(e){alert('Error: '+e.message);}})();`;
                            },

                            copySnippet() {
                                navigator.clipboard.writeText(this.bookmarklet).then(() => {
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2500);
                                });
                            },

                            async uploadUdiseJson(e) {
                                const file = e.target.files[0];
                                if (!file) return;
                                this.loading = true;
                                try {
                                    const text = await file.text();
                                    const json = JSON.parse(text);
                                    const students = Array.isArray(json) ? json : (json.data || json.students || []);
                                    if (!students.length) {
                                        alert('No student records found in JSON file.');
                                        this.loading = false;
                                        return;
                                    }
                                    const res = await fetch('{{ route('udise.ingest') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': CSRF
                                        },
                                        body: JSON.stringify({
                                            school_id: this.schoolId,
                                            token: this.token,
                                            udise_code: this.udiseCode,
                                            students: students
                                        })
                                    });
                                    const data = await res.json();
                                    if (data.ok) {
                                        alert('✅ ' + data.message);
                                        window.location.reload();
                                    } else {
                                        alert('❌ ' + (data.error || 'Failed to import UDISE data.'));
                                        this.loading = false;
                                    }
                                } catch(err) {
                                    alert('❌ Error reading file: ' + err.message);
                                    this.loading = false;
                                }
                            },

                            startPolling() {
                                this.syncing = true;
                                if (this.pollTimer) clearInterval(this.pollTimer);
                                this.pollTimer = setInterval(() => this.pollIngest(), 2500);
                            },

                            async pollIngest() {
                                try {
                                    const r = await fetch('{{ route('udise.ingest_poll') }}', { headers: { 'Accept': 'application/json' } });
                                    const d = await r.json();
                                    if (d.ok && d.synced) {
                                        clearInterval(this.pollTimer);
                                        this.syncing = false;
                                        alert('✅ Sync complete! ' + d.total + ' students loaded. Reloading dashboard…');
                                        window.location.reload();
                                    }
                                } catch(e) {}
                            },

                            async skipUdise() {
                                this.loading = true;
                                const r = await fetch('{{ route('school.udise.skip') }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
                                });
                                const d = await r.json();
                                if (d.ok) window.location.href = '{{ route('dashboard') }}?step=2';
                                else this.loading = false;
                            }
                         }">

                        {{-- Card A: UDISE Console Sync --}}
                        <div class="bg-white rounded-2xl shadow-xs border-2 border-emerald-500/60 p-6 flex flex-col justify-between">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-3">
                                    🌐 UDISE+ Console Sync (Recommended)
                                </span>
                                <h3 class="text-base font-bold text-slate-900 mb-1.5">Fetch Students via Console Snippet</h3>
                                <p class="text-xs text-slate-500 leading-relaxed mb-4">
                                    UDISE+ uses strict cross-origin security. The most reliable way is to run a one-line script directly in the UDISE+ browser console.
                                </p>

                                {{-- Step-by-step --}}
                                <ol class="space-y-3 text-xs mb-4">
                                    <li class="flex gap-3">
                                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-600 text-white text-[10px] font-black flex items-center justify-center">1</span>
                                        <div>
                                            <div class="font-semibold text-slate-800">Log in to UDISE+ SDMS</div>
                                            <a href="https://sdms.udiseplus.gov.in/" target="_blank"
                                               class="text-indigo-600 hover:underline font-medium">Open UDISE+ →</a>
                                            <span class="text-slate-500"> → Sign in → go to <strong>Student → All Students</strong></span>
                                        </div>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-600 text-white text-[10px] font-black flex items-center justify-center">2</span>
                                        <div>
                                            <div class="font-semibold text-slate-800">Open Console &amp; enable pasting</div>
                                            <div class="text-slate-500">Press <kbd class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px] font-mono border border-slate-300">F12</kbd> → Console tab → type <kbd class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px] font-mono border border-slate-300">allow pasting</kbd> → Enter</div>
                                        </div>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-600 text-white text-[10px] font-black flex items-center justify-center">3</span>
                                        <div class="w-full">
                                            <div class="font-semibold text-slate-800 mb-1.5">Copy &amp; paste this snippet → Enter</div>
                                            <div class="relative">
                                                <div class="bg-slate-50 hover:bg-slate-100/70 rounded-xl px-3.5 py-2.5 pr-20 font-mono text-[9px] text-slate-700 break-all leading-relaxed overflow-hidden max-h-16 border border-slate-200 select-all transition">
                                                    <span x-text="bookmarklet"></span>
                                                </div>
                                                <button @click="copySnippet()"
                                                        class="absolute top-2 right-2 px-2.5 py-1 rounded-lg text-[10px] font-bold border transition shadow-2xs active:scale-95"
                                                        :class="copied ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50 hover:border-slate-400'">
                                                    <span x-text="copied ? '✓ Copied' : 'Copy'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-600 text-white text-[10px] font-black flex items-center justify-center">4</span>
                                        <div>
                                            <div class="font-semibold text-slate-800">Watch for success alert → return here</div>
                                            <div class="text-slate-500">An alert confirms sync &amp; automatically downloads the JSON file to your Downloads folder.</div>
                                        </div>
                                    </li>
                                </ol>

                                {{-- Direct Upload Option --}}
                                <div class="p-3 bg-emerald-50/80 rounded-xl border border-emerald-200/80 mb-4">
                                    <label class="block text-[11px] font-bold text-emerald-950 mb-1 flex items-center gap-1.5">
                                        <span>📁</span> Already have UDISE JSON file? Upload it directly:
                                    </label>
                                    <input type="file" accept=".json" @change="uploadUdiseJson($event)"
                                           class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                                </div>
                            </div>
                            <div class="space-y-2 pt-3 border-t border-slate-100">
                                <button @click="startPolling()"
                                        :disabled="syncing || loading"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold transition shadow-sm active:scale-95 text-white"
                                        :class="syncing ? 'bg-amber-500 cursor-wait' : 'bg-emerald-600 hover:bg-emerald-700'">
                                    <span x-text="syncing ? '⏳ Waiting for sync completion…' : '✅ I\'ve pasted the snippet — check for sync'"></span>
                                </button>
                                <a href="https://sdms.udiseplus.gov.in/" target="_blank"
                                   class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition">
                                    🌐 Open UDISE+ SDMS
                                </a>
                            </div>
                        </div>

                        {{-- Card B: Skip --}}
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 hover:border-slate-300 transition p-6 flex flex-col justify-between">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 mb-3">
                                    Manual / Offline Mode
                                </span>
                                <h3 class="text-base font-bold text-slate-900 mb-2">Skip UDISE &amp; Continue Directly</h3>
                                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                                    No UDISE login? Proceed directly to HPC — download blank Excel templates, fill student data offline, and upload.
                                </p>
                                <ul class="space-y-1.5 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200 mb-4">
                                    <li>✓ No government portal credentials needed</li>
                                    <li>✓ Download official blank HPC Excel template</li>
                                    <li>✓ Full manual entry, upload &amp; evaluation features</li>
                                </ul>
                            </div>
                            <div class="pt-3 border-t border-slate-100">
                                <button @click="skipUdise()"
                                        :disabled="loading || syncing"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition shadow-xs active:scale-95">
                                    ➡️ Skip UDISE &amp; Continue to Step 2
                                </button>
                            </div>
                        </div>

                    </div>
                @endif

            </div>


            <!-- ========================================================================= -->
            <!-- STEP 2 CONTENT: HPC SCHOOL CONNECT & RECORD SESSION                       -->
            <!-- ========================================================================= -->
            <div x-show="currentStep === 2" x-transition class="space-y-6">

                <!-- 2A. HPC School Portal Session & Connect Card -->
                <div class="bg-white rounded-2xl shadow-xs border border-indigo-200 p-6 space-y-4">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">PARAKH Portal Connection</div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>🌐 HPC School Portal Session</span>
                                @if(!empty($hasSchoolProfile))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                        ✓ Session Recorded &amp; Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-300">
                                        No Active Session
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Connect to the official PARAKH HPC portal (<code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700 font-mono">https://hpc.parakh.ncert.gov.in/en/login</code>) as School. The browser session is recorded and preserved securely.
                            </p>
                        </div>

                        <!-- Session Action Buttons -->
                        <div class="shrink-0 flex items-center gap-2.5 flex-wrap">
                            @if(!empty($hasSchoolProfile))
                                <button onclick="startHpcSchoolSession()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🚀 Reopen Chrome &amp; Reconnect
                                </button>
                                <button onclick="endHpcSchoolSession()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🚪 End School Session
                                </button>
                            @else
                                <button onclick="startHpcSchoolSession()"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                    🚀 Connect to HPC School Portal &amp; Record Session
                                </button>
                            @endif
                            <a href="{{ config('hpc.login_url') }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                                🔗 Portal ↗
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2B. School Profile & Registration Details -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">School Registration Details</div>
                            <h3 class="text-lg font-black text-slate-900">{{ $school?->name ?? 'My School' }}</h3>
                        </div>
                        <button onclick="openSchoolModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-300 transition active:scale-95">
                            ✏️ Edit Profile Details
                        </button>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">UDISE Code</span>
                            <strong class="text-slate-800 font-mono text-sm">{{ $school?->udise_code ?? '—' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Category</span>
                            <strong class="text-slate-800 font-semibold text-sm">{{ $school?->category ?? 'Government School' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Contact Phone</span>
                            <strong class="text-slate-800 font-mono text-sm">{{ $school?->phone ?? '—' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Location</span>
                            <strong class="text-slate-800 font-semibold text-sm">{{ $school?->district ?? 'District' }}{{ $school?->state ? ', ' . $school->state : '' }}</strong>
                        </div>
                    </div>
                </div>

                <!-- 2B-2. Student Templates Download Hub (Two Official Links) -->
                <div class="bg-white rounded-2xl shadow-xs border border-indigo-100 p-6 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Student Template Generation</div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>📥 Official HPC Student Templates</span>
                                @if(isset($udiseCount) && $udiseCount > 0)
                                    <span class="text-xs font-bold bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full">
                                        ✨ {{ $udiseCount }} Students Loaded
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Download verified Excel evaluation sheets for student records, DOBs, and unmasked 12-digit APAAR IDs.
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <a href="{{ route('hpc.template.download') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📄 Blank Template (.xlsx)
                            </a>
                            <a href="{{ route('hpc.template.download_udise') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                👥 Pre-filled UDISE Template (.xlsx)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Step 2 Bottom Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <button @click="setStep(1)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition active:scale-95">
                        ← Back to Step 1: UDISE Login
                    </button>
                    <button @click="setStep(3)"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                        Proceed to Step 3: Student Data &amp; Submit ➔
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- STEP 3 CONTENT: STUDENT DATA, TEMPLATE SUITE & HPC SUBMISSION             -->
            <!-- ========================================================================= -->
            <div x-show="currentStep === 3" x-transition class="space-y-6">

                <!-- 3A. Template Download & Upload Hub -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Template Generation Suite</div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>Excel Spreadsheets for PARAKH HPC</span>
                                @if(isset($udiseCount) && $udiseCount > 0)
                                    <span class="text-xs font-bold bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full">
                                        ✨ {{ $udiseCount }} Students Ready
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Download the verified template format, complete or modify records, and upload for automated portal ingestion.
                            </p>
                        </div>

                        <!-- Template Buttons -->
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <a href="{{ route('hpc.template.download') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📥 Download Blank Template (.xlsx)
                            </a>
                            <a href="{{ route('hpc.template.download_udise') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📥 Download with UDISE Data (.xlsx)
                            </a>
                        </div>
                    </div>

                    <!-- Upload Excel Form -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <form action="{{ route('hpc.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
                            @csrf
                            <input type="file" name="file" accept=".xlsx, .csv" required
                                   class="text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white file:text-slate-700 hover:file:bg-slate-100 cursor-pointer">
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📤 Upload Completed Spreadsheet
                            </button>
                        </form>

                        <!-- Batch Automation Runner Trigger -->
                        <button onclick="startRun()" id="btn-run"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-xs shadow-sm transition active:scale-95">
                            🚀 Run Automation &amp; Submit to HPC Portal
                        </button>
                    </div>

                    <!-- Automation Live Progress Bar -->
                    <div id="run-progress-box" class="hidden p-4 rounded-xl bg-slate-900 text-white space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span id="run-status-text">⚡ Connecting to Chrome &amp; HPC Portal...</span>
                            <span id="run-progress-percent">0%</span>
                        </div>
                        <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                            <div id="run-progress-bar" class="bg-emerald-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- 3B. Paginated Student Records Review -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span>📋 Student Records Roster</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ count($preview) }} Records Loaded
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Review and edit individual records before portal submission. Displaying 20 records per page.
                            </p>
                        </div>

                        <!-- Search Box & Filter -->
                        <div class="flex items-center gap-2.5">
                            <input type="text" id="student-search-input" onkeyup="filterStudentTable()"
                                   placeholder="🔍 Search by Name or APAAR..."
                                   class="px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500 w-56">
                            <button onclick="openAddStudentModal()"
                                    class="px-3.5 py-1.5 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition">
                                ➕ Add Student
                            </button>
                        </div>
                    </div>

                    <!-- Compact Paginated Table -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 font-bold text-slate-600 uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-3">#</th>
                                    <th class="py-3 px-3">Student Full Name</th>
                                    <th class="py-3 px-3">Grade &amp; Sec</th>
                                    <th class="py-3 px-3">Date of Birth</th>
                                    <th class="py-3 px-3">APAAR ID (12-Digit)</th>
                                    <th class="py-3 px-3">Status</th>
                                    <th class="py-3 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="student-table-body">
                                <!-- Populated dynamically by renderTablePage() -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="flex items-center justify-between text-xs text-slate-500 pt-2">
                        <div>
                            Showing <span id="page-start" class="font-bold text-slate-800">1</span>
                            to <span id="page-end" class="font-bold text-slate-800">20</span>
                            of <span id="page-total" class="font-bold text-slate-800">{{ count($preview) }}</span> records
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button id="btn-page-prev" onclick="prevPage()"
                                    class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg font-bold text-slate-700 disabled:opacity-40">
                                ‹ Prev
                            </button>
                            <div id="page-numbers" class="flex items-center gap-1"></div>
                            <button id="btn-page-next" onclick="nextPage()"
                                    class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg font-bold text-slate-700 disabled:opacity-40">
                                Next ›
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Nav -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button @click="setStep(2)"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition">
                            ← Back to Step 2
                        </button>
                        <button @click="setStep(4)"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition">
                            Proceed to Step 4: HPC Teacher Connect ➔
                        </button>
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- STEP 4 CONTENT: HPC TEACHER REGISTRATION & SESSION MANAGEMENT             -->
            <!-- ========================================================================= -->
            <div x-show="currentStep === 4" x-transition class="space-y-6">

                @if(!empty($hasTeacherProfile))
                    <!-- 4A. AUTHENTICATED TEACHER DETAILS (SHOWN ONLY WHEN SESSION IS STORED) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-emerald-200 p-6 sm:p-7 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-4 border-b border-slate-100">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                        ✓ Teacher Session Recorded &amp; Authenticated
                                    </span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">
                                    {{ $activeTeacher['name'] ?? 'Authenticated HPC Teacher' }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    School: <strong class="text-slate-800 font-semibold">{{ $school?->name ?? 'My School' }}</strong> 
                                    • UDISE: <span class="font-mono font-semibold text-slate-700">{{ $school?->udise_code ?? '—' }}</span>
                                    • District: <span class="text-slate-700 font-semibold">{{ $school?->district ?? 'District' }}</span>
                                </p>
                            </div>

                            <div class="shrink-0 flex items-center gap-2.5 flex-wrap">
                                <a href="{{ config('hpc.login_url') }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🔗 Reopen Teacher Portal ↗
                                </a>
                                <button onclick="endHpcTeacherSession()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🚪 End Session
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- 4B. NO STORED TEACHER SESSION: HPC REGISTRATION / LOGIN PORTAL LINK -->
                    <div class="bg-white rounded-2xl shadow-xs border border-indigo-200 p-6 sm:p-7 space-y-4">
                        <div class="max-w-2xl space-y-2">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                Teacher Session Not Yet Stored
                            </div>
                            <h3 class="text-xl font-black text-slate-900">
                                Official PARAKH HPC Teacher Registration &amp; Connect
                            </h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                To evaluate students and record academic marks, teachers must log in or register directly on the official NCERT PARAKH portal. Once your session is connected and stored, your authenticated teacher details will appear here automatically.
                            </p>
                        </div>

                        <!-- Action Box -->
                        <div class="p-4 rounded-xl bg-indigo-50/70 border border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="text-xs font-bold text-indigo-900">Step 1: Open Official HPC Portal</div>
                                <div class="text-[11px] text-indigo-700">Register or sign in with your official teacher credentials:</div>
                                <code class="text-[11px] text-slate-700 bg-white px-2 py-0.5 rounded border border-indigo-200 font-mono inline-block mt-1">
                                    https://hpc.parakh.ncert.gov.in/en/login/SCHOOL,TEACHER
                                </code>
                            </div>

                            <div class="flex items-center gap-3 flex-wrap shrink-0">
                                <a href="https://hpc.parakh.ncert.gov.in/en/login/SCHOOL,TEACHER" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                    🔗 Open Official Portal to Register ↗
                                </a>
                                <button onclick="startHpcTeacherSession()"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                    ⚡ Record Teacher Session &amp; Proceed
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 4C. TEACHER ASSESSMENT TEMPLATES & UPLOAD SUITE (ALWAYS VISIBLE UNDER TEACHERS) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-5">
                    <div class="border-b border-slate-100 pb-3">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Class Assessment &amp; Evaluation Tools</div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2 mt-0.5">
                            <span>📝 Teacher Assessment Spreadsheets &amp; Portal Upload</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Download the official template to enter marks, attendance, and co-curricular grades offline, then upload the completed file.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        
                        <!-- Teacher Template Download -->
                        <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs text-slate-900 flex items-center gap-2">
                                    <span>📥 Class Assessment Template</span>
                                </h4>
                                <span class="text-[10px] font-bold bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded">.XLSX</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Download the specialized template formatted for Monthly Attendance, Credit Marks, Co-Curricular activities, and holistic teacher remarks.
                            </p>
                            <a href="{{ route('hpc.teacher.template.download') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📥 Download Teacher Template (.xlsx)
                            </a>
                        </div>

                        <!-- Teacher Template Upload -->
                        <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs text-slate-900 flex items-center gap-2">
                                    <span>📤 Upload Completed Assessment</span>
                                </h4>
                                <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded">Bulk Sync</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Upload your completed spreadsheet to validate marks, verify calculation rules, and prepare batch submission into the portal.
                            </p>
                            <form action="{{ route('hpc.teacher.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap pt-1">
                                @csrf
                                <input type="file" name="file" accept=".xlsx, .csv" required
                                       class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white file:text-slate-700 hover:file:bg-slate-100 cursor-pointer">
                                <button type="submit"
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                    Upload Assessment
                                </button>
                            </form>
                        </div>

                    </div>

                    <!-- Online 15-Part Evaluation Link -->
                    <div class="p-4 rounded-xl bg-indigo-50 border border-indigo-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <strong class="text-xs text-indigo-900 block font-bold">Prefer Online Form Filling?</strong>
                            <span class="text-xs text-indigo-700">Open the 15-part collapsible evaluation form with live auto-saving and validation.</span>
                        </div>
                        <a href="{{ route('hpc.teacher.dashboard') }}"
                           class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95 shrink-0">
                            👩‍🏫 Open Online Evaluation &rarr;
                        </a>
                    </div>

                    <!-- Bottom Nav -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button @click="setStep(3)"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition active:scale-95">
                            ← Back to Step 3: Student Records
                        </button>
                        <a href="{{ route('hpc.teacher.dashboard') }}"
                           class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                            Proceed to Online Evaluation ➔
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- ── SCHOOL PROFILE EDIT MODAL ── -->
    <div id="school-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-base text-slate-900">Edit School Profile</h3>
                <button onclick="closeSchoolModal()" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
            </div>
            <form onsubmit="saveSchoolProfile(event)" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">School Name</label>
                    <input type="text" id="modal-school-name" value="{{ $school?->name }}" required
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">UDISE Code</label>
                        <input type="text" id="modal-school-udise" value="{{ $school?->udise_code }}" required
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Head / Principal Name (Optional)</label>
                        <input type="text" id="modal-school-principal" value="{{ $school?->principal_name }}" placeholder="Optional"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Contact Phone</label>
                        <input type="text" id="modal-school-phone" value="{{ $school?->phone }}"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">District</label>
                        <input type="text" id="modal-school-district" value="{{ $school?->district ?? 'Pulwama' }}"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeSchoolModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>


    <!-- ── STUDENT EDIT MODAL ── -->
    <div id="student-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-base text-slate-900" id="student-modal-title">Edit Student Record</h3>
                <button onclick="closeStudentModal()" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
            </div>
            <form onsubmit="saveStudentRecord(event)" class="space-y-3 text-xs">
                <input type="hidden" id="modal-student-index" value="-1">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">First Name *</label>
                        <input type="text" id="modal-student-first" required
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Last Name</label>
                        <input type="text" id="modal-student-last"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Grade / Class</label>
                        <input type="text" id="modal-student-grade" placeholder="Grade 6"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Section</label>
                        <input type="text" id="modal-student-section" value="A"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date of Birth</label>
                        <input type="text" id="modal-student-dob" placeholder="DD/MM/YYYY"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">APAAR ID (12-Digit)</label>
                        <input type="text" id="modal-student-apaar" maxlength="12" placeholder="12-digit number"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeStudentModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold">Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CSRF = "{{ csrf_token() }}";
        window.rawStudentRows = @json($preview ?? []);
        window.filteredStudentRows = [...window.rawStudentRows];
        window.currentPage = 1;
        window.pageSize = 20;

        function workflowApp(initialStep) {
            return {
                currentStep: initialStep,
                setStep(s) {
                    this.currentStep = s;
                    const url = new URL(window.location);
                    url.searchParams.set('step', s);
                    window.history.replaceState({}, '', url);
                    if (s === 3) setTimeout(renderStudentTablePage, 50);
                }
            };
        }

        // ── Step 1 UDISE Manager ──
        function udiseManager() {
            return {
                syncing: false,
                loading: false,
                copiedSnippet: false,
                schoolId: {{ $school?->id ?? 0 }},
                schoolToken: '{{ $schoolToken ?? '' }}',
                udiseCode: '{{ $school?->udise_code ?? '' }}',
                statusType: 'idle',
                statusTitle: '',
                statusMessage: '',
                pollTimer: null,

                get syncSnippet() {
                    return `(async function(){try{let s=window.location.href.match(/school\\/(\\d+)/)?.[1]||document.querySelector('[href*="school/"]')?.getAttribute('href')?.match(/school\\/(\\d+)/)?.[1]||'${this.schoolId}';let b=document.createElement('div');b.style.cssText='position:fixed;top:20px;right:20px;z-index:999999;background:#065f46;color:#fff;padding:16px 22px;border-radius:12px;font-family:sans-serif;box-shadow:0 12px 30px rgba(0,0,0,0.4);font-size:14px;font-weight:bold;';b.innerHTML='🔄 HPC Assist: Fetching students & APAAR IDs...';document.body.appendChild(b);let r=await fetch('/p0/api/cy/snapshot/students/all/'+s);if(!r.ok)r=await fetch('/p0/api/cy/students/all/'+s);let j=await r.json();let st=j.data||[];b.innerHTML='⚡ Sending '+st.length+' students to HPC Assist...';let p=await fetch('https://ctetmonktest.fun/udise/ingest',{method:'POST',mode:'cors',headers:{'Content-Type':'application/json'},body:JSON.stringify({school_id:${this.schoolId},token:'${this.schoolToken}',udise_code:'${this.udiseCode}',students:st})});let d=await p.json();if(d.ok){b.style.background='#047857';b.innerHTML='✅ '+st.length+' students synced! Redirecting to HPC Assist...';setTimeout(()=>{window.location.href='https://ctetmonktest.fun/dashboard?step=2'},1200)}else{b.style.background='#991b1b';b.innerHTML='❌ Error: '+(d.error||'Failed')}}catch(e){alert('Sync Error: '+e.message)}})();`;
                },

                get bookmarkletHref() {
                    return 'javascript:' + encodeURIComponent(this.syncSnippet);
                },

                copySyncSnippet() {
                    navigator.clipboard.writeText(this.syncSnippet);
                    this.copiedSnippet = true;
                    setTimeout(() => this.copiedSnippet = false, 3000);
                },

                async uploadUdiseDirectFile(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    const fd = new FormData();
                    fd.append('file', file);
                    this.statusType = 'loading';
                    this.statusTitle = 'Importing UDISE file...';
                    this.statusMessage = 'Reading student records & extracting unmasked APAAR IDs...';
                    try {
                        const res = await fetch("{{ route('hpc.upload') }}", {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': CSRF },
                            body: fd
                        });
                        const data = await res.json();
                        if (data.ok) {
                            this.statusType = 'success';
                            this.statusTitle = 'Imported Successfully!';
                            this.statusMessage = `Loaded ${data.total} students into your school. Redirecting...`;
                            setTimeout(() => { window.location.href = "{{ route('dashboard') }}?step=2"; }, 1200);
                        } else {
                            alert(data.error || 'Could not import file.');
                            this.statusType = 'idle';
                        }
                    } catch(e) {
                        alert('Upload failed: ' + e.message);
                        this.statusType = 'idle';
                    }
                },

                init() {},

                async linkExistingRoster() {
                    this.loading = true;
                    this.statusType = 'loading';
                    this.statusTitle = 'Linking Student Records...';
                    this.statusMessage = 'Applying student roster and unmasked APAAR IDs to school account...';
                    try {
                        const res = await fetch("{{ route('school.udise.sync_existing') }}", {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.ok) {
                            this.statusType = 'success';
                            this.statusTitle = 'Synced!';
                            this.statusMessage = data.message;
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            alert(data.message || 'Could not link records.');
                            this.statusType = 'idle';
                        }
                    } catch (e) {
                        alert('Error linking existing records: ' + e.message);
                        this.statusType = 'idle';
                    } finally {
                        this.loading = false;
                    }
                },

                async startLoginAndAutoSync() {
                    this.syncing = true;
                    this.statusType = 'waiting';
                    this.statusTitle = 'Connecting to UDISE+...';
                    this.statusMessage = 'Launching browser session & opening official UDISE portal...';

                    try {
                        await fetch("{{ route('udise.session.start') }}", {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                        });
                    } catch (e) {}

                    window.open("https://sdms.udiseplus.gov.in/", "_blank");
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    this.pollTimer = setInterval(() => this.checkSession(), 2500);
                },

                async checkSession() {
                    try {
                        const res = await fetch("{{ route('udise.session.check_fetch') }}");
                        const data = await res.json();

                        if (data.ok && data.logged_in && data.status === 'fetched') {
                            clearInterval(this.pollTimer);
                            this.syncing = false;
                            this.statusType = 'success';
                            this.statusTitle = 'UDISE Data Synchronized!';
                            this.statusMessage = `Fetched ${data.total} students (${data.apaar_count} unmasked APAAR IDs). Reloading...`;
                            setTimeout(() => window.location.reload(), 1500);
                        } else if (data.state === 'waiting_login') {
                            this.statusType = 'waiting';
                            this.statusTitle = 'UDISE Login Screen Open';
                            this.statusMessage = data.message || 'Please log in to your school account on UDISE+...';
                        } else if (data.state === 'no_chrome') {
                            this.statusType = 'waiting';
                            this.statusTitle = 'Remote Web Server Mode';
                            @if(!empty($udiseCount) && $udiseCount > 0 && ($school?->udise_code === '01041800702'))
                                this.statusMessage = 'A remote server cannot control desktop Chrome tabs on your PC. Click "Link Server Student Roster" above to instantly link your records.';
                            @else
                                this.statusMessage = 'Browser automation requires local Chrome. You can import your school records using Option B (Excel template) or proceed directly to Step 2.';
                            @endif
                        } else if (data.message) {
                            this.statusMessage = data.message;
                        }
                    } catch (e) {
                        console.error('Session check error', e);
                    }
                },

                async skipUdise() {
                    this.loading = true;
                    try {
                        const res = await fetch("{{ route('school.udise.skip') }}", {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.ok) {
                            window.location.href = "{{ route('dashboard') }}?step=2";
                        }
                    } catch(e) {
                        alert('Could not record skip choice.');
                        this.loading = false;
                    }
                }
            };
        }


        async function resetUdiseSync() {
            if (!confirm('Re-sync UDISE+ data? This will reset the current sync status.')) return;
            try {
                const res = await fetch("{{ route('school.udise.reset') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.ok) window.location.href = "{{ route('dashboard') }}?step=1";
            } catch(e) {
                alert('Could not reset UDISE sync.');
            }
        }
        // Alias for any remaining references
        const resetUdiseDecision = resetUdiseSync;



        // ── Step 2 HPC School Session Controls ──
        async function startHpcSchoolSession() {
            try {
                const res = await fetch("{{ route('hpc.session.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                window.open(data.url || "{{ config('hpc.login_url') }}", "_blank");
                alert(data.message || 'Connected to HPC School session. Opening official portal.');
                window.location.reload();
            } catch(e) {
                window.open("{{ config('hpc.login_url') }}", "_blank");
                window.location.reload();
            }
        }

        async function endHpcSchoolSession() {
            if (!confirm('Log out and clear active HPC school session?')) return;
            try {
                const res = await fetch("{{ route('hpc.session.end') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                alert(data.message || 'School session ended.');
                window.location.reload();
            } catch(e) {
                alert('Could not end school session.');
            }
        }

        // ── Step 4 HPC Teacher Session Controls ──
        async function startHpcTeacherSession() {
            try {
                const res = await fetch("{{ route('hpc.teacher.session.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                window.open(data.url || "{{ config('hpc.login_url') }}", "_blank");
                alert(data.message || 'Connected to HPC Teacher session. Opening official portal.');
                window.location.reload();
            } catch(e) {
                window.open("{{ config('hpc.login_url') }}", "_blank");
                window.location.reload();
            }
        }

        async function endHpcTeacherSession() {
            if (!confirm('Log out and clear active HPC teacher session?')) return;
            try {
                const res = await fetch("{{ route('hpc.teacher.session.end') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                alert(data.message || 'Teacher session ended.');
                window.location.reload();
            } catch(e) {
                alert('Could not end teacher session.');
            }
        }

        // ── School Profile Modal ──
        function openSchoolModal() { document.getElementById('school-modal').classList.remove('hidden'); }
        function closeSchoolModal() { document.getElementById('school-modal').classList.add('hidden'); }
        async function saveSchoolProfile(e) {
            e.preventDefault();
            const payload = {
                name: document.getElementById('modal-school-name').value,
                udise_code: document.getElementById('modal-school-udise').value,
                principal_name: document.getElementById('modal-school-principal').value,
                phone: document.getElementById('modal-school-phone').value,
                district: document.getElementById('modal-school-district').value,
            };
            const res = await fetch("{{ route('school.update') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
            else alert(data.error || 'Failed to save school profile.');
        }


        // ── Step 3 Student Records Table Pagination ──
        function renderStudentTablePage() {
            const tbody = document.getElementById('student-table-body');
            if (!tbody) return;
            const total = window.filteredStudentRows.length;
            const startIdx = (window.currentPage - 1) * window.pageSize;
            const endIdx = Math.min(startIdx + window.pageSize, total);
            const pageItems = window.filteredStudentRows.slice(startIdx, endIdx);

            if (total === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400">No student records found.</td></tr>`;
                updateStudentPaginationControls(0, 0, 0);
                return;
            }

            tbody.innerHTML = pageItems.map((r, i) => {
                const idx = startIdx + i;
                const name = (r.first_name || r.name || '') + ' ' + (r.last_name || '');
                const apaar = r.apaar_id || r.apaarId || r.national_id || '—';
                const dob = r.dob || r.date_of_birth || '—';
                const grade = r.grade || r.class || 'Grade 6';
                const sec = r.section || 'A';
                return `
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2.5 px-3 font-mono text-slate-400">${idx + 1}</td>
                        <td class="py-2.5 px-3 font-bold text-slate-800">${name.trim() || 'Student'}</td>
                        <td class="py-2.5 px-3 font-semibold text-slate-600">${grade} (${sec})</td>
                        <td class="py-2.5 px-3 font-mono text-slate-600">${dob}</td>
                        <td class="py-2.5 px-3 font-mono text-slate-800">${apaar}</td>
                        <td class="py-2.5 px-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Ready
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right">
                            <button onclick="openEditStudentModal(${idx})" class="px-2 py-1 text-slate-600 hover:text-indigo-600 font-semibold bg-slate-100 hover:bg-indigo-50 rounded-lg">✏️</button>
                            <button onclick="deleteStudentRecord(${idx})" class="px-2 py-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg ml-1">🗑️</button>
                        </td>
                    </tr>
                `;
            }).join('');

            updateStudentPaginationControls(startIdx + 1, endIdx, total);
        }

        function updateStudentPaginationControls(start, end, total) {
            document.getElementById('page-start').textContent = start;
            document.getElementById('page-end').textContent = end;
            document.getElementById('page-total').textContent = total;
            const totalPages = Math.ceil(total / window.pageSize) || 1;
            document.getElementById('btn-page-prev').disabled = (window.currentPage <= 1);
            document.getElementById('btn-page-next').disabled = (window.currentPage >= totalPages);

            const numContainer = document.getElementById('page-numbers');
            numContainer.innerHTML = '';
            for (let p = 1; p <= Math.min(totalPages, 5); p++) {
                const btn = document.createElement('button');
                btn.textContent = p;
                btn.onclick = () => { window.currentPage = p; renderStudentTablePage(); };
                btn.className = (p === window.currentPage)
                    ? "px-2 py-1 bg-indigo-600 text-white rounded-lg text-xs font-bold"
                    : "px-2 py-1 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-medium";
                numContainer.appendChild(btn);
            }
        }

        function prevPage() { if (window.currentPage > 1) { window.currentPage--; renderStudentTablePage(); } }
        function nextPage() { const tp = Math.ceil(window.filteredStudentRows.length / window.pageSize); if (window.currentPage < tp) { window.currentPage++; renderStudentTablePage(); } }
        function filterStudentTable() {
            const query = (document.getElementById('student-search-input').value || '').toLowerCase();
            window.filteredStudentRows = window.rawStudentRows.filter(r => {
                const name = ((r.first_name || '') + ' ' + (r.last_name || '')).toLowerCase();
                const apaar = (r.apaar_id || r.apaarId || '').toString();
                return name.includes(query) || apaar.includes(query);
            });
            window.currentPage = 1;
            renderStudentTablePage();
        }

        // Student Modals
        function openAddStudentModal() {
            document.getElementById('modal-student-index').value = -1;
            document.getElementById('modal-student-first').value = '';
            document.getElementById('modal-student-last').value = '';
            document.getElementById('modal-student-grade').value = 'Grade 6';
            document.getElementById('modal-student-section').value = 'A';
            document.getElementById('modal-student-dob').value = '';
            document.getElementById('modal-student-apaar').value = '';
            document.getElementById('student-modal-title').textContent = 'Add Student Record';
            document.getElementById('student-modal').classList.remove('hidden');
        }
        function openEditStudentModal(idx) {
            const r = window.filteredStudentRows[idx];
            document.getElementById('modal-student-index').value = idx;
            document.getElementById('modal-student-first').value = r.first_name || r.name || '';
            document.getElementById('modal-student-last').value = r.last_name || '';
            document.getElementById('modal-student-grade').value = r.grade || 'Grade 6';
            document.getElementById('modal-student-section').value = r.section || 'A';
            document.getElementById('modal-student-dob').value = r.dob || '';
            document.getElementById('modal-student-apaar').value = r.apaar_id || r.apaarId || '';
            document.getElementById('student-modal-title').textContent = 'Edit Student Record';
            document.getElementById('student-modal').classList.remove('hidden');
        }
        function closeStudentModal() { document.getElementById('student-modal').classList.add('hidden'); }
        async function saveStudentRecord(e) {
            e.preventDefault();
            const idx = parseInt(document.getElementById('modal-student-index').value);
            const payload = {
                index: idx,
                first_name: document.getElementById('modal-student-first').value,
                last_name: document.getElementById('modal-student-last').value,
                grade: document.getElementById('modal-student-grade').value,
                section: document.getElementById('modal-student-section').value,
                dob: document.getElementById('modal-student-dob').value,
                apaar_id: document.getElementById('modal-student-apaar').value,
            };
            const res = await fetch("{{ route('hpc.students.save') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                closeStudentModal();
                window.location.reload();
            } else alert('Failed to save student.');
        }
        async function deleteStudentRecord(idx) {
            if (!confirm('Delete this student record?')) return;
            const res = await fetch("{{ route('hpc.students.delete') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ index: idx })
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
            else alert('Failed to delete student.');
        }

        // Automation Runner
        async function startRun() {
            const btn = document.getElementById('btn-run');
            const box = document.getElementById('run-progress-box');
            btn.disabled = true;
            btn.textContent = 'Starting Automation...';
            box.classList.remove('hidden');

            try {
                const res = await fetch("{{ route('hpc.run.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                if (!data.ok) {
                    alert(data.error || 'Failed to start automation');
                    btn.disabled = false;
                    btn.textContent = '🚀 Run Automation & Submit to HPC Portal';
                    box.classList.add('hidden');
                    return;
                }
                pollAutomationStatus();
            } catch(e) {
                alert('Connection error');
                btn.disabled = false;
            }
        }

        function pollAutomationStatus() {
            const interval = setInterval(async () => {
                try {
                    const res = await fetch("{{ route('hpc.run.status') }}");
                    const data = await res.json();
                    if (data.running) {
                        const pct = data.total > 0 ? Math.round((data.current / data.total) * 100) : 10;
                        document.getElementById('run-progress-bar').style.width = pct + '%';
                        document.getElementById('run-progress-percent').textContent = pct + '%';
                        document.getElementById('run-status-text').textContent = data.message || `Submitting student ${data.current} of ${data.total}...`;
                    } else {
                        clearInterval(interval);
                        document.getElementById('run-progress-bar').style.width = '100%';
                        document.getElementById('run-progress-percent').textContent = '100%';
                        document.getElementById('run-status-text').textContent = 'Automation Completed!';
                        document.getElementById('btn-run').disabled = false;
                        document.getElementById('btn-run').textContent = '🚀 Run Automation & Submit to HPC Portal';
                    }
                } catch(e) {}
            }, 2000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderStudentTablePage();
        });
    </script>
</x-app-layout>
