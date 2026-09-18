<?php

namespace Tests\Feature;

use Database\Seeders\TestCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_can_be_restored_without_overwriting_existing_products(): void
    {
        Storage::fake('persistent');
        $userCount = DB::table('users')->count();
        $this->artisan('madina:test-catalogue')->assertExitCode(0);
        $this->assertSame(3, DB::table('inventory_products')->count());
        $this->assertSame(['available_now', 'on_order', 'out_of_stock'], DB::table('inventory_products')->orderBy('public_availability_status')->pluck('public_availability_status')->all());
        foreach (DB::table('inventory_products')->get() as $product) {
            Storage::disk('persistent')->assertExists($product->photo_path);
        }
        DB::table('inventory_products')->where('reference', 'PRD-001')->update(['sale_price' => 123456, 'quantity' => 7]);
        $this->seed(TestCatalogueSeeder::class);
        $this->assertSame(3, DB::table('inventory_products')->count());
        $this->assertDatabaseHas('inventory_products', ['reference' => 'PRD-001', 'sale_price' => 123456, 'quantity' => 7]);
        $this->assertDatabaseCount('users', $userCount);
    }
}
