<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('phone', 30)->nullable());
        Schema::create('email_verification_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('last_sent_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('method');
            $t->string('display_name');
            $t->string('account_holder');
            $t->string('account_number');
            $t->text('additional_instructions')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('proof_required')->default(false);
            $t->integer('sort_order')->default(0);
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('checkout_key')->nullable()->unique();
            $t->string('payment_status')->nullable();
            $t->string('currency', 3)->default('MGA');
            $t->json('delivery_snapshot')->nullable();
            $t->timestamp('payment_confirmed_at')->nullable();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->string('sku')->nullable();
            $t->json('options')->nullable();
            $t->decimal('unit_price', 18, 2)->nullable();
            $t->boolean('stock_managed')->nullable();
        });
        Schema::create('payment_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('payment_account_id')->constrained();
            $t->uuid('submission_key')->unique();
            $t->json('account_snapshot');
            $t->string('transaction_reference', 120);
            $t->string('proof_path')->nullable();
            $t->string('status')->default('under_review');
            $t->timestamp('submitted_at');
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['sku', 'options', 'unit_price', 'stock_managed']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['checkout_key', 'payment_status', 'currency', 'delivery_snapshot', 'payment_confirmed_at']);
        });
        Schema::dropIfExists('payment_accounts');
        Schema::table('clients', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('email_verification_codes');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
    }
};
