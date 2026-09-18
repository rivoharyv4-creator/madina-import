<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TestCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $root = database_path('fixtures/catalogue');
        $products = json_decode(file_get_contents($root.'/products.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($products as $product) {
            if (DB::table('inventory_products')->where('reference', $product['reference'])->exists()) {
                continue;
            }
            $path = $product['photo_path'];
            if (! Storage::disk('persistent')->exists($path)) {
                Storage::disk('persistent')->put($path, file_get_contents($root.'/'.$path));
            }
            DB::table('inventory_products')->insert([...$product, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
