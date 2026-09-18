<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        // Customer login can leave a checkout destination in this browser's session.
        // Management login must only retain destinations in the back office.
        $intended = $request->session()->get('url.intended');
        if (is_string($intended)) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($intended));
                if (! in_array('backoffice.account', $route->gatherMiddleware(), true)) {
                    $request->session()->forget('url.intended');
                }
            } catch (HttpExceptionInterface $e) {
                $request->session()->forget('url.intended');
            }
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user->hasTwoFactorAuthentication()) {
            $code = trim((string) $request->input('code'));
            if ($code === '') {
                Auth::guard('web')->logout();
                $request->session()->put('two_factor_login', [
                    'user_id' => $user->id,
                    'remember' => $request->boolean('remember'),
                    'expires_at' => time() + 300,
                ]);

                return redirect()->route('two-factor.challenge');
            }

            $key = 'two-factor-login:'.$user->id.'|'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages(['code' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.']);
            }

            $usedStep = preg_match('/^\d{6}$/', $code) === 1
                ? $twoFactor->verifyNewer($user->two_factor_secret, $code, $user->two_factor_last_used_step)
                : null;
            $remainingRecoveryCodes = $usedStep === null
                ? $twoFactor->consumeRecoveryCode($user->two_factor_recovery_codes ?? [], $code)
                : null;

            if ($usedStep === null && $remainingRecoveryCodes === null) {
                RateLimiter::hit($key, 300);
                Auth::guard('web')->logout();
                throw ValidationException::withMessages(['code' => 'Code Google Authenticator ou code de récupération invalide.']);
            }

            $user->forceFill($usedStep !== null
                ? ['two_factor_last_used_step' => $usedStep]
                : ['two_factor_recovery_codes' => $remainingRecoveryCodes]
            )->save();
            RateLimiter::clear($key);
        }

        $firstPermission = collect(array_keys(config('access.menus', [])))->first(fn ($menu) => $user->canAccessModule($menu));
        $destination = (! $firstPermission || $firstPermission === 'dashboard') ? route('dashboard', absolute: false) : '/modules/'.$firstPermission;

        return redirect()->intended($destination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
