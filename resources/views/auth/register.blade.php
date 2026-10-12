<x-guest-layout>
    <div class="mb-5 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 mb-2 font-bold text-xl">
            🏫
        </div>
        <h2 class="text-xl font-bold text-slate-900">Register Your School Account</h2>
        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
            One login per school. Manage your school profile, multiple teachers, and HPC workflow with complete data isolation.
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- School Name -->
        <div>
            <x-input-label for="school_name" :value="__('School Name *')" />
            <x-text-input id="school_name" class="block mt-1 w-full text-sm" type="text" name="school_name" :value="old('school_name')" required autofocus placeholder="e.g. Govt Middle School Sugin" />
            <x-input-error :messages="$errors->get('school_name')" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- UDISE Code -->
            <div>
                <x-input-label for="udise_code" :value="__('UDISE+ Code *')" />
                <x-text-input id="udise_code" class="block mt-1 w-full text-sm font-mono" type="text" name="udise_code" :value="old('udise_code')" required maxlength="15" placeholder="e.g. 01041800702" />
                <x-input-error :messages="$errors->get('udise_code')" class="mt-1" />
            </div>

            <!-- Principal / Head Incharge Name -->
            <div>
                <x-input-label for="principal_name" :value="__('Principal / Head Incharge *')" />
                <x-text-input id="principal_name" class="block mt-1 w-full text-sm" type="text" name="principal_name" :value="old('principal_name')" required placeholder="e.g. Mohammad Amin" />
                <x-input-error :messages="$errors->get('principal_name')" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- School Official Email -->
            <div>
                <x-input-label for="email" :value="__('School Login Email *')" />
                <x-text-input id="email" class="block mt-1 w-full text-sm" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="school@bms.school.gov.in" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <!-- Contact Phone -->
            <div>
                <x-input-label for="phone" :value="__('Contact Mobile Number')" />
                <x-text-input id="phone" class="block mt-1 w-full text-sm" type="text" name="phone" :value="old('phone')" placeholder="e.g. 9419012345" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
        </div>

        <!-- District -->
        <div>
            <x-input-label for="district" :value="__('District / Zone')" />
            <x-text-input id="district" class="block mt-1 w-full text-sm" type="text" name="district" :value="old('district')" placeholder="e.g. Pulwama / Kakapora" />
            <x-input-error :messages="$errors->get('district')" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password *')" />
                <x-text-input id="password" class="block mt-1 w-full text-sm"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password *')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full text-sm"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="underline text-xs text-slate-500 hover:text-slate-800 rounded-md focus:outline-none" href="{{ route('login') }}">
                {{ __('Already have a school account? Sign in') }}
            </a>

            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-sm transition active:scale-95">
                <span>Register School &rarr;</span>
            </button>
        </div>
    </form>
</x-guest-layout>
