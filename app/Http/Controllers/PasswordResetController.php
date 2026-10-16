<?php
// app/Http/Controllers/PasswordResetController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** "Forgot password" flow: e-mail a one-hour link, then set a new password. */
class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the e-mail exists, so nobody can probe which addresses have accounts.
        return back()->with('success', 'If that e-mail has an account, we sent a password reset link.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            // The model's "hashed" cast hashes it. A new remember token signs out other devices.
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

            event(new PasswordReset($user));
            ActivityLog::record('password_reset', 'Password reset using an e-mail link', $user);
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password was reset. Please sign in.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
