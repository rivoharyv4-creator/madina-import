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
        foreach (['clients','devis','commandes','paiements','factures','fournisseurs','achats','logistique','stock','depenses','employes','salaires','fiscalite'] as $module) {
            $this->actingAs($this->manager)->get("/modules/$module/create")->assertOk();
        }
    }

    public function test_payment_form_exposes_the_new_payment_reasons(): void
    {
        $expected=['Acompte de commande','Solde de commande','Paiement fournisseur en Chine','Frais de fret / transport','Frais de service','Autre'];

        $this->actingAs($this->manager)->get('/modules/paiements/create')->assertInertia(fn(Assert $page)=>$page
            ->where('fields',function($fields) use($expected){
                $type=collect($fields)->firstWhere('name','type');
                return $type['label']==='Motif du paiement'&&collect($type['options'])->pluck('label')->all()===$expected;
            })
        );
    }

    public function test_other_payment_reason_requires_a_note(): void
    {
        $client=DB::table('clients')->first();

        $this->actingAs($this->manager)->post('/modules/paiements',[
            'client_id'=>$client->id,'paid_at'=>'2026-08-27','amount'=>100000,'allocated_amount'=>0,
            'method'=>'Espèces','reference'=>'AUTRE-SANS-NOTE','type'=>'autre','payment_object'=>'Paiement exceptionnel','notes'=>'',
        ])->assertSessionHasErrors('notes');
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
                ['name'=>'Présentoir métallique','specifications'=>'Noir, quatre niveaux','quantity'=>12,'supplier_id'=>$supplier->id,'source_url'=>'https://detail.1688.com/test-display','supplier_price'=>150000,'china_delivery'=>50000,'packaging'=>40000,'weight'=>120,'cbm'=>1.8,'freight'=>450000,'margin'=>600000,'commission'=>144000,'client_total'=>249500],
                ['name'=>'Étiquettes de prix','specifications'=>'Lot de 500','quantity'=>2,'supplier_id'=>$supplier->id,'source_url'=>null,'supplier_price'=>100000,'china_delivery'=>0,'packaging'=>0,'weight'=>5,'cbm'=>0.05,'freight'=>50000,'margin'=>150000,'commission'=>0,'client_total'=>250000],
            ],
        ])->assertRedirect('/modules/commandes');
        $order=DB::table('orders')->where('number','MI-2026-004')->first();
        $this->assertSame(15080000.0,(float)$order->balance_due);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'name'=>'Présentoir métallique']);
        $this->assertSame(2,DB::table('order_items')->where('order_id',$order->id)->count());
    }

    public function test_quote_can_contain_multiple_products_and_totals_are_aggregated(): void
    {
        $client=DB::table('clients')->first(); $suppliers=DB::table('suppliers')->limit(2)->pluck('id')->all();
        $this->actingAs($this->manager)->post('/modules/devis',['client_id'=>$client->id,'valid_until'=>'2026-09-30','status'=>'brouillon','items'=>[
            ['name'=>'Table restaurant','specifications'=>'Bois clair','quantity'=>5,'supplier_id'=>$suppliers[0],'source_url'=>null,'supplier_price'=>200000,'china_delivery'=>50000,'packaging'=>25000,'weight'=>100,'cbm'=>1.2,'freight'=>300000,'margin'=>400000,'commission'=>100000,'total'=>1],
            ['name'=>'Suspension LED','specifications'=>'Noir mat','quantity'=>10,'supplier_id'=>$suppliers[1],'source_url'=>null,'supplier_price'=>50000,'china_delivery'=>20000,'packaging'=>10000,'weight'=>15,'cbm'=>0.2,'freight'=>80000,'margin'=>200000,'commission'=>50000,'total'=>1],
        ]])->assertRedirect('/modules/devis');
        $quote=DB::table('quotes')->where('number','DV-MI-2026-002')->first();
        $this->assertSame(2,DB::table('quote_items')->where('quote_id',$quote->id)->count());
        $this->assertSame(8475000.0,(float)$quote->total);
        $this->assertSame([3600000.0,4875000.0],DB::table('quote_items')->where('quote_id',$quote->id)->pluck('total')->map(fn($total)=>(float)$total)->sort()->values()->all());
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
        $records=['clients'=>'clients','devis'=>'quotes','commandes'=>'orders','paiements'=>'client_payments','factures'=>'invoices','fournisseurs'=>'suppliers','achats'=>'supplier_payments','logistique'=>'shipments','stock'=>'inventory_products','depenses'=>'expenses','employes'=>'employees','salaires'=>'salaries','fiscalite'=>'tax_records'];
        foreach($records as $module=>$table) { $id=DB::table($table)->value('id'); if($id) $this->actingAs($this->manager)->get("/modules/$module/$id/edit")->assertOk(); }
    }

    public function test_manager_can_correct_order_stock_and_payment_with_recalculation(): void
    {
        $order=DB::table('orders')->where('number','MI-2026-002')->first(); $item=DB::table('order_items')->where('order_id',$order->id)->first();
        $this->actingAs($this->manager)->put("/modules/commandes/{$order->id}",['client_id'=>$order->client_id,'commission_enabled'=>1,'commission_rate'=>8,'deposit'=>3000000,'ordered_at'=>$order->ordered_at,'shipping_mode'=>'aerien','status'=>'achat_en_cours','notes'=>'Total corrigé','items'=>[['name'=>$item->name,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'source_url'=>$item->source_url,'supplier_price'=>112000,'china_delivery'=>$item->china_delivery,'packaging'=>$item->packaging,'weight'=>$item->weight,'cbm'=>$item->cbm,'freight'=>$item->freight,'margin'=>$item->margin,'commission'=>$item->commission,'client_total'=>170000]]])->assertRedirect('/modules/commandes');
        $this->assertSame(129544000.0,(float)DB::table('orders')->where('id',$order->id)->value('balance_due'));

        $product=DB::table('inventory_products')->where('reference','PRD-002')->first();
        $this->actingAs($this->manager)->put("/modules/stock/{$product->id}",['name'=>$product->name,'quantity'=>6,'purchase_price'=>78000,'sale_price'=>135000,'alert_threshold'=>5])->assertRedirect('/modules/stock');
        $this->assertDatabaseHas('inventory_products',['id'=>$product->id,'quantity'=>6,'stock_value'=>468000]);
        $this->assertDatabaseHas('stock_movements',['inventory_product_id'=>$product->id,'type'=>'inventaire','after_quantity'=>6]);

        $payment=DB::table('client_payments')->where('reference','MVOLA-829104')->first();
        $this->actingAs($this->manager)->put("/modules/paiements/{$payment->id}",['client_id'=>$payment->client_id,'new_client_name'=>'','new_client_contact'=>'','new_client_type'=>'','new_client_address'=>'','order_id'=>$payment->order_id,'invoice_id'=>$payment->invoice_id,'paid_at'=>$payment->paid_at,'amount'=>4000000,'allocated_amount'=>3800000,'method'=>$payment->method,'reference'=>$payment->reference,'type'=>$payment->type,'payment_object'=>'Acompte mobilier','notes'=>'Affectation corrigée'])->assertRedirect('/modules/paiements');
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
            'allocated_amount'=>1000000,'method'=>'Mobile Money','reference'=>'TEST-PAY-001','type'=>'frais_service','payment_object'=>'Frais de gestion commande','notes'=>'Paiement test',
        ])->assertRedirect('/modules/paiements');
        $this->assertSame($beforeCredit+200000,(float)DB::table('clients')->where('id',$order->client_id)->value('credit_balance'));
        $this->assertSame(2720000.0,(float)DB::table('orders')->where('id',$order->id)->value('balance_due'));
        $this->assertSame(2720000.0,(float)DB::table('invoices')->where('id',$invoice->id)->value('balance_due'));
    }

    public function test_unlinked_payment_is_fully_saved_as_credit_for_a_future_order(): void
    {
        $client=DB::table('clients')->where('number','CLI-2026-004')->first();
        $before=(float)$client->credit_balance;

        $this->actingAs($this->manager)->post('/modules/paiements',[
            'client_id'=>$client->id,'paid_at'=>'2026-08-25','amount'=>750000,'allocated_amount'=>500000,
            'method'=>'Virement bancaire','reference'=>'CREDIT-FUTUR-001','type'=>'acompte_commande','payment_object'=>'Avance future commande','notes'=>'Avance pour une future commande',
        ])->assertRedirect('/modules/paiements');

        $this->assertDatabaseHas('client_payments',['client_id'=>$client->id,'order_id'=>null,'invoice_id'=>null,'amount'=>750000,'allocated_amount'=>0,'type'=>'acompte','notes'=>"[motif:acompte_commande]\n[objet:".base64_encode('Avance future commande')."]\nAvance pour une future commande"]);
        $this->assertSame($before+750000,(float)DB::table('clients')->where('id',$client->id)->value('credit_balance'));
    }

    public function test_manager_can_create_a_client_directly_with_an_unlinked_payment(): void
    {
        $this->actingAs($this->manager)->post('/modules/paiements',[
            'new_client_name'=>'Client paiement comptoir',
            'new_client_contact'=>'+261 34 00 111 22',
            'new_client_type'=>'particulier',
            'new_client_address'=>'Antananarivo',
            'paid_at'=>'2026-08-27',
            'amount'=>900000,
            'allocated_amount'=>0,
            'method'=>'Espèces',
            'reference'=>'CREDIT-MANUEL-001',
            'type'=>'acompte_commande',
            'payment_object'=>'Réservation de future commande',
            'notes'=>'Avance pour une future commande',
        ])->assertRedirect('/modules/paiements');

        $client=DB::table('clients')->where('name','Client paiement comptoir')->first();
        $this->assertNotNull($client);
        $this->assertSame(900000.0,(float)$client->credit_balance);
        $this->assertDatabaseHas('client_payments',[
            'client_id'=>$client->id,
            'amount'=>900000,
            'allocated_amount'=>0,
            'reference'=>'CREDIT-MANUEL-001',
        ]);
    }

    public function test_confirmed_order_with_existing_product_decrements_and_restores_stock(): void
    {
        $product=DB::table('inventory_products')->where('reference','PRD-001')->first();
        $client=DB::table('clients')->first();
        $initialQuantity=(float)$product->quantity;
        $line=['inventory_product_id'=>$product->id,'quantity'=>3,'client_total'=>1];

        $this->actingAs($this->manager)->get('/modules/commandes/create')->assertInertia(fn(Assert $page)=>$page
            ->where('itemFields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='inventory_product_id'&&collect($field['options'])->contains(fn($option)=>(int)$option['value']===$product->id)))
        );
        $this->actingAs($this->manager)->post('/modules/commandes',[
            'client_id'=>$client->id,'commission_enabled'=>0,'commission_rate'=>8,'deposit'=>0,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'confirmee','notes'=>'Commande depuis le stock','items'=>[$line],
        ])->assertRedirect('/modules/commandes');

        $order=DB::table('orders')->where('notes','Commande depuis le stock')->first();
        $this->assertSame((float)$product->sale_price*3,(float)$order->client_total);
        $this->assertSame($initialQuantity-3,(float)DB::table('inventory_products')->where('id',$product->id)->value('quantity'));
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'inventory_product_id'=>$product->id,'name'=>$product->name,'supplier_price'=>$product->purchase_price,'client_total'=>(float)$product->sale_price*3,'quantity'=>3]);

        $item=DB::table('order_items')->where('order_id',$order->id)->first();
        $line=[...$line,'id'=>$item->id,'quantity'=>5,'client_total'=>(float)$product->sale_price];
        $this->actingAs($this->manager)->put("/modules/commandes/{$order->id}",[
            'client_id'=>$client->id,'commission_enabled'=>0,'commission_rate'=>8,'deposit'=>0,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'confirmee','notes'=>'Commande depuis le stock','items'=>[$line],
        ])->assertRedirect('/modules/commandes');
        $this->assertSame($initialQuantity-5,(float)DB::table('inventory_products')->where('id',$product->id)->value('quantity'));

        $item=DB::table('order_items')->where('order_id',$order->id)->first();
        $line['id']=$item->id;
        $this->actingAs($this->manager)->put("/modules/commandes/{$order->id}",[
            'client_id'=>$client->id,'commission_enabled'=>0,'commission_rate'=>8,'deposit'=>0,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'brouillon','notes'=>'Commande depuis le stock','items'=>[$line],
        ])->assertRedirect('/modules/commandes');
        $this->assertSame($initialQuantity,(float)DB::table('inventory_products')->where('id',$product->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements',['inventory_product_id'=>$product->id,'type'=>'sortie','quantity'=>3]);
        $this->assertDatabaseHas('stock_movements',['inventory_product_id'=>$product->id,'type'=>'entree','quantity'=>5]);
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
                ['name'=>'Assiettes','photo'=>UploadedFile::fake()->image('assiettes.jpg',500,500),'quantity'=>10,'supplier_id'=>$supplier->id,'supplier_price'=>10000,'china_delivery'=>0,'packaging'=>0,'weight'=>8,'cbm'=>0.08,'freight'=>10000,'margin'=>10000,'commission'=>0,'client_total'=>12000],
                ['name'=>'Verres','quantity'=>20,'supplier_id'=>$supplier->id,'supplier_price'=>5000,'china_delivery'=>0,'packaging'=>0,'weight'=>6,'cbm'=>0.06,'freight'=>10000,'margin'=>10000,'commission'=>0,'client_total'=>6000],
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

    public function test_stock_flow_calculates_availability_costs_sales_and_order_origins(): void
    {
        $orders=DB::table('orders')->limit(2)->pluck('id')->all();
        $this->actingAs($this->manager)->get('/modules/stock/create')->assertInertia(fn(Assert $page)=>$page
            ->where('module','stock')
            ->where('fields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='available_quantity'&&$field['readOnly']===true)
                &&collect($fields)->contains(fn($field)=>$field['name']==='total_purchase_cost'&&$field['readOnly']===true)
                &&collect($fields)->contains(fn($field)=>$field['name']==='sale_total'&&$field['readOnly']===true)
                &&collect($fields)->contains(fn($field)=>$field['name']==='origin_order_ids'&&$field['type']==='multiselect'))
        );

        $this->actingAs($this->manager)->post('/modules/stock',[
            'reference'=>'SKU-STOCK-FLOW-001','name'=>'Produit flux stock','quantity'=>20,'reserved_quantity'=>6,
            'purchase_price'=>100000,'cbm'=>1.25,'freight'=>400000,'sale_price'=>175000,'origin_order_ids'=>$orders,
        ])->assertRedirect('/modules/stock');

        $product=DB::table('inventory_products')->where('reference','SKU-STOCK-FLOW-001')->first();
        $this->assertSame(14.0,(float)$product->available_quantity);
        $this->assertSame(2400000.0,(float)$product->total_purchase_cost);
        $this->assertSame(3500000.0,(float)$product->sale_total);
        $this->assertSame(2,DB::table('inventory_product_origins')->where('inventory_product_id',$product->id)->count());

        $this->actingAs($this->manager)->post('/modules/stock',[
            'reference'=>'SKU-STOCK-FLOW-INVALID','name'=>'Réservation invalide','quantity'=>2,'reserved_quantity'=>3,
            'purchase_price'=>1000,'freight'=>0,'sale_price'=>2000,
        ])->assertSessionHasErrors('reserved_quantity');
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

    public function test_logistics_tracking_follows_the_internal_management_flow(): void
    {
        $order=DB::table('orders')->first();

        $this->actingAs($this->manager)->get('/modules/logistique/create')->assertInertia(fn(Assert $page)=>$page
            ->where('module','logistique')
            ->where('fields',fn($fields)=>collect($fields)->pluck('section')->filter()->unique()->values()->all()===[
                '1. Liaison','2. Statut','3. Suivi','4. Dates','5. Volume','6. Coût',
            ])
            ->has("orderTemplates.{$order->id}")
        );

        $this->actingAs($this->manager)->post('/modules/logistique',[
            'order_id'=>$order->id,
            'status'=>'expedie',
            'tracking'=>'TRACK-MI-2026-TEST',
            'forwarder'=>'SinoMada Test',
            'container_reference'=>'CONT-MG-001',
            'china_departure_at'=>'2026-08-20',
            'china_warehouse_at'=>'2026-08-18',
            'expected_madagascar_at'=>'2026-09-25',
            'arrived_madagascar_at'=>null,
            'cbm'=>4.25,
            'package_count'=>12,
            'carton_count'=>30,
            'cost'=>2000000,
        ])->assertRedirect('/modules/logistique');

        $shipment=DB::table('shipments')->where('tracking','TRACK-MI-2026-TEST')->first();
        $this->assertNotNull($shipment);
        $this->assertSame('maritime',$shipment->mode);
        $this->assertSame('CONT-MG-001',$shipment->container_reference);
        $this->assertSame(12,$shipment->package_count);
        $this->assertSame(30,$shipment->carton_count);
        $this->assertSame(2000000.0,(float)$shipment->cost);

        $this->actingAs($this->manager)->get("/modules/logistique/{$shipment->id}")->assertInertia(fn(Assert $page)=>$page
            ->component('Module/ShipmentShow')
            ->where('shipment.order_number',$order->number)
            ->where('shipment.container_reference','CONT-MG-001')
            ->where('shipment.package_count',12)
            ->has('products')
        );

        $this->actingAs($this->manager)->put("/modules/logistique/{$shipment->id}",[
            'order_id'=>$order->id,
            'status'=>'en_transit',
            'tracking'=>'TRACK-MI-2026-TEST',
            'forwarder'=>'SinoMada Test',
            'container_reference'=>'CONT-MG-001-B',
            'china_departure_at'=>'2026-08-20',
            'china_warehouse_at'=>'2026-08-18',
            'expected_madagascar_at'=>'2026-09-25',
            'arrived_madagascar_at'=>null,
            'cbm'=>4.5,
            'package_count'=>13,
            'carton_count'=>32,
            'cost'=>2100000,
        ])->assertRedirect('/modules/logistique');

        $this->assertDatabaseHas('shipments',['id'=>$shipment->id,'status'=>'en_transit','container_reference'=>'CONT-MG-001-B','package_count'=>13,'carton_count'=>32]);
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
            'supplier_id'=>$supplier->id,'order_id'=>$order->id,'paid_at'=>'2026-08-25','quantity'=>5,'unit_price'=>70000,'amount'=>1,'method'=>'Alipay','reference'=>'ALI-PROOF-001','status'=>'partiel','notes'=>'Acompte avec justificatif',
            'proof'=>UploadedFile::fake()->image('capture-alipay.png',800,600),'proof_url'=>'https://example.test/payment/ALI-PROOF-001',
        ])->assertRedirect('/modules/achats');

        $payment=DB::table('supplier_payments')->where('reference','ALI-PROOF-001')->first();
        $this->assertNotNull($payment->proof_path);
        $this->assertSame(5.0,(float)$payment->quantity);
        $this->assertSame(70000.0,(float)$payment->unit_price);
        $this->assertSame(350000.0,(float)$payment->amount);
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

    public function test_catalogue_management_is_separated_from_stock(): void
    {
        $product=DB::table('inventory_products')->first();

        $this->actingAs($this->manager)->get("/modules/stock/{$product->id}/edit")->assertInertia(fn(Assert $page)=>$page
            ->where('module','stock')
            ->where('fields',fn($fields)=>!collect($fields)->contains(fn($field)=>in_array($field['name'],['slug','gallery','category','short_description','catalog_description','is_published','is_featured','show_price'],true)))
        );

        $this->actingAs($this->manager)->get('/modules/catalogue')->assertInertia(fn(Assert $page)=>$page
            ->where('module','catalogue')
            ->where('config.title','Catalogue')
        );

        $this->actingAs($this->manager)->get("/modules/catalogue/{$product->id}/edit")->assertInertia(fn(Assert $page)=>$page
            ->where('module','catalogue')
            ->where('fields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='catalog_description'))
        );

        $this->actingAs($this->manager)->put("/modules/catalogue/{$product->id}",[
            'slug'=>'produit-catalogue-separe','category'=>'Maison','short_description'=>'Description courte','catalog_description'=>'Description complète du catalogue',
            'is_published'=>1,'is_featured'=>1,'show_price'=>1,
        ])->assertRedirect('/modules/catalogue');

        $this->assertDatabaseHas('inventory_products',[
            'id'=>$product->id,'slug'=>'produit-catalogue-separe','category'=>'Maison','is_published'=>1,'is_featured'=>1,'show_price'=>1,
        ]);
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
