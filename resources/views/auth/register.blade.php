<x-guest-layout maxWidth="max-w-2xl">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8" 
         x-data="{ 
             submitting: false, 
             showPass: false,
             pass: '', 
             passConfirm: '',
             get passwordsMatch() {
                 return this.pass && this.passConfirm && this.pass === this.passConfirm;
             },
             get passwordsMismatch() {
                 return this.pass && this.passConfirm && this.pass !== this.passConfirm;
             }
         }">
        
        <!-- Header Section -->
        <div class="mb-6 pb-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 font-bold text-xs">
                        🏫
                    </span>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Register School Account</h1>
                </div>
                <p class="text-xs sm:text-sm text-slate-500">
                    Set up your institutional portal for PARAKH HPC assessment workflows.
                </p>
            </div>
            <div class="hidden sm:block text-right">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    Institutional Master Account
                </span>
            </div>
        </div>

        <!-- Explanatory Trust Notice -->
        <div class="mb-6 p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-600 flex items-start gap-2.5">
            <svg class="w-4 h-4 text-indigo-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>
                <strong>One account per school:</strong> Registering creates your institution's central workspace. As Head/Principal, you can configure UDISE+ student rosters and assign multiple teachers from your dashboard.
            </p>
        </div>

        <!-- Registration Form -->
        <form method="POST" action="{{ route('register') }}" @submit="submitting = true" class="space-y-6">
            @csrf

            <!-- SECTION 1: Institutional Details -->
            <div class="space-y-4">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-1">
                    1. Institutional Information
                </h2>

                <div>
                    <label for="school_name" class="block text-xs font-bold text-slate-700 mb-1">
                        School Name <span class="text-rose-500">*</span>
                    </label>
                    <input id="school_name" 
                           class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5" 
                           type="text" 
                           name="school_name" 
                           value="{{ old('school_name') }}" 
                           required 
                           autofocus 
                           placeholder="e.g. Central Model Higher Secondary School" />
                    <x-input-error :messages="$errors->get('school_name')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- UDISE Code -->
                    <div>
                        <label for="udise_code" class="block text-xs font-bold text-slate-700 mb-1">
                            UDISE+ Code <span class="text-rose-500">*</span>
                        </label>
                        <input id="udise_code" 
                               class="block w-full text-sm font-mono rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5" 
                               type="text" 
                               name="udise_code" 
                               value="{{ old('udise_code') }}" 
                               required 
                               maxlength="15" 
                               placeholder="e.g. 01020304050" />
                        <p class="text-[11px] text-slate-400 mt-1">11-digit school national code.</p>
                        <x-input-error :messages="$errors->get('udise_code')" class="mt-1" />
                    </div>

                    <!-- District / Zone -->
                    <div>
                        <label for="district" class="block text-xs font-bold text-slate-700 mb-1">
                            District / Zone
                        </label>
                        <input id="district" 
                               class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5" 
                               type="text" 
                               name="district" 
                               value="{{ old('district') }}" 
                               placeholder="e.g. Central District / North Zone" />
                        <x-input-error :messages="$errors->get('district')" class="mt-1" />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Head of School & Official Contact -->
            <div class="space-y-4 pt-2">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-1">
                    2. Head of School &amp; Communication
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Principal Name -->
                    <div>
                        <label for="principal_name" class="block text-xs font-bold text-slate-700 mb-1">
                            Principal / Head Incharge Name <span class="text-rose-500">*</span>
                        </label>
                        <input id="principal_name" 
                               class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5" 
                               type="text" 
                               name="principal_name" 
                               value="{{ old('principal_name') }}" 
                               required 
                               placeholder="e.g. Dr. Ramesh Kumar / Principal Name" />
                        <x-input-error :messages="$errors->get('principal_name')" class="mt-1" />
                    </div>

                    <!-- Contact Phone -->
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                            Contact Mobile Number
                        </label>
                        <input id="phone" 
                               class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5 font-mono" 
                               type="tel" 
                               name="phone" 
                               value="{{ old('phone') }}" 
                               placeholder="e.g. 9876543210" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                </div>

                <!-- Official Email -->
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        School Official Login Email <span class="text-rose-500">*</span>
                    </label>
                    <input id="email" 
                           class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5" 
                           type="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           autocomplete="username" 
                           placeholder="principal@schoolportal.edu.in" />
                    <p class="text-[11px] text-slate-400 mt-1">This email serves as the primary master login identifier.</p>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
            </div>

            <!-- SECTION 3: Account Security Credentials -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-1">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-400">
                        3. Portal Security Credentials
                    </h2>
                    <button type="button" 
                            @click="showPass = !showPass" 
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold focus:outline-none">
                        <span x-text="showPass ? 'Hide Passwords' : 'Show Passwords'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                            Password <span class="text-rose-500">*</span>
                        </label>
                        <input id="password" 
                               class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5"
                               :type="showPass ? 'text' : 'password'"
                               name="password"
                               x-model="pass"
                               required 
                               autocomplete="new-password" 
                               placeholder="Minimum 8 characters" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-xs font-bold text-slate-700 mb-1">
                            Confirm Password <span class="text-rose-500">*</span>
                        </label>
                        <input id="password_confirmation" 
                               class="block w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition py-2.5 px-3.5"
                               :type="showPass ? 'text' : 'password'"
                               name="password_confirmation" 
                               x-model="passConfirm"
                               required 
                               autocomplete="new-password" 
                               placeholder="Re-enter password" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>
                </div>

                <!-- Password Match Feedback -->
                <div x-show="passConfirm.length > 0" class="text-xs font-medium transition-all" x-cloak>
                    <span x-show="passwordsMatch" class="text-emerald-600 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Passwords match correctly.
                    </span>
                    <span x-show="passwordsMismatch" class="text-rose-600 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Passwords do not match yet.
                    </span>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a class="text-xs font-medium text-slate-500 hover:text-slate-800 transition" href="{{ route('login') }}">
                    &larr; Already have a school account? <strong class="text-indigo-600">Sign in</strong>
                </a>

                <button type="submit" 
                        :disabled="submitting || passwordsMismatch"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 text-white rounded-xl font-bold text-sm shadow-sm transition active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <span x-show="!submitting" class="flex items-center gap-1.5">
                        <span>Register Institution</span>
                        <span>&rarr;</span>
                    </span>
                    <span x-show="submitting" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Creating School Workspace...</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
