<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_contact');
            $table->string('source', 40);
            $table->text('description');
            $table->unsignedInteger('quantity');
            $table->decimal('budget', 18, 2)->nullable();
            $table->string('desired_deadline')->nullable();
            $table->string('shipping_mode', 30)->default('non_precise');
            $table->string('status', 40)->default('nouvelle_demande');
            $table->date('request_date');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
