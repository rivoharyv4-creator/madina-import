<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email');
            $table->json('permissions')->nullable()->after('role');
            $table->boolean('active')->default(true)->after('permissions');
        });

        DB::table('users')->update([
            'role' => 'super_admin',
            'permissions' => json_encode(array_keys(config('access.menus', []))),
            'active' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions', 'active']);
        });
    }
};
