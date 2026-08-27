<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->manager = User::where('email', 'manager@madina-import.mg')->firstOrFail();
    }

    public function test_manager_can_open_every_available_creation_form(): void
    {
        foreach (['clients','devis','commandes','paiements','factures','fournisseurs','achats','logistique','stock','ventes','depenses','employes','salaires','fiscalite'] as $module) {
            $this->actingAs($this->manager)->get("/modules/$module/create")->assertOk();
        }
    }

    public function test_manager_can_create_and_edit_an_employee_before_preparing_salary(): void
    {
        $this->actingAs($this->manager)->post('/modules/employes',['name'=>'Faneva Andria','position'=>'Agent logistique','monthly_salary'=>950000,'irsa_mode'=>'pourcentage','irsa_value'=>5,'active'=>1])->assertRedirect('/modules/employes');
        $employee=DB::table('employees')->where('name','Faneva Andria')->first(); $this->assertNotNull($employee);
        $this->actingAs($this->manager)->put("/modules/employes/{$employee->id}",['name'=>'Faneva Andria','position'=>'Responsable logistique','monthly_salary'=>1050000,'irsa_mode'=>'fixe','irsa_value'=>50000,'active'=>1])->assertRedirect('/modules/employes');
        $this->assertDatabaseHas('employees',['id'=>$employee->id,'position'=>'Responsable logistique','monthly_salary'=>1050000,'irsa_mode'=>'fixe']);
        $this->actingAs($this->manager)->get('/modules/salaires/create')->assertOk();
    }

    public function test_employee_departure_is_soft_deleted_and_salary_history_is_preserved(): void
    {
        $employee=DB::table('employees')->where('name','Mialy Rasoanaivo')->first();
        $salaryCount=DB::table('salaries')->where('employee_id',$employee->id)->count();
        $this->actingAs($this->manager)->delete("/modules/employes/{$employee->id}",['left_at'=>'2026-08-25','departure_reason'=>'Fin de contrat'])->assertRedirect('/modules/employes');
        $departed=DB::table('employees')->find($employee->id);
        $this->assertSame(0,(int)$departed->active); $this->assertSame('2026-08-25',$departed->left_at); $this->assertSame('Fin de contrat',$departed->departure_reason); $this->assertNotNull($departed->deleted_at);
        $this->assertSame($salaryCount,DB::table('salaries')->where('employee_id',$employee->id)->count());
        $this->assertDatabaseHas('audit_logs',['auditable_id'=>$employee->id,'event'=>'employes.depart']);
    }

    public function test_manager_can_create_client_product_and_direct_order(): void
    {
        $this->actingAs($this->manager)->post('/modules/clients', [
            'name'=>'Épicerie Soa','contact'=>'+261 34 00 111 22','type'=>'revendeur','address'=>'Mahajanga','notes'=>'Nouveau prospect','active'=>1,
        ])->assertRedirect('/modules/clients');
        $client=DB::table('clients')->where('number','CLI-2026-005')->first();
        $this->assertSame('Épicerie Soa',$client->name);

        $this->actingAs($this->manager)->post('/modules/stock', [
            'name'=>'Ventilateur rechargeable','quantity'=>10,'purchase_price'=>85000,'sale_price'=>145000,'alert_threshold'=>3,
        ])->assertRedirect('/modules/stock');
        $this->assertDatabaseHas('inventory_products',['reference'=>'PRD-005','stock_value'=>850000]);

        $supplier=DB::table('suppliers')->first();
        $this->actingAs($this->manager)->post('/modules/commandes', [
            'client_id'=>$client->id,'commission_enabled'=>1,'commission_rate'=>8,'deposit'=>1000000,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'confirmee','notes'=>'Test création directe','items'=>[
                ['name'=>'Présentoir métallique','specifications'=>'Noir, quatre niveaux','quantity'=>12,'supplier_id'=>$supplier->id,'source_url'=>'https://detail.1688.com/test-display','supplier_price'=>150000,'china_delivery'=>50000,'packaging'=>40000,'weight'=>120,'cbm'=>1.8,'freight'=>450000,'margin'=>600000,'commission'=>144000,'client_total'=>2994000],
                ['name'=>'Étiquettes de prix','specifications'=>'Lot de 500','quantity'=>2,'supplier_id'=>$supplier->id,'source_url'=>null,'supplier_price'=>100000,'china_delivery'=>0,'packaging'=>0,'weight'=>5,'cbm'=>0.05,'freight'=>50000,'margin'=>150000,'commission'=>0,'client_total'=>500000],
            ],
        ])->assertRedirect('/modules/commandes');
        $order=DB::table('orders')->where('number','MI-2026-004')->first();
        $this->assertSame(2494000.0,(float)$order->balance_due);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'name'=>'Présentoir métallique']);
        $this->assertSame(2,DB::table('order_items')->where('order_id',$order->id)->count());
    }

    public function test_quote_can_contain_multiple_products_and_totals_are_aggregated(): void
    {
        $client=DB::table('clients')->first(); $suppliers=DB::table('suppliers')->limit(2)->pluck('id')->all();
        $this->actingAs($this->manager)->post('/modules/devis',['client_id'=>$client->id,'valid_until'=>'2026-09-30','status'=>'brouillon','items'=>[
            ['name'=>'Table restaurant','specifications'=>'Bois clair','quantity'=>5,'supplier_id'=>$suppliers[0],'source_url'=>null,'supplier_price'=>200000,'china_delivery'=>50000,'packaging'=>25000,'weight'=>100,'cbm'=>1.2,'freight'=>300000,'margin'=>400000,'commission'=>100000,'total'=>1875000],
            ['name'=>'Suspension LED','specifications'=>'Noir mat','quantity'=>10,'supplier_id'=>$suppliers[1],'source_url'=>null,'supplier_price'=>50000,'china_delivery'=>20000,'packaging'=>10000,'weight'=>15,'cbm'=>0.2,'freight'=>80000,'margin'=>200000,'commission'=>50000,'total'=>860000],
        ]])->assertRedirect('/modules/devis');
        $quote=DB::table('quotes')->where('number','DV-MI-2026-002')->first();
        $this->assertSame(2,DB::table('quote_items')->where('quote_id',$quote->id)->count());
        $this->assertSame(2735000.0,(float)$quote->total);
        $this->assertSame(1500000.0,(float)$quote->supplier_estimate);
    }

    public function test_manager_can_edit_a_client_and_change_its_status(): void
    {
        $client=DB::table('clients')->where('number','CLI-2026-001')->first();
        $this->actingAs($this->manager)->get("/modules/clients/{$client->id}/edit")->assertOk();
        $this->actingAs($this->manager)->put("/modules/clients/{$client->id}",[
            'name'=>'Hôtel Baobab Antananarivo - Rénové','contact'=>'+261 34 12 345 67','type'=>'hotel','address'=>'Ivandry, Antananarivo','notes'=>'Fiche mise à jour','active'=>0,
        ])->assertRedirect('/modules/clients');
        $this->assertDatabaseHas('clients',['id'=>$client->id,'name'=>'Hôtel Baobab Antananarivo - Rénové','active'=>0]);
        $this->assertDatabaseHas('audit_logs',['auditable_id'=>$client->id,'event'=>'clients.modifie']);
    }

    public function test_delete_action_is_not_available(): void
    {
        $client=DB::table('clients')->first();
        $this->actingAs($this->manager)->delete("/modules/clients/{$client->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('clients',['id'=>$client->id,'deleted_at'=>null]);
    }

    public function test_manager_can_open_edit_form_for_every_correctable_table(): void
    {
        $records=['clients'=>'clients','devis'=>'quotes','commandes'=>'orders','paiements'=>'client_payments','factures'=>'invoices','fournisseurs'=>'suppliers','achats'=>'supplier_payments','logistique'=>'shipments','stock'=>'inventory_products','ventes'=>'local_sales','depenses'=>'expenses','employes'=>'employees','salaires'=>'salaries','fiscalite'=>'tax_records'];
        foreach($records as $module=>$table) { $id=DB::table($table)->value('id'); if($id) $this->actingAs($this->manager)->get("/modules/$module/$id/edit")->assertOk(); }
    }

    public function test_manager_can_correct_order_stock_and_payment_with_recalculation(): void
    {
        $order=DB::table('orders')->where('number','MI-2026-002')->first(); $item=DB::table('order_items')->where('order_id',$order->id)->first();
        $this->actingAs($this->manager)->put("/modules/commandes/{$order->id}",['client_id'=>$order->client_id,'commission_enabled'=>1,'commission_rate'=>8,'deposit'=>3000000,'ordered_at'=>$order->ordered_at,'shipping_mode'=>'aerien','status'=>'achat_en_cours','notes'=>'Total corrigé','items'=>[['name'=>$item->name,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'source_url'=>$item->source_url,'supplier_price'=>112000,'china_delivery'=>$item->china_delivery,'packaging'=>$item->packaging,'weight'=>$item->weight,'cbm'=>$item->cbm,'freight'=>$item->freight,'margin'=>$item->margin,'commission'=>$item->commission,'client_total'=>6800000]]])->assertRedirect('/modules/commandes');
        $this->assertSame(3800000.0,(float)DB::table('orders')->where('id',$order->id)->value('balance_due'));

        $product=DB::table('inventory_products')->where('reference','PRD-002')->first();
        $this->actingAs($this->manager)->put("/modules/stock/{$product->id}",['name'=>$product->name,'quantity'=>6,'purchase_price'=>78000,'sale_price'=>135000,'alert_threshold'=>5])->assertRedirect('/modules/stock');
        $this->assertDatabaseHas('inventory_products',['id'=>$product->id,'quantity'=>6,'stock_value'=>468000]);
        $this->assertDatabaseHas('stock_movements',['inventory_product_id'=>$product->id,'type'=>'inventaire','after_quantity'=>6]);

        $payment=DB::table('client_payments')->where('reference','MVOLA-829104')->first();
        $this->actingAs($this->manager)->put("/modules/paiements/{$payment->id}",['client_id'=>$payment->client_id,'order_id'=>$payment->order_id,'invoice_id'=>$payment->invoice_id,'paid_at'=>$payment->paid_at,'amount'=>4000000,'allocated_amount'=>3800000,'method'=>$payment->method,'reference'=>$payment->reference,'type'=>$payment->type,'notes'=>'Affectation corrigée'])->assertRedirect('/modules/paiements');
        $this->assertSame(200000.0,(float)DB::table('clients')->where('id',$payment->client_id)->value('credit_balance'));
        $this->assertDatabaseHas('audit_logs',['auditable_id'=>$payment->id,'event'=>'paiements.modifie']);
    }

    public function test_payment_updates_credit_order_and_invoice(): void
    {
        $order=DB::table('orders')->where('number','MI-2026-002')->first();
        $invoice=DB::table('invoices')->where('number','FA-MI-2026-002')->first();
        $beforeCredit=(float)DB::table('clients')->where('id',$order->client_id)->value('credit_balance');
        $this->actingAs($this->manager)->post('/modules/paiements',[
            'client_id'=>$order->client_id,'order_id'=>$order->id,'invoice_id'=>$invoice->id,'paid_at'=>'2026-08-25','amount'=>1200000,
            'allocated_amount'=>1000000,'method'=>'Mobile Money','reference'=>'TEST-PAY-001','type'=>'intermediaire','notes'=>'Paiement test',
        ])->assertRedirect('/modules/paiements');
        $this->assertSame($beforeCredit+200000,(float)DB::table('clients')->where('id',$order->client_id)->value('credit_balance'));
        $this->assertSame(2720000.0,(float)DB::table('orders')->where('id',$order->id)->value('balance_due'));
        $this->assertSame(2720000.0,(float)DB::table('invoices')->where('id',$invoice->id)->value('balance_due'));
    }

    public function test_local_sale_decrements_stock_and_records_movement(): void
    {
        $product=DB::table('inventory_products')->where('reference','PRD-001')->first();
        $this->actingAs($this->manager)->post('/modules/ventes',[
            'inventory_product_id'=>$product->id,'sold_at'=>'2026-08-25','quantity'=>3,'unit_price'=>189000,'paid_amount'=>400000,
            'payment_method'=>'Mobile Money','buyer_name'=>'Entreprise Kanto','buyer_contact'=>'+261 33 44 555 66','notes'=>'Solde à recevoir',
        ])->assertRedirect('/modules/ventes');
        $this->assertSame(15.0,(float)DB::table('inventory_products')->where('id',$product->id)->value('quantity'));
        $this->assertDatabaseHas('local_sales',['inventory_product_id'=>$product->id,'total'=>567000,'balance_due'=>167000,'status'=>'partiel']);
        $this->assertDatabaseHas('stock_movements',['inventory_product_id'=>$product->id,'type'=>'sortie','after_quantity'=>15]);
    }

    public function test_products_accept_photos_and_an_order_can_be_split_into_measured_packages(): void
    {
        Storage::fake('persistent');
        $photo=UploadedFile::fake()->image('article.webp',600,600);
        $this->actingAs($this->manager)->post('/modules/stock',['name'=>'Article photographié','photo'=>$photo,'quantity'=>4,'purchase_price'=>50000,'sale_price'=>85000,'alert_threshold'=>1])->assertRedirect('/modules/stock');
        $photoPath=DB::table('inventory_products')->where('name','Article photographié')->value('photo_path');
        $this->assertNotNull($photoPath); Storage::disk('persistent')->assertExists($photoPath);
        $stockId=DB::table('inventory_products')->where('name','Article photographié')->value('id');
        $this->actingAs($this->manager)->post("/modules/stock/{$stockId}",['_method'=>'put','name'=>'Article photographié corrigé','quantity'=>4,'purchase_price'=>50000,'sale_price'=>90000,'alert_threshold'=>1])->assertRedirect('/modules/stock');
        $this->assertSame($photoPath,DB::table('inventory_products')->where('id',$stockId)->value('photo_path'));

        $client=DB::table('clients')->first(); $supplier=DB::table('suppliers')->first();
        $this->actingAs($this->manager)->post('/modules/commandes',[
            'client_id'=>$client->id,'commission_enabled'=>0,'commission_rate'=>8,'deposit'=>0,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'confirmee','notes'=>'Commande avec colis test',
            'items'=>[
                ['name'=>'Assiettes','photo'=>UploadedFile::fake()->image('assiettes.jpg',500,500),'quantity'=>10,'supplier_id'=>$supplier->id,'supplier_price'=>10000,'china_delivery'=>0,'packaging'=>0,'weight'=>8,'cbm'=>0.08,'freight'=>10000,'margin'=>10000,'commission'=>0,'client_total'=>120000],
                ['name'=>'Verres','quantity'=>20,'supplier_id'=>$supplier->id,'supplier_price'=>5000,'china_delivery'=>0,'packaging'=>0,'weight'=>6,'cbm'=>0.06,'freight'=>10000,'margin'=>10000,'commission'=>0,'client_total'=>120000],
            ],
            'packages'=>[
                ['reference'=>'COLIS-A','billing_unit'=>'kg','weight_kg'=>9.5,'volume_cbm'=>0.09,'items'=>[['item_index'=>0,'quantity'=>10],['item_index'=>1,'quantity'=>5]]],
                ['reference'=>'COLIS-B','billing_unit'=>'cbm','weight_kg'=>4.5,'volume_cbm'=>0.05,'items'=>[['item_index'=>1,'quantity'=>15]]],
            ],
        ])->assertRedirect('/modules/commandes');
        $order=DB::table('orders')->where('notes','Commande avec colis test')->first();
        $this->assertSame(0.14,(float)$order->cbm); $this->assertSame(2,DB::table('order_packages')->where('order_id',$order->id)->count());
        $orderPhoto=DB::table('order_items')->where('order_id',$order->id)->where('name','Assiettes')->value('photo_path'); $this->assertNotNull($orderPhoto); Storage::disk('persistent')->assertExists($orderPhoto);
        $this->assertSame(3,DB::table('order_package_items')->join('order_packages','order_packages.id','=','order_package_items.order_package_id')->where('order_packages.order_id',$order->id)->count());
    }

    public function test_quotes_accept_product_photos_while_invoice_and_tracking_receive_real_order_products(): void
    {
        $order=DB::table('orders')->first(); $item=DB::table('order_items')->where('order_id',$order->id)->first();
        DB::table('order_items')->where('id',$item->id)->update(['photo_path'=>'product-photos/real-item.jpg']);

        $this->actingAs($this->manager)->get('/modules/devis/create')->assertInertia(fn(Assert $page)=>$page->where('module','devis')->where('itemFields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='photo')));
        foreach(['factures','logistique'] as $module) {
            $this->actingAs($this->manager)->get("/modules/{$module}/create")->assertInertia(fn(Assert $page)=>$page->where('module',$module)->where('orderProducts',fn($groups)=>collect($groups[$order->id]??[])->contains(fn($product)=>$product['photo_url']==='/product-photo/real-item.jpg')));
        }
    }

    public function test_manager_can_preview_an_order_with_products_photos_and_packages(): void
    {
        $order=DB::table('orders')->first();
        $item=DB::table('order_items')->where('order_id',$order->id)->first();
        DB::table('order_items')->where('id',$item->id)->update(['photo_path'=>'product-photos/order-preview.jpg']);

        $packageId=DB::table('order_packages')->insertGetId([
            'order_id'=>$order->id,'reference'=>'COLIS-PREVIEW','billing_unit'=>'kg','weight_kg'=>12.5,'volume_cbm'=>0.12,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('order_package_items')->insert([
            'order_package_id'=>$packageId,'order_item_id'=>$item->id,'quantity'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($this->manager)->get("/modules/commandes/{$order->id}")->assertInertia(fn(Assert $page)=>$page
            ->component('Module/OrderShow')
            ->where('order.number',$order->number)
            ->where('company.address','Lot IIB 106 Ambatomainty Antananarivo')
            ->where('company.contact','+261 34 98 732 08')
            ->where('company.whatsapp','+86 158 0200 3702')
            ->where('company.email','contactmadinaimport@gmail.com')
            ->where('company.nif','4019196145')
            ->where('company.rcs','2025B00524')
            ->where('company.stat','46101 11 2025 0 10528')
            ->has('items',fn(Assert $items)=>$items->where('0.photo_url','/product-photo/order-preview.jpg')->etc())
            ->has('packages',fn(Assert $packages)=>$packages->where('0.reference','COLIS-PREVIEW')->where('0.items.0.name',$item->name)->etc())
        );
    }

    public function test_supplier_purchase_accepts_a_proof_capture_and_link(): void
    {
        Storage::fake('persistent');
        $supplier=DB::table('suppliers')->first();
        $order=DB::table('orders')->first();
        $this->actingAs($this->manager)->post('/modules/achats',[
            'supplier_id'=>$supplier->id,'order_id'=>$order->id,'paid_at'=>'2026-08-25','amount'=>350000,'method'=>'Alipay','reference'=>'ALI-PROOF-001','status'=>'partiel','notes'=>'Acompte avec justificatif',
            'proof'=>UploadedFile::fake()->image('capture-alipay.png',800,600),'proof_url'=>'https://example.test/payment/ALI-PROOF-001',
        ])->assertRedirect('/modules/achats');

        $payment=DB::table('supplier_payments')->where('reference','ALI-PROOF-001')->first();
        $this->assertNotNull($payment->proof_path);
        $this->assertSame('https://example.test/payment/ALI-PROOF-001',$payment->proof_url);
        Storage::disk('persistent')->assertExists($payment->proof_path);
        $this->actingAs($this->manager)->get("/modules/achats/{$payment->id}/edit")->assertInertia(fn(Assert $page)=>$page
            ->where('module','achats')
            ->where('fields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='proof'&&$field['preview']==='/purchase-proof/'.basename($payment->proof_path)))
        );
    }

    public function test_supplier_can_have_a_product_catalogue(): void
    {
        $this->actingAs($this->manager)->post('/modules/fournisseurs',[
            'name'=>'Catalogue fournisseur test','category'=>'Maison','moq'=>10,'production_days'=>8,'contact'=>'supplier@example.test','quality_rating'=>4,'active'=>1,
            'products'=>[['name'=>'Assiette premium','specifications'=>'Porcelaine blanche','price'=>12000,'local_delivery'=>1000,'packaging'=>500,'cbm'=>0.01,'freight'=>2000,'margin'=>3000,'source_url'=>'https://detail.1688.com/test-assiette']],
        ])->assertRedirect('/modules/fournisseurs');
        $supplier=DB::table('suppliers')->where('name','Catalogue fournisseur test')->first();
        $this->assertDatabaseHas('supplier_products',['supplier_id'=>$supplier->id,'name'=>'Assiette premium','price'=>12000]);
        $this->actingAs($this->manager)->get("/modules/fournisseurs/{$supplier->id}/edit")->assertInertia(fn(Assert $page)=>$page->where('initialSupplierProducts.0.name','Assiette premium'));
    }

    public function test_expense_can_be_general_or_linked_to_an_order(): void
    {
        $this->actingAs($this->manager)->post('/modules/depenses',[
            'category'=>'loyer_depot_chine','amount'=>1800000,'spent_at'=>'2026-08-25','type'=>'business','description'=>'Loyer mensuel du dépôt en Chine','order_id'=>null,'status'=>'paye',
        ])->assertRedirect('/modules/depenses');
        $this->assertDatabaseHas('expenses',['category'=>'loyer_depot_chine','amount'=>1800000,'order_id'=>null]);

        $order=DB::table('orders')->first();
        $this->actingAs($this->manager)->post('/modules/depenses',[
            'category'=>'logistique','amount'=>250000,'spent_at'=>'2026-08-25','type'=>'business','description'=>'Manutention spécifique','order_id'=>$order->id,'status'=>'paye',
        ])->assertRedirect('/modules/depenses');
        $this->assertDatabaseHas('expenses',['description'=>'Manutention spécifique','order_id'=>$order->id]);
    }
}
