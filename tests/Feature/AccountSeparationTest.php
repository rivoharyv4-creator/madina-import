<?php

namespace Tests\Feature;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_email_renders_with_real_mail_transport(): void
    {
        config(['mail.default' => 'array']);
        $mail = new CustomerMessage('Confirmation', 'Votre adresse doit être confirmée.', '001234');
        $mail->assertSeeInHtml('Votre adresse doit être confirmée.');
        $mail->assertSeeInHtml('001 234');
        $mail->assertSeeInText('Votre adresse doit être confirmée.');
        $mail->assertSeeInText('001 234');
    }

    public function test_customer_cannot_login_through_management_login(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'permissions' => []]);
        $this->post(route('login'), ['email' => $customer->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_short_registration_password_has_readable_french_error_and_sends_no_email(): void
    {
        app()->setLocale('fr');
        Mail::fake();
        $this->post('/inscription', ['name' => 'Client', 'phone' => '0341234567', 'email' => 'short@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['password' => 'Le champ mot de passe doit contenir au moins 8 caractères.']);
        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
        Mail::assertNothingSent();
    }

    public function test_staff_cannot_login_through_customer_login(): void
    {
        foreach (['super_admin', 'admin', 'assistant', 'user'] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->post('/connexion', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_customer_cannot_access_management_even_with_injected_permissions(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'permissions' => array_keys(config('access.menus'))]);
        $this->actingAs($customer);
        foreach (['/dashboard', '/modules/stock', '/admin/utilisateurs', '/admin/paiements-manuels', '/profile'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_staff_cannot_access_customer_checkout_or_order_space(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/commande')->assertForbidden();
        $this->post('/commande')->assertForbidden();
        $this->get('/mes-commandes')->assertForbidden();
    }

    public function test_customer_is_excluded_from_staff_management_and_cannot_be_promoted_here(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create();
        $customer = User::factory()->create(['role' => 'customer', 'permissions' => []]);
        $this->actingAs($admin)->get('/admin/utilisateurs')->assertInertia(fn ($p) => $p->where('users', fn ($users) => ! collect($users)->pluck('id')->contains($customer->id)));
        $this->put('/admin/utilisateurs/'.$customer->id, ['role' => 'assistant'])->assertForbidden();
        $this->get('/admin/utilisateurs/'.$customer->id.'/double-authentification')->assertForbidden();
        $this->assertSame('customer', $customer->fresh()->role);
    }

    public function test_public_registration_cannot_assign_staff_role_or_permissions(): void
    {
        Mail::fake();
        $this->post('/inscription', ['name' => 'Public', 'email' => 'public@example.com', 'phone' => '0341234567', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'super_admin', 'permissions' => ['dashboard', 'paiements']])->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'public@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame([], $user->permissions);
        $this->assertNull($user->email_verified_at);
    }

    public function test_authenticated_customer_is_redirected_to_customer_space_from_login(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'permissions' => []]);
        $this->actingAs($customer)->get('/connexion')->assertRedirect(route('customer.orders.index'));
    }

    public function test_customer_login_pages_do_not_redirect_staff_to_management(): void
    {
        $this->withoutVite();
        foreach (['super_admin', 'admin', 'assistant', 'user'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/connexion')->assertOk()->assertInertia(fn ($page) => $page->component('Customer/Auth')->where('register', false));
            $this->get('/inscription')->assertOk()->assertInertia(fn ($page) => $page->component('Customer/Auth')->where('register', true));
        }
    }

    private function backOfficeEndpoints(): array
    {
        $endpoints = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('backoffice.account', $route->gatherMiddleware(), true)) {
                continue;
            }
            $path = '/'.preg_replace_callback('/\{([^}?]+)\??\}/', fn ($m) => [
                'module' => 'devis', 'id' => '1', 'user' => '1', 'category' => 'payment', 'filename' => 'example.png',
            ][$m[1]] ?? '1', $route->uri());
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $endpoints[] = [$method, $path];
                }
            }
        }
        $this->assertGreaterThan(30, count($endpoints));

        return $endpoints;
    }

    public function test_all_back_office_endpoints_reject_public_customers(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'permissions' => array_keys(config('access.menus'))]);
        $this->actingAs($customer);
        foreach ($this->backOfficeEndpoints() as [$method,$path]) {
            $this->call($method, $path)->assertForbidden();
        }
    }

    public function test_all_back_office_endpoints_require_login_for_visitors(): void
    {
        foreach ($this->backOfficeEndpoints() as [$method,$path]) {
            $this->call($method, $path)->assertRedirect(route('customer.login', absolute: false));
        }
    }

    public function test_unknown_account_role_cannot_access_back_office(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'unknown', 'permissions' => array_keys(config('access.menus'))]));
        $this->get('/profile')->assertForbidden();
        $this->get('/modules/stock')->assertForbidden();
    }
}
