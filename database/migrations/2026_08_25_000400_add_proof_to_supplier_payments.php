<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference');
            $table->text('proof_url')->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', fn(Blueprint $table)=>$table->dropColumn(['proof_path','proof_url']));
    }
};
