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
                    <div class="max-h-56 overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-white text-slate-500 border-b border-slate-100 font-semibold sticky top-0">
                                <tr>
                                    <th class="px-4 py-2">#</th>
                                    <th class="px-4 py-2">Student Name</th>
                                    <th class="px-4 py-2">HPC Login ID</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Assessment URL</th>
                                </tr>
                            </thead>
                            <tbody id="students-table-body" class="divide-y divide-slate-100 bg-white">
                                @forelse($students as $st)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-4 py-2 font-mono text-slate-400">{{ $st['row'] ?? '-' }}</td>
                                        <td class="px-4 py-2 font-bold text-slate-800">{{ $st['name'] }}</td>
                                        <td class="px-4 py-2 font-mono text-slate-600">{{ $st['login_id'] }}</td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                {{ $st['status'] ?? 'HPC Pending' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 font-mono text-[11px] text-indigo-600">
                                            @if(!empty($st['form_url']))
                                                <span class="text-emerald-600 font-bold">✓ Form Mapped</span>
                                            @else
                                                <span class="text-slate-400">Ready via stage</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-slate-400 italic">
                                            No students loaded yet. Click "Sync Stage Students" above to fetch from the portal.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TWO COLUMNS: STEP 1 (TEMPLATE) & STEP 2 (UPLOAD) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- STEP 1: Download Excel Template -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">1</span>
                            <h3 class="text-sm font-bold text-slate-900">Download Assessment Template</h3>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            Generates an Excel workbook with your students' names and HPC Login IDs pre-filled in rows, along with dropdowns for attendance, goals, ambitions, and subject evaluations.
                        </p>
                        <div class="bg-indigo-50/60 rounded-xl p-3 border border-indigo-100 text-[11px] text-indigo-900 space-y-1 mb-4">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>📋 Included Columns:</span>
                            </div>
                            <div class="text-indigo-700">
                                Student Name, Login ID, Mother Tongue, Parent Relation, Parent Name, Attendance Days, Interests, Goals, Ambitions, Self &amp; Peer Ratings, Progress Levels.
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('hpc.teacher.template.download') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                        📥 Download Pre-Filled Template (.xlsx)
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
                                    <th class="px-4 py-2">Row</th>
                                    <th class="px-4 py-2">Student Name</th>
                                    <th class="px-4 py-2">Login ID</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Outcome / Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="results-table-body" class="divide-y divide-slate-100 bg-white">
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400 italic">
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
                tr.innerHTML = `
                    <td class="px-4 py-2 font-mono text-slate-400">${p.row}</td>
                    <td class="px-4 py-2 font-bold text-slate-800">${p.name}</td>
                    <td class="px-4 py-2 font-mono text-slate-600">${p.login_id}</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${p.errors.length ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}">${p.errors.length ? 'Invalid' : 'Ready'}</span></td>
                    <td class="px-4 py-2 text-slate-500">${p.errors.length ? p.errors.join(', ') : 'Activity: ' + p.activity + ' | Goal: ' + p.goal}</td>
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
                const isSuccess = r.status === 'success';
                tr.innerHTML = `
                    <td class="px-4 py-2 font-mono text-slate-400">${r.row}</td>
                    <td class="px-4 py-2 font-bold text-slate-800">${r.name}</td>
                    <td class="px-4 py-2 font-mono text-slate-600">${r.login_id}</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${isSuccess ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}">${isSuccess ? 'Success' : 'Error'}</span></td>
                    <td class="px-4 py-2 text-slate-500">${r.message}</td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('results-count').innerText = `${results.length} evaluated`;
        }

        async function resetRun() {
            if (!confirm('Reset current evaluation results?')) return;
            await fetch('{{ route('hpc.teacher.run.reset') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            window.location.reload();
        }
    </script>
</x-app-layout>
