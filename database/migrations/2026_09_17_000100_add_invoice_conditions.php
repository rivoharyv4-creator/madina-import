<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('shipping_delay')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('warranty')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['shipping_delay', 'bank_details', 'payment_terms', 'warranty', 'notes']));
    }
};
