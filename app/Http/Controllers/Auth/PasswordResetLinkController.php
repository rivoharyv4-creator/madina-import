<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'loginUrl' => route('login'),
        ]);
    }

    /**
     * Reset a password after verifying the locally stored secret phrase.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'secret_phrase' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $key = 'secret-phrase-password-reset:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'secret_phrase' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $user = User::query()
            ->where('email', $data['email'])
            ->where('role', 'super_admin')
            ->where('active', true)
            ->first();
        $dummyHash = '$2y$12$l8DBfSLOWwK7fslarVqPAOi7MKZX72r5sEK7f4I8oG2xO1AuxfQ.S';
        $valid = Hash::check($data['secret_phrase'], $user?->password_recovery_phrase ?? $dummyHash);

        if (! $user || ! filled($user->password_recovery_phrase) || ! $valid) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'secret_phrase' => 'Les informations de récupération sont invalides.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        RateLimiter::clear($key);
        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Mot de passe réinitialisé. Vous pouvez maintenant vous connecter.');
    }
}
