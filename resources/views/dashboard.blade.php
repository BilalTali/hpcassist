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
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            <!-- HPC Tool Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-1">Automation Tool</p>
                        <h3 class="text-lg font-bold text-gray-900">HPC Student Entry Tool</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Automate student data entry on the PARAKH · NCERT portal.
                        </p>
                    </div>
                    <a href="{{ route('hpc.dashboard') }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white
                              bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                              transition-all shadow-md hover:shadow-indigo-200 hover:-translate-y-0.5">
                        🎓 Open HPC Tool
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
