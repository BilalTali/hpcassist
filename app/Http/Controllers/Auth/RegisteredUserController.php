<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $request->filled('school_name') && $request->filled('name')) {
            $request->merge(['school_name' => $request->input('name')]);
        }
        if (! $request->filled('udise_code')) {
            $request->merge(['udise_code' => '01010100101']);
        }
        if (! $request->filled('principal_name')) {
            $request->merge(['principal_name' => $request->input('name') ?: 'School Incharge']);
        }

        $request->validate([
            'school_name'    => ['required', 'string', 'max:255'],
            'udise_code'     => ['required', 'string', 'max:15'],
            'principal_name' => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'district'       => ['nullable', 'string', 'max:100'],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->school_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $school = School::create([
            'user_id'        => $user->id,
            'name'           => $request->school_name,
            'udise_code'     => preg_replace('/[^0-9]/', '', $request->udise_code),
            'principal_name' => $request->principal_name,
            'phone'          => $request->phone,
            'district'       => $request->district ?: 'Default District',
            'state'          => 'Jammu & Kashmir',
            'is_active'      => true,
        ]);

        $user->update(['school_id' => $school->id]);

        // Create initial default teacher (Principal / Head Incharge)
        Teacher::create([
            'school_id'    => $school->id,
            'name'         => $request->principal_name,
            'designation'  => 'Headmaster / Principal',
            'mobile'       => $request->phone,
            'email'        => $request->email,
            'classes'      => 'All Classes',
            'is_active'    => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
