<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->date('delivered_at');
            $table->string('delivery_address');
            $table->unsignedInteger('package_count')->default(1);
            $table->text('observations')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('a_livrer');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['order_id','delivered_at','status']);
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('ordered_quantity',14,3);
            $table->decimal('delivered_quantity',14,3);
            $table->timestamps();
        });

        DB::table('users')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions=json_decode($user->permissions,true)?:[];
            if(in_array('commandes',$permissions,true)&&!in_array('bons-livraison',$permissions,true)) {
                $permissions[]='bons-livraison';
                DB::table('users')->where('id',$user->id)->update(['permissions'=>json_encode(array_values($permissions))]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};
