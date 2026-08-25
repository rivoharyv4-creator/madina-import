<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_is_complete_and_relationally_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, DB::table('clients')->count());
        $this->assertSame(3, DB::table('suppliers')->count());
        $this->assertSame(3, DB::table('orders')->count());
        $this->assertSame(3, DB::table('invoices')->count());
        $this->assertSame(4, DB::table('inventory_products')->count());
        $this->assertSame(7250000.0, (float) DB::table('client_payments')->sum('amount'));
        $this->assertSame(1785000.0, (float) DB::table('expenses')->sum('amount'));
        $this->assertSame(250000.0, (float) DB::table('clients')->where('number', 'CLI-2026-002')->value('credit_balance'));
        $this->actingAs(User::where('email','manager@madina-import.mg')->firstOrFail())->get('/dashboard')->assertOk();
    }
}
