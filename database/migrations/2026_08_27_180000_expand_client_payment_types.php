<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->enum('type', [
                'acompte',
                'intermediaire',
                'solde',
                'remboursement',
                'acompte_commande',
                'solde_commande',
                'fournisseur_chine',
                'fret_transport',
                'frais_service',
                'autre',
            ])->change();
        });
    }

    public function down(): void
    {
        DB::table('client_payments')->where('type', 'acompte_commande')->update(['type' => 'acompte']);
        DB::table('client_payments')->where('type', 'solde_commande')->update(['type' => 'solde']);
        DB::table('client_payments')->whereIn('type', ['fournisseur_chine', 'fret_transport', 'frais_service', 'autre'])->update(['type' => 'intermediaire']);

        Schema::table('client_payments', function (Blueprint $table) {
            $table->enum('type', ['acompte', 'intermediaire', 'solde', 'remboursement'])->change();
        });
    }
};
