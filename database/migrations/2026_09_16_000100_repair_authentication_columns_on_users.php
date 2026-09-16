<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'password_recovery_phrase')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password_recovery_phrase')->nullable()->after('password');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('two_factor_secret')->nullable()->after('remember_token');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_last_used_step')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_confirmed_at');
            });
        }
    }

    public function down(): void
    {
        // This migration repairs production schema drift and must not remove existing data.
    }
};
