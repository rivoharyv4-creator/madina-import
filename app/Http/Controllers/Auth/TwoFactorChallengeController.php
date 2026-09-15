<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login')->with('status', 'Votre demande de connexion a expiré.');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('status', 'Votre demande de connexion a expiré.');
        }

        $key = 'two-factor-login:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.']);
        }

        $usedStep = preg_match('/^\d{6}$/', $data['code']) === 1
            ? $twoFactor->verifyNewer($user->two_factor_secret, $data['code'], $user->two_factor_last_used_step)
            : null;
        $valid = $usedStep !== null || $this->consumeRecoveryCode($user, $twoFactor, $data['code']);

        if (! $valid) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => 'Code d’authentification ou de récupération invalide.']);
        }

        if ($usedStep !== null) {
            $user->forceFill(['two_factor_last_used_step' => $usedStep])->save();
        }

        $remember = (bool) $request->session()->get('two_factor_login.remember', false);
        $request->session()->forget('two_factor_login');
        RateLimiter::clear($key);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $firstPermission = collect(array_keys(config('access.menus', [])))->first(fn ($menu) => $user->canAccessModule($menu));
        $destination = (! $firstPermission || $firstPermission === 'dashboard') ? route('dashboard', absolute: false) : '/modules/'.$firstPermission;

        return redirect()->intended($destination);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor_login');
        if (! $pending || (int) ($pending['expires_at'] ?? 0) < time()) {
            $request->session()->forget('two_factor_login');

            return null;
        }

        $user = User::query()->whereKey($pending['user_id'])->where('active', true)->first();

        return $user?->hasTwoFactorAuthentication() ? $user : null;
    }

    private function consumeRecoveryCode(User $user, TwoFactorAuthenticationService $twoFactor, string $code): bool
    {
        $remaining = $twoFactor->consumeRecoveryCode($user->two_factor_recovery_codes ?? [], $code);
        if ($remaining === null) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }
}
