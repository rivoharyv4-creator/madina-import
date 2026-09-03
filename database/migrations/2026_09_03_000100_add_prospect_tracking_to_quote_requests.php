<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('sourcing_priority', 20)->default('normal')->after('status');
            $table->string('destination')->nullable()->after('sourcing_priority');
        });

        DB::table('quote_requests')->where('status', 'nouvelle_demande')->update(['status' => 'nouveau']);
        DB::table('quote_requests')->where('status', 'en_cours_analyse')->update(['status' => 'a_qualifier']);
        DB::table('quote_requests')->where('status', 'sans_suite')->update(['status' => 'perdu']);
    }

    public function down(): void
    {
        DB::table('quote_requests')->where('status', 'nouveau')->update(['status' => 'nouvelle_demande']);
        DB::table('quote_requests')->where('status', 'a_qualifier')->update(['status' => 'en_cours_analyse']);
        DB::table('quote_requests')->where('status', 'perdu')->update(['status' => 'sans_suite']);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['sourcing_priority', 'destination']);
        });
    }
};
