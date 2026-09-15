<?php

use App\Services\MadinaRevenueCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_records', function (Blueprint $table) {
            $table->string('calculation_base', 50)->default('benefice_net')->change();
        });

        if (! Schema::hasTable('invoices') || ! Schema::hasTable('expenses')) {
            return;
        }

        $calculator = app(MadinaRevenueCalculator::class);

        DB::table('tax_records')->select('fiscal_year')->distinct()->pluck('fiscal_year')->each(
            function (int $year) use ($calculator): void {
                DB::table('tax_records')->where('fiscal_year', $year)->update([
                    'calculation_base' => 'benefice_net',
                    'base_amount' => $calculator->annualProfit($year),
                    'rate' => MadinaRevenueCalculator::ANNUAL_TAX_RATE,
                    'calculated_amount' => $calculator->annualTax($year),
                    'updated_at' => now(),
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table('tax_records', function (Blueprint $table) {
            $table->string('calculation_base', 50)->change();
        });
    }
};
