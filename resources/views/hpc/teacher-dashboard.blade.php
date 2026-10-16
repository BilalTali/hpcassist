<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                @if(Auth::user()?->isTeacher())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        👩‍🏫 Class Teacher Portal
                    </span>
                    <span class="text-slate-300 text-xs">/</span>
                    <span class="text-xs font-semibold text-slate-700">
                        {{ $activeSchool['name'] ?? 'My School' }}
                    </span>
                @else
                    <a href="{{ route('dashboard') }}?step=3" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        ← Back to School Admin (Step 3)
                    </a>
                    <span class="text-slate-300 text-xs">/</span>
                @endif
                <h2 class="font-extrabold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    Teacher HPC Assessment Suite
                </h2>
            </div>

            <!-- Profile & Active Session Controls -->
            <div class="flex items-center gap-3 flex-wrap">
                @if(Auth::user()?->isTeacher())
                    <!-- Dedicated Teacher Badge -->
                    <div class="flex items-center gap-2 bg-white border border-indigo-200 rounded-xl px-3 py-1.5 shadow-sm">
                        <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                            <span>👩‍🏫</span> {{ $activeTeacher['name'] ?? Auth::user()->name }}
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ $activeTeacher['classes'] ?? 'Assigned Class' }}
                        </span>
                    </div>
                @else
                    <!-- Teacher Profile Switcher (For School Admin preview) -->
                    <div class="flex items-center gap-2 bg-white border border-slate-300/80 rounded-xl px-3 py-1.5 shadow-sm">
                        <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                            👩‍🏫 Teacher:
                        </span>
                        <select id="teacher-select" onchange="handleTeacherChange(this.value)"
                                class="text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
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
                @endif

                <!-- Session Status Badge -->
                @if($hasSession)
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm"
                          title="Logged in session active for {{ $activeTeacher['name'] }}">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $activeTeacher['name'] }} Ready
                    </span>
                    <button onclick="endTeacherSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition shadow-sm"
                            title="Log out teacher session">
                        🚪 Log Out
                    </button>
                @else
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        No Active Session
                    </span>
                    <button onclick="startTeacherSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm"
                            title="Open Chrome to log in as Teacher">
                        🚀 Log In as Teacher
                    </button>
                @endif

                <a href="{{ config('hpc.hpc_home_url') }}" target="_blank"
                   class="text-xs text-slate-600 hover:text-indigo-600 bg-white border border-slate-200 px-3 py-1.5 rounded-xl font-medium transition shadow-sm flex items-center gap-1">
                    🔗 Portal ↗
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-slate-100/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- EXECUTIVE SUMMARY HERO CARD -->
            <div class="rounded-2xl p-4 shadow-md border border-slate-800"
                 style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%) !important; color: #ffffff !important;">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] font-bold tracking-wider uppercase text-indigo-300 bg-indigo-900/60 border border-indigo-500/40 px-2 py-0.5 rounded-full">
                                PARAKH Institutional Evaluation
                            </span>
                            <span class="text-slate-400">•</span>
                            <span class="text-xs text-slate-300 font-medium">School: <strong class="text-white">{{ $activeSchool['name'] }}</strong></span>
                        </div>
                        <h1 class="text-lg font-black text-white tracking-tight">
                            Holistic Progress Card (HPC) Evaluation Command Center
                        </h1>
                        <p class="text-[11px] text-slate-300 mt-0.5 max-w-3xl leading-relaxed">
                            Configure school working days and subject marks defaults, download condensed 33-column templates with real-time cell highlighting, and automate submission across all PARAKH stages.
                        </p>
                    </div>

                    <!-- Quick Metrics Chips -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl px-2.5 py-1.5 text-center min-w-[75px]">
                            <div class="text-[9px] font-bold text-slate-300 uppercase tracking-wider">Enrolled</div>
                            <div class="text-xs font-black text-white mt-0.5"><span id="metric-students-count">{{ count($students) }}</span> Students</div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl px-2.5 py-1.5 text-center min-w-[75px]">
                            <div class="text-[9px] font-bold text-slate-300 uppercase tracking-wider">Calendar</div>
                            <div class="text-xs font-black text-white mt-0.5">{{ $workingDays->total_days ?? 222 }} Days</div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl px-2.5 py-1.5 text-center min-w-[75px]">
                            <div class="text-[9px] font-bold text-slate-300 uppercase tracking-wider">Max Marks</div>
                            <div class="text-xs font-black text-white mt-0.5">{{ $maxMarks->l1_max ?? 50 }} Marks</div>
                        </div>
                        <div class="bg-indigo-600/40 border border-indigo-400/30 rounded-xl px-2.5 py-1.5 text-center min-w-[75px]">
                            <div class="text-[9px] font-bold text-indigo-200 uppercase tracking-wider">Template</div>
                            <div class="text-xs font-black text-indigo-300 mt-0.5">33 Visible Cols</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 1: SEEDER CONFIGURATIONS (COMPACT SMALL DIVS) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 12px;">

                <!-- CARD A: SCHOOL WORKING DAYS CONFIGURATION (COMPACT SMALL DIV) -->
                @php
                    $calMonths = [
                        'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
                        'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
                        'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
                        'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
                    ];
                @endphp
                <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3 hover:border-slate-300 transition">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
                                <span>📅</span> Working Days Calendar
                            </span>
                            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.2 rounded">
                                AY {{ $workingDays->academic_year ?? '2025-2026' }}
                            </span>
                            <span class="text-[10px] text-slate-500 font-medium">
                                Total: <strong id="wd-total-display" class="font-mono text-indigo-700 font-extrabold">{{ $workingDays->total_days ?? 222 }}</strong>d
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button onclick="resetWorkingDaysToStandard()"
                                    class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-[10px] font-semibold transition"
                                    title="Reset to standard 222 working days">
                                ↺ 222d
                            </button>
                            <button onclick="saveWorkingDays()" id="btn-save-wd"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-[10px] font-bold shadow-xs transition">
                                <span>💾 Save Default</span>
                            </button>
                        </div>
                    </div>

                    <!-- 12 Monthly Compact Inline Grid -->
                    <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 4px; padding-top: 6px;">
                        @foreach($calMonths as $mKey => $mLabel)
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 2px; text-align: center;">
                                <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: #64748b; line-height: 1;">{{ $mLabel }}</div>
                                <input type="number" id="wd-{{ $mKey }}" min="0" max="31"
                                       value="{{ $workingDays->{$mKey} ?? 20 }}"
                                       oninput="recalcWorkingDaysTotal()"
                                       style="width: 100%; text-align: center; font-weight: 700; font-size: 11px; font-family: monospace; padding: 2px 0; border: 1px solid #cbd5e1; border-radius: 4px; margin-top: 2px; color: #0f172a; background: #ffffff;">
                            </div>
                        @endforeach
                    </div>

                    <div id="wd-status-msg" class="mt-1.5 text-[10px] font-semibold hidden"></div>
                </div>

                <!-- CARD B: CURRICULAR MAX MARKS CONFIGURATION (COMPACT SMALL DIV) -->
                @php
                    $curricularSubjects = [
                        'l1_max'  => ['name' => 'Lang 1', 'full' => 'Language 1 (R1)'],
                        'l2_max'  => ['name' => 'Lang 2', 'full' => 'Language 2 (R2)'],
                        'l3_max'  => ['name' => 'Lang 3', 'full' => 'Language 3 (R3)'],
                        'm_max'   => ['name' => 'Maths',  'full' => 'Mathematics'],
                        'sci_max' => ['name' => 'Sci',    'full' => 'Science'],
                        'soc_max' => ['name' => 'Soc Sci','full' => 'Social Science'],
                        'art_max' => ['name' => 'Art Ed', 'full' => 'Art Education'],
                        'pe_max'  => ['name' => 'PE',     'full' => 'PE & Well Being'],
                    ];
                @endphp
                <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3 hover:border-slate-300 transition">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
                                <span>🎯</span> Curricular Max Marks
                            </span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded">
                                HPC Credit
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="flex items-center gap-0.5 bg-slate-50 border border-slate-200 rounded p-0.5 text-[9px] font-semibold text-slate-600">
                                <button type="button" onclick="presetMaxMarks(50)" class="px-1.5 py-0.5 hover:bg-white rounded transition" title="Set all to 50">50</button>
                                <button type="button" onclick="presetMaxMarks(100)" class="px-1.5 py-0.5 hover:bg-white rounded transition" title="Set all to 100">100</button>
                                <button type="button" onclick="presetMaxMarks(40)" class="px-1.5 py-0.5 hover:bg-white rounded transition" title="Set all to 40">40</button>
                            </div>
                            <button onclick="saveMaxMarks()" id="btn-save-mm"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-xs transition">
                                <span>💾 Save Default</span>
                            </button>
                        </div>
                    </div>

                    <!-- 8 Subjects Compact Inline Grid -->
                    <div style="display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 4px; padding-top: 6px;">
                        @foreach($curricularSubjects as $sKey => $sInfo)
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 2px; text-align: center;" title="{{ $sInfo['full'] }}">
                                <div style="font-size: 9px; font-weight: 700; color: #334155; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $sInfo['name'] }}</div>
                                <input type="number" id="mm-{{ $sKey }}" min="1" max="100"
                                       value="{{ $maxMarks->{$sKey} ?? 50 }}"
                                       style="width: 100%; text-align: center; font-weight: 700; font-size: 11px; font-family: monospace; padding: 2px 0; border: 1px solid #cbd5e1; border-radius: 4px; margin-top: 2px; color: #0f172a; background: #ffffff;">
                            </div>
                        @endforeach
                    </div>

                    <div id="mm-status-msg" class="mt-1.5 text-[10px] font-semibold hidden"></div>
                </div>

            </div>

            <!-- SECTION 2: 3-STEP EVALUATION WORKFLOW -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">

                <!-- STEP 1: Download Template -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">1</span>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                33 Visible Columns
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Download Assessment Template</h3>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            Compact sheet without horizontal dragging. Fill obtained marks directly on screen.
                        </p>

                        <!-- Feature highlights -->
                        <div class="space-y-1 mb-4 text-[11px]">
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span><strong>Identity:</strong> Only Student Name visible (frozen on left)</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <span class="text-rose-600 font-bold">🔴</span>
                                <span><strong>Red Empty Alert:</strong> Empty cells turn red until filled</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <span class="text-amber-600 font-bold">✏️</span>
                                <span><strong>Marks Ready:</strong> 8 subject columns highlighted yellow</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <span class="text-indigo-600 font-bold">📁</span>
                                <span><strong>Expand [ + ]:</strong> 114 detailed columns collapsed in groups</span>
                            </div>
                        </div>
                    </div>

                    <a id="btn-download-template" href="{{ route('hpc.teacher.template.download') }}{{ !empty($selectedGrade) ? '?grade=' . urlencode($selectedGrade) : (!empty($selectedStage) && $selectedStage !== 'all' ? '?stage=' . urlencode($selectedStage) : '') }}"
                       @if(empty($students)) onclick="if(!confirm('⚠️ No students have been fetched from PARAKH portal yet.\n\nTo ensure your evaluations match PARAKH portal, please login and click \'📥 Fetch Students from This Page\' in the extension widget first.\n\nDo you still want to proceed?')) return false;" @endif
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                        📥 Download Template (.xlsx)
                    </a>
                </div>

                <!-- STEP 2: Upload Excel -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center">2</span>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                Instant Validation
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Upload Completed Assessment</h3>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            Upload your completed spreadsheet. Rows will be validated instantly before submission.
                        </p>

                        <div id="drop-zone" onclick="document.getElementById('file-input').click()"
                             class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-xl p-3 text-center cursor-pointer transition bg-slate-50/60 hover:bg-emerald-50/30">
                            <input type="file" id="file-input" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleFileUpload(this.files[0])">
                            <div class="text-2xl mb-1">📤</div>
                            <div class="text-xs font-bold text-slate-700">Click to upload or drag &amp; drop</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">XLSX, XLS (up to 10MB)</div>
                        </div>
                    </div>

                    <div id="upload-status" class="mt-2 text-xs font-medium text-slate-500 text-center hidden"></div>
                </div>

                <!-- STEP 3: Execute Assessment -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-6 h-6 rounded-full bg-purple-600 text-white font-bold text-xs flex items-center justify-center">3</span>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-700 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded-full">
                                Automated Submission
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Execute Bulk Evaluation</h3>
                        <p class="text-xs text-slate-500 mb-2 leading-relaxed">
                            Automates form submission on PARAKH portal across all pages with real-time feedback.
                        </p>

                        <!-- Progress Bar Container -->
                        <div id="progress-container" class="space-y-1 mb-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl hidden">
                            <div class="flex justify-between text-xs font-bold text-slate-700">
                                <span id="progress-text">Processing...</span>
                                <span id="progress-percent">0%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                <div id="progress-bar" class="bg-gradient-to-r from-emerald-500 to-indigo-600 h-1.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button id="btn-reset" onclick="resetRun()"
                                class="w-1/3 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition text-center">
                            ↺ Reset
                        </button>
                        <button id="btn-run" onclick="startRun()"
                                class="w-2/3 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                            🚀 Start Evaluation
                        </button>
                    </div>
                </div>

            </div>

            <!-- SECTION 3: DUAL MONITOR (STUDENT ROSTER & LIVE EXECUTION MONITOR) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 16px;">

                <!-- PANEL 1: ENROLLED STUDENTS ROSTER TABLE -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-900">Enrolled Students Roster</span>
                            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                <span id="students-count">{{ count($students) }}</span> Students
                            </span>
                        </div>

                        <!-- Stage Sync Controls -->
                        <div class="flex items-center gap-2">
                            <select id="stage-select" onchange="discoverStageStudents()"
                                    class="text-xs font-bold bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                @if(!empty($teacherClass))
                                    <option value="class:{{ $teacherClass }}" {{ ($selectedGrade === $teacherClass) ? 'selected' : '' }}>
                                        👩‍🏫 My Class ({{ $teacherClass }})
                                    </option>
                                @endif
                                <option value="all" {{ (empty($selectedGrade) && $selectedStage === 'all') ? 'selected' : '' }}>
                                    🏫 All Students (Whole School)
                                </option>
                                @foreach($stages as $st)
                                    <option value="{{ $st['slug'] }}" {{ ($selectedStage === $st['slug'] && empty($selectedGrade)) ? 'selected' : '' }}>
                                        {{ $st['title'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button id="btn-discover" onclick="discoverStageStudents()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-900 hover:bg-black text-white rounded-lg font-bold text-xs shadow-sm transition">
                                <span id="discover-spinner" class="hidden">⏳</span>
                                <span>📥 Fetch Data</span>
                            </button>
                        </div>
                    </div>

                    <!-- 5 Rows Visible Height & Scrollable -->
                    <div style="max-height: 220px; overflow-y: auto;" class="border-t border-slate-100">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50/80 text-slate-500 border-b border-slate-200 font-semibold sticky top-0 backdrop-blur-sm">
                                <tr>
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">HPC Login ID *</th>
                                    <th class="px-3 py-2">Student Name</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Stage</th>
                                </tr>
                            </thead>
                            <tbody id="students-table-body" class="divide-y divide-slate-100 bg-white">
                                @if(!empty($students))
                                    @foreach($students as $idx => $st)
                                        @php
                                            $stStatus = $st['status'] ?? 'pending';
                                            $isCompleted = str_contains(strtolower($stStatus), 'complete') || str_contains(strtolower($stStatus), 'success');
                                            $loginId = !empty($st['login_id']) ? $st['login_id'] : (!empty($st['apaar_id']) ? $st['apaar_id'] : '—');
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="px-3 py-2 font-mono text-slate-400">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-2 font-mono font-bold text-slate-800">{{ $loginId }}</td>
                                            <td class="px-3 py-2 font-bold text-slate-900">{{ $st['name'] ?? '' }}</td>
                                            <td class="px-3 py-2">
                                                @if($isCompleted)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Successful</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">⏳ Ready for Assessment</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-slate-600 text-[11px] font-medium">{{ $st['grade'] ?? ($st['stage'] ?? 'Middle Stage') }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="p-4">
                                            <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-4 text-slate-700">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <span class="text-base">🔒</span>
                                                    <h4 class="text-xs font-bold text-indigo-900">Step 1: Fetch Live Student Roster from PARAKH Portal</h4>
                                                </div>
                                                <p class="text-[11px] text-slate-600 mb-3 leading-relaxed">
                                                    To prevent mismatched evaluations, your Excel template is populated <strong>strictly from your logged-in PARAKH portal stage roster</strong> (not from the local database).
                                                </p>
                                                <div class="space-y-1.5 text-[11px] mb-3">
                                                    <div class="flex items-start gap-1.5">
                                                        <span class="font-bold text-indigo-600">1.</span>
                                                        <span>Click <strong class="text-slate-900">"🚀 Log In as Teacher"</strong> (top right) or open PARAKH portal in Chrome.</span>
                                                    </div>
                                                    <div class="flex items-start gap-1.5">
                                                        <span class="font-bold text-indigo-600">2.</span>
                                                        <span>Navigate to your stage page (e.g. <a href="https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage" target="_blank" class="text-indigo-600 underline font-semibold">Middle Stage</a>).</span>
                                                    </div>
                                                    <div class="flex items-start gap-1.5">
                                                        <span class="font-bold text-indigo-600">3.</span>
                                                        <span>Click <strong class="text-orange-600">"📥 Fetch Students from This Page"</strong> in the floating HPC Assist widget.</span>
                                                    </div>
                                                    <div class="flex items-start gap-1.5">
                                                        <span class="font-bold text-indigo-600">4.</span>
                                                        <span>Refresh this page — your exact portal students will appear here, and Step 1 Excel template will be unlocked!</span>
                                                    </div>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-indigo-100">
                                                    <button onclick="startTeacherSession()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                                                        🚀 Open PARAKH Login
                                                    </button>
                                                    <button onclick="copyTeacherSyncScript()" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-indigo-700 border border-indigo-300 rounded-lg font-semibold text-xs transition" title="Copy 1-line sync script to paste into Chrome DevTools console if extension is not installed">
                                                        📋 Copy Console Sync Script
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PANEL 2: VALIDATION & LIVE RESULTS MONITOR -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-900">Assessment Validation &amp; Results Monitor</span>
                            <span id="results-count" class="text-[10px] font-bold text-slate-600 bg-white border border-slate-200 px-2 py-0.5 rounded-full">
                                {{ !empty($preview) ? count($preview) . ' loaded' : '0 evaluated' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="resetTeacherEvaluations()" class="text-[10px] text-slate-400 hover:text-rose-600 transition underline font-semibold">
                                🔄 Reset
                            </button>
                            <span class="text-[10px] text-slate-400 font-medium">Auto-refreshes during run</span>
                        </div>
                    </div>

                    <!-- 5 Rows Visible Height & Scrollable -->
                    <div style="max-height: 220px; overflow-y: auto;" class="border-t border-slate-100">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50/80 text-slate-500 border-b border-slate-200 font-semibold sticky top-0 backdrop-blur-sm">
                                <tr>
                                    <th class="px-3 py-2">Row</th>
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
                                            $stText = trim($p['status'] ?? '');
                                            $stLower = strtolower($stText);
                                            $isCompleted = !empty($p['is_completed']) || str_contains($stLower, 'completed') || str_contains($stLower, 'success');
                                            $isUnderProcess = str_contains($stLower, 'process') || str_contains($stLower, 'progress');
                                            $hasErrors = !empty($p['errors']);
                                        @endphp
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-3 py-2 font-mono text-slate-400">{{ $p['row'] ?? '-' }}</td>
                                            <td class="px-3 py-2 font-mono font-bold text-slate-800">{{ $p['login_id'] ?? '' }}</td>
                                            <td class="px-3 py-2 font-bold text-slate-900">{{ $p['name'] ?? '' }}</td>
                                            <td class="px-3 py-2">
                                                @if($isDemo)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">📖 Demo Guide</span>
                                                @elseif($hasErrors)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Invalid</span>
                                                @elseif($isCompleted)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Success</span>
                                                @elseif($isUnderProcess)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">⏳ Under Process</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">⏳ Pending</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs text-slate-600">
                                                @if($isDemo)
                                                    <span class="text-amber-700 italic">Sample guide (skipped in run)</span>
                                                @elseif($hasErrors)
                                                    <span class="text-rose-600 font-semibold">{{ implode(', ', $p['errors']) }}</span>
                                                @elseif($isCompleted)
                                                    <span class="text-emerald-700 font-semibold">Completed on PARAKH portal</span>
                                                @elseif($isUnderProcess)
                                                    <span class="text-blue-700 font-semibold">In Progress on portal — Ready for submission</span>
                                                @else
                                                    <span>Grade: {{ $p['grade'] ?? 'Grade 6' }} | Section: {{ $p['section'] ?? 'A' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                            <div class="flex flex-col items-center justify-center gap-1">
                                                <span class="text-xl">📊</span>
                                                <span class="text-xs font-bold text-slate-600">No assessment data loaded</span>
                                                <span class="text-[11px] text-slate-400">Upload an Excel file in Step 2 to preview students and start evaluation.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
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
            if (d.ok && d.url) {
                window.open(d.url, '_blank');
            }
            setTimeout(() => window.location.reload(), 1500);
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

        function copyTeacherSyncScript() {
            const serverUrl = window.location.origin;
            const schoolId = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_id) ? window.__HPC_CONFIG.school_id : {{ json_encode($schoolId) }};
            const script = `(async () => {
    const rows = Array.from(document.querySelectorAll('table tbody tr'));
    const stage = location.pathname.split('/').filter(Boolean).pop() || 'middle-stage';
    const students = [];
    rows.forEach((tr, idx) => {
        const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
        if (cells.length >= 3) {
            students.push({
                row: idx + 1,
                login_id: cells[1] || ('ST_' + (idx + 1)),
                name: cells[2] || ('Student ' + (idx + 1)),
                status: cells[3] || 'HPC In Progress',
                stage: stage,
                grade: stage.includes('middle') ? 'Grade 6' : (stage.includes('prep') ? 'Grade 4' : 'Grade 1')
            });
        }
    });
    if (!students.length) { alert('No students found in the stage table on this page!'); return; }
    const res = await fetch('${serverUrl}/hpc/teacher/sync-roster', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ school_id: ${schoolId}, stage: stage, students: students })
    });
    const d = await res.json();
    if (d.ok) alert('✅ Successfully synced ' + (d.count || students.length) + ' student(s) to HPC Assist! Refresh your teacher dashboard.');
    else alert('❌ Sync failed: ' + (d.error || 'Server error'));
})();`;
            navigator.clipboard.writeText(script).then(() => {
                alert('✅ Console sync script copied to clipboard!\n\nOpen your PARAKH portal stage page (e.g. Middle Stage), press F12 (or Cmd+Option+I), paste into Console, and press Enter to instantly sync your stage roster.');
            }).catch(() => {
                prompt('Copy this sync script:', script);
            });
        }

        // ── Stage Student Discovery ───────────────────────────────────────────
        async function discoverStageStudents() {
            const stageVal = document.getElementById('stage-select').value;
            let stage = stageVal;
            let grade = '';
            if (stageVal.startsWith('class:')) {
                grade = stageVal.replace('class:', '');
                stage = 'all';
            }

            const btn = document.getElementById('btn-discover');
            const spinner = document.getElementById('discover-spinner');
            const tbody = document.getElementById('students-table-body');
            const countBadge = document.getElementById('students-count');
            const dlBtn = document.getElementById('btn-download-template');

            // Dynamically update Step 1 template download URL
            if (dlBtn) {
                const params = new URLSearchParams();
                if (grade) params.set('grade', grade);
                if (stage && stage !== 'all') params.set('stage', stage);
                dlBtn.href = '{{ route('hpc.teacher.template.download') }}' + (params.toString() ? '?' + params.toString() : '');
            }

            if (btn) btn.disabled = true;
            if (spinner) spinner.classList.remove('hidden');

            try {
                const res = await fetch('{{ route('hpc.teacher.discover') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ stage, grade })
                });
                const d = await res.json();
                if (d.ok && d.students) {
                    tbody.innerHTML = '';
                    if (d.students.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="p-4">
                            <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-4 text-center text-slate-700">
                                <span class="text-xl">📋</span>
                                <div class="text-xs font-bold text-indigo-900 mt-1">No portal students synced yet for this stage.</div>
                                <div class="text-[11px] text-slate-500 mt-1 mb-3">Open PARAKH portal stage page and click <strong>"📥 Fetch Students from This Page"</strong> in the HPC Assist extension widget.</div>
                                <button onclick="startTeacherSession()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-sm transition">
                                    🚀 Open PARAKH Login
                                </button>
                            </div>
                        </td></tr>`;
                        countBadge.innerText = '0';
                    } else {
                        d.students.forEach((st, idx) => {
                            const isCompleted = (st.status || '').toLowerCase().includes('completed');
                            const statusBadge = isCompleted 
                                ? `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Successful</span>`
                                : `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">⏳ Ready for Assessment</span>`;
                            
                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-slate-50/80';
                            tr.innerHTML = `
                                <td class="px-3 py-2 font-mono text-slate-400">${st.row || (idx + 1)}</td>
                                <td class="px-3 py-2 font-mono font-bold text-slate-800">${st.login_id || ''}</td>
                                <td class="px-3 py-2 font-bold text-slate-900">${st.name || ''}</td>
                                <td class="px-3 py-2">${statusBadge}</td>
                                <td class="px-3 py-2 text-slate-600 text-[11px] font-medium">${st.grade || st.stage || 'Middle Stage'}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                        countBadge.innerText = d.students.length;
                        const metricBadge = document.getElementById('metric-students-count');
                        if (metricBadge) metricBadge.innerText = d.students.length;
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="p-4">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center text-slate-700">
                            <div class="text-xs font-bold text-amber-900">⚠️ ${d.error || 'No students loaded'}</div>
                            <div class="text-[11px] text-slate-600 mt-1 mb-2">Please login to PARAKH portal and click "📥 Fetch Students" in the extension widget.</div>
                            <button onclick="copyTeacherSyncScript()" class="px-3 py-1 bg-white border border-amber-300 text-amber-800 rounded-lg text-xs font-semibold">📋 Copy Console Sync Script</button>
                        </div>
                    </td></tr>`;
                }
            } catch (e) {
                alert('Discovery error: ' + e.message);
            } finally {
                if (btn) btn.disabled = false;
                if (spinner) spinner.classList.add('hidden');
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
                const stLower = (p.status || '').toLowerCase();
                const isCompleted = p.is_completed || stLower.includes('completed') || stLower.includes('success');
                const isUnderProcess = stLower.includes('process') || stLower.includes('progress');

                if (p.is_demo) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">📖 Demo Guide</span>';
                    remarks = '<span class="text-amber-700 italic">Sample guide (skipped in run)</span>';
                } else if (p.errors && p.errors.length) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Invalid</span>';
                    remarks = '<span class="text-rose-600 font-semibold">' + p.errors.join(', ') + '</span>';
                } else if (isCompleted) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Success</span>';
                    remarks = '<span class="text-emerald-700 font-semibold">Completed on PARAKH portal</span>';
                } else if (isUnderProcess) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">⏳ Under Process</span>';
                    remarks = '<span class="text-blue-700 font-semibold">In Progress on portal — Ready for submission</span>';
                } else {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">⏳ Pending</span>';
                    remarks = '<span class="text-slate-600">Grade: ' + (p.grade || 'Grade 6') + ' | Section: ' + (p.section || 'A') + '</span>';
                }

                tr.className = 'hover:bg-slate-50/80';
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-slate-400">${p.row}</td>
                    <td class="px-3 py-2 font-mono font-bold text-slate-800">${p.login_id}</td>
                    <td class="px-3 py-2 font-bold text-slate-900">${p.name}</td>
                    <td class="px-3 py-2">${badge}</td>
                    <td class="px-3 py-2 text-xs text-slate-600">${remarks}</td>
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
                const st = (r.status || '').toLowerCase();

                const msg = (r.message || '').toLowerCase();
                if (st === 'success' || st === 'completed' || msg.includes('completed') || msg.includes('success')) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Success</span>';
                } else if (st === 'skipped') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">⏭ Left Untouched</span>';
                } else if (st === 'processing' || st === 'in_progress' || st.includes('process') || st.includes('progress') || msg.includes('evaluating')) {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">⏳ Under Process</span>';
                } else if (st === 'pending') {
                    badge = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">⏳ Pending</span>';
                } else {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">✕ Error</span>';
                }

                tr.className = 'hover:bg-slate-50/80';
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-slate-400">${r.row}</td>
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

        async function resetTeacherEvaluations() {
            if (!confirm('Are you sure you want to clear evaluation results and preview?')) return;
            try {
                const res = await fetch('{{ route('hpc.teacher.run.reset') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                const d = await res.json();
                if (d.ok) {
                    window.location.reload();
                }
            } catch(e) {
                alert('Reset error: ' + e.message);
            }
        }
    </script>

    {{-- HPC Extension Bridge Configuration for Teacher Role --}}
    <script>
        window.__HPC_CONFIG = {
            server_url: "{{ rtrim(config('app.url'), '/') }}",
            school_id: {{ json_encode($schoolId) }},
            school_name: "{{ addslashes($activeSchool['name'] ?? ($school?->name ?? '')) }}",
            teacher_id: "{{ $activeTeacher['id'] ?? '' }}",
            teacher_name: "{{ addslashes($activeTeacher['name'] ?? '') }}",
            role: "teacher",
            progress_token: "{{ config('hpc.progress_token') }}"
        };
    </script>
</x-app-layout>
