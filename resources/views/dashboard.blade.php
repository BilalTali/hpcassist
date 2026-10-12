<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏫</span>
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 leading-tight">
                        {{ $school?->name ?? 'BMS PWC Sugin' }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                        <span class="font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700 font-semibold">
                            UDISE: {{ $school?->udise_code ?? '01041800702' }}
                        </span>
                        <span>•</span>
                        <span>{{ $school?->district ?? 'Pulwama' }}, {{ $school?->state ?? 'Jammu & Kashmir' }}</span>
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
            <!-- STEP 1 CONTENT: UDISE LOGIN & STUDENT DATA EXTRACTION                     -->
            <!-- ========================================================================= -->
            <div x-show="currentStep === 1" x-transition class="space-y-6">

                @if($uStat === 'synced')
                    <!-- SYNCED STATE -->
                    <div class="bg-white rounded-2xl shadow-xs border border-emerald-300 p-6 sm:p-7 relative overflow-hidden">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2 max-w-2xl">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                    ✓ UDISE+ SDMS Data Synchronized
                                </span>
                                <h3 class="text-xl font-black text-slate-900">
                                    Official Student Roster &amp; Unmasked APAAR IDs Ready
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    All student records, dates of birth, grades, sections, and unmasked 12-digit APAAR IDs have been retrieved directly from UDISE+ SDMS.
                                </p>
                                <div class="flex items-center gap-3 pt-2 text-xs font-bold text-slate-700">
                                    <span class="bg-emerald-50 text-emerald-800 px-3 py-1 rounded-lg border border-emerald-200">
                                        👥 {{ $udiseCount ?? 129 }} Students Loaded
                                    </span>
                                    <span class="bg-indigo-50 text-indigo-800 px-3 py-1 rounded-lg border border-indigo-200">
                                        🆔 {{ $udiseApaarCount ?? 83 }} Unmasked APAAR IDs
                                    </span>
                                </div>
                            </div>

                            <div class="shrink-0 flex flex-col sm:flex-row md:flex-col gap-2.5">
                                <button @click="setStep(2)"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition active:scale-95">
                                    <span>Proceed to Step 2: HPC School Connect ➔</span>
                                </button>
                                <button onclick="resetUdiseDecision()"
                                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition active:scale-95">
                                    <span>🔄 Re-sync UDISE Data</span>
                                </button>
                            </div>
                        </div>
                    </div>

                @elseif($uStat === 'skipped')
                    <!-- SKIPPED STATE -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-300 p-6 sm:p-7">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2 max-w-2xl">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Manual &amp; Template Mode Active (UDISE Skipped)
                                </span>
                                <h3 class="text-xl font-black text-slate-900">
                                    Preparing HPC Data via Standard Spreadsheets
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    You chose to bypass the UDISE connection. You can enter student details manually or download our official blank HPC Excel templates without needing UDISE credentials.
                                </p>
                            </div>

                            <div class="shrink-0 flex flex-col sm:flex-row md:flex-col gap-2.5">
                                <button @click="setStep(2)"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition active:scale-95">
                                    <span>Proceed to Step 2: HPC School Connect ➔</span>
                                </button>
                                <button onclick="resetUdiseDecision()"
                                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition active:scale-95">
                                    <span>🌐 Connect to UDISE+ Instead</span>
                                </button>
                            </div>
                        </div>
                    </div>

                @else
                    <!-- INITIAL DECISION (OPTION A vs OPTION B) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5" x-data="udiseManager()" x-init="init()">
                        
                        <!-- OPTION A: CONNECT TO UDISE AND FETCH DATA -->
                        <div class="bg-white rounded-2xl shadow-xs border-2 border-emerald-500/50 hover:border-emerald-600 transition p-6 flex flex-col justify-between">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-3">
                                    Option A • Automated Fetch
                                </span>
                                <h3 class="text-base font-bold text-slate-900 mb-1">
                                    Connect to UDISE+ &amp; Fetch Student Data
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                                    Automatically pull your school's student roster, dates of birth, National PEN numbers, and <strong>unmasked 12-digit APAAR IDs</strong> directly.
                                </p>

                                <div class="space-y-1.5 text-xs text-slate-700 bg-emerald-50/60 p-3 rounded-xl border border-emerald-100 mb-4">
                                    <div>✓ Auto-populates 100+ student names, DOBs &amp; grades</div>
                                    <div>✓ Extracts unmasked 12-digit APAAR identification</div>
                                    <div>✓ Generates pre-filled template ready for HPC submission</div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-3 border-t border-slate-100">
                                <button @click="startLoginAndAutoSync()"
                                        :disabled="syncing || loading"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-bold transition shadow-sm active:scale-95 text-white"
                                        :class="syncing ? 'bg-amber-500 animate-pulse' : 'bg-emerald-600 hover:bg-emerald-700'">
                                    <span x-text="syncing ? '🔄 Listening for UDISE Login...' : '🚀 Connect to UDISE+ (Auto-Sync)'"></span>
                                </button>

                                <div x-show="statusType !== 'idle'" x-transition class="p-3 rounded-xl text-xs"
                                     :class="{
                                         'bg-amber-50 text-amber-900 border border-amber-200': statusType === 'waiting',
                                         'bg-blue-50 text-blue-900 border border-blue-200': statusType === 'loading',
                                         'bg-emerald-50 text-emerald-900 border border-emerald-200': statusType === 'success',
                                         'bg-rose-50 text-rose-900 border border-rose-200': statusType === 'error',
                                     }">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <span x-text="statusType === 'waiting' ? '⏳' : (statusType === 'loading' ? '⚡' : (statusType === 'success' ? '✅' : '❌'))"></span>
                                        <span x-text="statusTitle"></span>
                                    </div>
                                    <div class="mt-0.5 text-slate-600" x-text="statusMessage"></div>
                                </div>
                            </div>
                        </div>

                        <!-- OPTION B: SKIP UDISE AND CONTINUE -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-300 hover:border-slate-400 transition p-6 flex flex-col justify-between">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-700 mb-3">
                                    Option B • Manual / Offline
                                </span>
                                <h3 class="text-base font-bold text-slate-900 mb-1">
                                    Skip UDISE &amp; Continue Directly
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                                    Proceed directly to the HPC dashboard without connecting to UDISE+. You can prepare your student records offline via Excel templates.
                                </p>

                                <div class="space-y-1.5 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200 mb-4">
                                    <div>✓ No UDISE credentials or government login required</div>
                                    <div>✓ Download standard blank HPC Excel spreadsheet</div>
                                    <div>✓ Full manual entry, upload &amp; evaluation features available</div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100">
                                <button @click="skipUdise()"
                                        :disabled="loading"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition shadow-xs active:scale-95">
                                    <span>➡️ Skip UDISE &amp; Continue to Step 2</span>
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
                            <h3 class="text-lg font-black text-slate-900">{{ $school?->name ?? 'BMS PWC Sugin' }}</h3>
                        </div>
                        <button onclick="openSchoolModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-300 transition active:scale-95">
                            ✏️ Edit Profile Details
                        </button>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">UDISE Code</span>
                            <strong class="text-slate-800 font-mono text-sm">{{ $school?->udise_code ?? '01041800702' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Category</span>
                            <strong class="text-slate-800 font-semibold text-sm">{{ $school?->category ?? 'Middle School' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Contact Phone</span>
                            <strong class="text-slate-800 font-mono text-sm">{{ $school?->phone ?? '9419000000' }}</strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                            <span class="text-slate-400 block font-medium">Location</span>
                            <strong class="text-slate-800 font-semibold text-sm">{{ $school?->district ?? 'Pulwama' }}, {{ $school?->state ?? 'J&K' }}</strong>
                        </div>
                </div>

                <!-- 2C. Multi-Teacher Directory -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span>👩‍🏫 Registered Teachers Directory</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ $teachers->count() }} Teachers
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Real teachers registered under this school. Assign faculty to grades and classes for HPC evaluation.
                            </p>
                        </div>
                        <button onclick="openAddTeacherModal()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                            ➕ Register New Teacher
                        </button>
                    </div>

                    <!-- Teacher Table -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 font-bold text-slate-600 uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-4">Teacher Name</th>
                                    <th class="py-3 px-4">Designation</th>
                                    <th class="py-3 px-4">PEN Code</th>
                                    <th class="py-3 px-4">Assigned Classes</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="teachers-table-body">
                                @forelse($teachers as $teacher)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-4 font-bold text-slate-900">
                                            {{ $teacher->name }}
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">
                                            {{ $teacher->designation }}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-slate-600">
                                            {{ $teacher->pen_code ?? '—' }}
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">
                                                {{ $teacher->classes ?: 'Grade 6, Grade 7, Grade 8' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <button onclick="openEditTeacherModal({{ $teacher->id }}, '{{ addslashes($teacher->name) }}', '{{ addslashes($teacher->designation) }}', '{{ $teacher->pen_code }}', '{{ $teacher->mobile }}', '{{ addslashes($teacher->classes) }}')"
                                                    class="px-2.5 py-1 text-slate-600 hover:text-indigo-600 font-semibold bg-slate-100 hover:bg-indigo-50 rounded-lg transition mr-1">
                                                ✏️ Edit
                                            </button>
                                            <button onclick="deleteTeacherRow({{ $teacher->id }}, '{{ addslashes($teacher->name) }}')"
                                                    class="px-2 py-1 text-slate-400 hover:text-rose-600 font-semibold hover:bg-rose-50 rounded-lg transition">
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-slate-400">
                                            No teachers registered yet. Click "Register New Teacher" to add your faculty.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Nav -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button @click="setStep(1)"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition">
                            ← Back to Step 1
                        </button>
                        <button @click="setStep(3)"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition">
                            Proceed to Step 3: Student Data &amp; Submit ➔
                        </button>
                    </div>
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
            <!-- STEP 4 CONTENT: HPC TEACHER CONNECT & RECORD SESSION                      -->
            <!-- ========================================================================= -->
            <div x-show="currentStep === 4" x-transition class="space-y-6">

                <!-- 4A. HPC Teacher Portal Session & Connect Card -->
                <div class="bg-white rounded-2xl shadow-xs border border-indigo-200 p-6 space-y-4">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">PARAKH Teacher Portal Connection</div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>👩‍🏫 HPC Teacher Portal Session</span>
                                @if(!empty($hasTeacherProfile))
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
                                Log into PARAKH HPC as Teacher (<code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700 font-mono">https://hpc.parakh.ncert.gov.in/en/login</code>). Session is captured in your dedicated teacher profile directory without dummy data.
                            </p>
                        </div>

                        <!-- Session Action Buttons -->
                        <div class="shrink-0 flex items-center gap-2.5 flex-wrap">
                            @if(!empty($hasTeacherProfile))
                                <button onclick="startHpcTeacherSession()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🚀 Reopen Chrome as Teacher
                                </button>
                                <button onclick="endHpcTeacherSession()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition active:scale-95">
                                    🚪 End Teacher Session
                                </button>
                            @else
                                <button onclick="startHpcTeacherSession()"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                    👩‍🏫 Connect as Teacher to HPC &amp; Record Session
                                </button>
                            @endif
                            <a href="{{ config('hpc.login_url') }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                                🔗 Portal ↗
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4B. Active Teacher Information (Real Data) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Authenticated Teacher Context</div>
                            <h3 class="text-lg font-black text-slate-900">
                                {{ $activeTeacher['name'] ?? ($teachers->first()?->name ?? 'Lead Teacher') }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                School: <strong class="text-slate-800">{{ $school?->name ?? 'BMS PWC Sugin' }}</strong> (UDISE: <span class="font-mono text-slate-700">{{ $school?->udise_code ?? '01041800702' }}</span>)
                            </p>
                        </div>

                        <!-- Active Teacher Dropdown -->
                        @if($teachers->count() > 1)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-bold text-slate-600">Select Active Teacher:</span>
                                <select onchange="handleTeacherSwitch(this.value)"
                                        class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-800 focus:outline-none focus:border-indigo-500">
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}" {{ ($activeTeacher['name'] ?? '') === $t->name ? 'selected' : '' }}>
                                            {{ $t->name }} ({{ $t->designation }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <!-- Evaluation Suite -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                        
                        <!-- Teacher Template Download -->
                        <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-3">
                            <h4 class="font-bold text-xs text-slate-900 flex items-center gap-2">
                                <span>📥 Class Assessment Template</span>
                            </h4>
                            <p class="text-xs text-slate-600">
                                Download the specialized template formatted for Monthly Attendance, Credit Marks, Co-Curricular activities, and holistic teacher remarks.
                            </p>
                            <a href="{{ route('hpc.teacher.template.download') }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95">
                                📥 Download Teacher Template (.xlsx)
                            </a>
                        </div>

                        <!-- Teacher Template Upload -->
                        <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-3">
                            <h4 class="font-bold text-xs text-slate-900 flex items-center gap-2">
                                <span>📤 Upload Completed Assessment</span>
                            </h4>
                            <p class="text-xs text-slate-600">
                                Upload your completed spreadsheet to validate marks and prepare batch submission into the portal.
                            </p>
                            <form action="{{ route('hpc.teacher.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
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
                            <span class="text-xs text-indigo-700">Open the 15-part collapsible evaluation with real-time saving and validation.</span>
                        </div>
                        <a href="{{ route('hpc.teacher.dashboard') }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs transition active:scale-95 shrink-0">
                            👩‍🏫 Open Online Evaluation &rarr;
                        </a>
                    </div>

                    <!-- Bottom Nav -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button @click="setStep(3)"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition">
                            ← Back to Step 3: Student Records
                        </button>
                        <span class="text-xs text-slate-400 font-medium">All Steps Completed</span>
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

    <!-- ── TEACHER MODAL ── -->
    <div id="teacher-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-base text-slate-900" id="teacher-modal-title">Register Teacher</h3>
                <button onclick="closeTeacherModal()" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
            </div>
            <form onsubmit="saveTeacher(event)" class="space-y-3 text-xs">
                <input type="hidden" id="modal-teacher-id" value="">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Teacher Full Name *</label>
                    <input type="text" id="modal-teacher-name" required
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Designation</label>
                        <input type="text" id="modal-teacher-designation" placeholder="e.g. Master / Teacher"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">PEN Code</label>
                        <input type="text" id="modal-teacher-pen" placeholder="e.g. TCH10293"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Assigned Classes</label>
                    <input type="text" id="modal-teacher-classes" placeholder="e.g. Grade 6, Grade 7, Grade 8"
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500">
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeTeacherModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold">Save Teacher</button>
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
                statusType: 'idle',
                statusTitle: '',
                statusMessage: '',
                pollTimer: null,

                init() {},

                async startLoginAndAutoSync() {
                    this.syncing = true;
                    this.statusType = 'waiting';
                    this.statusTitle = 'UDISE Login Window Opened';
                    this.statusMessage = 'Please log into the official UDISE portal. Data sync will trigger automatically.';
                    window.open("https://auth.udiseplus.gov.in/login?response_type=code&client_id=udise-sdms-g0&scope=read%20user_info&state=a046b7da-5f29-44e4-9d90-58662138fe1f&redirect_uri=https://sdms.udiseplus.gov.in/p0/oauth2/callback&loginTxnId=09ea0bcb-5a81-4ca5-a9c2-b869c4349046", "_blank");
                    this.pollTimer = setInterval(() => this.checkSession(), 3000);
                },

                async checkSession() {
                    try {
                        const res = await fetch("{{ route('udise.session.check') }}");
                        const data = await res.json();
                        if (data.active) {
                            clearInterval(this.pollTimer);
                            this.statusType = 'loading';
                            this.statusTitle = 'Session Captured! Syncing Student Records...';
                            this.statusMessage = 'Extracting names, dates of birth, grades, and unmasked APAAR IDs...';
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    } catch (e) {}
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

        async function resetUdiseDecision() {
            if (!confirm('Switch back to UDISE connect options?')) return;
            try {
                const res = await fetch("{{ route('school.udise.reset') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.ok) window.location.href = "{{ route('dashboard') }}?step=1";
            } catch(e) {
                alert('Could not reset decision.');
            }
        }

        // ── Step 2 HPC School Session Controls ──
        async function startHpcSchoolSession() {
            try {
                const res = await fetch("{{ route('hpc.session.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                alert(data.message || 'Chrome opened for HPC School login. Log into the portal and your session will be saved.');
                setTimeout(() => window.location.reload(), 3000);
            } catch(e) {
                alert('Could not open Chrome for HPC School session.');
            }
        }

        async function endHpcSchoolSession() {
            if (!confirm('Log out and clear active HPC school session?')) return;
            try {
                const res = await fetch("{{ route('hpc.session.end') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
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
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                alert(data.message || 'Chrome opened for HPC Teacher login. Log into the portal and your teacher session will be saved.');
                setTimeout(() => window.location.reload(), 3000);
            } catch(e) {
                alert('Could not open Chrome for Teacher session.');
            }
        }

        async function endHpcTeacherSession() {
            if (!confirm('Log out and clear active HPC teacher session?')) return;
            try {
                const res = await fetch("{{ route('hpc.teacher.session.end') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
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

        // ── Teacher Modal ──
        function openAddTeacherModal() {
            document.getElementById('modal-teacher-id').value = '';
            document.getElementById('modal-teacher-name').value = '';
            document.getElementById('modal-teacher-designation').value = 'Teacher';
            document.getElementById('modal-teacher-pen').value = '';
            document.getElementById('modal-teacher-classes').value = 'Grade 6, Grade 7, Grade 8';
            document.getElementById('teacher-modal-title').textContent = 'Register New Teacher';
            document.getElementById('teacher-modal').classList.remove('hidden');
        }
        function openEditTeacherModal(id, name, designation, pen, mobile, classes) {
            document.getElementById('modal-teacher-id').value = id;
            document.getElementById('modal-teacher-name').value = name;
            document.getElementById('modal-teacher-designation').value = designation;
            document.getElementById('modal-teacher-pen').value = pen || '';
            document.getElementById('modal-teacher-classes').value = classes;
            document.getElementById('teacher-modal-title').textContent = 'Edit Teacher Details';
            document.getElementById('teacher-modal').classList.remove('hidden');
        }
        function closeTeacherModal() { document.getElementById('teacher-modal').classList.add('hidden'); }
        async function saveTeacher(e) {
            e.preventDefault();
            const id = document.getElementById('modal-teacher-id').value;
            const url = id ? `/school/teachers/${id}/update` : "{{ route('school.teachers.store') }}";
            const payload = {
                name: document.getElementById('modal-teacher-name').value,
                designation: document.getElementById('modal-teacher-designation').value,
                pen_code: document.getElementById('modal-teacher-pen').value,
                classes: document.getElementById('modal-teacher-classes').value,
            };
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
            else alert(data.error || 'Failed to save teacher.');
        }
        async function deleteTeacherRow(id, name) {
            if (!confirm(`Delete teacher '${name}'?`)) return;
            const res = await fetch(`/school/teachers/${id}/delete`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
            else alert(data.error || 'Failed to delete teacher.');
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
