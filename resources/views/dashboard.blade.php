<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Existing "You're logged in" card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Welcome back, {{ Auth::user()?->name ?? 'User' }}!</h1>
                        <p class="text-sm text-gray-500 mt-1">Select an automated tool or external government portal below to begin.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        ● System Active
                    </span>
                </div>
            </div>

            <!-- UDISE+ SDMS Portal Card (Interactive Automated Student Sync & Downloader) -->
            <div x-data="udiseManager()" x-init="init()" class="bg-white overflow-hidden shadow-md sm:rounded-xl border-2 border-emerald-500/30 hover:border-emerald-500/50 transition-all">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-6 flex-wrap">
                        <div class="max-w-2xl">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                    🇮🇳 Official Govt Portal
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                      :class="syncing ? 'bg-amber-100 text-amber-800 animate-pulse' : 'bg-gray-100 text-gray-600'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="syncing ? 'bg-amber-500 animate-ping' : 'bg-gray-400'"></span>
                                    <span x-text="syncing ? 'Auto-Sync Active: Listening for Login' : 'Ready to Sync'"></span>
                                </span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <span>🌐 UDISE+ SDMS Automated Student Sync & APAAR Downloader</span>
                            </h3>
                            <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                                Simply click <strong>Login to UDISE</strong> below. As soon as you sign in on the UDISE+ SDMS portal, our system <strong>automatically detects your login, extracts all student records with unmasked 12-digit APAAR IDs</strong> and national PENs, and compiles your master spreadsheet for instant download.
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="shrink-0 flex flex-wrap gap-3 sm:items-center">
                            <!-- Button 1: Login & Auto-Sync -->
                            <button @click="startLoginAndAutoSync()"
                                    :disabled="syncing || loading"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold transition-all shadow-md active:scale-95 disabled:opacity-60"
                                    :class="syncing ? 'bg-amber-500 text-white animate-pulse' : 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white hover:shadow-indigo-200'">
                                <svg x-show="!syncing" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                <svg x-show="syncing" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="syncing ? '🔄 Listening for UDISE Login...' : '🚀 1. Login to UDISE (Auto-Sync)'"></span>
                            </button>

                            <!-- Button 2: Quick Re-Fetch (Instant if already logged in) -->
                            <button @click="fetchStudentsNow()"
                                    :disabled="loading || syncing"
                                    title="Fetch latest student records immediately from active session"
                                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-xs font-bold text-teal-800 bg-teal-50 hover:bg-teal-100 border border-teal-200 transition-all active:scale-95 disabled:opacity-50">
                                <svg x-show="!loading" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg x-show="loading" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="loading ? 'Syncing...' : '⚡ Quick Refresh'"></span>
                            </button>

                            <!-- Button 3: Download Master Excel List -->
                            <a :href="downloadUrl"
                               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 transition-all shadow-md hover:shadow-emerald-200 hover:-translate-y-0.5 active:scale-95 ring-2 ring-emerald-400/40 ring-offset-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>📥 Download Student List (.xlsx)</span>
                            </a>
                        </div>
                    </div>

                    <!-- Live Sync Alert / Banner -->
                    <div x-show="statusType !== 'idle'"
                         x-transition
                         class="mt-4 p-3.5 rounded-lg text-xs flex items-center justify-between"
                         :class="{
                             'bg-amber-50 text-amber-900 border border-amber-200': statusType === 'waiting',
                             'bg-blue-50 text-blue-900 border border-blue-200': statusType === 'loading',
                             'bg-emerald-50 text-emerald-900 border border-emerald-200': statusType === 'success',
                             'bg-rose-50 text-rose-900 border border-rose-200': statusType === 'error'
                         }">
                        <div class="flex items-center gap-2.5">
                            <span x-show="statusType === 'waiting'" class="text-base animate-spin">⏳</span>
                            <span x-show="statusType === 'loading'" class="text-base animate-pulse">🔄</span>
                            <span x-show="statusType === 'success'" class="text-base">🎉</span>
                            <span x-show="statusType === 'error'" class="text-base">⚠️</span>
                            <div>
                                <strong x-show="statusType === 'waiting'" class="block font-bold text-amber-950">Auto-Sync in Progress:</strong>
                                <strong x-show="statusType === 'success'" class="block font-bold text-emerald-950">Students Synchronized!</strong>
                                <span x-text="statusText" class="font-medium"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button x-show="syncing" @click="stopSync()" class="px-2 py-1 text-[11px] font-bold bg-amber-200 text-amber-900 rounded hover:bg-amber-300">
                                Stop Watching
                            </button>
                            <button @click="statusType = 'idle'" class="text-gray-400 hover:text-gray-600 font-bold px-1">✕</button>
                        </div>
                    </div>

                    <!-- Live Extracted Statistics Row -->
                    <div class="mt-4 pt-3.5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-sm">
                                📊 Enrolled Students: <strong x-text="totalStudents" class="text-emerald-900 font-black"></strong>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200 shadow-sm">
                                🆔 Unmasked APAAR IDs: <strong x-text="apaarCount" class="text-teal-900 font-black"></strong>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200 shadow-sm">
                                🎓 National PENs: <strong x-text="penCount" class="text-blue-900 font-black"></strong>
                            </span>
                            <span x-show="lastSynced" class="text-[11px] text-gray-500 font-medium">
                                • Synced: <span x-text="lastSynced" class="text-gray-700"></span>
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 font-medium">
                            🏫 School: <strong x-text="schoolName" class="text-gray-800"></strong> (<span x-text="udiseCode" class="font-semibold text-gray-700"></span>)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teacher Evaluation Tool Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-2 border-violet-500/20 hover:border-violet-500/40 transition-all">
                <div class="p-6 flex items-center justify-between gap-6 flex-wrap">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-violet-100 text-violet-800">
                                👩‍🏫 Teacher Workspace
                            </span>
                            <span class="text-xs font-semibold text-gray-400">• Middle Stage (Grades 6–8)</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span>Teacher Holistic Assessment Dashboard</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                            Generate streamlined Excel evaluation templates with 15 collapsible parts, auto-calculating attendance, dynamic missing-cell highlighting, and automated submission to PARAKH.
                        </p>
                    </div>
                    <div class="shrink-0">
                        <a href="{{ route('hpc.teacher.dashboard') }}"
                           style="background-color: #7c3aed; color: #ffffff;"
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-bold text-white
                                  bg-violet-600 hover:bg-violet-700 active:bg-violet-800
                                  transition-all shadow-md hover:shadow-violet-200 hover:-translate-y-0.5">
                            <span>👩‍🏫 Open Teacher Workspace</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- HPC Tool Card (PARAKH NCERT Portal Automation) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex items-center justify-between gap-6 flex-wrap">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-1.5">PARAKH NCERT Automation</p>
                        <h3 class="text-lg font-bold text-gray-900">PARAKH Student Registration Tool</h3>
                        <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                            Automate student registration, school verification, and batch enrollment directly on the PARAKH · NCERT portal.
                        </p>
                    </div>
                    <div class="shrink-0">
                        <a href="{{ route('hpc.dashboard') }}"
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-semibold text-white
                                  bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                                  transition-all shadow-md hover:shadow-indigo-200 hover:-translate-y-0.5">
                            🎓 Open Registration Tool
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function udiseManager() {
            return {
                loading: false,
                syncing: false,
                pollTimer: null,
                statusText: '',
                statusType: 'idle', // 'idle' | 'waiting' | 'loading' | 'success' | 'error'
                totalStudents: 129,
                apaarCount: 83,
                penCount: 129,
                schoolName: 'BOYS MIDDLE SCHOOL P.W CHECK',
                udiseCode: '01041800702',
                lastSynced: '',
                downloadUrl: '{{ route('udise.students.download') }}',

                async init() {
                    try {
                        const res = await fetch('{{ route('udise.status') }}');
                        const data = await res.json();
                        if (data.ok && data.has_data) {
                            this.updateStats(data.data);
                        }
                    } catch (e) {
                        console.error('UDISE status check failed:', e);
                    }
                },

                updateStats(data) {
                    if (!data) return;
                    this.totalStudents = data.total_students || 0;
                    this.apaarCount = data.total_apaar || 0;
                    this.penCount = data.total_pen || 0;
                    this.schoolName = data.school_name || 'School';
                    this.udiseCode = data.udise_code || '';
                    if (data.fetched_at) {
                        const d = new Date(data.fetched_at);
                        this.lastSynced = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ', ' + d.toLocaleDateString();
                    }
                },

                async startLoginAndAutoSync() {
                    this.syncing = true;
                    this.statusType = 'waiting';
                    this.statusText = 'Opening UDISE+ SDMS in Chrome. Please enter your school login credentials. We are actively monitoring your session...';

                    // 1. Launch Login Page in Chrome
                    try {
                        await fetch('{{ route('udise.session.start') }}', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' }
                        });
                    } catch (e) {
                        console.error('Session start trigger:', e);
                    }

                    // 2. Start Live Auto-Watcher
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    let attempts = 0;
                    const maxAttempts = 120; // 5 minutes (every 2.5s)

                    this.pollTimer = setInterval(async () => {
                        attempts++;
                        if (attempts > maxAttempts) {
                            this.stopSync();
                            this.statusType = 'idle';
                            return;
                        }

                        try {
                            const res = await fetch('{{ route('udise.session.check_fetch') }}', {
                                method: 'GET',
                                headers: { 'Accept': 'application/json' }
                            });
                            const data = await res.json();

                            if (data.ok && data.logged_in && data.status === 'fetched') {
                                // Login detected & students fetched!
                                this.stopSync();
                                this.updateStats({
                                    total_students: data.total,
                                    total_apaar: data.apaar_count,
                                    total_pen: data.pen_count,
                                    school_name: data.school_name,
                                    udise_code: data.udise_code,
                                    fetched_at: data.fetched_at
                                });
                                this.statusType = 'success';
                                this.statusText = `Successfully detected login for ${data.school_name}! Extracted ${data.total} students (${data.apaar_count} unmasked APAAR IDs, ${data.pen_count} PENs). Master spreadsheet is ready!`;
                                
                                // Automatically trigger download in user's browser
                                window.location.href = this.downloadUrl;
                            } else if (data.state === 'waiting_login') {
                                this.statusText = 'Waiting for you to sign in on UDISE (Enter username, password, captcha)...';
                            }
                        } catch (err) {
                            console.error('Polling error:', err);
                        }
                    }, 2500);
                },

                stopSync() {
                    this.syncing = false;
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                },

                async fetchStudentsNow() {
                    this.loading = true;
                    this.statusType = 'loading';
                    this.statusText = 'Connecting to active UDISE session... extracting all student details and unmasked APAAR IDs...';
                    try {
                        const res = await fetch('{{ route('udise.students.fetch') }}', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.ok) {
                            this.updateStats({
                                total_students: data.total,
                                total_apaar: data.apaar_count,
                                total_pen: data.pen_count,
                                school_name: data.school_name,
                                udise_code: data.udise_code,
                                fetched_at: data.fetched_at
                            });
                            this.statusType = 'success';
                            this.statusText = data.message;
                            
                            // Trigger download
                            window.location.href = this.downloadUrl;
                        } else {
                            this.statusType = 'error';
                            this.statusText = data.error || 'Failed to fetch student details. Please ensure you are logged into UDISE in Chrome.';
                        }
                    } catch (e) {
                        this.statusType = 'error';
                        this.statusText = 'Network error: ' + e.message;
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
