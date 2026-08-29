<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $products = [
            'PRD-001' => ['slug' => 'camera-wifi-4mp', 'category' => 'Sécurité', 'short_description' => 'Caméra Wi-Fi 4MP pour une surveillance nette, de jour comme de nuit.'],
            'PRD-002' => ['slug' => 'lampe-solaire-300w', 'category' => 'Énergie solaire', 'short_description' => 'Éclairage solaire autonome et puissant pour vos espaces extérieurs.'],
            'PRD-003' => ['slug' => 'robinet-mitigeur-noir', 'category' => 'Sanitaire', 'short_description' => 'Mitigeur au fini noir mat pour un aménagement moderne et durable.'],
            'PRD-004' => ['slug' => 'etagere-metallique-5-niveaux', 'category' => 'Rangement', 'short_description' => 'Étagère métallique robuste à cinq niveaux pour organiser votre espace.'],
        ];

        foreach ($products as $reference => $fields) {
            DB::table('inventory_products')->where('reference', $reference)->update([
                ...$fields,
                'is_published' => true,
                'is_featured' => true,
                'show_price' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_products')->whereIn('reference', ['PRD-001', 'PRD-002', 'PRD-003', 'PRD-004'])->update([
            'is_published' => false,
            'is_featured' => false,
            'show_price' => false,
        ]);
    }
};
