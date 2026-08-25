<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('left_at')->nullable()->after('active');
            $table->text('departure_reason')->nullable()->after('left_at');
            $table->softDeletes();
            $table->index(['active', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['active', 'left_at']);
            $table->dropColumn(['left_at', 'departure_reason', 'deleted_at']);
        });
    }
};
