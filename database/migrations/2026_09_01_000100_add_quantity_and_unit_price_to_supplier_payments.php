<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->default(1)->after('paid_at');
            $table->decimal('unit_price', 18, 2)->default(0)->after('quantity');
        });

        DB::table('supplier_payments')->orderBy('id')->chunkById(100, function ($payments) {
            foreach ($payments as $payment) {
                DB::table('supplier_payments')->where('id', $payment->id)->update([
                    'quantity' => 1,
                    'unit_price' => $payment->amount,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
