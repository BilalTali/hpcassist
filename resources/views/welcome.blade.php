<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'HPC Assist') }} — PARAKH Digital HPC &amp; UDISE+ Workflow Suite</title>
        <meta name="description" content="Streamline school assessment, UDISE+ student roster sync with APAAR IDs, validated master Excel generation, and PARAKH Holistic Progress Card submissions.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 bg-slate-50 antialiased selection:bg-indigo-600 selection:text-white" x-data="{ mobileMenuOpen: false }">

        <!-- ── HEADER & NAVIGATION ───────────────────────────────────────────── -->
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/80 transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 sm:h-20">
                    
                    <!-- Brand Identity -->
                    <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                        <x-application-logo class="w-10 h-10 shadow-xs group-hover:scale-105 transition-transform" />
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-xl text-slate-900 tracking-tight">HPC Assist</span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    PARAKH
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 hidden sm:block">Digital HPC &amp; UDISE+ Workflow Suite</p>
                        </div>
                    </a>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                        <a href="#workflow" class="hover:text-indigo-600 transition">Workflow</a>
                        <a href="#features" class="hover:text-indigo-600 transition">Features</a>
                        <a href="#excel-engine" class="hover:text-indigo-600 transition">Excel Engine</a>
                        <a href="#roles" class="hover:text-indigo-600 transition">Roles &amp; Access</a>
                        <a href="#faq" class="hover:text-indigo-600 transition">FAQ</a>
                    </nav>

                    <!-- Auth Action Controls -->
                    <div class="hidden sm:flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" 
                               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition active:scale-95">
                                <span>Go to Dashboard</span>
                                <span>&rarr;</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" 
                               class="px-4 py-2 text-xs font-bold text-slate-700 hover:text-indigo-600 hover:bg-slate-100/80 rounded-xl transition">
                                Sign In
                            </a>
                            <a href="{{ route('register') }}" 
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition active:scale-95">
                                <span>Register School</span>
                                <span>&rarr;</span>
                            </a>
                        @endauth
                    </div>

                    <!-- Mobile Hamburger Button -->
                    <button type="button" 
                            @click="mobileMenuOpen = !mobileMenuOpen" 
                            class="md:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none"
                            aria-label="Toggle Navigation Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" x-cloak/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Drawer Menu -->
            <div x-show="mobileMenuOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="md:hidden bg-white border-b border-slate-200 px-4 pt-3 pb-6 space-y-3" 
                 x-cloak>
                <div class="flex flex-col space-y-2 text-sm font-semibold text-slate-700">
                    <a href="#workflow" @click="mobileMenuOpen = false" class="px-3 py-2 rounded-lg hover:bg-slate-100">Workflow</a>
                    <a href="#features" @click="mobileMenuOpen = false" class="px-3 py-2 rounded-lg hover:bg-slate-100">Features</a>
                    <a href="#excel-engine" @click="mobileMenuOpen = false" class="px-3 py-2 rounded-lg hover:bg-slate-100">Excel Engine</a>
                    <a href="#roles" @click="mobileMenuOpen = false" class="px-3 py-2 rounded-lg hover:bg-slate-100">Roles &amp; Access</a>
                    <a href="#faq" @click="mobileMenuOpen = false" class="px-3 py-2 rounded-lg hover:bg-slate-100">FAQ</a>
                </div>
                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full text-center py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold">
                            Go to Dashboard &rarr;
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full text-center py-2.5 border border-slate-300 text-slate-700 rounded-xl text-xs font-bold">
                            Sign In
                        </a>
                        <a href="{{ route('register') }}" class="w-full text-center py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold">
                            Register School &rarr;
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <!-- ── HERO SECTION ──────────────────────────────────────────────────── -->
        <section class="relative pt-12 pb-20 sm:pt-20 sm:pb-28 overflow-hidden bg-radial-[at_50%_0%] from-indigo-50/70 via-slate-50 to-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Pill Notice -->
                <div class="text-center mb-6">
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-white text-indigo-700 border border-indigo-200/80 shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                        PARAKH Holistic Progress Card &amp; UDISE+ Standard Alignment
                    </span>
                </div>

                <!-- Main Hero Headings -->
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-[1.15]">
                        Unified Progress Cards &amp; Assessment Automation
                    </h1>
                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto">
                        A modern administrative platform for school in-charges and educators. Synchronize UDISE+ student rosters, generate validated Part-by-Part evaluation sheets, and streamline PARAKH portal submissions.
                    </p>
                </div>

                <!-- Hero Call to Action Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" 
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm transition active:scale-95">
                            <span>Open School Dashboard</span>
                            <span>&rarr;</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" 
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm transition active:scale-95">
                            <span>Sign In to School Portal</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="{{ route('register') }}" 
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-bold shadow-2xs transition active:scale-95">
                            <span>Register New School</span>
                        </a>
                    @endauth
                </div>

                <!-- Trust Stats Strip -->
                <div class="mt-12 max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-white/80 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
                        <div class="text-xl sm:text-2xl font-black text-slate-900">4-Step</div>
                        <div class="text-xs text-slate-500 font-medium mt-0.5">Guided Workflow</div>
                    </div>
                    <div class="bg-white/80 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
                        <div class="text-xl sm:text-2xl font-black text-indigo-600 font-mono">12-Digit</div>
                        <div class="text-xs text-slate-500 font-medium mt-0.5">APAAR ID Extraction</div>
                    </div>
                    <div class="bg-white/80 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
                        <div class="text-xl sm:text-2xl font-black text-slate-900">7 Parts</div>
                        <div class="text-xs text-slate-500 font-medium mt-0.5">A1 to Credit Sheets</div>
                    </div>
                    <div class="bg-white/80 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
                        <div class="text-xl sm:text-2xl font-black text-emerald-600">100%</div>
                        <div class="text-xs text-slate-500 font-medium mt-0.5">Session Isolation</div>
                    </div>
                </div>

                <!-- Interactive Workflow Stepper Preview -->
                <div class="mt-14 max-w-4xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-4 sm:p-6" 
                     x-data="{ activeTab: 1 }">
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                            <span class="text-xs font-bold text-slate-500 ml-2">School Central Workspace Workflow</span>
                        </div>
                        <span class="text-xs font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-600">
                            Version 2.0 Live Architecture
                        </span>
                    </div>

                    <!-- Tabs -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-6">
                        <button @click="activeTab = 1" 
                                type="button"
                                :class="activeTab === 1 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                class="p-3 rounded-xl border text-left transition text-xs font-bold flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-xs" :class="activeTab === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">1</span>
                            <span>UDISE+ Connect</span>
                        </button>
                        <button @click="activeTab = 2" 
                                type="button"
                                :class="activeTab === 2 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                class="p-3 rounded-xl border text-left transition text-xs font-bold flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-xs" :class="activeTab === 2 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">2</span>
                            <span>School Profile</span>
                        </button>
                        <button @click="activeTab = 3" 
                                type="button"
                                :class="activeTab === 3 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                class="p-3 rounded-xl border text-left transition text-xs font-bold flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-xs" :class="activeTab === 3 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">3</span>
                            <span>PARAKH School</span>
                        </button>
                        <button @click="activeTab = 4" 
                                type="button"
                                :class="activeTab === 4 ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                class="p-3 rounded-xl border text-left transition text-xs font-bold flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-xs" :class="activeTab === 4 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">4</span>
                            <span>Teacher Workspaces</span>
                        </button>
                    </div>

                    <!-- Tab Previews -->
                    <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-5 text-sm">
                        <!-- TAB 1 -->
                        <div x-show="activeTab === 1" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-slate-900">Step 1: Live UDISE+ Roster Sync or Skip Mode</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Automated or Offline</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                Connect via Chrome session to automatically sync students enrolled under your UDISE+ code along with unmasked 12-digit APAAR IDs. Alternatively, schools can click <strong>Skip UDISE</strong> to work in manual mode without connecting to SDMS.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div class="bg-white p-3 rounded-xl border border-slate-200 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">✓</div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Live UDISE Sync</div>
                                        <div class="text-[11px] text-slate-500">Auto-detects active student rosters</div>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-200 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">⚡</div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Skip / Manual Option</div>
                                        <div class="text-[11px] text-slate-500">Instant continuation without delay</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2 -->
                        <div x-show="activeTab === 2" class="space-y-3" x-cloak>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-slate-900">Step 2: Institutional Setup &amp; Teacher Assignment</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">School Profile</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                Verify institution details (UDISE code, Principal name, district, contact numbers) and register multiple teachers assigned to Grade 6, Grade 7, and Grade 8.
                            </p>
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200 flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Institutional Head &amp; Teacher Delegation</div>
                                    <div class="text-[11px] text-slate-500">Each teacher receives an isolated browser execution profile</div>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">Multi-Teacher Ready</span>
                            </div>
                        </div>

                        <!-- TAB 3 -->
                        <div x-show="activeTab === 3" class="space-y-3" x-cloak>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-slate-900">Step 3: Headmaster PARAKH Portal Session</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Portal Bridge</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                Connect to the official PARAKH HPC portal once under the school head login. Session cookies are maintained locally, preserving session integrity across evaluation periods.
                            </p>
                        </div>

                        <!-- TAB 4 -->
                        <div x-show="activeTab === 4" class="space-y-3" x-cloak>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-slate-900">Step 4: Teacher Workspaces &amp; Batch Evaluation Run</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Automated Submission</span>
                            </div>
                            <p class="text-xs text-slate-600">
                                Teachers select their profile, download pre-formatted Part-by-Part Excel evaluation sheets, enter student marks/descriptors, and execute batch assessment submissions with live status diagnostics.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ── WORKFLOW SECTION ──────────────────────────────────────────────── -->
        <section id="workflow" class="py-20 bg-white border-y border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Structured Workflow</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mt-1">
                        How HPC Assist Simplifies Assessment
                    </h2>
                    <p class="text-sm text-slate-500 mt-2">
                        Designed around the real administrative realities of government and private schools.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Card 1 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 hover:border-indigo-300 transition-all flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center mb-4">
                                01
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mb-2">School Authentication</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                One institutional login per school. The Head/Principal manages school setup, teacher assignments, and roster synchronization from a single dashboard.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-200/60 text-[11px] font-semibold text-indigo-600 flex items-center gap-1">
                            <span>Role-isolated security</span>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 hover:border-indigo-300 transition-all flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center mb-4">
                                02
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mb-2">UDISE+ Sync or Skip</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Automatically extract your student roster with unmasked 12-digit APAAR IDs via UDISE+ SDMS. If offline or not needed, instantly skip directly to manual templates.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-200/60 text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
                            <span>Flexible sync &amp; skip</span>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 hover:border-indigo-300 transition-all flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center mb-4">
                                03
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Validated Excel Engine</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Download pre-formatted Excel evaluation templates (blank or student-populated). Features isolated collapsible outline brackets for each Part and native dropdown formulas.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-200/60 text-[11px] font-semibold text-indigo-600 flex items-center gap-1">
                            <span>Formula &amp; list validation</span>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 hover:border-indigo-300 transition-all flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center mb-4">
                                04
                            </div>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Teacher Assessment Run</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Dedicated teacher workspaces. Execute automated evaluations into PARAKH with live status badges (Success, Pending, Under Process) and instant error feedback.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-200/60 text-[11px] font-semibold text-indigo-600 flex items-center gap-1">
                            <span>Live execution monitors</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── EXCEL ENGINE SECTION ──────────────────────────────────────────── -->
        <section id="excel-engine" class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    
                    <div class="space-y-5">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Smart Excel Technology</span>
                        <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">
                            Pre-Validated Part-by-Part Evaluation Spreadsheets
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Traditional progress card entry suffers from complex web portal form timeouts and repetitive dropdown clicks. HPC Assist solves this by generating smart Excel templates that teachers can complete offline at their own pace.
                        </p>

                        <div class="space-y-3 pt-2">
                            <div class="flex items-start gap-3 bg-white p-3.5 rounded-xl border border-slate-200">
                                <div class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">1</div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Collapsible Outline Brackets</h4>
                                    <p class="text-xs text-slate-500">Each Part (A1, A2, A3, A4, B, C, Credit) has its own independent collapse/expand bracket, preventing visual clutter.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 bg-white p-3.5 rounded-xl border border-slate-200">
                                <div class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">2</div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Native Dropdown Validation</h4>
                                    <p class="text-xs text-slate-500">Cells enforce exact PARAKH values (Beginner, Developing, Proficient, Advanced) with interactive in-cell dropdowns.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 bg-white p-3.5 rounded-xl border border-slate-200">
                                <div class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">3</div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">One-Click Roster Population</h4>
                                    <p class="text-xs text-slate-500">Download blank templates or pre-filled sheets containing your school's actual students and unmasked APAAR IDs.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visual Representation Card -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <span class="text-xs font-bold text-slate-700">Supported PARAKH Evaluation Domains</span>
                            <span class="text-xs font-mono bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold">Grade 6 to 8</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part A1</span>
                                <span class="text-slate-600">Attendance &amp; School Health Record</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part A2</span>
                                <span class="text-slate-600">Curricular Learning &amp; Subject Assessment</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part A3</span>
                                <span class="text-slate-600">Self Assessment (Self Reflection)</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part A4</span>
                                <span class="text-slate-600">Peer Assessment (Group Evaluation)</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part B</span>
                                <span class="text-slate-600">Activities &amp; Skill Development</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="font-bold text-indigo-600 block">Part C &amp; Credit</span>
                                <span class="text-slate-600">Teacher Remarks &amp; Credit Accumulation</span>
                            </div>
                        </div>

                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 flex items-center gap-2">
                            <span>💡</span>
                            <span>Fully compatible with Microsoft Excel, LibreOffice Calc, and Google Sheets.</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── FEATURES / ARCHITECTURE SECTION ──────────────────────────────── -->
        <section id="features" class="py-20 bg-white border-t border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">System Capabilities</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mt-1">
                        Built for Accuracy, Speed, &amp; Security
                    </h2>
                    <p class="text-sm text-slate-500 mt-2">
                        Every feature solves a specific real-world bottleneck in the digital progress card cycle.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                            🔒
                        </div>
                        <h3 class="font-bold text-slate-900 text-base">Multi-Teacher Isolation</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Each registered teacher works in their own dedicated browser session directory. This prevents session expiration and cookie conflicts when multiple teachers submit grades.
                        </p>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                            📋
                        </div>
                        <h3 class="font-bold text-slate-900 text-base">Unmasked APAAR Identification</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Automatically parses and captures the full 12-digit student APAAR (Automated Permanent Academic Account Registry) ID, ensuring 100% submission fidelity to PARAKH.
                        </p>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                            📊
                        </div>
                        <h3 class="font-bold text-slate-900 text-base">Real-Time Status Badges</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Interactive monitors display live evaluation counts (Pending, Under Process, Success). School heads can instantly see which student cards have been finalized.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── ROLES & ACCESS SECTION ────────────────────────────────────────── -->
        <section id="roles" class="py-20 bg-slate-50 border-t border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Institutional Onboarding</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mt-1">
                        Roles &amp; Account Provisioning
                    </h2>
                    <p class="text-sm text-slate-500 mt-2">
                        Clear separation between institutional administrative authority and classroom grading.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    
                    <!-- Headmaster / School Incharge -->
                    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xs space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
                                🏫
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-slate-900">Headmaster / Principal</h3>
                                <p class="text-xs text-slate-500">Master Institutional Account</p>
                            </div>
                        </div>

                        <ul class="space-y-2.5 text-xs text-slate-600">
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Registers the official school account with UDISE+ code.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Connects institutional UDISE+ and SDMS student records.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Adds and assigns teachers to specific grades and subjects.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Maintains the master school profile and institutional contact info.</span>
                            </li>
                        </ul>

                        <div class="pt-3">
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                                <span>Register Your Institution</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- Teacher Workspace -->
                    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xs space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                                👨‍🏫
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-slate-900">Class &amp; Subject Teachers</h3>
                                <p class="text-xs text-slate-500">Assessment Workspaces</p>
                            </div>
                        </div>

                        <ul class="space-y-2.5 text-xs text-slate-600">
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Assigned by the school head (PEN Code, Mobile, Grade).</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Downloads pre-formatted student evaluation templates.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Enters student marks and holistic learning descriptors offline.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Uploads completed Excel sheets for automated portal entry.</span>
                            </li>
                        </ul>

                        <div class="pt-3">
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                                <span>Sign In to Teacher Workspace</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── FAQ SECTION ───────────────────────────────────────────────────── -->
        <section id="faq" class="py-20 bg-white border-t border-slate-200/80">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Help &amp; Clarifications</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mt-1">
                        Frequently Asked Questions
                    </h2>
                </div>

                <div class="space-y-3" x-data="{ openFaq: null }">
                    
                    <!-- FAQ 1 -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50">
                        <button type="button" 
                                @click="openFaq = openFaq === 1 ? null : 1"
                                class="w-full p-4 sm:p-5 text-left font-bold text-sm text-slate-900 flex items-center justify-between gap-3">
                            <span>Is connecting to UDISE+ mandatory for our school?</span>
                            <span class="text-slate-400 font-bold text-lg" x-text="openFaq === 1 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 1" class="px-5 pb-5 text-xs text-slate-600 leading-relaxed" x-cloak>
                            No. While connecting to UDISE+ allows automatic extraction of enrolled students and APAAR IDs, HPC Assist includes a <strong>Skip UDISE</strong> feature. Schools can proceed directly with blank templates or manually upload student lists.
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50">
                        <button type="button" 
                                @click="openFaq = openFaq === 2 ? null : 2"
                                class="w-full p-4 sm:p-5 text-left font-bold text-sm text-slate-900 flex items-center justify-between gap-3">
                            <span>How are teacher accounts provisioned?</span>
                            <span class="text-slate-400 font-bold text-lg" x-text="openFaq === 2 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 2" class="px-5 pb-5 text-xs text-slate-600 leading-relaxed" x-cloak>
                            Teachers do not need to register individually. The School Head/Principal registers the master school account and creates teacher profiles (with designated grades, mobile, and PEN code) directly in the School Management tab.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50">
                        <button type="button" 
                                @click="openFaq = openFaq === 3 ? null : 3"
                                class="w-full p-4 sm:p-5 text-left font-bold text-sm text-slate-900 flex items-center justify-between gap-3">
                            <span>Which grades and evaluation parts are supported?</span>
                            <span class="text-slate-400 font-bold text-lg" x-text="openFaq === 3 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 3" class="px-5 pb-5 text-xs text-slate-600 leading-relaxed" x-cloak>
                            HPC Assist supports the complete Middle Stage rubric (Grades 6, 7, and 8) across all 7 assessment sections: Part A1 (Attendance/Health), Part A2 (Curricular Areas), Part A3 (Self-Reflection), Part A4 (Peer Review), Part B (Activities), Part C (Teacher Remarks), and Credit Accumulation.
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50">
                        <button type="button" 
                                @click="openFaq = openFaq === 4 ? null : 4"
                                class="w-full p-4 sm:p-5 text-left font-bold text-sm text-slate-900 flex items-center justify-between gap-3">
                            <span>How is student and teacher data secured?</span>
                            <span class="text-slate-400 font-bold text-lg" x-text="openFaq === 4 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 4" class="px-5 pb-5 text-xs text-slate-600 leading-relaxed" x-cloak>
                            All records are protected by CSRF validation, bcrypt password hashing, and role-based session isolation. Browser sessions and cookies are maintained inside local encrypted profiles without transmitting credentials to any third-party service.
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ── ONBOARDING CALLOUT BANNER ──────────────────────────────────────── -->
        <section class="py-16 bg-slate-900 text-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                    Get Started Today
                </span>
                <h2 class="text-2xl sm:text-4xl font-black tracking-tight text-white">
                    Ready to Streamline Your School's Progress Cards?
                </h2>
                <p class="text-sm text-slate-300 max-w-xl mx-auto leading-relaxed">
                    Sign in to your school dashboard or register your institution with your 11-digit UDISE+ code in under two minutes.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition">
                            Open Dashboard &rarr;
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition">
                            Sign In to Portal &rarr;
                        </a>
                        <a href="{{ route('register') }}" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold text-xs transition">
                            Register Institution
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        <!-- ── FOOTER ────────────────────────────────────────────────────────── -->
        <footer class="bg-white border-t border-slate-200 py-10 text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-application-logo class="w-8 h-8" />
                        <div>
                            <span class="font-black text-sm text-slate-900">HPC Assist</span>
                            <span class="text-[11px] text-slate-400 block">PARAKH Digital HPC &amp; UDISE+ Workflow Suite</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 font-medium text-slate-600 flex-wrap">
                        <a href="#workflow" class="hover:text-indigo-600 transition">Workflow</a>
                        <a href="#features" class="hover:text-indigo-600 transition">Features</a>
                        <a href="#excel-engine" class="hover:text-indigo-600 transition">Excel Engine</a>
                        <a href="{{ route('login') }}" class="hover:text-indigo-600 transition">Sign In</a>
                        <a href="{{ route('register') }}" class="hover:text-indigo-600 transition">Register School</a>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-slate-400">
                    <p>&copy; {{ date('Y') }} HPC Assist. Designed for school heads and educators managing PARAKH progress cards.</p>
                    <div class="flex items-center gap-2">
                        <span>Compliant with UDISE+ &amp; PARAKH standards</span>
                    </div>
                </div>
            </div>
        </footer>

    </body>
</html>
