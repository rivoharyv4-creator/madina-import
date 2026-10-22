<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE invoices MODIFY type ENUM('produits','frais','achat_stock') NOT NULL");
        }

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('destination_type', 20)->default('order')->after('supplier_id');
        });

        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
        });

        Schema::create('supplier_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('china_delivery', 18, 4)->default(0);
            $table->decimal('exchange_rate', 18, 4);
            $table->decimal('total_amount', 18, 2);
            $table->timestamps();
        });

        DB::table('supplier_payments')->orderBy('id')->chunkById(100, function ($payments) {
            foreach ($payments as $payment) {
                DB::table('supplier_payment_items')->insert([
                    'supplier_payment_id' => $payment->id,
                    'product_name' => 'Achat fournisseur',
                    'quantity' => $payment->quantity,
                    'unit_price' => $payment->unit_price,
                    'china_delivery' => 0,
                    'exchange_rate' => 1,
                    'total_amount' => $payment->amount,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_items');

        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropColumn('destination_type');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE invoices MODIFY type ENUM('produits','frais') NOT NULL");
        }
    }
};
