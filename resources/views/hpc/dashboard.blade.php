<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    ← Dashboard
                </a>
                <span class="text-slate-300">/</span>
                <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight flex items-center gap-2">
                    🎓 HPC Portal Automation Suite
                </h2>
            </div>

            <!-- School Selector & Session Controls -->
            <div class="flex items-center gap-3 flex-wrap">
                <!-- School Profile Switcher -->
                <div class="flex items-center gap-2 bg-white border border-slate-300 rounded-xl px-3 py-1.5 shadow-sm">
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                        🏫 School:
                    </span>
                    <select id="school-select" onchange="handleSchoolChange(this.value)"
                            class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 text-slate-800 focus:outline-none focus:border-indigo-500 cursor-pointer">
                        @foreach($schoolsData['schools'] as $school)
                            <option value="{{ $school['id'] }}" {{ $school['id'] === $activeSchool['id'] ? 'selected' : '' }}>
                                {{ $school['name'] }}
                            </option>
                        @endforeach
                        <option value="__add_new__">➕ + Add New School Account...</option>
                    </select>
                    @if($activeSchool['id'] !== 'default')
                        <button onclick="deleteActiveSchool('{{ $activeSchool['id'] }}', '{{ addslashes($activeSchool['name']) }}')"
                                class="text-slate-400 hover:text-rose-600 p-0.5 text-xs transition" title="Delete this school profile">
                            🗑️
                        </button>
                    @endif
                </div>

                <!-- Session Status for Active School -->
                @if($hasProfile)
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm"
                          title="Logged in session active for {{ $activeSchool['name'] }}">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $activeSchool['name'] }} Ready
                    </span>
                    <button onclick="endSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition shadow-sm"
                            title="Log out and clear session for {{ $activeSchool['name'] }}">
                        🚪 Log Out School
                    </button>
                @else
                    <span id="session-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        No Active Session
                    </span>
                    <button onclick="startSession()"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition shadow-sm"
                            title="Open Chrome to log in as {{ $activeSchool['name'] }}">
                        🚀 Log In to {{ $activeSchool['name'] }}
                    </button>
                @endif
                <a href="{{ config('hpc.login_url') }}" target="_blank"
                   class="text-xs text-slate-600 hover:text-indigo-600 bg-white border border-slate-200 px-3 py-1.5 rounded-full font-medium transition shadow-sm flex items-center gap-1">
                    🔗 Portal ↗
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-100/60 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- SECTION 1: Universal Form URL Discovery & Template Generator -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🔍 Fetch Form Details &amp; Create Template</span>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">Universal</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Enter any HPC portal form URL (e.g. Students, Teachers, Classes) to inspect inputs and generate the corresponding Excel template.
                        </p>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="{{ route('hpc.template.download') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition hover:shadow">
                            📥 Download Template (.xlsx)
                        </a>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <input type="url" id="form-url-input"
                               value="{{ $currentFormUrl }}"
                               placeholder="https://hpc.parakh.ncert.gov.in/en/students/add"
                               class="w-full bg-slate-50 border border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 rounded-xl px-4 py-2.5 text-xs font-mono text-slate-800 outline-none transition">
                    </div>
                    <button id="btn-discover" onclick="discoverForm()"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl font-bold text-xs transition shadow-sm shrink-0">
                        ⚡ Scan Form &amp; Rebuild Template
                    </button>
                </div>

                <!-- Discovered fields tags -->
                <div id="discover-result-box" class="mt-4 pt-4 border-t border-slate-100 {{ $hasFields ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            Active Form Fields:
                        </span>
                        <span id="fields-count-badge" class="font-mono text-slate-500 text-[11px]">7 fields ready</span>
                    </div>
                    <div id="fields-tags" class="flex flex-wrap gap-1.5 text-xs">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-medium text-[11px]">First Name *</span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-medium text-[11px]">Last Name</span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-medium text-[11px]">Date of Birth * (DD/MM/YYYY)</span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-medium text-[11px]">Email *</span>
                        <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-semibold text-[11px]">Grade * (Balvatika 1-3, Grade 1-8)</span>
                        <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-semibold text-[11px]">Section * (A-H)</span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-medium text-[11px]">APAAR ID (Optional)</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Upload Data & Automation Control Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Upload Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <span>📤 Upload Completed Excel Sheet</span>
                            </h4>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Step 1</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            Upload your filled <code>.xlsx</code> file. Drop it below to validate student records instantly.
                        </p>

                        <!-- Dropzone -->
                        <div id="drop-area"
                             class="border-2 border-dashed border-slate-300 hover:border-indigo-500 rounded-2xl p-6 text-center cursor-pointer transition bg-slate-50/50 hover:bg-indigo-50/20">
                            <input type="file" id="file-input" class="hidden" accept=".xlsx,.xls,.csv" onchange="handleFileSelect(event)">
                            <div class="space-y-2">
                                <div class="w-10 h-10 mx-auto rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <div class="text-xs font-semibold text-slate-700">
                                    <span class="text-indigo-600 hover:underline">Click to browse file</span> or drag &amp; drop
                                </div>
                                <p class="text-[11px] text-slate-400">Supports .xlsx or .xls</p>
                            </div>
                        </div>

                        <!-- Upload summary counts -->
                        <div id="upload-summary" class="mt-4 grid grid-cols-3 gap-3 {{ count($preview) ? '' : 'hidden' }}">
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center">
                                <div class="text-[11px] text-slate-400 font-medium">Total Loaded</div>
                                <div id="stat-total" class="text-base font-bold text-slate-800">{{ count($preview) }}</div>
                            </div>
                            <div class="bg-emerald-50 p-2.5 rounded-xl border border-emerald-200 text-center">
                                <div class="text-[11px] text-emerald-600 font-medium">Valid Entries</div>
                                <div id="stat-valid" class="text-base font-bold text-emerald-700">
                                    {{ count(array_filter($preview, fn($s) => empty($s['errors']))) }}
                                </div>
                            </div>
                            <div class="bg-rose-50 p-2.5 rounded-xl border border-rose-200 text-center">
                                <div class="text-[11px] text-rose-600 font-medium">Issues</div>
                                <div id="stat-invalid" class="text-base font-bold text-rose-700">
                                    {{ count(array_filter($preview, fn($s) => !empty($s['errors']))) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <button onclick="document.getElementById('file-input').click()"
                                class="text-xs font-semibold text-slate-700 hover:text-indigo-600 transition flex items-center gap-1.5">
                            📁 Select from computer
                        </button>
                        <span id="upload-status-text" class="text-xs text-slate-400 hidden">Uploading...</span>
                    </div>
                </div>

                <!-- Run Automation Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <span>⚡ Batch Run Controller</span>
                            </h4>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Step 2</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            Submits each entry into the portal sequentially and captures real-time feedback.
                        </p>

                        <!-- Progress Bar & Status -->
                        <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="flex justify-between items-center text-xs font-semibold">
                                <span id="run-progress-label" class="text-slate-700">Automation Progress</span>
                                <span id="run-progress-pct" class="text-indigo-600 font-mono">0%</span>
                            </div>
                            <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
                                <div id="run-progress-bar" class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full transition-all duration-300 w-0"></div>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-600 pt-1">
                                <span id="run-status-icon">💤</span>
                                <span id="run-status-msg" class="font-medium truncate">Ready to run when students are loaded.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-3">
                        @php
                            $validCount = count(array_filter($preview, fn($s) => empty($s['errors'])));
                        @endphp
                        <button id="btn-run"
                                onclick="startRun()"
                                {{ $validCount > 0 ? '' : 'disabled' }}
                                class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm text-white shadow-sm transition-all
                                       {{ $validCount > 0 ? 'bg-gradient-to-r from-indigo-600 to-emerald-600 hover:from-indigo-700 hover:to-emerald-700 cursor-pointer hover:shadow active:scale-98' : 'bg-slate-300 cursor-not-allowed opacity-60' }}">
                            🚀 Run Batch Submission
                        </button>
                        <button onclick="resetRun()"
                                class="px-3 py-3 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition text-xs font-semibold"
                                title="Reset current execution results">
                            ↺ Reset
                        </button>
                    </div>
                </div>

            </div>

            <!-- SECTION 3: Clean, Full-Width Records Table (No Awkward 5-Row Scrollbox!) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                            <span>📋 Student Records &amp; Submission Feedback</span>
                            <span id="results-count-badge" class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-100 font-bold px-2 py-0.5 rounded-full">
                                {{ count($preview) }} loaded
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Full clean view of all uploaded records and their portal submission status.</p>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <input type="text" id="table-search" oninput="searchTable(this.value)" placeholder="Search students..."
                               class="text-xs px-3 py-1.5 border border-slate-300 rounded-lg focus:outline-none focus:border-indigo-500 w-full sm:w-48 bg-white">
                        <div class="flex items-center gap-1 shrink-0">
                            <button onclick="filterResults('all')" id="filter-all"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                All
                            </button>
                            <button onclick="filterResults('success')" id="filter-success"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
                                Successful
                            </button>
                            <button onclick="filterResults('rejected')" id="filter-rejected"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
                                Rejected
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Student Name</th>
                                <th class="py-3 px-4">Grade &amp; Section</th>
                                <th class="py-3 px-4">Date of Birth</th>
                                <th class="py-3 px-4">Email</th>
                                <th class="py-3 px-4">APAAR ID</th>
                                <th class="py-3 px-4 w-36">Status</th>
                                <th class="py-3 px-4">Portal Feedback / Details</th>
                            </tr>
                        </thead>
                        <tbody id="results-table-body" class="divide-y divide-slate-100 text-slate-700">
                            @forelse($preview as $idx => $row)
                                @php
                                    $res = null;
                                    if (!empty($lastRun['results'])) {
                                        foreach ($lastRun['results'] as $r) {
                                            if (($r['row'] ?? null) == ($row['row'] ?? ($idx + 1)) || ($r['name'] ?? null) == ($row['name'] ?? null)) {
                                                $res = $r;
                                                break;
                                            }
                                        }
                                    }
                                    $status = 'pending';
                                    if (!empty($row['errors'])) {
                                        $status = 'rejected';
                                    } elseif ($res) {
                                        $status = $res['status'] === 'success' ? 'success' : 'rejected';
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition result-row"
                                    data-status="{{ $status }}"
                                    data-row="{{ $row['row'] ?? ($idx + 1) }}">
                                    <td class="py-3 px-4 text-center font-mono text-slate-400">{{ $idx + 1 }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900 student-name-cell">{{ $row['name'] }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-800">
                                            {{ $row['grade'] }} - {{ $row['section'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">{{ $row['dob'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ $row['email'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-400">{{ $row['apaar_id'] ?: '—' }}</td>
                                    <td class="py-3 px-4 status-cell">
                                        @if(!empty($row['errors']))
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                ❌ Invalid Data
                                            </span>
                                        @elseif($res && $res['status'] === 'success')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                ✅ Successful
                                            </span>
                                        @elseif($res && $res['status'] !== 'success')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                ❌ Rejected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                                ⏳ Ready to Run
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 message-cell text-slate-500">
                                        @if(!empty($row['errors']))
                                            <span class="text-rose-600">{{ implode(', ', $row['errors']) }}</span>
                                        @elseif($res && $res['status'] === 'success')
                                            <span class="text-emerald-700 font-semibold">{{ $res['message'] ?? 'Student added successfully' }}</span>
                                        @elseif($res && $res['status'] !== 'success')
                                            <span class="text-rose-700 font-semibold">{{ $res['message'] ?? 'Submission failed' }}</span>
                                        @else
                                            <span class="text-slate-400">Waiting for automation execution</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr id="empty-row">
                                    <td colspan="8" class="py-16 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <span class="text-4xl">📂</span>
                                            <p class="font-bold text-slate-700 text-sm">No student records loaded</p>
                                            <p class="text-xs text-slate-400">Download the Excel template above, fill student details, and drop it here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Live Polling & Client Script -->
    <script>
        const CSRF = "{{ csrf_token() }}";
        let pollInterval = null;
        let isRunning = false;

        // School Profile Actions
        async function handleSchoolChange(val) {
            if (val === '__add_new__') {
                const name = prompt('Enter the School Name or UDISE Code for the new account:');
                if (!name || !name.trim()) {
                    window.location.reload();
                    return;
                }
                try {
                    const res = await fetch("{{ route('hpc.school.add') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF
                        },
                        body: JSON.stringify({ name: name.trim() })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        alert(data.message || 'School account created.');
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to add school.');
                        window.location.reload();
                    }
                } catch (e) {
                    alert('Request failed: ' + e.message);
                    window.location.reload();
                }
                return;
            }

            try {
                const res = await fetch("{{ route('hpc.school.switch') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify({ id: val })
                });
                const data = await res.json();
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to switch school.');
                }
            } catch (e) {
                alert('Switch failed: ' + e.message);
            }
        }

        async function deleteActiveSchool(id, name) {
            if (!confirm(`Are you sure you want to delete the profile for "${name}"? Saved logins for this school will be removed.`)) return;
            try {
                const res = await fetch("{{ route('hpc.school.delete') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.ok) {
                    alert(data.message || 'School profile deleted.');
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to delete school.');
                }
            } catch (e) {
                alert('Delete failed: ' + e.message);
            }
        }

        // Session Actions
        async function endSession() {
            if (!confirm('Are you sure you want to end this portal session? Chrome will close and saved cookies will be cleared so you can start fresh.')) return;
            try {
                const res = await fetch("{{ route('hpc.session.end') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                alert(data.message || 'Session ended');
                window.location.reload();
            } catch (err) {
                alert('Failed to end session: ' + err.message);
            }
        }

        async function startSession() {
            try {
                const res = await fetch("{{ route('hpc.session.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                alert(data.message || 'Chrome opened');
            } catch (err) {
                alert('Failed to start session: ' + err.message);
            }
        }

        // Form Discovery by URL
        async function discoverForm() {
            const urlInput = document.getElementById('form-url-input');
            const url = urlInput.value.trim();
            if (!url) {
                alert('Please enter a valid form URL.');
                return;
            }

            const btn = document.getElementById('btn-discover');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="animate-spin rounded-full h-3.5 w-3.5 border-2 border-white border-t-transparent inline-block"></span> Scanning...`;

            try {
                const res = await fetch("{{ route('hpc.form.discover') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify({ url: url })
                });
                const data = await res.json();
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (!data.ok) {
                    alert(data.error || 'Discovery failed');
                    return;
                }

                // Show discovered fields
                const resultBox = document.getElementById('discover-result-box');
                const countBadge = document.getElementById('fields-count-badge');
                const tagsBox = document.getElementById('fields-tags');

                resultBox.classList.remove('hidden');
                countBadge.textContent = `${data.total} fields detected`;

                tagsBox.innerHTML = data.fields.map(f => `
                    <span class="px-2.5 py-1 rounded-lg ${f.required ? 'bg-indigo-50 border-indigo-200 text-indigo-700 font-semibold' : 'bg-slate-100 border-slate-200 text-slate-700 font-medium'} border text-[11px]">
                        ${escapeHtml(f.label)} ${f.required ? '*' : ''}
                    </span>
                `).join('');

                alert(`Success! Scanned ${data.total} fields from ${data.url}. The template has been updated.`);

            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert('Request failed: ' + err.message);
            }
        }

        // Drag & Drop Setup
        const dropArea = document.getElementById('drop-area');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('border-indigo-500', 'bg-indigo-50/40'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('border-indigo-500', 'bg-indigo-50/40'), false);
        });

        dropArea.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length) uploadFile(files[0]);
        });

        function handleFileSelect(e) {
            if (e.target.files.length) uploadFile(e.target.files[0]);
        }

        async function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            const statusText = document.getElementById('upload-status-text');
            statusText.classList.remove('hidden');
            statusText.textContent = `Uploading ${file.name}...`;

            try {
                const res = await fetch("{{ route('hpc.upload') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    body: formData
                });
                const data = await res.json();
                statusText.classList.add('hidden');

                if (!data.ok) {
                    alert(data.error || 'Upload failed');
                    return;
                }

                // Update Summary Cards
                document.getElementById('upload-summary').classList.remove('hidden');
                document.getElementById('stat-total').textContent = data.total;
                document.getElementById('stat-valid').textContent = data.valid;
                document.getElementById('stat-invalid').textContent = data.invalid;

                // Render Table
                renderTable(data.preview);

                // Enable/disable Run Button
                const runBtn = document.getElementById('btn-run');
                if (data.valid > 0) {
                    runBtn.disabled = false;
                    runBtn.className = "flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm text-white shadow-sm transition-all bg-gradient-to-r from-indigo-600 to-emerald-600 hover:from-indigo-700 hover:to-emerald-700 cursor-pointer hover:shadow active:scale-98";
                    document.getElementById('run-status-msg').textContent = `${data.valid} student(s) ready to submit. Click Run to begin.`;
                    document.getElementById('run-status-icon').textContent = '✅';
                } else {
                    runBtn.disabled = true;
                    runBtn.className = "flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm text-white shadow-sm transition-all bg-slate-300 cursor-not-allowed opacity-60";
                    document.getElementById('run-status-msg').textContent = 'Please fix errors in the Excel sheet and upload again.';
                    document.getElementById('run-status-icon').textContent = '⚠️';
                }

            } catch (err) {
                statusText.classList.add('hidden');
                alert('Upload request failed: ' + err.message);
            }
        }

        function renderTable(rows) {
            const tbody = document.getElementById('results-table-body');
            document.getElementById('results-count-badge').textContent = `${rows.length} loaded`;

            if (!rows.length) {
                tbody.innerHTML = `
                    <tr id="empty-row">
                        <td colspan="8" class="py-16 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <span class="text-4xl">📂</span>
                                <p class="font-bold text-slate-700 text-sm">No student records loaded</p>
                            </div>
                        </td>
                    </tr>`;
                return;
            }

            tbody.innerHTML = rows.map((r, i) => {
                const hasErrors = r.errors && r.errors.length > 0;
                const statusBadge = hasErrors
                    ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">❌ Invalid</span>`
                    : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">⏳ Ready</span>`;

                const msg = hasErrors
                    ? `<span class="text-rose-600">${r.errors.join(', ')}</span>`
                    : `<span class="text-slate-400">Waiting for automation execution</span>`;

                return `
                    <tr class="hover:bg-slate-50/70 transition result-row" data-status="${hasErrors ? 'rejected' : 'pending'}" data-row="${r.row || (i + 1)}">
                        <td class="py-3 px-4 text-center font-mono text-slate-400">${i + 1}</td>
                        <td class="py-3 px-4 font-bold text-slate-900 student-name-cell">${escapeHtml(r.name)}</td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-800">
                                ${escapeHtml(r.grade)} - ${escapeHtml(r.section)}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">${escapeHtml(r.dob)}</td>
                        <td class="py-3 px-4 font-mono text-[11px] text-slate-500">${escapeHtml(r.email)}</td>
                        <td class="py-3 px-4 font-mono text-[11px] text-slate-400">${escapeHtml(r.apaar_id || '—')}</td>
                        <td class="py-3 px-4 status-cell">${statusBadge}</td>
                        <td class="py-3 px-4 message-cell text-slate-500">${msg}</td>
                    </tr>
                `;
            }).join('');
        }

        // Start Run
        async function startRun() {
            const btn = document.getElementById('btn-run');
            btn.disabled = true;
            btn.innerHTML = `
                <span class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent inline-block"></span>
                Starting Automation...
            `;

            try {
                const res = await fetch("{{ route('hpc.run.start') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();

                if (!data.ok) {
                    alert(data.error || 'Failed to start run');
                    btn.disabled = false;
                    btn.innerHTML = `🚀 Run Batch Submission`;
                    return;
                }

                isRunning = true;
                document.getElementById('run-status-icon').textContent = '🌐';
                document.getElementById('run-status-msg').textContent = 'Connecting to Chrome & checking session...';

                if (pollInterval) clearInterval(pollInterval);
                pollInterval = setInterval(pollRunStatus, 1500);

            } catch (err) {
                alert('Request failed: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = `🚀 Run Batch Submission`;
            }
        }

        async function pollRunStatus() {
            try {
                const res = await fetch("{{ route('hpc.run.status') }}");
                const data = await res.json();

                if (!data || !data.started) return;

                const total = data.total || 1;
                const processed = data.processed || 0;
                const pct = Math.round((processed / total) * 100);

                document.getElementById('run-progress-pct').textContent = `${pct}%`;
                document.getElementById('run-progress-bar').style.width = `${pct}%`;
                document.getElementById('run-progress-label').textContent = `Processed ${processed} of ${total}`;

                if (data.login_wait) {
                    document.getElementById('run-status-icon').textContent = '🔑';
                    document.getElementById('run-status-msg').textContent = 'Verifying portal login / session...';
                } else if (data.fatal) {
                    document.getElementById('run-status-icon').textContent = '❌';
                    document.getElementById('run-status-msg').textContent = data.fatal;
                    finishRun(false);
                    return;
                } else if (!data.done) {
                    document.getElementById('run-status-icon').textContent = '⚡';
                    document.getElementById('run-status-msg').textContent = `Submitting student ${processed + 1} of ${total}...`;
                }

                if (data.results && data.results.length) {
                    data.results.forEach((res, i) => {
                        const rowEl = document.querySelector(`tr[data-row="${res.row}"]`) || document.querySelectorAll('.result-row')[i];
                        if (rowEl) {
                            const statusCell = rowEl.querySelector('.status-cell');
                            const msgCell = rowEl.querySelector('.message-cell');

                            if (res.status === 'success') {
                                rowEl.setAttribute('data-status', 'success');
                                statusCell.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">✅ Successful</span>`;
                                msgCell.innerHTML = `<span class="text-emerald-700 font-semibold">${escapeHtml(res.message || 'Added successfully')}</span>`;
                            } else {
                                rowEl.setAttribute('data-status', 'rejected');
                                statusCell.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">❌ Rejected</span>`;
                                msgCell.innerHTML = `<span class="text-rose-700 font-semibold">${escapeHtml(res.message || 'Rejected by portal')}</span>`;
                            }
                        }
                    });
                }

                if (data.done) {
                    finishRun(true);
                }

            } catch (err) {
                console.error("Polling error:", err);
            }
        }

        function finishRun(success) {
            clearInterval(pollInterval);
            isRunning = false;
            const btn = document.getElementById('btn-run');
            btn.disabled = false;
            btn.innerHTML = `🚀 Run Again`;

            if (success) {
                document.getElementById('run-status-icon').textContent = '🎉';
                document.getElementById('run-status-msg').textContent = 'Batch submission complete! Check results below.';
            }
        }

        async function resetRun() {
            if (!confirm('Reset current execution results?')) return;
            try {
                await fetch("{{ route('hpc.run.reset') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF }
                });
                document.getElementById('run-progress-pct').textContent = '0%';
                document.getElementById('run-progress-bar').style.width = '0%';
                document.getElementById('run-progress-label').textContent = 'Automation Progress';
                document.getElementById('run-status-icon').textContent = '💤';
                document.getElementById('run-status-msg').textContent = 'Execution history reset.';
                window.location.reload();
            } catch (err) {
                alert('Reset failed: ' + err.message);
            }
        }

        // Live search filter in table
        function searchTable(query) {
            const q = query.toLowerCase().trim();
            document.querySelectorAll('.result-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        }

        function filterResults(type) {
            ['all', 'success', 'rejected'].forEach(t => {
                const btn = document.getElementById('filter-' + t);
                if (t === type) {
                    btn.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200";
                } else {
                    btn.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50";
                }
            });

            document.querySelectorAll('.result-row').forEach(row => {
                const status = row.getAttribute('data-status');
                if (type === 'all') row.style.display = '';
                else if (type === 'success' && status === 'success') row.style.display = '';
                else if (type === 'rejected' && (status === 'rejected' || status === 'error')) row.style.display = '';
                else row.style.display = 'none';
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        window.addEventListener('DOMContentLoaded', () => {
            fetch("{{ route('hpc.run.status') }}")
                .then(r => r.json())
                .then(data => {
                    if (data && data.started && !data.done) {
                        isRunning = true;
                        pollInterval = setInterval(pollRunStatus, 1500);
                    } else if (data && data.done) {
                        pollRunStatus();
                    }
                })
                .catch(() => {});
        });
    </script>
</x-app-layout>
