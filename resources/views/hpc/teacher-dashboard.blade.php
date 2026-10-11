<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    ← Dashboard
                </a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('hpc.dashboard') }}" class="text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                    Student Registration
                </a>
                <span class="text-slate-300">/</span>
                <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight flex items-center gap-2">
                    👩‍🏫 Teacher HPC Evaluation Suite
                </h2>
            </div>

            <!-- Multi-School & Multi-Teacher Controls -->
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Teacher Selector -->
                <div class="flex items-center gap-2 bg-white border border-slate-300 rounded-xl px-3 py-1.5 shadow-sm">
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                        👩‍🏫 Teacher:
                    </span>
                    <select id="teacher-select" onchange="handleTeacherChange(this.value)"
                            class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
                        @foreach($activeSchool['teachers'] ?? [] as $t)
                            <option value="{{ $t['id'] }}" {{ $t['id'] === $activeTeacher['id'] ? 'selected' : '' }}>
                                {{ $t['name'] }}
                            </option>
                        @endforeach
                        <option value="__add_new_teacher__">➕ + Add New Teacher Profile...</option>
                    </select>
                    @if(($activeTeacher['id'] ?? '') !== 'teacher_default')
                        <button onclick="deleteActiveTeacher('{{ $activeTeacher['id'] }}', '{{ addslashes($activeTeacher['name']) }}')"
                                class="text-slate-400 hover:text-rose-600 p-0.5 text-xs transition" title="Delete this teacher profile">
                            🗑️
                        </button>
                    @endif
                </div>

                <!-- Session Status Badge -->
                @if($hasSession)
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm"
                          title="Logged in session active for {{ $activeTeacher['name'] }}">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $activeTeacher['name'] }} Ready
                    </span>
                    <button onclick="endTeacherSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition shadow-sm"
                            title="Log out teacher session">
                        🚪 Log Out Teacher
                    </button>
                @else
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        No Active Session
                    </span>
                    <button onclick="startTeacherSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition shadow-sm"
                            title="Open Chrome to log in as Teacher">
                        🚀 Log In as Teacher
                    </button>
                @endif

                <a href="{{ config('hpc.hpc_home_url') }}" target="_blank"
                   class="text-xs text-slate-600 hover:text-indigo-600 bg-white border border-slate-200 px-3 py-1.5 rounded-full font-medium transition shadow-sm flex items-center gap-1">
                    🔗 Portal ↗
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-100/60 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- STAGE SELECTOR & STUDENT DISCOVERY -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🏫 Stage Selection &amp; Student Discovery</span>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">Live Sync</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Select the evaluation stage to sync student rosters and direct assessment URLs from the HPC portal.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <select id="stage-select"
                                class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
                            <option value="all" selected>All Stages (Middle &amp; Preparatory)</option>
                            @foreach($stages as $st)
                                <option value="{{ $st['slug'] }}">
                                    {{ $st['title'] }} ({{ $st['slug'] }})
                                </option>
                            @endforeach
                        </select>
                        <button id="btn-discover" onclick="discoverStageStudents()"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs shadow-sm transition">
                            <span id="discover-spinner" class="hidden">⏳</span>
                            <span>🔄 Sync Stage Students</span>
                        </button>
                    </div>
                </div>

                <!-- Discovered Students Roster Table -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">
                            Enrolled Students (<span id="students-count">{{ count($students) }}</span>)
                        </span>
                        <span class="text-[11px] text-slate-500 font-medium">
                            School: <strong class="text-slate-800">{{ $activeSchool['name'] }}</strong>
                        </span>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-white text-slate-500 border-b border-slate-100 font-semibold sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">APAAR ID</th>
                                    <th class="px-3 py-2">HPC Login ID *</th>
                                    <th class="px-3 py-2">Student Name</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Stage</th>
                                    <th class="px-3 py-2">Update Action</th>
                                </tr>
                            </thead>
                            <tbody id="students-table-body" class="divide-y divide-slate-100 bg-white">
                                @forelse($students as $st)
                                    @php
                                        $isCompleted = strcasecmp($st['status'] ?? '', 'HPC Completed') === 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-3 py-2 font-mono text-slate-400">{{ $st['row'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-slate-600">{{ !empty($st['apaar_id']) ? $st['apaar_id'] : '—' }}</td>
                                        <td class="px-3 py-2 font-mono font-bold text-slate-800">{{ $st['login_id'] }}</td>
                                        <td class="px-3 py-2 font-bold text-slate-900">{{ $st['name'] }}</td>
                                        <td class="px-3 py-2">
                                            @if($isCompleted)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    ✓ HPC Completed (Protected)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                                    ⏳ {{ $st['status'] ?? 'HPC Pending' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-600 text-[11px] font-medium">
                                            {{ $st['stage_title'] ?? $st['stage'] ?? 'Middle Stage' }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-[11px]">
                                            @if($isCompleted)
                                                <span class="text-slate-400 italic">Left untouched</span>
                                            @else
                                                <span class="text-indigo-600 font-semibold">Ready for update</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-slate-400 italic">
                                            No students loaded yet. Click "Sync Stage Students" above to fetch from Middle &amp; Preparatory stages.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SCHOOL WORKING DAYS CONFIGURATION (ACADEMIC CALENDAR) -->
            @php
                $calMonths = [
                    'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
                    'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
                    'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
                    'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
                ];
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>📅 School Working Days Configuration</span>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-md">
                                Academic Year {{ $workingDays->academic_year ?? '2025-2026' }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Working days are identical for each school and all students. The monthly days configured below are set as default and automatically pre-populated into every student's evaluation row in the Excel template.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                            <span class="text-xs text-slate-500 font-medium">Total Working Days:</span>
                            <span id="wd-total-display" class="text-sm font-extrabold text-indigo-700 font-mono">{{ $workingDays->total_days ?? 222 }}</span>
                            <span class="text-xs text-slate-400">days</span>
                        </div>
                        <button onclick="saveWorkingDays()" id="btn-save-wd"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition">
                            <span>💾 Set as Default</span>
                        </button>
                        <button onclick="resetWorkingDaysToStandard()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition"
                                title="Reset to standard academic 222 working days">
                            <span>↺ Standard</span>
                        </button>
                    </div>
                </div>

                <!-- 12 Monthly Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 pt-2">
                    @foreach($calMonths as $mKey => $mLabel)
                        <div class="bg-slate-50 hover:bg-slate-100/80 border border-slate-200 rounded-xl p-2.5 transition flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700">{{ $mLabel }}</span>
                                <span class="text-[10px] text-slate-400">Working Days</span>
                            </div>
                            <input type="number" id="wd-{{ $mKey }}" min="0" max="31"
                                   value="{{ $workingDays->{$mKey} ?? 20 }}"
                                   oninput="recalcWorkingDaysTotal()"
                                   class="w-14 text-center font-bold text-sm bg-white border border-slate-300 rounded-lg px-1.5 py-1 text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 font-mono">
                        </div>
                    @endforeach
                </div>

                <div id="wd-status-msg" class="mt-3 text-xs font-semibold hidden"></div>
            </div>

            <!-- CURRICULAR MAX MARKS CONFIGURATION (HPC CREDIT) -->
            @php
                $curricularSubjects = [
                    'l1_max'  => ['name' => 'Lang 1 (R1)', 'full' => 'Language 1'],
                    'l2_max'  => ['name' => 'Lang 2 (R2)', 'full' => 'Language 2'],
                    'l3_max'  => ['name' => 'Lang 3 (R3)', 'full' => 'Language 3'],
                    'm_max'   => ['name' => 'Maths',       'full' => 'Mathematics'],
                    'sci_max' => ['name' => 'Science',     'full' => 'Science'],
                    'soc_max' => ['name' => 'Soc Sci',     'full' => 'Social Science'],
                    'art_max' => ['name' => 'Art Ed',      'full' => 'Art Education'],
                    'pe_max'  => ['name' => 'Phys Ed',     'full' => 'PE & Well Being'],
                ];
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🎯 Curricular Max Marks Configuration (HPC Credit)</span>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md">
                                Summative Assessment
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Set default maximum marks per subject for your school (standard PARAKH Middle Stage is 50). These defaults are automatically pre-populated into every student's row in the Excel template.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-600 font-medium">
                            <span>Presets:</span>
                            <button type="button" onclick="presetMaxMarks(50)"
                                    class="px-2 py-0.5 bg-white hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-md font-bold text-[11px] shadow-sm transition"
                                    title="Set all subjects to 50 (PARAKH Standard)">
                                All 50
                            </button>
                            <button type="button" onclick="presetMaxMarks(100)"
                                    class="px-2 py-0.5 bg-white hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-md font-bold text-[11px] shadow-sm transition"
                                    title="Set all subjects to 100">
                                All 100
                            </button>
                            <button type="button" onclick="presetMaxMarks(40)"
                                    class="px-2 py-0.5 bg-white hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-md font-bold text-[11px] shadow-sm transition"
                                    title="Set all subjects to 40">
                                All 40
                            </button>
                        </div>
                        <button onclick="saveMaxMarks()" id="btn-save-mm"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-sm transition">
                            <span>💾 Set as Default</span>
                        </button>
                    </div>
                </div>

                <!-- 8 Subjects Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 pt-2">
                    @foreach($curricularSubjects as $sKey => $sInfo)
                        <div class="bg-slate-50 hover:bg-slate-100/80 border border-slate-200 rounded-xl p-2.5 transition flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700">{{ $sInfo['name'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ $sInfo['full'] }}</span>
                            </div>
                            <input type="number" id="mm-{{ $sKey }}" min="1" max="100"
                                   value="{{ $maxMarks->{$sKey} ?? 50 }}"
                                   class="w-14 text-center font-bold text-sm bg-white border border-slate-300 rounded-lg px-1.5 py-1 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 font-mono">
                        </div>
                    @endforeach
                </div>

                <div id="mm-status-msg" class="mt-3 text-xs font-semibold hidden"></div>
            </div>

            <!-- TWO COLUMNS: STEP 1 (TEMPLATE) & STEP 2 (UPLOAD) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- STEP 1: Download Excel Template -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">1</span>
                            <h3 class="text-sm font-bold text-slate-900">Download Condensed Assessment Template (36 Columns Visible)</h3>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">
                            Opens in a compact <strong>36-column default view</strong> with zero horizontal dragging. 111 detailed secondary columns are collapsed into 7 groups ready to expand with <code>[ + ]</code>.
                        </p>

                        <!-- Simplification Features List -->
                        <div class="space-y-2 mb-4">
                            <div class="bg-emerald-50 rounded-xl p-2.5 border border-emerald-200 text-[11px] text-emerald-950 flex items-start gap-2">
                                <span class="text-sm mt-0.5">⚡</span>
                                <div>
                                    <strong class="text-emerald-900 font-bold">Zero Horizontal Dragging:</strong> Non-essential columns are collapsed by default. You see Identity, Attendance Summary, Ambition, Activity Title, and all 8 Subject Marks right on screen!
                                </div>
                            </div>

                            <div class="bg-amber-50 rounded-xl p-2.5 border border-amber-200 text-[11px] text-amber-950 flex items-start gap-2">
                                <span class="text-sm mt-0.5">✏️</span>
                                <div>
                                    <strong class="text-amber-900 font-bold">Obtained Marks Focus:</strong> All 8 subject columns (<code>✏️ Lang 1 Marks Obtained</code> to <code>Physical Ed Marks Obt</code>) are highlighted in soft yellow with default max marks (50) already set.
                                </div>
                            </div>

                            <div class="bg-indigo-50/70 rounded-xl p-2.5 border border-indigo-100 text-[11px] text-indigo-950 flex items-start gap-2">
                                <span class="text-sm mt-0.5">📁</span>
                                <div>
                                    <strong class="text-indigo-900 font-bold">One-Click Group Expansion [ + ]:</strong> Monthly Attendance (24 cols), Part-A2 (26 cols), Part-A3 (9 cols), Part-A4 (26 cols), and Part-B reflections (20 cols) expand instantly whenever needed.
                                </div>
                            </div>

                            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-200 text-[11px] text-slate-800 flex items-start gap-2">
                                <span class="text-sm mt-0.5">🔒</span>
                                <div>
                                    <strong class="text-slate-900 font-bold">Single Demo Guide (Row 4 Only):</strong> Only Row 4 has sample reference entries. Real students (Row 5+) have clean blank evaluation fields—no dummy text or dummy marks to erase!
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('hpc.teacher.template.download') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                        📥 Download Condensed Assessment Template (.xlsx)
                    </a>
                </div>

                <!-- STEP 2: Upload Filled Excel -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">2</span>
                            <h3 class="text-sm font-bold text-slate-900">Upload Filled Assessment Excel</h3>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">
                            Upload your completed spreadsheet. Rows will be validated instantly before submission.
                        </p>

                        <div id="drop-zone" onclick="document.getElementById('file-input').click()"
                             class="border-2 border-dashed border-slate-300 hover:border-indigo-500 rounded-xl p-4 text-center cursor-pointer transition bg-slate-50/60 hover:bg-indigo-50/30">
                            <input type="file" id="file-input" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleFileUpload(this.files[0])">
                            <div class="text-2xl mb-1">📤</div>
                            <div class="text-xs font-bold text-slate-700">Click to upload or drag &amp; drop</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">XLSX, XLS (up to 10MB)</div>
                        </div>
                    </div>

                    <div id="upload-status" class="mt-3 text-xs font-medium text-slate-500 hidden"></div>
                </div>
            </div>

            <!-- STEP 3: Preview & Execution Engine -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">3</span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Execute Bulk HPC Assessment</h3>
                            <p class="text-xs text-slate-500">Auto-submits evaluation forms across all stages for each validated student.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button id="btn-reset" onclick="resetRun()"
                                class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                            Reset Run
                        </button>
                        <button id="btn-run" onclick="startRun()"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                            🚀 Start Bulk Evaluation
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div id="progress-container" class="space-y-2 mb-6 hidden">
                    <div class="flex justify-between text-xs font-bold text-slate-700">
                        <span id="progress-text">Processing...</span>
                        <span id="progress-percent">0%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                        <div id="progress-bar" class="bg-gradient-to-r from-emerald-500 to-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Live Results Table -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 text-xs font-bold text-slate-700 flex justify-between">
                        <span>Execution Results</span>
                        <span id="results-count" class="font-normal text-slate-500">0 evaluated</span>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-white text-slate-500 border-b border-slate-100 font-semibold sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">Row</th>
                                    <th class="px-3 py-2">APAAR ID</th>
                                    <th class="px-3 py-2">HPC Login ID *</th>
                                    <th class="px-3 py-2">Student Name</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Outcome / Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="results-table-body" class="divide-y divide-slate-100 bg-white">
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                                        Upload an Excel file and start the evaluation to see live progress here.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- JavaScript Controllers -->
    <script>
        const csrfToken = '{{ csrf_token() }}';

        // ── Teacher Profile Switcher ──────────────────────────────────────────
        async function handleTeacherChange(val) {
            if (val === '__add_new_teacher__') {
                const name = prompt('Enter Teacher Name:');
                if (!name) {
                    document.getElementById('teacher-select').value = '{{ $activeTeacher['id'] }}';
                    return;
                }
                const res = await fetch('{{ route('hpc.teacher.add') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ name })
                });
                const d = await res.json();
                if (d.ok) window.location.reload();
                else alert(d.error || 'Failed to add teacher');
                return;
            }

            const res = await fetch('{{ route('hpc.teacher.switch') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ id: val })
            });
            const d = await res.json();
            if (d.ok) window.location.reload();
        }

        async function deleteActiveTeacher(id, name) {
            if (!confirm(`Are you sure you want to delete profile for teacher ${name}?`)) return;
            const res = await fetch('{{ route('hpc.teacher.delete') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ id })
            });
            const d = await res.json();
            if (d.ok) window.location.reload();
            else alert(d.error || 'Cannot delete profile');
        }

        async function startTeacherSession() {
            const res = await fetch('{{ route('hpc.teacher.session.start') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            const d = await res.json();
            alert(d.message || 'Chrome opened');
            setTimeout(() => window.location.reload(), 2000);
        }

        async function endTeacherSession() {
            if (!confirm('Log out active teacher session?')) return;
            const res = await fetch('{{ route('hpc.teacher.session.end') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            const d = await res.json();
            window.location.reload();
        }

        // ── Stage Student Discovery ───────────────────────────────────────────
        async function discoverStageStudents() {
            const stage = document.getElementById('stage-select').value;
            const btn = document.getElementById('btn-discover');
            const spinner = document.getElementById('discover-spinner');

            btn.disabled = true;
            spinner.classList.remove('hidden');

            try {
                const res = await fetch('{{ route('hpc.teacher.discover') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ stage })
                });
                const d = await res.json();
                if (d.ok) {
                    alert(`Discovered ${d.total} students in ${stage}!`);
                    window.location.reload();
                } else {
                    alert(d.error || 'Discovery failed');
                }
            } catch (e) {
                alert('Discovery error: ' + e.message);
            } finally {
                btn.disabled = false;
                spinner.classList.add('hidden');
            }
        }

        // ── File Upload ───────────────────────────────────────────────────────
        async function handleFileUpload(file) {
            if (!file) return;
            const statusDiv = document.getElementById('upload-status');
            statusDiv.classList.remove('hidden');
            statusDiv.innerHTML = '<span class="text-indigo-600 font-bold">Uploading &amp; validating Excel...</span>';

            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await fetch('{{ route('hpc.teacher.upload') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData
                });
                const d = await res.json();
                if (d.ok) {
                    statusDiv.innerHTML = `<span class="text-emerald-600 font-bold">✓ Validated ${d.valid} students (${d.total} total rows)</span>`;
                    renderPreviewTable(d.preview);
                } else {
                    statusDiv.innerHTML = `<span class="text-rose-600 font-bold">✕ Error: ${d.error}</span>`;
                }
            } catch (e) {
                statusDiv.innerHTML = `<span class="text-rose-600 font-bold">✕ Upload failed: ${e.message}</span>`;
            }
        }

        function renderPreviewTable(preview) {
            const tbody = document.getElementById('results-table-body');
            tbody.innerHTML = '';
            preview.forEach(p => {
                const tr = document.createElement('tr');
                let badge = '';
                let remarks = '';

                if (p.is_demo) {
                    badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">📖 Demo Guide (Skipped)</span>';
                    remarks = '<span class="text-amber-700 font-semibold italic">Sample student guide demonstrating all 65 filled fields (skipped in run)</span>';
                } else if (p.is_completed) {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">✓ HPC Completed (Protected)</span>';
                    remarks = '<span class="text-slate-400 italic">Will be left untouched during bulk run</span>';
                } else if (p.errors && p.errors.length) {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Invalid</span>';
                    remarks = '<span class="text-rose-600 font-semibold">' + p.errors.join(', ') + '</span>';
                } else {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">● Ready to Update</span>';
                    remarks = '<span class="text-slate-600">Grade: ' + p.grade + ' | Mother Tongue: ' + p.mother_tongue + ' | Approach: ' + p.act_approach + '</span>';
                }

                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-slate-400">${p.row}</td>
                    <td class="px-3 py-2 font-mono text-slate-600">${p.apaar_id || '—'}</td>
                    <td class="px-3 py-2 font-mono font-bold text-slate-800">${p.login_id}</td>
                    <td class="px-3 py-2 font-bold text-slate-900">${p.name}</td>
                    <td class="px-3 py-2">${badge}</td>
                    <td class="px-3 py-2 text-xs">${remarks}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // ── Execution Runner ──────────────────────────────────────────────────
        let pollTimer = null;

        async function startRun() {
            if (!confirm('Start bulk HPC assessment evaluation for all uploaded students?')) return;
            const res = await fetch('{{ route('hpc.teacher.run.start') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            const d = await res.json();
            if (d.ok) {
                document.getElementById('progress-container').classList.remove('hidden');
                pollProgress();
            } else {
                alert(d.error || 'Failed to start evaluation runner');
            }
        }

        function pollProgress() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(async () => {
                const res = await fetch('{{ route('hpc.teacher.run.status') }}');
                const data = await res.json();

                if (!data.started) return;

                const processed = data.processed || 0;
                const total = data.total || 1;
                const pct = Math.round((processed / total) * 100);

                document.getElementById('progress-bar').style.width = pct + '%';
                document.getElementById('progress-percent').innerText = pct + '%';
                document.getElementById('progress-text').innerText = data.login_wait ? 'Waiting for portal login...' : `Evaluating student ${processed} of ${total}...`;

                if (data.results && data.results.length) {
                    renderResultsTable(data.results);
                }

                if (data.done || data.fatal) {
                    clearInterval(pollTimer);
                    document.getElementById('progress-text').innerText = data.fatal ? `Fatal Error: ${data.fatal}` : 'Evaluation Run Completed!';
                    alert(data.fatal ? `Run failed: ${data.fatal}` : 'All students evaluated successfully!');
                }
            }, 2000);
        }

        function renderResultsTable(results) {
            const tbody = document.getElementById('results-table-body');
            tbody.innerHTML = '';
            results.forEach(r => {
                const tr = document.createElement('tr');
                let badge = '';
                if (r.status === 'skipped') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">⏭ Left Untouched</span>';
                } else if (r.status === 'success') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">✓ Completed</span>';
                } else {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Error</span>';
                }

                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-slate-400">${r.row}</td>
                    <td class="px-3 py-2 font-mono text-slate-600">${r.apaar_id || '—'}</td>
                    <td class="px-3 py-2 font-mono font-bold text-slate-800">${r.login_id}</td>
                    <td class="px-3 py-2 font-bold text-slate-900">${r.name}</td>
                    <td class="px-3 py-2">${badge}</td>
                    <td class="px-3 py-2 text-slate-600">${r.message}</td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('results-count').innerText = `${results.length} processed`;
        }

        async function resetRun() {
            if (!confirm('Reset current evaluation results?')) return;
            await fetch('{{ route('hpc.teacher.run.reset') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            window.location.reload();
        }

        // ─── Working Days Defaults Management ─────────────────────────────────────
        const wdMonthKeys = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];

        function recalcWorkingDaysTotal() {
            let total = 0;
            wdMonthKeys.forEach(m => {
                const inp = document.getElementById('wd-' + m);
                const val = inp ? parseInt(inp.value, 10) : 0;
                total += (isNaN(val) ? 0 : val);
            });
            const disp = document.getElementById('wd-total-display');
            if (disp) disp.innerText = total;
            return total;
        }

        function resetWorkingDaysToStandard() {
            const standard = {
                apr: 20, may: 22, jun: 10, jul: 20, aug: 22, sep: 21,
                oct: 19, nov: 20, dec: 15, jan: 15, feb: 20, mar: 18
            };
            wdMonthKeys.forEach(m => {
                const inp = document.getElementById('wd-' + m);
                if (inp) inp.value = standard[m];
            });
            recalcWorkingDaysTotal();
            showWdStatus('Reset to standard calendar (222 days). Click "Set as Default" to save changes.', 'text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg inline-block');
        }

        async function saveWorkingDays() {
            const btn = document.getElementById('btn-save-wd');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>⏳ Saving...</span>';

            const payload = {};
            wdMonthKeys.forEach(m => {
                const inp = document.getElementById('wd-' + m);
                payload[m] = inp ? parseInt(inp.value, 10) : 0;
            });

            try {
                const res = await fetch('{{ route('hpc.teacher.working_days.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    showWdStatus('✓ ' + data.message + ' Total: ' + (data.data.total_days || recalcWorkingDaysTotal()) + ' Days.', 'text-emerald-800 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg inline-block');
                } else {
                    showWdStatus('✕ ' + (data.error || 'Failed to save working days.'), 'text-rose-800 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg inline-block');
                }
            } catch (err) {
                showWdStatus('✕ Network error: ' + err.message, 'text-rose-800 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg inline-block');
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        function showWdStatus(msg, classes) {
            const el = document.getElementById('wd-status-msg');
            if (!el) return;
            el.className = 'mt-3 text-xs font-semibold ' + classes;
            el.innerText = msg;
            el.classList.remove('hidden');
        }

        // ─── Curricular Max Marks Defaults Management ──────────────────────────────
        const mmSubjectKeys = ['l1_max', 'l2_max', 'l3_max', 'm_max', 'sci_max', 'soc_max', 'art_max', 'pe_max'];

        function presetMaxMarks(val) {
            mmSubjectKeys.forEach(k => {
                const inp = document.getElementById('mm-' + k);
                if (inp) inp.value = val;
            });
            showMmStatus('Preset applied: All subjects set to ' + val + ' marks. Click "Set as Default" to save changes.', 'text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg inline-block');
        }

        async function saveMaxMarks() {
            const btn = document.getElementById('btn-save-mm');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>⏳ Saving...</span>';

            const payload = {};
            mmSubjectKeys.forEach(k => {
                const inp = document.getElementById('mm-' + k);
                payload[k] = inp ? parseInt(inp.value, 10) : 50;
            });

            try {
                const res = await fetch('{{ route('hpc.teacher.max_marks.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    showMmStatus('✓ ' + data.message + ' (All student rows in newly downloaded Excel will reflect these marks)', 'text-emerald-800 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg inline-block');
                } else {
                    showMmStatus('✕ ' + (data.message || 'Failed to save max marks.'), 'text-rose-800 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg inline-block');
                }
            } catch (err) {
                showMmStatus('✕ Network error: ' + err.message, 'text-rose-800 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg inline-block');
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        function showMmStatus(msg, classes) {
            const el = document.getElementById('mm-status-msg');
            if (!el) return;
            el.className = 'mt-3 text-xs font-semibold ' + classes;
            el.innerText = msg;
            el.classList.remove('hidden');
        }
    </script>
</x-app-layout>
