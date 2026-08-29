<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->boolean('is_published')->default(false)->after('alert_threshold');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->boolean('show_price')->default(false)->after('is_featured');
            $table->string('category')->nullable()->after('show_price');
            $table->string('short_description', 320)->nullable()->after('category');
            $table->text('catalog_description')->nullable()->after('short_description');
            $table->json('gallery_paths')->nullable()->after('photo_path');
            $table->index(['is_published', 'is_featured']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('public_tracking_code', 64)->nullable()->unique()->after('number');
            $table->boolean('public_tracking_enabled')->default(false)->after('public_tracking_code');
        });

        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact');
            $table->enum('client_type', ['revendeur','entrepreneur','particulier','hotel','entreprise']);
            $table->string('need');
            $table->text('message');
            $table->boolean('consent')->default(false);
            $table->string('status', 30)->default('nouvelle');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['public_tracking_code','public_tracking_enabled']));
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropIndex(['is_published','is_featured']);
            $table->dropColumn(['slug','is_published','is_featured','show_price','category','short_description','catalog_description','gallery_paths']);
        });
    }
};
