<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->decimal('reserved_quantity',14,3)->default(0)->after('quantity');
            $table->decimal('available_quantity',14,3)->default(0)->after('reserved_quantity');
            $table->decimal('cbm',12,4)->nullable()->after('purchase_price');
            $table->decimal('freight',18,2)->default(0)->after('cbm');
            $table->decimal('total_purchase_cost',18,2)->default(0)->after('freight');
            $table->decimal('sale_total',18,2)->default(0)->after('sale_price');
        });

        DB::table('inventory_products')->orderBy('id')->each(function($product): void {
            DB::table('inventory_products')->where('id',$product->id)->update([
                'available_quantity'=>$product->quantity,
                'total_purchase_cost'=>(float)$product->purchase_price*(float)$product->quantity,
                'sale_total'=>(float)$product->sale_price*(float)$product->quantity,
            ]);
        });

        Schema::create('inventory_product_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['inventory_product_id','order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_origins');
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn(['reserved_quantity','available_quantity','cbm','freight','total_purchase_cost','sale_total']);
        });
    }
};
