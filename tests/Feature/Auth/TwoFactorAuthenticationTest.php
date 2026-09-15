<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_configure_google_authenticator_for_an_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'assistant']);

        $this->actingAs($superAdmin)
            ->post("/admin/utilisateurs/{$admin->id}/double-authentification", ['current_password' => 'password'])
            ->assertRedirect();

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNotSame($admin->two_factor_secret, $admin->getRawOriginal('two_factor_secret'));

        $code = (new Google2FA)->getCurrentOtp($admin->two_factor_secret);
        $this->post("/admin/utilisateurs/{$admin->id}/double-authentification/confirmer", ['code' => $code])
            ->assertRedirect();

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorAuthentication());
        $this->assertCount(8, $admin->two_factor_recovery_codes);
    }

    public function test_two_factor_user_is_not_authenticated_until_the_totp_is_verified(): void
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['code' => 'Le code Google Authenticator est obligatoire.']);

        $this->assertGuest();
    }

    public function test_two_factor_code_can_be_submitted_with_email_and_password(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'password',
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_non_super_admin_cannot_manage_two_factor_configuration(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        $target = User::factory()->create(['role' => 'assistant']);

        $this->actingAs($assistant)
            ->get("/admin/utilisateurs/{$target->id}/double-authentification")
            ->assertForbidden();
    }

    public function test_authentication_pages_send_security_headers(): void
    {
        $this->get(route('login', absolute: false))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_a_totp_code_cannot_be_replayed(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        $code = $google2fa->getCurrentOtp($secret);

        $this->post(route('login', absolute: false), ['email' => $user->email, 'password' => 'password', 'code' => $code]);
        $this->post('/logout');

        $this->post(route('login', absolute: false), ['email' => $user->email, 'password' => 'password', 'code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_recovery_code_is_consumed_after_one_login(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $recoveryCode = 'abcde-12345';
        $user = User::factory()->create([
            'two_factor_secret' => (new Google2FA)->generateSecretKey(32),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes([$recoveryCode]),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login', absolute: false), ['email' => $user->email, 'password' => 'password', 'code' => $recoveryCode]);

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->refresh()->two_factor_recovery_codes);
    }

    public function test_unconfigured_assistant_cannot_log_in(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);

        $this->post(route('login', absolute: false), [
            'email' => $assistant->email,
            'password' => 'password',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_unconfigured_super_admin_is_restricted_to_own_enrollment_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->post(route('login', absolute: false), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.users.two-factor.show', $superAdmin, false));

        $this->assertAuthenticatedAs($superAdmin);
        $this->get('/dashboard')->assertRedirect(route('admin.users.two-factor.show', $superAdmin, false));
    }
}
