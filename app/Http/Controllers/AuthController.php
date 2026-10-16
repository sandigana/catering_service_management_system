<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Page
    |--------------------------------------------------------------------------
    */

    public function showLogin()
    {
        return view('auth.login');
    }


    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Deactivated accounts can never sign in (is_active must be true).
        if (Auth::attempt($credentials + ['is_active' => true])) {

            $request->session()->regenerate();

            $user = Auth::user();

            ActivityLog::record('login', 'Signed in', $user);

            /*
            |--------------------------------------------------------------------------
            | ADMIN
            |--------------------------------------------------------------------------
            */

            if ($user->isBackOffice()) {

                return redirect()
                    ->route('admin.dashboard')
                    ->with('success', 'Welcome Admin!');
            }


            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            if ($user->role === 'user') {

                return redirect()
                    ->route('customer.dashboard')
                    ->with('success', 'Welcome!');
            }


            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account role is not valid.'
            ]);
        }

        return back()->withErrors([
            'email' => 'The email or password is incorrect.'
        ])->withInput(
            $request->only('email')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Register Page
    |--------------------------------------------------------------------------
    */

    public function showRegister()
    {
        return view('auth.register');
    }


    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email'
            ],

            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],

            'password' => [
                'required',
                'confirmed',
                PasswordRule::defaults(),
            ],
        ]);


        $user = User::create([
            'name' => $validated['name'],

            'email' => $validated['email'],

            'phone' => $validated['phone'] ?? null,

            'password' => Hash::make(
                $validated['password']
            ),

            // New registered accounts are USER
            'role' => 'user',
        ]);


        // The new customer is NOT signed in automatically: they go to the
        // login page and sign in with the account they just created.
        return redirect()
            ->route('login')
            ->with(
                'success',
                'Account created successfully! Please sign in.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        if ($request->user()) {
            ActivityLog::record('logout', 'Signed out', $request->user());
        }

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login');
    }
}