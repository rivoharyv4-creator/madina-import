<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_phrase_password_reset_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ForgotPassword')
                ->where('loginUrl', route('login'))
            );
    }

    public function test_password_can_be_reset_with_the_secret_phrase_without_sending_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'password_recovery_phrase' => Hash::make('ma phrase secrete 2026'),
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
            'secret_phrase' => 'ma phrase secrete 2026',
            'password' => 'NouveauMotDePasse2026',
            'password_confirmation' => 'NouveauMotDePasse2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NouveauMotDePasse2026', $user->refresh()->password));
        Notification::assertNothingSent();
    }

    public function test_password_cannot_be_reset_with_an_invalid_secret_phrase(): void
    {
        $user = User::factory()->create([
            'password_recovery_phrase' => Hash::make('ma phrase secrete 2026'),
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
            'secret_phrase' => 'phrase incorrecte',
            'password' => 'NouveauMotDePasse2026',
            'password_confirmation' => 'NouveauMotDePasse2026',
        ])->assertSessionHasErrors('secret_phrase');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_non_super_admin_cannot_use_secret_phrase_password_recovery(): void
    {
        $assistant = User::factory()->create([
            'role' => 'assistant',
            'password_recovery_phrase' => Hash::make('ancienne phrase secrete'),
        ]);

        $this->post('/forgot-password', [
            'email' => $assistant->email,
            'secret_phrase' => 'ancienne phrase secrete',
            'password' => 'NouveauMotDePasse2026',
            'password_confirmation' => 'NouveauMotDePasse2026',
        ])->assertSessionHasErrors('secret_phrase');

        $this->assertTrue(Hash::check('password', $assistant->refresh()->password));
    }

    public function test_legacy_email_reset_token_routes_are_disabled(): void
    {
        $this->get('/reset-password/ancien-token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
    }
}
