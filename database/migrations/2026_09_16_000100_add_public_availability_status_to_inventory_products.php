<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('public_availability_status', 30)->default('available_now')->after('is_published');
        });

        DB::table('inventory_products')
            ->where('quantity', '<=', 0)
            ->update(['public_availability_status' => 'out_of_stock']);
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn('public_availability_status');
        });
    }
};
