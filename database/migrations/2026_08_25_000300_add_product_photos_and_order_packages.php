<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('source_url');
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('name');
        });

        Schema::create('order_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->enum('billing_unit', ['kg', 'cbm']);
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->decimal('volume_cbm', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'reference']);
        });

        Schema::create('order_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
            $table->unique(['order_package_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_package_items');
        Schema::dropIfExists('order_packages');

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
