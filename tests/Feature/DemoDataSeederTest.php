<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_is_complete_and_relationally_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(24, DB::table('clients')->count());
        $this->assertSame(23, DB::table('suppliers')->count());
        $this->assertSame(21, DB::table('quotes')->count());
        $this->assertSame(23, DB::table('orders')->count());
        $this->assertSame(23, DB::table('invoices')->count());
        $this->assertSame(22, DB::table('client_payments')->count());
        $this->assertSame(20, DB::table('supplier_payments')->where('reference', 'like', 'ACH-DEMO-%')->count());
        $this->assertSame(20, DB::table('shipments')->where('tracking', 'like', 'TRACK-DEMO-%')->count());
        $this->assertSame(24, DB::table('inventory_products')->count());
        $this->assertSame(21, DB::table('local_sales')->count());
        $this->assertSame(20, DB::table('expenses')->where('source_type', 'demo')->count());
        $this->assertSame(21, DB::table('employees')->count());
        $this->assertSame(21, DB::table('salaries')->count());
        $this->assertSame(21, DB::table('tax_records')->count());
        $this->assertSame(20, DB::table('audit_logs')->where('event', 'demo.donnee_creee')->count());
        $this->assertSame(250000.0, (float) DB::table('clients')->where('number', 'CLI-2026-002')->value('credit_balance'));
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $this->actingAs($manager)->get('/dashboard')->assertOk();
        $this->actingAs($manager)->get('/modules/clients')->assertInertia(fn(Assert $page)=>$page
            ->where('pagination.total',24)
            ->where('pagination.per_page',20)
            ->where('pagination.last_page',2)
            ->has('rows',20)
        );
        $this->actingAs($manager)->get('/modules/clients?q=Client%20D%C3%A9mo%20005')->assertInertia(fn(Assert $page)=>$page
            ->where('pagination.total',1)
            ->has('rows',1)
            ->where('rows.0.name','Client Démo 005')
        );
        $this->actingAs($manager)->get('/modules/devis?filter_status=accepte')->assertInertia(fn(Assert $page)=>$page
            ->where('activeFilters.status','accepte')
            ->where('pagination.total',6)
            ->has('rows',6)
            ->has('filterOptions',1)
        );
    }
}
