<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id(); $t->string('type', 30); $t->unsignedSmallInteger('year'); $t->unsignedInteger('last_number')->default(0);
            $t->unique(['type', 'year']); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->string('name'); $t->string('contact');
            $t->enum('type', ['revendeur','entrepreneur','particulier','hotel']); $t->string('address')->nullable();
            $t->text('notes')->nullable(); $t->boolean('active')->default(true); $t->decimal('credit_balance', 18, 2)->default(0);
            $t->timestamps(); $t->softDeletes(); $t->index(['name','active']);
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('category')->nullable(); $t->unsignedInteger('moq')->nullable();
            $t->unsignedInteger('production_days')->nullable(); $t->string('contact')->nullable(); $t->unsignedTinyInteger('quality_rating')->default(3);
            $t->text('notes')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('supplier_products', function (Blueprint $t) {
            $t->id(); $t->foreignId('supplier_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->text('specifications')->nullable();
            $t->decimal('price',18,2)->default(0); $t->decimal('local_delivery',18,2)->default(0); $t->decimal('packaging',18,2)->default(0);
            $t->decimal('cbm',12,4)->nullable(); $t->decimal('freight',18,2)->default(0); $t->decimal('margin',18,2)->default(0);
            $t->string('contact')->nullable(); $t->string('photo_path')->nullable(); $t->text('source_url')->nullable(); $t->timestamps();
        });
        Schema::create('quotes', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->foreignId('client_id')->constrained(); $t->string('contact'); $t->string('client_type');
            $t->date('sent_at')->nullable(); $t->date('valid_until'); $t->enum('status',['brouillon','envoye','negociation','accepte','refuse','sans_reponse','expire','transforme'])->default('brouillon');
            $t->decimal('supplier_estimate',18,2)->default(0); $t->decimal('logistics_estimate',18,2)->default(0); $t->decimal('margin',18,2)->default(0);
            $t->decimal('total',18,2)->default(0); $t->string('currency',3)->default('MGA'); $t->text('notes')->nullable(); $t->timestamps(); $t->softDeletes(); $t->index(['client_id','status','valid_until']);
        });
        Schema::create('quote_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('quote_id')->constrained()->cascadeOnDelete(); $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name'); $t->text('specifications')->nullable(); $t->decimal('quantity',14,3); $t->text('source_url')->nullable(); $t->string('photo_path')->nullable();
            $t->decimal('supplier_price',18,2)->default(0); $t->decimal('china_delivery',18,2)->default(0); $t->decimal('packaging',18,2)->default(0);
            $t->decimal('estimated_weight',12,3)->nullable(); $t->decimal('estimated_cbm',12,4)->nullable(); $t->decimal('estimated_freight',18,2)->default(0);
            $t->decimal('margin',18,2)->default(0); $t->decimal('commission',18,2)->default(0); $t->decimal('total',18,2)->default(0); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->foreignId('client_id')->constrained(); $t->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('manager_id')->constrained('users'); $t->enum('origin',['devis','directe'])->default('directe'); $t->date('ordered_at');
            $t->string('shipping_mode')->nullable(); $t->decimal('cbm',12,4)->nullable(); $t->decimal('freight',18,2)->default(0); $t->decimal('supplier_total',18,2)->default(0);
            $t->boolean('commission_enabled')->default(false); $t->decimal('commission_base',18,2)->default(0); $t->decimal('commission_rate',7,3)->default(8);
            $t->decimal('commission_amount',18,2)->default(0); $t->decimal('margin',18,2)->default(0); $t->decimal('client_total',18,2)->default(0);
            $t->decimal('deposit',18,2)->default(0); $t->decimal('balance_due',18,2)->default(0); $t->string('status')->default('brouillon'); $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes(); $t->index(['client_id','status','ordered_at']);
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name'); $t->text('specifications')->nullable(); $t->decimal('quantity',14,3); $t->text('source_url')->nullable(); $t->decimal('supplier_price',18,2)->default(0);
            $t->decimal('china_delivery',18,2)->default(0); $t->decimal('packaging',18,2)->default(0); $t->decimal('weight',12,3)->nullable(); $t->decimal('cbm',12,4)->nullable();
            $t->decimal('freight',18,2)->default(0); $t->decimal('margin',18,2)->default(0); $t->decimal('commission',18,2)->default(0); $t->decimal('client_total',18,2)->default(0);
            $t->string('status')->default('brouillon'); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->foreignId('order_id')->constrained(); $t->foreignId('client_id')->constrained(); $t->enum('type',['produits','frais']);
            $t->enum('status',['brouillon','provisoire','finale','payee','partielle','annulee'])->default('brouillon'); $t->date('issued_at');
            $t->decimal('subtotal',18,2)->default(0); $t->decimal('paid_amount',18,2)->default(0); $t->decimal('balance_due',18,2)->default(0); $t->json('lines')->nullable();
            $t->timestamps(); $t->softDeletes(); $t->index(['client_id','status','issued_at']);
        });
        Schema::create('client_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id')->constrained(); $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->date('paid_at'); $t->decimal('amount',18,2); $t->decimal('allocated_amount',18,2)->default(0); $t->string('method'); $t->string('reference')->nullable();
            $t->enum('type',['acompte_commande','solde_commande','fournisseur_chine','fret_transport','frais_service','autre']); $t->string('status')->default('valide'); $t->text('notes')->nullable(); $t->timestamps(); $t->index(['client_id','paid_at','status']);
        });
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('supplier_id')->constrained(); $t->date('paid_at'); $t->decimal('amount',18,2); $t->enum('method',['WeChat','Alipay','banque']);
            $t->string('reference')->nullable(); $t->enum('status',['paye','partiel','en_attente']); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('supplier_payment_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete(); $t->foreignId('order_id')->constrained(); $t->decimal('amount',18,2); $t->unique(['supplier_payment_id','order_id']);
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->string('tracking')->nullable(); $t->date('supplier_sent_at')->nullable(); $t->date('china_warehouse_at')->nullable();
            $t->decimal('weight',12,3)->nullable(); $t->decimal('cbm',12,4)->nullable(); $t->decimal('cost',18,2)->default(0); $t->enum('mode',['aerien','maritime']);
            $t->string('forwarder')->nullable(); $t->date('china_departure_at')->nullable(); $t->date('expected_madagascar_at')->nullable(); $t->date('arrived_madagascar_at')->nullable();
            $t->date('delivered_at')->nullable(); $t->string('status')->default('en_attente'); $t->timestamps(); $t->index(['order_id','status']);
        });
        Schema::create('inventory_products', function (Blueprint $t) {
            $t->id(); $t->string('reference')->unique(); $t->string('name'); $t->decimal('quantity',14,3)->default(0); $t->decimal('purchase_price',18,2)->default(0);
            $t->decimal('sale_price',18,2)->default(0); $t->decimal('stock_value',18,2)->default(0); $t->date('entered_at')->nullable(); $t->date('exited_at')->nullable();
            $t->decimal('alert_threshold',14,3)->nullable(); $t->timestamps(); $t->softDeletes(); $t->index(['name','quantity']);
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_product_id')->constrained()->cascadeOnDelete(); $t->enum('type',['entree','sortie','inventaire']); $t->decimal('quantity',14,3);
            $t->decimal('before_quantity',14,3); $t->decimal('after_quantity',14,3); $t->text('notes')->nullable(); $t->timestamp('moved_at'); $t->foreignId('user_id')->constrained();
        });
        Schema::create('monthly_inventories', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_product_id')->constrained(); $t->date('month'); $t->decimal('recorded_quantity',14,3); $t->decimal('counted_quantity',14,3);
            $t->decimal('difference',14,3); $t->decimal('validated_quantity',14,3)->nullable(); $t->date('validated_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
            $t->unique(['inventory_product_id','month']);
        });
        Schema::create('local_sales', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_product_id')->constrained(); $t->date('sold_at'); $t->decimal('quantity',14,3); $t->decimal('unit_price',18,2);
            $t->decimal('total',18,2); $t->decimal('paid_amount',18,2)->default(0); $t->decimal('balance_due',18,2)->default(0); $t->string('payment_method')->nullable();
            $t->enum('status',['paye','partiel','credit']); $t->string('buyer_name')->nullable(); $t->string('buyer_contact')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id(); $t->enum('category',['achat','logistique','marketing','transport','salaire','IRSA','autre']); $t->decimal('amount',18,2); $t->date('spent_at');
            $t->enum('type',['business','personnel']); $t->text('description'); $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $t->string('status')->default('paye');
            $t->string('source_type')->nullable(); $t->unsignedBigInteger('source_id')->nullable(); $t->timestamps(); $t->unique(['source_type','source_id']); $t->index(['spent_at','category','type']);
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('position'); $t->decimal('monthly_salary',18,2); $t->enum('irsa_mode',['pourcentage','fixe']);
            $t->decimal('irsa_value',18,3)->default(0); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('salaries', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained(); $t->date('month'); $t->decimal('gross_salary',18,2); $t->enum('irsa_mode',['pourcentage','fixe']);
            $t->decimal('irsa_rate',7,3)->nullable(); $t->decimal('irsa_amount',18,2); $t->decimal('net_salary',18,2); $t->date('paid_at')->nullable(); $t->enum('status',['a_payer','paye']); $t->timestamps();
            $t->unique(['employee_id','month']);
        });
        Schema::create('tax_records', function (Blueprint $t) {
            $t->id(); $t->enum('type',['IRSA','impot_synthetique']); $t->string('period'); $t->unsignedSmallInteger('fiscal_year'); $t->enum('calculation_base',['ca_facture','ca_encaisse','salaires_bruts']);
            $t->decimal('base_amount',18,2); $t->decimal('rate',7,3)->default(0); $t->decimal('calculated_amount',18,2); $t->decimal('declared_amount',18,2)->nullable();
            $t->date('due_at')->nullable(); $t->date('declared_at')->nullable(); $t->date('paid_at')->nullable(); $t->string('status')->default('estimation'); $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id(); $t->nullableMorphs('documentable'); $t->string('name'); $t->string('disk')->default('private'); $t->string('path')->nullable(); $t->text('external_url')->nullable();
            $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size')->nullable(); $t->foreignId('uploaded_by')->constrained('users'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('event'); $t->nullableMorphs('auditable');
            $t->json('old_values')->nullable(); $t->json('new_values')->nullable(); $t->string('ip_address',45)->nullable(); $t->timestamp('created_at')->useCurrent(); $t->index(['event','created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs','documents','tax_records','salaries','employees','expenses','local_sales','monthly_inventories','stock_movements','inventory_products','shipments','supplier_payment_allocations','supplier_payments','client_payments','invoices','order_items','orders','quote_items','quotes','supplier_products','suppliers','clients','number_sequences'] as $table) Schema::dropIfExists($table);
    }
};
