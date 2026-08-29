<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('container_reference')->nullable()->after('forwarder');
            $table->unsignedInteger('package_count')->nullable()->after('cbm');
            $table->unsignedInteger('carton_count')->nullable()->after('package_count');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['container_reference', 'package_count', 'carton_count']);
        });
    }
};
