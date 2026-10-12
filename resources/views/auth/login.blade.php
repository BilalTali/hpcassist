<x-guest-layout maxWidth="max-w-md">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8" x-data="{ submitting: false }">
        
        <!-- Header Banner -->
        <div class="mb-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 mb-3 font-bold text-xl ring-4 ring-indigo-50/50">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sign in to School Portal</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xs mx-auto">
                Access your institutional dashboard, UDISE+ student rosters, and teacher evaluation tools.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-5" :status="session('status')" />

        <!-- Login Form -->
        <form method="POST" action="{{ route('login') }}" @submit="submitting = true" class="space-y-4">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    School Login Email <span class="text-rose-500">*</span>
                </label>
                <div class="relative rounded-xl shadow-2xs">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                        </svg>
                    </div>
                    <input id="email" 
                           class="block w-full pl-10 pr-3.5 py-2.5 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition" 
                           type="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           autofocus 
                           autocomplete="username" 
                           placeholder="principal@school.gov.in" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <!-- Password with Accessible Show/Hide Toggle -->
            <div x-data="{ showPass: false }">
                <div class="flex items-center justify-between mb-1">
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline transition" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <div class="relative rounded-xl shadow-2xs">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <input id="password" 
                           class="block w-full pl-10 pr-10 py-2.5 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           :type="showPass ? 'text' : 'password'"
                           name="password"
                           required 
                           autocomplete="current-password"
                           placeholder="••••••••••••" />
                    <button type="button" 
                            @click="showPass = !showPass" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none transition"
                            :aria-label="showPass ? 'Hide password' : 'Show password'">
                        <template x-if="!showPass">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </template>
                        <template x-if="showPass">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </template>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember_me" class="inline-flex items-center cursor-pointer select-none">
                    <input id="remember_me" 
                           type="checkbox" 
                           class="w-4 h-4 rounded border-slate-300 text-indigo-600 shadow-2xs focus:ring-indigo-500 transition" 
                           name="remember">
                    <span class="ms-2 text-xs font-medium text-slate-600">Remember this device</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit" 
                        :disabled="submitting"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white rounded-xl font-bold text-sm shadow-sm transition active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <span x-show="!submitting" class="flex items-center gap-1.5">
                        <span>Sign In to Dashboard</span>
                        <span>&rarr;</span>
                    </span>
                    <span x-show="submitting" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Authenticating...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Divider & Registration Prompt -->
        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-500">
                New school onboarding? 
                <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:text-indigo-800 hover:underline transition">
                    Register School Account &rarr;
                </a>
            </p>
        </div>
    </div>
</x-guest-layout>
