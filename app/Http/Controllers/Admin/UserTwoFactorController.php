<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserTwoFactorController extends Controller
{
    public function show(Request $request, User $user, TwoFactorAuthenticationService $twoFactor): Response
    {
        $setup = null;
        $pendingSetup = $request->session()->get('two_factor_setup');
        if ((int) ($pendingSetup['user_id'] ?? 0) === $user->id
            && (int) ($pendingSetup['expires_at'] ?? 0) >= time()
            && filled($pendingSetup['secret'] ?? null)) {
            $setup = [
                'secret' => $pendingSetup['secret'],
                'provisioningUri' => $twoFactor->provisioningUri($user->email, $pendingSetup['secret']),
            ];
        }

        $recovery = $request->session()->get('two_factor_recovery');

        return Inertia::render('Users/TwoFactor', [
            'managedUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'enabled' => $user->hasTwoFactorAuthentication(),
            ],
            'setup' => $setup,
            'recoveryCodes' => (int) ($recovery['user_id'] ?? 0) === $user->id ? $recovery['codes'] : null,
        ]);
    }

    public function setup(Request $request, User $user, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ]);

        $request->session()->put('two_factor_setup', [
            'user_id' => $user->id,
            'expires_at' => time() + 600,
            'secret' => $twoFactor->generateSecret(),
        ]);

        return back()->with('success', 'Scannez le QR code puis confirmez un code à six chiffres.');
    }

    public function confirm(Request $request, User $user, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $setup = $request->session()->get('two_factor_setup');

        abort_unless(
            (int) ($setup['user_id'] ?? 0) === $user->id
                && (int) ($setup['expires_at'] ?? 0) >= time()
                && filled($setup['secret'] ?? null),
            403,
            'La configuration a expiré. Recommencez la procédure.',
        );

        $usedStep = $twoFactor->verifyNewer($setup['secret'], $data['code'], null);
        if ($usedStep === null) {
            return back()->withErrors(['code' => 'Le code saisi est invalide ou expiré.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $setup['secret'],
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
        ])->save();

        $request->session()->forget('two_factor_setup');
        if ($request->user()->is($user)) {
            $request->session()->forget('two_factor_enrollment_only');
        }

        return back()
            ->with('two_factor_recovery', ['user_id' => $user->id, 'codes' => $codes])
            ->with('success', 'Google Authenticator est maintenant activé.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ]);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
        $request->session()->forget('two_factor_setup');

        return redirect()->route('admin.users.index')->with('success', 'Double authentification désactivée.');
    }
}
