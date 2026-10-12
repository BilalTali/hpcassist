<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HPC Assist') }} — PARAKH Digital HPC Platform</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased min-h-full flex flex-col justify-between selection:bg-indigo-500 selection:text-white bg-slate-50">
        <!-- Top Public Header -->
        <header class="w-full bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3 group transition">
                    <x-application-logo class="w-10 h-10 shadow-xs group-hover:scale-105 transition-transform" />
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-lg text-slate-900 tracking-tight">HPC Assist</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                PARAKH
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 hidden sm:block">Digital HPC &amp; UDISE+ Workflow Suite</p>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
                        <span>&larr;</span>
                        <span>Back to Home</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Slot -->
        <main class="flex-grow flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8">
            <div class="w-full {{ $maxWidth ?? 'max-w-md' }}">
                {{ $slot }}
            </div>
        </main>

        <!-- Simple Trust Footer -->
        <footer class="w-full py-6 text-center text-xs text-slate-500 border-t border-slate-200/60 bg-white/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-slate-500">
                    <span>&copy; {{ date('Y') }} HPC Assist</span>
                    <span>•</span>
                    <span>Holistic Progress Card Management System</span>
                </div>
                <div class="flex items-center gap-3 text-slate-400">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 1a9 9 0 100 18 9 9 0 000-18zm3.707 7.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Role-Isolated Session Security</span>
                    </span>
                </div>
            </div>
        </footer>
    </body>
</html>
