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

            <!-- UDISE+ SDMS Portal Card (Direct Student Data Download) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-2 border-emerald-500/20 hover:border-emerald-500/40 transition-all">
                <div class="p-6 flex items-center justify-between gap-6 flex-wrap">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                🇮🇳 Official Govt Portal
                            </span>
                            <span class="text-xs font-semibold text-gray-400">• Student Database Management System</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span>🌐 UDISE+ SDMS Student Portal</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                            Sign in to your school's <strong>UDISE+ SDMS</strong> account to access registered student records, verify APAAR IDs, and export/download the student dataset for automated HPC entry.
                        </p>
                        <div class="mt-2 text-xs text-gray-400 flex items-center gap-2">
                            <span>Direct link: <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-700">auth.udiseplus.gov.in</code></span>
                            <span>•</span>
                            <a href="https://sdms.udiseplus.gov.in/" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline">
                                Standard SDMS Home ↗
                            </a>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a href="https://auth.udiseplus.gov.in/login?response_type=code&client_id=udise-sdms-g0&scope=read%20user_info&state=a046b7da-5f29-44e4-9d90-58662138fe1f&redirect_uri=https://sdms.udiseplus.gov.in/p0/oauth2/callback&loginTxnId=09ea0bcb-5a81-4ca5-a9c2-b869c4349046"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-semibold text-white
                                  bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800
                                  transition-all shadow-md hover:shadow-emerald-200 hover:-translate-y-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>Login to UDISE+ & Download Data</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
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
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-semibold text-white
                                  bg-violet-600 hover:bg-violet-700 active:bg-violet-800
                                  transition-all shadow-md hover:shadow-violet-200 hover:-translate-y-0.5">
                            <span>Open Teacher Workspace</span>
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
</x-app-layout>
