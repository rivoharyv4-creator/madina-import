<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shipment_type')->default('order')->after('id');
            $table->foreignId('inventory_product_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->decimal('stock_quantity', 14, 3)->nullable()->after('inventory_product_id');
            $table->foreignId('order_id')->nullable()->change();
            $table->index(['shipment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['shipment_type', 'status']);
            $table->dropConstrainedForeignId('inventory_product_id');
            $table->dropColumn(['shipment_type', 'stock_quantity']);
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
