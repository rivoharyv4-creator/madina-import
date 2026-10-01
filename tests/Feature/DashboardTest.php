<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_manager_can_open_dashboard_and_every_module(): void
    {
        $user = User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        foreach (['clients','devis','commandes','paiements','factures','fournisseurs','achats','logistique','stock','depenses','salaires','fiscalite','rapports','parametres'] as $module) {
            $this->actingAs($user)->get('/modules/'.$module)->assertOk();
        }
        $this->actingAs($user)->get('/modules/bons-livraison')->assertOk();
        $this->actingAs($user)->get('/modules/ventes')->assertNotFound();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_expenses_are_listed_from_most_recent_spending_date_to_oldest(): void
    {
        $user = User::factory()->create();
        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        DB::table('expenses')->insert([
            [...$timestamps, 'category' => 'autre', 'amount' => 100, 'spent_at' => '2026-09-20', 'type' => 'business', 'description' => 'Première le même jour', 'status' => 'paye'],
            [...$timestamps, 'category' => 'autre', 'amount' => 200, 'spent_at' => '2026-09-10', 'type' => 'business', 'description' => 'La plus ancienne', 'status' => 'paye'],
            [...$timestamps, 'category' => 'autre', 'amount' => 300, 'spent_at' => '2026-09-22', 'type' => 'business', 'description' => 'La plus récente', 'status' => 'paye'],
            [...$timestamps, 'category' => 'autre', 'amount' => 400, 'spent_at' => '2026-09-20', 'type' => 'business', 'description' => 'Dernière le même jour', 'status' => 'paye'],
        ]);

        $this->actingAs($user)->get('/modules/depenses')->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.description', 'La plus récente')
            ->where('rows.1.description', 'Dernière le même jour')
            ->where('rows.2.description', 'Première le même jour')
            ->where('rows.3.description', 'La plus ancienne')
        );
    }

    public function test_settings_list_only_displays_internal_users_with_an_explicit_role(): void
    {
        $manager = User::factory()->create(['name' => 'Manager interne']);
        User::factory()->create([
            'name' => 'Client public',
            'role' => 'customer',
            'permissions' => [],
        ]);

        $this->actingAs($manager)->get('/modules/parametres')->assertInertia(fn (Assert $page) => $page
            ->where('config.columns.name', 'Utilisateur interne')
            ->where('config.columns.role', 'Rôle')
            ->where('rows', fn ($rows) => collect($rows)->contains('name', 'Manager interne')
                && ! collect($rows)->contains('name', 'Client public')
                && collect($rows)->every(fn ($row) => in_array($row['role'], ['super_admin', 'admin', 'assistant', 'user'], true)))
        );
    }

    public function test_madina_revenue_deducts_only_costs_directly_linked_to_invoiced_orders(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $clientId = DB::table('clients')->insertGetId([
            'number' => 'CLI-CA-001', 'name' => 'Client CA', 'contact' => '0340000000',
            'type' => 'entrepreneur', 'active' => true, 'credit_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'number' => 'CMD-CA-001', 'client_id' => $clientId, 'manager_id' => $user->id,
            'origin' => 'directe', 'ordered_at' => now()->toDateString(), 'supplier_total' => 11000000,
            'freight' => 2000000, 'commission_enabled' => false, 'commission_base' => 0,
            'commission_rate' => 8, 'commission_amount' => 0, 'margin' => 0,
            'client_total' => 20000000, 'deposit' => 0, 'balance_due' => 20000000,
            'status' => 'confirmee', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoices')->insert([
            'number' => 'FAC-CA-001', 'order_id' => $orderId, 'client_id' => $clientId,
            'type' => 'produits', 'status' => 'finale', 'issued_at' => now()->toDateString(),
            'subtotal' => 20000000, 'paid_amount' => 0, 'balance_due' => 20000000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('expenses')->insert([
            ['category' => 'autre', 'amount' => 1000000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Coût direct', 'order_id' => $orderId, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'salaire', 'amount' => 500000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Salaire', 'order_id' => null, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'marketing', 'amount' => 250000, 'spent_at' => now()->toDateString(), 'type' => 'business', 'description' => 'Marketing', 'order_id' => null, 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('metrics.invoiced', 6000000)
            ->where('metrics.profit', 5250000)
            ->where('chart.5.facture', 6000000)
        );
    }

    public function test_dashboard_can_be_filtered_to_last_month(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $user = User::factory()->create(['email_verified_at' => now()]);
        $clientId = DB::table('clients')->insertGetId([
            'number' => 'CLI-FILTRE-001', 'name' => 'Client filtre', 'contact' => '0340000000',
            'type' => 'particulier', 'active' => true, 'credit_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_payments')->insert([
            ['client_id' => $clientId, 'paid_at' => '2026-09-12', 'amount' => 900000, 'allocated_amount' => 0, 'method' => 'Espèces', 'type' => 'acompte', 'status' => 'valide', 'created_at' => now(), 'updated_at' => now()],
            ['client_id' => $clientId, 'paid_at' => '2026-10-12', 'amount' => 100000, 'allocated_amount' => 0, 'method' => 'Espèces', 'type' => 'acompte', 'status' => 'valide', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('expenses')->insert([
            ['category' => 'marketing', 'amount' => 250000, 'spent_at' => '2026-09-10', 'type' => 'business', 'description' => 'Septembre', 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'transport', 'amount' => 50000, 'spent_at' => '2026-10-10', 'type' => 'business', 'description' => 'Octobre', 'status' => 'paye', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->get('/dashboard?period=last_month')->assertInertia(fn (Assert $page) => $page
            ->where('periodFilter.value', 'last_month')
            ->where('periodFilter.label', 'Mois dernier')
            ->where('metrics.received', 900000)
            ->where('metrics.business', 250000)
            ->where('expenseCategories.0.name', 'Marketing')
            ->where('expenseCategories.0.value', 250000)
            ->where('chart.5.encaisse', 900000)
        );
    }
}
