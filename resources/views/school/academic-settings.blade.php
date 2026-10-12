<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 transition shadow-2xs">
                    ←
                </a>
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 leading-tight">
                        Academic Settings: Working Days &amp; Max Marks
                    </h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Configure stage-wise working days calendar and curricular max marks for {{ $school?->name ?? 'School' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-2xs transition active:scale-95">
                    🏠 Return to Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $months = [
            'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
            'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
            'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
            'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
        ];

        $middleSubjects = [
            'l1_max'  => ['name' => 'Lang 1 (R1)', 'full' => 'Language 1 (First Regional / Mother Tongue)'],
            'l2_max'  => ['name' => 'Lang 2 (R2)', 'full' => 'Language 2 (Second Language)'],
            'l3_max'  => ['name' => 'Lang 3 (R3)', 'full' => 'Language 3 (Third Language)'],
            'm_max'   => ['name' => 'Mathematics', 'full' => 'Mathematics'],
            'sci_max' => ['name' => 'Science',     'full' => 'Science'],
            'soc_max' => ['name' => 'Social Sci',  'full' => 'Social Science'],
            'art_max' => ['name' => 'Art Ed',      'full' => 'Art Education'],
            'pe_max'  => ['name' => 'PE & Health', 'full' => 'Physical Education & Well Being'],
        ];

        $prepSubjects = [
            'l1_max'  => ['name' => 'Lang 1 (R1)', 'full' => 'Language 1 (Primary Instruction)'],
            'l2_max'  => ['name' => 'Lang 2 (R2)', 'full' => 'Language 2 (Secondary Language)'],
            'l3_max'  => ['name' => 'Lang 3 / Reg','full' => 'Language 3 / Regional Subject'],
            'm_max'   => ['name' => 'Mathematics', 'full' => 'Foundational Mathematics'],
            'sci_max' => ['name' => 'The World (EVS)', 'full' => 'The World Around Us (EVS)'],
            'soc_max' => ['name' => 'Social & Env','full' => 'Social & Environmental Studies'],
            'art_max' => ['name' => 'Art Ed',      'full' => 'Art & Creative Expression'],
            'pe_max'  => ['name' => 'PE & Health', 'full' => 'Physical & Health Education'],
        ];
    @endphp

    <div class="py-8 bg-slate-50 min-h-screen" x-data="{ activeStage: 'middle' }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- ── STAGE SELECTION SWITCHER ── -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-2 sm:p-2.5 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <button @click="activeStage = 'middle'"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition active:scale-95"
                            :class="activeStage === 'middle' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                        <span>🎓 Middle Stage</span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                              :class="activeStage === 'middle' ? 'bg-indigo-500 text-white' : 'bg-slate-200 text-slate-700'">
                            Grades 6 – 8
                        </span>
                    </button>

                    <button @click="activeStage = 'preparatory'"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition active:scale-95"
                            :class="activeStage === 'preparatory' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                        <span>🌱 Preparatory Stage</span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                              :class="activeStage === 'preparatory' ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700'">
                            Grades 3 – 5
                        </span>
                    </button>
                </div>

                <div class="text-xs text-slate-400 font-medium px-2 hidden sm:block">
                    Select a stage to customize its independent working calendar &amp; subject marks
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. MIDDLE STAGE CONFIGURATION PANEL                                      -->
            <!-- ========================================================================= -->
            <div x-show="activeStage === 'middle'" x-transition class="space-y-6">

                <!-- 1A. Middle Stage Working Days -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 sm:p-7 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg">📅</span>
                                <h3 class="text-base font-black text-slate-900">Middle Stage — School Working Days Calendar</h3>
                                <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                    AY {{ $middleWd->academic_year ?? '2025-2026' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Total Days: <strong id="middle-wd-total" class="text-indigo-700 font-black font-mono text-sm">{{ $middleWd->total_days ?? 222 }}</strong> Days
                                &bull; Enter working days for each month (April to March)
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="resetWd('middle')"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition"
                                    title="Reset to 222 standard days">
                                ↺ Reset 222d
                            </button>
                            <button onclick="saveWd('middle')" id="btn-save-middle-wd"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition active:scale-95">
                                <span>💾 Save Middle Days</span>
                            </button>
                        </div>
                    </div>

                    <!-- 12 Months Grid -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-12 gap-2.5 pt-2">
                        @foreach($months as $mKey => $mLabel)
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-center hover:border-slate-300 transition">
                                <label for="middle-wd-{{ $mKey }}" class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    {{ $mLabel }}
                                </label>
                                <input type="number" id="middle-wd-{{ $mKey }}" min="0" max="31"
                                       value="{{ $middleWd->{$mKey} ?? 20 }}"
                                       oninput="recalcWdTotal('middle')"
                                       class="w-full text-center font-bold text-sm font-mono p-1 border border-slate-300 rounded-lg text-slate-900 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        @endforeach
                    </div>
                    <div id="middle-wd-status" class="text-xs font-semibold hidden"></div>
                </div>

                <!-- 1B. Middle Stage Curricular Max Marks -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 sm:p-7 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🎯</span>
                                <h3 class="text-base font-black text-slate-900">Middle Stage — Curricular Max Marks Defaults</h3>
                                <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                    Grades 6, 7 &amp; 8
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Sets default maximum marks across 8 Middle Stage curricular areas in generated spreadsheets.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1 bg-slate-100 border border-slate-200 rounded-xl p-1 text-xs font-bold text-slate-700">
                                <span class="text-[10px] text-slate-400 px-1">Presets:</span>
                                <button type="button" onclick="presetMm('middle', 50)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 50">50</button>
                                <button type="button" onclick="presetMm('middle', 100)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 100">100</button>
                                <button type="button" onclick="presetMm('middle', 25)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 25">25</button>
                            </div>
                            <button onclick="saveMm('middle')" id="btn-save-middle-mm"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition active:scale-95">
                                <span>💾 Save Middle Marks</span>
                            </button>
                        </div>
                    </div>

                    <!-- 8 Subjects Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 pt-2">
                        @foreach($middleSubjects as $sKey => $sMeta)
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-center hover:border-slate-300 transition">
                                <label for="middle-mm-{{ $sKey }}" class="block text-[10px] font-bold uppercase text-slate-600 truncate mb-1" title="{{ $sMeta['full'] }}">
                                    {{ $sMeta['name'] }}
                                </label>
                                <input type="number" id="middle-mm-{{ $sKey }}" min="1" max="100"
                                       value="{{ $middleMm->{$sKey} ?? 50 }}"
                                       class="w-full text-center font-bold text-sm font-mono p-1 border border-slate-300 rounded-lg text-slate-900 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        @endforeach
                    </div>
                    <div id="middle-mm-status" class="text-xs font-semibold hidden"></div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- 2. PREPARATORY STAGE CONFIGURATION PANEL                                 -->
            <!-- ========================================================================= -->
            <div x-show="activeStage === 'preparatory'" x-transition class="space-y-6">

                <!-- 2A. Preparatory Stage Working Days -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 sm:p-7 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg">📅</span>
                                <h3 class="text-base font-black text-slate-900">Preparatory Stage — School Working Days Calendar</h3>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                    AY {{ $prepWd->academic_year ?? '2025-2026' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Total Days: <strong id="preparatory-wd-total" class="text-emerald-700 font-black font-mono text-sm">{{ $prepWd->total_days ?? 222 }}</strong> Days
                                &bull; Enter working days for each month (April to March)
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="resetWd('preparatory')"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition"
                                    title="Reset to 222 standard days">
                                ↺ Reset 222d
                            </button>
                            <button onclick="saveWd('preparatory')" id="btn-save-preparatory-wd"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition active:scale-95">
                                <span>💾 Save Preparatory Days</span>
                            </button>
                        </div>
                    </div>

                    <!-- 12 Months Grid -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-12 gap-2.5 pt-2">
                        @foreach($months as $mKey => $mLabel)
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-center hover:border-slate-300 transition">
                                <label for="preparatory-wd-{{ $mKey }}" class="block text-[10px] font-bold uppercase text-slate-500 mb-1">
                                    {{ $mLabel }}
                                </label>
                                <input type="number" id="preparatory-wd-{{ $mKey }}" min="0" max="31"
                                       value="{{ $prepWd->{$mKey} ?? 20 }}"
                                       oninput="recalcWdTotal('preparatory')"
                                       class="w-full text-center font-bold text-sm font-mono p-1 border border-slate-300 rounded-lg text-slate-900 bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        @endforeach
                    </div>
                    <div id="preparatory-wd-status" class="text-xs font-semibold hidden"></div>
                </div>

                <!-- 2B. Preparatory Stage Curricular Max Marks -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 sm:p-7 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🎯</span>
                                <h3 class="text-base font-black text-slate-900">Preparatory Stage — Curricular Max Marks Defaults</h3>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                    Grades 3, 4 &amp; 5
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Sets default maximum marks across 8 Preparatory Stage curricular areas in generated spreadsheets.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1 bg-slate-100 border border-slate-200 rounded-xl p-1 text-xs font-bold text-slate-700">
                                <span class="text-[10px] text-slate-400 px-1">Presets:</span>
                                <button type="button" onclick="presetMm('preparatory', 50)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 50">50</button>
                                <button type="button" onclick="presetMm('preparatory', 100)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 100">100</button>
                                <button type="button" onclick="presetMm('preparatory', 25)" class="px-2 py-0.5 hover:bg-white rounded-lg transition" title="Set all to 25">25</button>
                            </div>
                            <button onclick="saveMm('preparatory')" id="btn-save-preparatory-mm"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition active:scale-95">
                                <span>💾 Save Preparatory Marks</span>
                            </button>
                        </div>
                    </div>

                    <!-- 8 Subjects Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 pt-2">
                        @foreach($prepSubjects as $sKey => $sMeta)
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-center hover:border-slate-300 transition">
                                <label for="preparatory-mm-{{ $sKey }}" class="block text-[10px] font-bold uppercase text-slate-600 truncate mb-1" title="{{ $sMeta['full'] }}">
                                    {{ $sMeta['name'] }}
                                </label>
                                <input type="number" id="preparatory-mm-{{ $sKey }}" min="1" max="100"
                                       value="{{ $prepMm->{$sKey} ?? 50 }}"
                                       class="w-full text-center font-bold text-sm font-mono p-1 border border-slate-300 rounded-lg text-slate-900 bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        @endforeach
                    </div>
                    <div id="preparatory-mm-status" class="text-xs font-semibold hidden"></div>
                </div>

            </div>

        </div>
    </div>

    <script>
        const CSRF = '{{ csrf_token() }}';
        const wdMonths = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        const mmSubjects = ['l1_max', 'l2_max', 'l3_max', 'm_max', 'sci_max', 'soc_max', 'art_max', 'pe_max'];

        function recalcWdTotal(stage) {
            let sum = 0;
            wdMonths.forEach(m => {
                const inp = document.getElementById(`${stage}-wd-${m}`);
                const val = inp ? parseInt(inp.value, 10) : 0;
                sum += (isNaN(val) ? 0 : val);
            });
            const badge = document.getElementById(`${stage}-wd-total`);
            if (badge) badge.innerText = sum;
            return sum;
        }

        function resetWd(stage) {
            const standard = {
                apr: 20, may: 22, jun: 10, jul: 20, aug: 22, sep: 21,
                oct: 19, nov: 20, dec: 15, jan: 15, feb: 20, mar: 18
            };
            wdMonths.forEach(m => {
                const inp = document.getElementById(`${stage}-wd-${m}`);
                if (inp) inp.value = standard[m];
            });
            recalcWdTotal(stage);
            showStatus(`${stage}-wd-status`, 'Reset to standard calendar (222 days). Click Save to apply.', 'text-amber-800 bg-amber-50 border border-amber-200');
        }

        async function saveWd(stage) {
            const btn = document.getElementById(`btn-save-${stage}-wd`);
            const oldHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳ Saving...</span>';
            }

            const payload = { stage: stage };
            wdMonths.forEach(m => {
                const inp = document.getElementById(`${stage}-wd-${m}`);
                payload[m] = inp ? parseInt(inp.value, 10) : 0;
            });

            try {
                const res = await fetch('{{ route('hpc.teacher.working_days.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    showStatus(`${stage}-wd-status`, `✓ ${data.message}`, 'text-emerald-800 bg-emerald-50 border border-emerald-200');
                } else {
                    showStatus(`${stage}-wd-status`, `✕ ${data.error || 'Failed to save.'}`, 'text-rose-800 bg-rose-50 border border-rose-200');
                }
            } catch(e) {
                showStatus(`${stage}-wd-status`, `✕ Connection error: ${e.message}`, 'text-rose-800 bg-rose-50 border border-rose-200');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = oldHtml;
                }
            }
        }

        function presetMm(stage, val) {
            mmSubjects.forEach(s => {
                const inp = document.getElementById(`${stage}-mm-${s}`);
                if (inp) inp.value = val;
            });
            showStatus(`${stage}-mm-status`, `Preset applied: All subjects set to ${val} marks. Click Save to apply.`, 'text-amber-800 bg-amber-50 border border-amber-200');
        }

        async function saveMm(stage) {
            const btn = document.getElementById(`btn-save-${stage}-mm`);
            const oldHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳ Saving...</span>';
            }

            const payload = { stage: stage };
            mmSubjects.forEach(s => {
                const inp = document.getElementById(`${stage}-mm-${s}`);
                payload[s] = inp ? parseInt(inp.value, 10) : 50;
            });

            try {
                const res = await fetch('{{ route('hpc.teacher.max_marks.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.ok) {
                    showStatus(`${stage}-mm-status`, `✓ ${data.message}`, 'text-emerald-800 bg-emerald-50 border border-emerald-200');
                } else {
                    showStatus(`${stage}-mm-status`, `✕ ${data.error || 'Failed to save.'}`, 'text-rose-800 bg-rose-50 border border-rose-200');
                }
            } catch(e) {
                showStatus(`${stage}-mm-status`, `✕ Connection error: ${e.message}`, 'text-rose-800 bg-rose-50 border border-rose-200');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = oldHtml;
                }
            }
        }

        function showStatus(elemId, msg, styleClasses) {
            const el = document.getElementById(elemId);
            if (!el) return;
            el.className = `p-3 rounded-xl text-xs font-semibold mt-3 ${styleClasses}`;
            el.innerText = msg;
            el.classList.remove('hidden');
        }
    </script>
</x-app-layout>
