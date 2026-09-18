<?php

namespace Tests\Feature;

use App\Mail\CustomerMessage;
use App\Models\User;
use App\Services\MadinaRevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private int $product;

    private int $account;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('persistent');
        $this->withoutVite();
        $this->customer = User::factory()->create(['role' => 'customer', 'permissions' => []]);
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->product = DB::table('inventory_products')->insertGetId(['reference' => 'CHECKOUT-1', 'name' => 'Produit test', 'quantity' => 20, 'available_quantity' => 20, 'sale_price' => 1250.50, 'is_published' => true, 'show_price' => true, 'slug' => 'produit-test', 'public_availability_status' => 'available_now']);
        $this->account = DB::table('payment_accounts')->insertGetId(['method' => 'MVola', 'display_name' => 'MVola Madina', 'account_holder' => 'Madina', 'account_number' => '0340000000', 'is_active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
    }

    private function payload(): array
    {
        return ['checkout_key' => (string) Str::uuid(), 'phone' => '0341234567', 'address' => 'Antananarivo', 'items' => [['id' => $this->product, 'quantity' => 2, 'price' => 1]], 'total' => 1];
    }

    private function order(): object
    {
        $this->actingAs($this->customer)->post('/commande', $this->payload())->assertRedirect();

        return DB::table('orders')->where('user_id', $this->customer->id)->first();
    }

    private function submission(object $o, array $extra = []): void
    {
        $this->actingAs($this->customer)->post('/mes-commandes/'.$o->number.'/paiement', ['submission_key' => (string) Str::uuid(), 'payment_account_id' => $this->account, 'transaction_reference' => 'TX-12345', ...$extra])->assertRedirect();
    }

    public function test_web_profit_deducts_purchase_cost_without_double_counting_invoices(): void
    {
        DB::table('inventory_products')->where('id', $this->product)->update(['purchase_price' => 400]);
        $order = $this->order();
        $calculator = app(MadinaRevenueCalculator::class);
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $this->assertEquals(0, $calculator->between($start, $end));
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'web_purchase_price' => 400]);
        DB::table('inventory_products')->where('id', $this->product)->update(['purchase_price' => 700, 'sale_price' => 2000]);
        $this->submission($order);
        $submission = DB::table('payment_submissions')->where('order_id', $order->id)->first();
        $this->actingAs($this->admin)->post('/admin/paiements-manuels/'.$submission->id, ['decision' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertEquals(1701, $calculator->between($start, $end));
        DB::table('expenses')->insert(['category' => 'autre', 'amount' => 101, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Charge générale', 'order_id' => null, 'status' => 'paye']);
        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('metrics.received', 2501)->where('metrics.profit', 1600));
        DB::table('invoices')->insert(['number' => 'WEB-PROFIT-INVOICE', 'order_id' => $order->id, 'client_id' => $order->client_id, 'type' => 'produits', 'status' => 'payee', 'issued_at' => now()->toDateString(), 'subtotal' => 2501, 'paid_amount' => 2501, 'balance_due' => 0]);
        $this->assertEquals(1701, $calculator->between($start, $end));
        $this->assertEquals(1600, $calculator->annualProfit((int) now()->year));
        DB::table('invoices')->where('order_id', $order->id)->update(['status' => 'annulee']);
        $this->assertEquals(1701, $calculator->between($start, $end));
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);
        $this->assertEquals(0, $calculator->between($start, $end));
    }

    public function test_web_profit_includes_freight_packaging_and_other_business_costs(): void
    {
        DB::table('inventory_products')->where('id', $this->product)->update(['purchase_price' => 400]);
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'confirmed', 'status' => 'confirmed', 'payment_confirmed_at' => now(), 'freight' => 100]);
        DB::table('order_items')->where('order_id', $order->id)->update(['china_delivery' => 30, 'packaging' => 20, 'freight' => 100]);
        foreach (['business' => 75, 'personnel' => 500] as $type => $amount) {
            DB::table('expenses')->insert(['category' => 'autre', 'amount' => $amount, 'spent_at' => now()->toDateString(), 'type' => $type, 'description' => 'Frais supplémentaires', 'order_id' => $order->id, 'status' => 'paye']);
        }
        $calculator = app(MadinaRevenueCalculator::class);
        $this->assertEquals(1426, $calculator->between(now()->startOfMonth(), now()->endOfMonth()));
        // Freight stored on both the order and its lines is deducted once.
        DB::table('orders')->where('id', $order->id)->update(['freight' => 0]);
        $this->assertEquals(1426, $calculator->between(now()->startOfMonth(), now()->endOfMonth()));
        foreach (['WEB-COSTS-1' => 1250, 'WEB-COSTS-2' => 1251] as $number => $amount) {
            DB::table('invoices')->insert(['number' => $number, 'order_id' => $order->id, 'client_id' => $order->client_id, 'type' => 'produits', 'status' => 'payee', 'issued_at' => now()->toDateString(), 'subtotal' => $amount, 'paid_amount' => $amount, 'balance_due' => 0]);
        }
        $this->assertEquals(1426, $calculator->between(now()->startOfMonth(), now()->endOfMonth()));
        $this->assertEquals(1426, $calculator->annualProfit((int) now()->year));
    }

    public function test_order_module_only_lists_confirmed_web_orders_and_manual_orders(): void
    {
        $order = $this->order();
        $template = (array) $order;
        unset($template['id']);
        $visible = [];
        foreach (['confirmed', 'processing', 'completed', 'cancelled', 'pending_payment'] as $status) {
            $number = 'WEB-'.$status;
            DB::table('orders')->insert([...$template, 'number' => $number, 'checkout_key' => (string) Str::uuid(), 'status' => $status, 'payment_status' => 'confirmed']);
            if (in_array($status, ['confirmed', 'processing', 'completed'])) {
                $visible[] = $number;
            }
        }
        DB::table('orders')->insert([...$template, 'number' => 'MANUAL-DRAFT', 'checkout_key' => null, 'user_id' => null, 'status' => 'brouillon']);
        $visible[] = 'MANUAL-DRAFT';
        $this->actingAs($this->admin)->get('/modules/commandes')->assertInertia(fn ($page) => $page
            ->where('pagination.total', count($visible))
            ->where('rows', function ($rows) use ($visible) {
                $numbers = collect($rows)->pluck('number')->all();
                sort($numbers);
                sort($visible);

                return $numbers === $visible;
            }));
        $this->actingAs($this->customer)->get('/mes-commandes/'.$order->number)->assertOk();
        $this->actingAs($this->admin)->get('/admin/paiements-manuels')->assertInertia(fn ($page) => $page->has('orders', 6));
    }

    public function test_order_images_use_product_photos_and_require_ownership(): void
    {
        $path = UploadedFile::fake()->image('product.jpg')->store('products', 'persistent');
        DB::table('inventory_products')->where('id', $this->product)->update(['photo_path' => $path]);
        $order = $this->order();
        $itemId = DB::table('order_items')->where('order_id', $order->id)->value('id');
        $url = route('customer.orders.image', ['number' => $order->number, 'item' => $itemId]);
        // Purchased product photos remain available when removed from the public catalogue.
        DB::table('inventory_products')->where('id', $this->product)->update(['is_published' => false]);
        $this->get('/mes-commandes/'.$order->number)->assertInertia(fn ($page) => $page
            ->where('items.0.image_url', $url)
            ->missing('items.0.photo_path')
            ->missing('items.0.product_photo_path'));
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get(route('customer.orders.image', ['number' => $order->number, 'item' => $itemId + 1]))->assertNotFound();

        $other = User::factory()->create(['role' => 'customer', 'permissions' => []]);
        $this->actingAs($other)->get($url)->assertNotFound();
    }

    public function test_order_images_prefer_item_photo_and_fall_back_when_missing(): void
    {
        $order = $this->order();
        $itemId = DB::table('order_items')->where('order_id', $order->id)->value('id');
        $this->get('/mes-commandes/'.$order->number)->assertInertia(fn ($page) => $page->where('items.0.image_url', null));
        $url = route('customer.orders.image', ['number' => $order->number, 'item' => $itemId]);
        $this->get($url)->assertNotFound();
        $snapshot = UploadedFile::fake()->image('snapshot.png')->store('order-items', 'persistent');
        $product = UploadedFile::fake()->image('product.jpg')->store('products', 'persistent');
        DB::table('order_items')->where('id', $itemId)->update(['photo_path' => $snapshot]);
        DB::table('inventory_products')->where('id', $this->product)->update(['photo_path' => $product]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
        Storage::disk('persistent')->delete($snapshot);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg');
    }

    public function test_registration_normalizes_email_hashes_password_and_sends_only_code(): void
    {
        $this->post('/inscription', ['name' => 'Client', 'phone' => '0341234567', 'email' => ' NEW@EXAMPLE.COM ', 'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!'])->assertRedirect(route('verification.notice'));
        $u = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertNull($u->email_verified_at);
        $this->assertSame('customer', $u->role);
        $this->assertTrue(Hash::check('SecurePassword123!', $u->password));
        Mail::assertSent(CustomerMessage::class, function ($m) use ($u) {
            $r = DB::table('email_verification_codes')->where('user_id', $u->id)->first();

            return preg_match('/^[0-9]{6}$/', $m->code) && Hash::check($m->code, $r->code_hash) && $r->code_hash !== $m->code;
        });
        Mail::assertSentCount(1);
    }

    public function test_mail_failure_keeps_account_without_duplicate(): void
    {
        config(['mail.default' => 'log']);
        $p = ['name' => 'Client', 'phone' => '0341234567', 'email' => 'retry@example.com', 'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!'];
        $this->post('/inscription', $p)->assertRedirect(route('verification.notice'));
        $this->post('/logout');
        $this->post('/inscription', $p)->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'retry@example.com')->count());
        Mail::assertNothingSent();
    }

    public function test_guest_cart_is_public_and_unverified_checkout_is_blocked(): void
    {
        $this->get('/panier')->assertOk();
        $this->get('/commande')->assertRedirect('/connexion');
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->customer)->get('/commande')->assertRedirect(route('verification.notice'));
        $this->post('/commande', $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_server_prices_snapshots_and_idempotent_checkout(): void
    {
        $p = $this->payload();
        $this->actingAs($this->customer)->post('/commande', $p)->assertRedirect();
        $this->post('/commande', $p)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('orders', ['client_total' => 2501, 'payment_status' => 'awaiting_submission']);
        $this->assertDatabaseHas('order_items', ['name' => 'Produit test', 'sku' => 'CHECKOUT-1', 'unit_price' => 1250.50, 'quantity' => 2, 'client_total' => 2501]);
        DB::table('inventory_products')->where('id', $this->product)->update(['name' => 'Nouveau nom', 'sale_price' => 99]);
        $this->assertDatabaseHas('order_items', ['name' => 'Produit test', 'unit_price' => 1250.50]);
        Mail::assertSentCount(1);
    }

    public function test_invalid_quantity_stock_and_inactive_product(): void
    {
        $this->actingAs($this->customer);
        foreach ([0, -1, 1.5, 10001] as $q) {
            $this->post('/commande', [...$this->payload(), 'items' => [['id' => $this->product, 'quantity' => $q]]])->assertSessionHasErrors('items.0.quantity');
        }$this->post('/commande', [...$this->payload(), 'items' => [['id' => $this->product, 'quantity' => 21]]])->assertSessionHasErrors('items');
        DB::table('inventory_products')->where('id', $this->product)->update(['is_published' => false]);
        $this->post('/commande', $this->payload())->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_no_active_account_prevents_order(): void
    {
        DB::table('payment_accounts')->update(['is_active' => false]);
        $this->actingAs($this->customer)->post('/commande', $this->payload())->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_owner_only_orders_and_active_accounts(): void
    {
        $o = $this->order();
        $this->get('/catalogue')->assertDontSee('0340000000');
        DB::table('payment_accounts')->insert(['method' => 'Other', 'display_name' => 'Inactive', 'account_holder' => 'Madina', 'account_number' => 'hidden', 'is_active' => false, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $this->get('/mes-commandes/'.$o->number)->assertInertia(fn ($p) => $p->has('accounts', 1));
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get('/mes-commandes/'.$o->number)->assertNotFound();
        $this->post('/mes-commandes/'.$o->number.'/paiement')->assertNotFound();
        $this->get('/admin/paiements-manuels')->assertForbidden();
    }

    public function test_private_proof_rejection_reason_and_resubmission_history(): void
    {
        $o = $this->order();
        $this->submission($o, ['proof' => UploadedFile::fake()->image('receipt.png')]);
        $s = DB::table('payment_submissions')->first();
        Storage::disk('persistent')->assertExists($s->proof_path);
        $this->get('/paiement-preuves/'.$s->id)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'customer', 'permissions' => []]))->get('/paiement-preuves/'.$s->id)->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'rejected'])->assertSessionHasErrors('rejection_reason');
        $this->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'rejected', 'rejection_reason' => 'Référence non retrouvée.'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'status' => 'pending_payment', 'payment_status' => 'rejected']);
        $this->submission($o);
        $this->assertDatabaseCount('payment_submissions', 2);
        $this->assertDatabaseHas('payment_submissions', ['id' => $s->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'payment_status' => 'under_review']);
    }

    public function test_forbidden_file_inactive_account_and_required_proof(): void
    {
        $o = $this->order();
        $base = ['submission_key' => (string) Str::uuid(), 'payment_account_id' => $this->account, 'transaction_reference' => 'TX-123'];
        $url = '/mes-commandes/'.$o->number.'/paiement';
        $this->post($url, [...$base, 'proof' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')])->assertSessionHasErrors('proof');
        DB::table('payment_accounts')->where('id', $this->account)->update(['proof_required' => true]);
        $this->post($url, $base)->assertSessionHasErrors('proof');
        DB::table('payment_accounts')->where('id', $this->account)->update(['is_active' => false]);
        $this->post($url, $base)->assertSessionHasErrors('payment_account_id');
        $this->assertDatabaseCount('payment_submissions', 0);
    }

    public function test_admin_confirmation_is_idempotent_and_client_cannot_confirm(): void
    {
        $o = $this->order();
        $this->submission($o);
        $s = DB::table('payment_submissions')->first();
        $url = '/admin/paiements-manuels/'.$s->id;
        $this->post($url, ['decision' => 'confirmed'])->assertForbidden();
        $this->actingAs($this->admin)->post($url, ['decision' => 'confirmed'])->assertRedirect();
        $this->post($url, ['decision' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'status' => 'confirmed', 'payment_status' => 'confirmed', 'balance_due' => 0]);
        $this->assertDatabaseHas('payment_submissions', ['id' => $s->id, 'reviewed_by' => $this->admin->id, 'status' => 'confirmed']);
        $this->assertDatabaseCount('client_payments', 1);
        Mail::assertSentCount(3);
        $this->post($url, ['decision' => 'rejected', 'rejection_reason' => 'Refus tardif interdit'])->assertSessionHasErrors('payment');
    }

    public function test_double_payment_submission_does_not_duplicate_history_or_mail(): void
    {
        $o = $this->order();
        $p = ['submission_key' => (string) Str::uuid(), 'payment_account_id' => $this->account, 'transaction_reference' => 'TX-123'];
        $this->post('/mes-commandes/'.$o->number.'/paiement', $p)->assertRedirect();
        $this->post('/mes-commandes/'.$o->number.'/paiement', $p)->assertRedirect();
        $this->assertDatabaseCount('payment_submissions', 1);
        Mail::assertSentCount(2);
    }

    public function test_confirmation_updates_stock_once_and_order_lifecycle_is_protected(): void
    {
        $o = $this->order();
        $this->submission($o);
        $s = DB::table('payment_submissions')->first();
        $this->actingAs($this->admin)->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'confirmed'])->assertRedirect();
        $this->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('inventory_products', ['id' => $this->product, 'quantity' => 18, 'available_quantity' => 18]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->patch('/admin/commandes-web/'.$o->id, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->patch('/admin/commandes-web/'.$o->id, ['status' => 'processing'])->assertRedirect();
        $this->patch('/admin/commandes-web/'.$o->id, ['status' => 'completed'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'status' => 'completed', 'payment_status' => 'confirmed']);
        $this->actingAs($this->customer)->patch('/admin/commandes-web/'.$o->id, ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_stock_failure_rolls_back_review_and_accounting(): void
    {
        $o = $this->order();
        $this->submission($o);
        $s = DB::table('payment_submissions')->first();
        DB::table('inventory_products')->where('id', $this->product)->update(['quantity' => 1, 'available_quantity' => 1]);
        $this->actingAs($this->admin)->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'confirmed'])->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('payment_submissions', ['id' => $s->id, 'status' => 'under_review', 'reviewed_by' => null]);
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'status' => 'pending_payment', 'payment_status' => 'under_review']);
        $this->assertDatabaseCount('client_payments', 0);
    }

    public function test_on_order_product_does_not_deduct_stock_and_internal_data_is_hidden(): void
    {
        DB::table('inventory_products')->where('id', $this->product)->update(['quantity' => 0, 'available_quantity' => 0, 'public_availability_status' => 'on_order']);
        $o = $this->order();
        $this->get('/mes-commandes/'.$o->number)->assertInertia(fn ($p) => $p->missing('order.notes')->missing('order.supplier_total')->missing('items.0.supplier_price')->missing('items.0.margin'));
        $this->submission($o);
        $s = DB::table('payment_submissions')->first();
        $this->actingAs($this->admin)->post('/admin/paiements-manuels/'.$s->id, ['decision' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_payment_account_management_requires_authorized_role(): void
    {
        $data = ['method' => 'Nouveau moyen', 'display_name' => 'Compte', 'account_holder' => 'Madina', 'account_number' => '123456', 'additional_instructions' => 'Test', 'is_active' => true, 'proof_required' => false, 'sort_order' => 2];
        $this->actingAs($this->customer)->post('/admin/comptes-paiement', $data)->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/comptes-paiement', $data)->assertRedirect();
        $this->assertDatabaseHas('payment_accounts', ['method' => 'Nouveau moyen', 'created_by' => $this->admin->id]);
        $this->put('/admin/comptes-paiement/'.$this->account, [...$data, 'is_active' => false])->assertRedirect();
        $this->assertDatabaseHas('payment_accounts', ['id' => $this->account, 'is_active' => false, 'updated_by' => $this->admin->id]);
    }

    public function test_inactive_authenticated_customer_is_blocked(): void
    {
        $this->customer->update(['active' => false]);
        $this->actingAs($this->customer)->post('/commande', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_soft_deleted_account_is_unavailable_but_payment_snapshot_remains(): void
    {
        $o = $this->order();
        $this->submission($o);
        $this->actingAs($this->admin)->delete('/admin/comptes-paiement/'.$this->account)->assertRedirect();
        $this->assertNotNull(DB::table('payment_accounts')->find($this->account)->deleted_at);
        $this->actingAs($this->customer)->get('/mes-commandes/'.$o->number)->assertInertia(fn ($p) => $p->has('accounts', 0)->where('submissions.0.account_snapshot.account_number', '0340000000'));
        $this->post('/commande', $this->payload())->assertSessionHasErrors('payment');
    }

    public function test_repeated_orders_reuse_client_and_management_cannot_overwrite_web_snapshot(): void
    {
        $o = $this->order();
        $this->post('/commande', $this->payload())->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('clients', 1);
        $this->actingAs($this->admin)->get('/modules/commandes/'.$o->id.'/edit')->assertRedirect(route('admin.manual-payments'));
    }

    public function test_cancelled_order_cannot_submit_payment_and_unpaid_order_cannot_process(): void
    {
        $o = $this->order();
        $this->actingAs($this->admin)->patch('/admin/commandes-web/'.$o->id, ['status' => 'processing'])->assertSessionHasErrors('status');
        $this->patch('/admin/commandes-web/'.$o->id, ['status' => 'cancelled'])->assertRedirect();
        $this->actingAs($this->customer)->post('/mes-commandes/'.$o->number.'/paiement', ['submission_key' => (string) Str::uuid(), 'payment_account_id' => $this->account, 'transaction_reference' => 'TX-123'])->assertSessionHasErrors('payment');
    }
}
