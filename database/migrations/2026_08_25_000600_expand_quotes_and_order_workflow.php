<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('status',50)->default('brouillon')->change();
            $table->string('client_name')->nullable()->after('client_id');
            $table->string('shipping_mode')->nullable()->after('valid_until');
            $table->string('shipping_delay')->nullable()->after('shipping_mode');
            $table->text('bank_details')->nullable()->after('shipping_delay');
            $table->text('payment_terms')->nullable()->after('bank_details');
            $table->text('warranty')->nullable()->after('payment_terms');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('supplier_id');
            $table->string('supplier_contact')->nullable()->after('supplier_name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('supplier_id');
            $table->string('supplier_contact')->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn(Blueprint $table)=>$table->dropColumn(['supplier_name','supplier_contact']));
        Schema::table('quote_items', fn(Blueprint $table)=>$table->dropColumn(['supplier_name','supplier_contact']));
        Schema::table('quotes', fn(Blueprint $table)=>$table->dropColumn(['client_name','shipping_mode','shipping_delay','bank_details','payment_terms','warranty']));
    }
};
