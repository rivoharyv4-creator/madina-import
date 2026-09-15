<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnualTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscality_is_five_percent_of_the_annual_profit(): void
    {
        $manager = User::factory()->create(['role' => 'super_admin']);
        $clientId = DB::table('clients')->insertGetId([
            'number' => 'CLI-TAX-001', 'name' => 'Client fiscalité', 'contact' => '0340000000',
            'type' => 'entrepreneur', 'active' => true, 'credit_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'number' => 'CMD-TAX-001', 'client_id' => $clientId, 'manager_id' => $manager->id,
            'origin' => 'directe', 'ordered_at' => now()->toDateString(), 'supplier_total' => 11000000,
            'freight' => 2000000, 'commission_enabled' => false, 'commission_base' => 0,
            'commission_rate' => 8, 'commission_amount' => 0, 'margin' => 0,
            'client_total' => 20000000, 'deposit' => 0, 'balance_due' => 20000000,
            'status' => 'confirmee', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoices')->insert([
            'number' => 'FAC-TAX-001', 'order_id' => $orderId, 'client_id' => $clientId,
            'type' => 'produits', 'status' => 'finale', 'issued_at' => now()->toDateString(),
            'subtotal' => 20000000, 'paid_amount' => 0, 'balance_due' => 20000000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('expenses')->insert([
            ['category' => 'autre', 'amount' => 1000000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Coût direct', 'order_id' => $orderId, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'salaire', 'amount' => 500000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Salaire', 'order_id' => null, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'marketing', 'amount' => 250000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Marketing', 'order_id' => null, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($manager)->post('/modules/fiscalite', [
            'fiscal_year' => now()->year,
            'status' => 'estimation',
        ])->assertRedirect('/modules/fiscalite');

        $tax = DB::table('tax_records')->latest('id')->first();
        $this->assertSame('benefice_net', $tax->calculation_base);
        $this->assertSame(5250000.0, (float) $tax->base_amount);
        $this->assertSame(5.0, (float) $tax->rate);
        $this->assertSame(262500.0, (float) $tax->calculated_amount);
    }
}
