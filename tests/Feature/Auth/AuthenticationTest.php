<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_login_discards_customer_checkout_destination(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->get('/connexion')->assertOk();
        $this->get(route('login'))->assertOk()->assertSessionMissing('url.intended');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_management_login_preserves_back_office_destination(): void
    {
        $this->withoutVite();
        $this->withSession(['url.intended' => url('/modules/stock')])->get(route('login'))
            ->assertOk()->assertSessionHas('url.intended', url('/modules/stock'));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login', absolute: false));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'password',
            'code' => $google2fa->getCurrentOtp($secret),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
