<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->decimal('web_purchase_price', 18, 2)->nullable());
        DB::table('order_items')->whereIn('order_id', DB::table('orders')->whereNotNull('user_id')->select('id'))
            ->orderBy('id')->chunkById(200, function ($items) {
                $prices = DB::table('inventory_products')->whereIn('id', $items->pluck('inventory_product_id'))->pluck('purchase_price', 'id');
                foreach ($items as $item) {
                    DB::table('order_items')->where('id', $item->id)->update(['web_purchase_price' => $prices[$item->inventory_product_id] ?? $item->supplier_price]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('web_purchase_price'));
    }
};
