<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $original=['achat','logistique','marketing','transport','salaire','IRSA','autre'];
    private array $expanded=['achat','logistique','marketing','transport','loyer_depot_chine','loyer_depot_madagascar','loyer_bureau','services_publics','salaire','IRSA','autre'];

    public function up(): void
    {
        Schema::table('expenses',fn(Blueprint $table)=>$table->enum('category',$this->expanded)->change());
    }

    public function down(): void
    {
        Schema::table('expenses',fn(Blueprint $table)=>$table->enum('category',$this->original)->change());
    }
};
