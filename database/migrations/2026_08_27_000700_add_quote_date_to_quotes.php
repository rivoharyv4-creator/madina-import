<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('quote_date')->nullable()->after('contact');
        });

        DB::table('quotes')->whereNull('quote_date')->update(['quote_date'=>DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('quotes', fn(Blueprint $table)=>$table->dropColumn('quote_date'));
    }
};
