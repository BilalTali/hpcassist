<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                    Dashboard
                </a>
                <span class="text-slate-300 text-xs">/</span>
                <a href="{{ route('hpc.dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    Student Registration
                </a>
                <span class="text-slate-300 text-xs">/</span>
                <h2 class="font-extrabold text-lg text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    Teacher Assessment Suite
                </h2>
            </div>

            <!-- Profile & Portal Controls -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Teacher Selector -->
                <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-lg px-2.5 py-1 shadow-sm">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Teacher:</span>
                    <select id="teacher-select" onchange="handleTeacherChange(this.value)"
                            class="text-xs font-bold bg-transparent border-none py-0 pl-1 pr-6 text-slate-800 focus:ring-0 cursor-pointer">
                        @foreach($activeSchool['teachers'] ?? [] as $t)
                            <option value="{{ $t['id'] }}" {{ $t['id'] === $activeTeacher['id'] ? 'selected' : '' }}>
                                {{ $t['name'] }}
                            </option>
                        @endforeach
                        <option value="__add_new_teacher__">+ Add New Teacher...</option>
                    </select>
                    @if(($activeTeacher['id'] ?? '') !== 'teacher_default')
                        <button onclick="deleteActiveTeacher('{{ $activeTeacher['id'] }}', '{{ addslashes($activeTeacher['name']) }}')"
                                class="text-slate-400 hover:text-rose-600 text-xs transition" title="Delete teacher profile">
                            ✕
                        </button>
                    @endif
                </div>

                <!-- Session Status Badge -->
                @if($hasSession)
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                          title="Logged in session active for {{ $activeTeacher['name'] }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $activeTeacher['name'] }} (Ready)
                    </span>
                    <button onclick="endTeacherSession()"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-rose-50 text-slate-600 hover:text-rose-700 border border-slate-200 transition shadow-sm">
                        Log Out
                    </button>
                @else
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        No Active Session
                    </span>
                    <button onclick="startTeacherSession()"
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                        Log In as Teacher
                    </button>
                @endif

                <a href="{{ config('hpc.hpc_home_url') }}" target="_blank"
                   class="text-xs text-slate-600 hover:text-indigo-600 bg-white border border-slate-200 px-2.5 py-1 rounded-lg font-medium transition shadow-sm flex items-center gap-1">
                    Portal ↗
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-slate-50/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- EXECUTIVE QUICK METRICS STRIP -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl p-3 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Enrolled Roster</div>
                        <div class="text-base font-extrabold text-slate-900 mt-0.5"><span id="metric-students-count">{{ count($students) }}</span> Students</div>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-sm">👥</span>
                </div>

                <div class="bg-white rounded-xl p-3 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Academic Calendar</div>
                        <div class="text-base font-extrabold text-slate-900 mt-0.5">{{ $workingDays->total_days ?? 222 }} Days</div>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 font-bold flex items-center justify-center text-sm">📅</span>
                </div>

                <div class="bg-white rounded-xl p-3 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Subject Max Marks</div>
                        <div class="text-base font-extrabold text-slate-900 mt-0.5">{{ $maxMarks->l1_max ?? 50 }} Marks</div>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 font-bold flex items-center justify-center text-sm">🎯</span>
                </div>

                <div class="bg-white rounded-xl p-3 border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Template View</div>
                        <div class="text-base font-extrabold text-indigo-700 mt-0.5">33 Visible Cols</div>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-sm">⚡</span>
                </div>
            </div>

            <!-- CORE 3-STEP ASSESSMENT PIPELINE -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- STEP 1: DOWNLOAD TEMPLATE -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Step 1 · Download
                            </span>
                            <span class="text-[11px] text-slate-400 font-medium">33 Cols Active</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Assessment Template (.xlsx)</h3>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            Compact sheet without horizontal dragging. Fill obtained marks directly on screen.
                        </p>

                        <ul class="text-[11px] text-slate-600 space-y-1.5 mb-4 font-medium">
                            <li class="flex items-center gap-1.5">
                                <span class="text-emerald-600">✓</span>
                                <span><strong>Identity:</strong> Only Student Name visible (frozen on left)</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-rose-600">●</span>
                                <span><strong>Smart Red Alert:</strong> Empty cells turn red until filled</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-amber-600">✏️</span>
                                <span><strong>Marks Ready:</strong> 8 subject columns highlighted yellow</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-indigo-600">📁</span>
                                <span><strong>Expand [ + ]:</strong> 114 detailed columns collapsed in groups</span>
                            </li>
                        </ul>
                    </div>

                    <a href="{{ route('hpc.teacher.template.download') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                        📥 Download Template (.xlsx)
                    </a>
                </div>

                <!-- STEP 2: UPLOAD & VALIDATE -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Step 2 · Upload
                            </span>
                            <span class="text-[11px] text-slate-400 font-medium">Auto-Validate</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Upload Completed Excel</h3>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            Upload your filled template. Real students are validated instantly before running.
                        </p>

                        <div id="drop-zone" onclick="document.getElementById('file-input').click()"
                             class="border-2 border-dashed border-slate-200 hover:border-indigo-500 rounded-lg p-5 text-center cursor-pointer transition bg-slate-50/50 hover:bg-indigo-50/30">
                            <input type="file" id="file-input" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleFileUpload(this.files[0])">
                            <div class="text-xl mb-1">📤</div>
                            <div class="text-xs font-bold text-slate-700">Click or drag &amp; drop filled file</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">XLSX, XLS (up to 10MB)</div>
                        </div>
                    </div>

                    <div id="upload-status" class="mt-3 text-xs font-medium text-slate-500 text-center hidden"></div>
                </div>

                <!-- STEP 3: BULK EVALUATION RUNNER -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200">
                                Step 3 · Evaluate
                            </span>
                            <span class="text-[11px] text-slate-400 font-medium">Chrome Automation</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Execute Assessment</h3>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            Automatically updates forms on the PARAKH portal student-by-student with live feedback.
                        </p>

                        <!-- Progress Bar (shown when active) -->
                        <div id="progress-container" class="space-y-1.5 mb-4 p-3 bg-slate-50 border border-slate-200 rounded-lg hidden">
                            <div class="flex justify-between text-xs font-bold text-slate-700">
                                <span id="progress-text">Processing...</span>
                                <span id="progress-percent">0%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                <div id="progress-bar" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button id="btn-reset" onclick="resetRun()"
                                class="w-1/3 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition text-center">
                            ↺ Reset
                        </button>
                        <button id="btn-run" onclick="startRun()"
                                class="w-2/3 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                            🚀 Start Run
                        </button>
                    </div>
                </div>

            </div>

            <!-- VALIDATION & RESULTS MONITOR TABLE -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50/80 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-900">Validation &amp; Execution Monitor</span>
                        <span id="results-count" class="text-[11px] font-semibold text-slate-500 bg-white border border-slate-200 px-2 py-0.5 rounded-md">
                            {{ !empty($preview) ? count($preview) . ' loaded' : '0 evaluated' }}
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400">Shows real-time status of student records</span>
                </div>
                <div class="max-h-60 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-white text-slate-500 border-b border-slate-100 font-semibold sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Row</th>
                                <th class="px-3 py-2">APAAR ID</th>
                                <th class="px-3 py-2">HPC Login ID</th>
                                <th class="px-3 py-2">Student Name</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Outcome / Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="results-table-body" class="divide-y divide-slate-100 bg-white">
                            @if(!empty($preview))
                                @foreach($preview as $p)
                                    @php
                                        $isDemo = !empty($p['is_demo']);
                                        $isCompleted = !empty($p['is_completed']);
                                        $hasErrors = !empty($p['errors']);
                                    @endphp
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-3 py-2 font-mono text-slate-400">{{ $p['row'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-slate-600">{{ $p['apaar_id'] ?: '—' }}</td>
                                        <td class="px-3 py-2 font-mono font-bold text-slate-800">{{ $p['login_id'] ?? '' }}</td>
                                        <td class="px-3 py-2 font-bold text-slate-900">{{ $p['name'] ?? '' }}</td>
                                        <td class="px-3 py-2">
                                            @if($isDemo)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">📖 Demo Guide</span>
                                            @elseif($isCompleted)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">✓ Completed</span>
                                            @elseif($hasErrors)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Invalid</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">● Ready</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-600">
                                            @if($isDemo)
                                                <span class="text-amber-700 italic">Sample student guide row (skipped during evaluation run)</span>
                                            @elseif($hasErrors)
                                                <span class="text-rose-600 font-semibold">{{ implode(', ', $p['errors']) }}</span>
                                            @else
                                                <span>Grade: {{ $p['grade'] ?? 'Grade 6' }} | Section: {{ $p['section'] ?? 'A' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                                        Upload an Excel file in Step 2 to preview students and start evaluation.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- COMPACT TABBED SETTINGS & ROSTER HUB -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Tab Navigation Bar -->
                <div class="flex items-center justify-between border-b border-slate-200 px-4 bg-slate-50/70">
                    <div class="flex gap-2">
                        <button type="button" id="tab-btn-roster" onclick="switchConfigTab('roster')"
                                class="config-tab-btn py-3 px-3 text-xs font-bold border-b-2 border-indigo-600 text-indigo-600 transition flex items-center gap-1.5">
                            <span>👥 Enrolled Students</span>
                            <span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.2 rounded text-[10px]">{{ count($students) }}</span>
                        </button>
                        <button type="button" id="tab-btn-calendar" onclick="switchConfigTab('calendar')"
                                class="config-tab-btn py-3 px-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition flex items-center gap-1.5">
                            <span>📅 Working Days Calendar</span>
                            <span class="bg-slate-200/80 text-slate-700 px-1.5 py-0.2 rounded text-[10px]">{{ $workingDays->total_days ?? 222 }}d</span>
                        </button>
                        <button type="button" id="tab-btn-marks" onclick="switchConfigTab('marks')"
                                class="config-tab-btn py-3 px-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition flex items-center gap-1.5">
                            <span>🎯 Curricular Max Marks</span>
                            <span class="bg-slate-200/80 text-slate-700 px-1.5 py-0.2 rounded text-[10px]">{{ $maxMarks->l1_max ?? 50 }}m</span>
                        </button>
                    </div>

                    <span class="text-[11px] text-slate-400 hidden sm:inline">School: <strong class="text-slate-700">{{ $activeSchool['name'] }}</strong></span>
                </div>

                <!-- TAB PANEL 1: ENROLLED STUDENTS ROSTER -->
                <div id="tab-panel-roster" class="config-tab-panel p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <p class="text-xs text-slate-500">
                            Synced rosters from PARAKH portal. Student Login IDs and names match exactly.
                        </p>
                        <div class="flex items-center gap-2">
                            <select id="stage-select"
                                    class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                <option value="all" selected>All Stages (Middle &amp; Preparatory)</option>
                                @foreach($stages as $st)
                                    <option value="{{ $st['slug'] }}">
                                        {{ $st['title'] }} ({{ $st['slug'] }})
                                    </option>
                                @endforeach
                            </select>
                            <button id="btn-discover" onclick="discoverStageStudents()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-black text-white rounded-lg font-bold text-xs shadow-sm transition">
                                <span id="discover-spinner" class="hidden">⏳</span>
                                <span>🔄 Sync Portal</span>
                            </button>
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-56 overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 text-slate-500 border-b border-slate-100 font-semibold sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">APAAR ID</th>
                                    <th class="px-3 py-2">HPC Login ID</th>
                                    <th class="px-3 py-2">Student Name</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Stage</th>
                                </tr>
                            </thead>
                            <tbody id="students-table-body" class="divide-y divide-slate-100 bg-white">
                                @forelse($students as $st)
                                    @php
                                        $isCompleted = strcasecmp($st['status'] ?? '', 'HPC Completed') === 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-3 py-1.5 font-mono text-slate-400">{{ $st['row'] ?? '-' }}</td>
                                        <td class="px-3 py-1.5 font-mono text-slate-600">{{ !empty($st['apaar_id']) ? $st['apaar_id'] : '—' }}</td>
                                        <td class="px-3 py-1.5 font-mono font-bold text-slate-800">{{ $st['login_id'] }}</td>
                                        <td class="px-3 py-1.5 font-bold text-slate-900">{{ $st['name'] }}</td>
                                        <td class="px-3 py-1.5">
                                            @if($isCompleted)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    ✓ Completed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    ⏳ Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-slate-500 text-[11px] font-medium">
                                            {{ $st['stage_title'] ?? $st['stage'] ?? 'Middle Stage' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                                            No students loaded. Click "Sync Portal" to fetch students from the HPC portal.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <span id="students-count" class="hidden">{{ count($students) }}</span>
                </div>

                <!-- TAB PANEL 2: ACADEMIC CALENDAR (WORKING DAYS) -->
                @php
                    $calMonths = [
                        'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
                        'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
                        'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
                        'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
                    ];
                @endphp
                <div id="tab-panel-calendar" class="config-tab-panel p-4 hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <p class="text-xs text-slate-500">
                            Working days are auto-filled into all student rows in newly generated templates. Total: <strong id="wd-total-display" class="font-mono text-indigo-700">{{ $workingDays->total_days ?? 222 }}</strong> days.
                        </p>
                        <div class="flex items-center gap-2">
                            <button onclick="resetWorkingDaysToStandard()"
                                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold text-xs transition">
                                ↺ Standard (222)
                            </button>
                            <button onclick="saveWorkingDays()" id="btn-save-wd"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                                <span>💾 Set as Default</span>
                            </button>
                        </div>
                    </div>

                    <!-- 12 Monthly Inputs in Compact Grid -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-12 gap-2">
                        @foreach($calMonths as $mKey => $mLabel)
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-2 text-center">
                                <span class="block text-[10px] font-bold text-slate-500 uppercase">{{ $mLabel }}</span>
                                <input type="number" id="wd-{{ $mKey }}" min="0" max="31"
                                       value="{{ $workingDays->{$mKey} ?? 20 }}"
                                       oninput="recalcWorkingDaysTotal()"
                                       class="w-full mt-1 text-center font-bold text-xs bg-white border border-slate-300 rounded px-1 py-1 text-slate-900 focus:outline-none focus:border-indigo-500 font-mono">
                            </div>
                        @endforeach
                    </div>
                    <div id="wd-status-msg" class="mt-2 text-xs font-semibold hidden"></div>
                </div>

                <!-- TAB PANEL 3: CURRICULAR MAX MARKS -->
                @php
                    $curricularSubjects = [
                        'l1_max'  => ['name' => 'Lang 1', 'full' => 'Language 1'],
                        'l2_max'  => ['name' => 'Lang 2', 'full' => 'Language 2'],
                        'l3_max'  => ['name' => 'Lang 3', 'full' => 'Language 3'],
                        'm_max'   => ['name' => 'Maths',  'full' => 'Mathematics'],
                        'sci_max' => ['name' => 'Science','full' => 'Science'],
                        'soc_max' => ['name' => 'Soc Sci','full' => 'Social Science'],
                        'art_max' => ['name' => 'Art Ed', 'full' => 'Art Education'],
                        'pe_max'  => ['name' => 'PE',     'full' => 'PE & Well Being'],
                    ];
                @endphp
                <div id="tab-panel-marks" class="config-tab-panel p-4 hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <p class="text-xs text-slate-500">
                            Subject maximum marks pre-filled in Excel. Standard PARAKH Middle Stage default is 50.
                        </p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">Presets:</span>
                            <button type="button" onclick="presetMaxMarks(50)"
                                    class="px-2 py-1 bg-white hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 rounded text-xs font-bold transition">
                                All 50
                            </button>
                            <button type="button" onclick="presetMaxMarks(100)"
                                    class="px-2 py-1 bg-white hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 rounded text-xs font-bold transition">
                                All 100
                            </button>
                            <button type="button" onclick="presetMaxMarks(40)"
                                    class="px-2 py-1 bg-white hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 rounded text-xs font-bold transition">
                                All 40
                            </button>
                            <button onclick="saveMaxMarks()" id="btn-save-mm"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                                <span>💾 Set as Default</span>
                            </button>
                        </div>
                    </div>

                    <!-- 8 Subjects Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                        @foreach($curricularSubjects as $sKey => $sInfo)
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-2 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-bold text-slate-700">{{ $sInfo['name'] }}</span>
                                    <span class="text-[9px] text-slate-400 truncate">{{ $sInfo['full'] }}</span>
                                </div>
                                <input type="number" id="mm-{{ $sKey }}" min="1" max="100"
                                       value="{{ $maxMarks->{$sKey} ?? 50 }}"
                                       class="w-12 text-center font-bold text-xs bg-white border border-slate-300 rounded px-1 py-1 text-slate-900 focus:outline-none focus:border-emerald-500 font-mono">
                            </div>
                        @endforeach
                    </div>
                    <div id="mm-status-msg" class="mt-2 text-xs font-semibold hidden"></div>
                </div>

            </div>

        </div>
    </div>

    <!-- JavaScript Controllers -->
    <script>
        const csrfToken = '{{ csrf_token() }}';

        // ── Config Tabs Switcher ──────────────────────────────────────────────
        function switchConfigTab(tabId) {
            document.querySelectorAll('.config-tab-btn').forEach(b => {
                b.classList.remove('border-indigo-600', 'text-indigo-600');
                b.classList.add('border-transparent', 'text-slate-500');
            });
            document.querySelectorAll('.config-tab-panel').forEach(p => p.classList.add('hidden'));

            const btn = document.getElementById('tab-btn-' + tabId);
            const panel = document.getElementById('tab-panel-' + tabId);
            if (btn) {
                btn.classList.remove('border-transparent', 'text-slate-500');
                btn.classList.add('border-indigo-600', 'text-indigo-600');
            }
            if (panel) panel.classList.remove('hidden');
        }

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
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">📖 Demo Guide</span>';
                    remarks = '<span class="text-amber-700 italic">Sample student guide row (skipped during evaluation run)</span>';
                } else if (p.is_completed) {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">✓ Completed</span>';
                    remarks = '<span class="text-slate-400 italic">Will be left untouched during bulk run</span>';
                } else if (p.errors && p.errors.length) {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Invalid</span>';
                    remarks = '<span class="text-rose-600 font-semibold">' + p.errors.join(', ') + '</span>';
                } else {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">● Ready</span>';
                    remarks = '<span class="text-slate-600">Grade: ' + (p.grade || 'Grade 6') + ' | Section: ' + (p.section || 'A') + '</span>';
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
            const rCount = document.getElementById('results-count');
            if (rCount) rCount.innerText = `${preview.length} loaded`;
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
            const rCount = document.getElementById('results-count');
            if (rCount) rCount.innerText = `${results.length} processed`;
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
            el.className = 'mt-2 text-xs font-semibold ' + classes;
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
            el.className = 'mt-2 text-xs font-semibold ' + classes;
            el.innerText = msg;
            el.classList.remove('hidden');
        }
    </script>
</x-app-layout>
