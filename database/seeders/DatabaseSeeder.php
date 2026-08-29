<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $manager = User::updateOrCreate(['email' => env('ADMIN_EMAIL', 'manager@madina-import.mg')], [
            'name' => 'Manager Madina',
            'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe!2026')),
            'email_verified_at' => now(),
            'role' => 'super_admin',
            'permissions' => array_keys(config('access.menus', [])),
            'active' => true,
        ]);

        DB::transaction(function () use ($manager): void {
            $now = now();
            $clients = [
                ['number'=>'CLI-2026-001','name'=>'Hôtel Baobab Antananarivo','contact'=>'+261 34 12 345 67','type'=>'hotel','address'=>'Ivandry, Antananarivo','notes'=>'Mobilier et équipements hôteliers','active'=>true,'credit_balance'=>0],
                ['number'=>'CLI-2026-002','name'=>'Mada Bâtiment SARL','contact'=>'+261 32 45 678 90','type'=>'entrepreneur','address'=>'Ankorondrano, Antananarivo','notes'=>'Client régulier, règlement par virement','active'=>true,'credit_balance'=>250000],
                ['number'=>'CLI-2026-003','name'=>'Ravo Distribution','contact'=>'+261 33 22 456 78','type'=>'revendeur','address'=>'Toamasina','notes'=>'Revendeur électroménager','active'=>true,'credit_balance'=>0],
                ['number'=>'CLI-2026-004','name'=>'Aina Rakoto','contact'=>'+261 34 98 765 43','type'=>'particulier','address'=>'Ambohimangakely','notes'=>null,'active'=>true,'credit_balance'=>0],
            ];
            foreach ($clients as &$client) { $client['created_at']=$now; $client['updated_at']=$now; }
            unset($client);
            DB::table('clients')->upsert($clients, ['number'], ['name','contact','type','address','notes','active','credit_balance','updated_at']);
            $clientIds = DB::table('clients')->pluck('id','number');

            $suppliers = [
                ['name'=>'Guangzhou Yimei Furniture','category'=>'Mobilier','moq'=>10,'production_days'=>18,'contact'=>'WeChat: yimei-furniture','quality_rating'=>5,'notes'=>'Finitions fiables, emballage renforcé','active'=>true],
                ['name'=>'Shenzhen Nova Electronics','category'=>'Électronique','moq'=>20,'production_days'=>12,'contact'=>'WeChat: nova-sales88','quality_rating'=>4,'notes'=>'Certificats CE disponibles','active'=>true],
                ['name'=>'Foshan Bright Sanitary','category'=>'Sanitaire','moq'=>5,'production_days'=>21,'contact'=>'sales@brightsanitary.cn','quality_rating'=>4,'notes'=>'Inspection avant expédition recommandée','active'=>true],
            ];
            foreach ($suppliers as &$supplier) { $supplier['created_at']=$now; $supplier['updated_at']=$now; }
            unset($supplier);
            foreach ($suppliers as $supplier) {
                DB::table('suppliers')->updateOrInsert(['name' => $supplier['name']], $supplier);
            }
            $supplierIds = DB::table('suppliers')->pluck('id','name');

            $supplierProducts = [
                ['supplier_id'=>$supplierIds['Guangzhou Yimei Furniture'],'name'=>'Chaise de restaurant velours','specifications'=>'Structure acier noir, velours bordeaux','price'=>185000,'local_delivery'=>18000,'packaging'=>12000,'cbm'=>0.18,'freight'=>95000,'margin'=>65000,'contact'=>'WeChat: yimei-furniture','source_url'=>'https://detail.1688.com/example-chair'],
                ['supplier_id'=>$supplierIds['Shenzhen Nova Electronics'],'name'=>'Caméra Wi-Fi extérieure','specifications'=>'4MP, IP66, vision nocturne','price'=>112000,'local_delivery'=>8000,'packaging'=>5000,'cbm'=>0.012,'freight'=>28000,'margin'=>47000,'contact'=>'WeChat: nova-sales88','source_url'=>'https://detail.1688.com/example-camera'],
                ['supplier_id'=>$supplierIds['Foshan Bright Sanitary'],'name'=>'Lavabo vasque céramique','specifications'=>'Blanc mat, 60 × 40 cm','price'=>240000,'local_delivery'=>35000,'packaging'=>25000,'cbm'=>0.095,'freight'=>120000,'margin'=>80000,'contact'=>'sales@brightsanitary.cn','source_url'=>'https://detail.1688.com/example-basin'],
            ];
            foreach ($supplierProducts as &$product) { $product['photo_path']=null; $product['created_at']=$now; $product['updated_at']=$now; }
            unset($product);
            foreach ($supplierProducts as $product) DB::table('supplier_products')->updateOrInsert(['supplier_id'=>$product['supplier_id'],'name'=>$product['name']],$product);

            $quoteId = DB::table('quotes')->updateOrInsert(['number'=>'DV-MI-2026-001'],[
                'client_id'=>$clientIds['CLI-2026-001'],'contact'=>'+261 34 12 345 67','client_type'=>'hotel','sent_at'=>$now->copy()->subDays(18)->toDateString(),'valid_until'=>$now->copy()->addDays(12)->toDateString(),'status'=>'accepte','supplier_estimate'=>3700000,'logistics_estimate'=>1250000,'margin'=>1300000,'total'=>6750000,'currency'=>'MGA','notes'=>'20 chaises pour la salle principale','created_at'=>$now,'updated_at'=>$now,
            ]);
            $quoteId = DB::table('quotes')->where('number','DV-MI-2026-001')->value('id');
            DB::table('quote_items')->updateOrInsert(['quote_id'=>$quoteId,'name'=>'Chaise de restaurant velours'],[
                'supplier_id'=>$supplierIds['Guangzhou Yimei Furniture'],'specifications'=>'Structure acier noir, velours bordeaux','quantity'=>20,'source_url'=>'https://detail.1688.com/example-chair','photo_path'=>null,'supplier_price'=>3700000,'china_delivery'=>180000,'packaging'=>240000,'estimated_weight'=>160,'estimated_cbm'=>3.6,'estimated_freight'=>1250000,'margin'=>1300000,'commission'=>540000,'total'=>6750000,'created_at'=>$now,'updated_at'=>$now,
            ]);

            $orders = [
                ['number'=>'MI-2026-001','client_id'=>$clientIds['CLI-2026-001'],'quote_id'=>$quoteId,'manager_id'=>$manager->id,'origin'=>'devis','ordered_at'=>$now->copy()->subDays(15)->toDateString(),'shipping_mode'=>'maritime','cbm'=>3.6,'freight'=>1250000,'supplier_total'=>4120000,'commission_enabled'=>true,'commission_base'=>6750000,'commission_rate'=>8,'commission_amount'=>540000,'margin'=>1300000,'client_total'=>6750000,'deposit'=>4000000,'balance_due'=>2750000,'status'=>'en_transit','notes'=>'Livraison partielle autorisée'],
                ['number'=>'MI-2026-002','client_id'=>$clientIds['CLI-2026-002'],'quote_id'=>null,'manager_id'=>$manager->id,'origin'=>'directe','ordered_at'=>$now->copy()->subDays(9)->toDateString(),'shipping_mode'=>'aerien','cbm'=>0.48,'freight'=>1120000,'supplier_total'=>4480000,'commission_enabled'=>true,'commission_base'=>4480000,'commission_rate'=>8,'commission_amount'=>358400,'margin'=>1881600,'client_total'=>6720000,'deposit'=>3000000,'balance_due'=>3720000,'status'=>'achat_en_cours','notes'=>'40 caméras pour deux chantiers'],
                ['number'=>'MI-2026-003','client_id'=>$clientIds['CLI-2026-003'],'quote_id'=>null,'manager_id'=>$manager->id,'origin'=>'directe','ordered_at'=>$now->copy()->subMonths(1)->subDays(3)->toDateString(),'shipping_mode'=>'aerien','cbm'=>0.3,'freight'=>680000,'supplier_total'=>2800000,'commission_enabled'=>false,'commission_base'=>0,'commission_rate'=>8,'commission_amount'=>0,'margin'=>1150000,'client_total'=>4630000,'deposit'=>4630000,'balance_due'=>0,'status'=>'livre','notes'=>'Commande clôturée et livrée'],
            ];
            foreach ($orders as &$order) { $order['created_at']=$now; $order['updated_at']=$now; }
            unset($order);
            DB::table('orders')->upsert($orders,['number'],array_keys($orders[0]));
            $orderIds=DB::table('orders')->pluck('id','number');

            $orderItems = [
                ['order_id'=>$orderIds['MI-2026-001'],'supplier_id'=>$supplierIds['Guangzhou Yimei Furniture'],'name'=>'Chaise de restaurant velours','specifications'=>'Acier noir, velours bordeaux','quantity'=>20,'source_url'=>'https://detail.1688.com/example-chair','supplier_price'=>3700000,'china_delivery'=>180000,'packaging'=>240000,'weight'=>164,'cbm'=>3.6,'freight'=>1250000,'margin'=>1300000,'commission'=>540000,'client_total'=>6750000,'status'=>'en_transit'],
                ['order_id'=>$orderIds['MI-2026-002'],'supplier_id'=>$supplierIds['Shenzhen Nova Electronics'],'name'=>'Caméra Wi-Fi extérieure','specifications'=>'4MP, IP66','quantity'=>40,'source_url'=>'https://detail.1688.com/example-camera','supplier_price'=>4480000,'china_delivery'=>120000,'packaging'=>80000,'weight'=>58,'cbm'=>0.48,'freight'=>1120000,'margin'=>1881600,'commission'=>358400,'client_total'=>6720000,'status'=>'achat_en_cours'],
            ];
            foreach($orderItems as &$item){$item['created_at']=$now;$item['updated_at']=$now;}
            unset($item);
            foreach($orderItems as $item) DB::table('order_items')->updateOrInsert(['order_id'=>$item['order_id'],'name'=>$item['name']],$item);

            $invoices=[
                ['number'=>'FA-MI-2026-001','order_id'=>$orderIds['MI-2026-001'],'client_id'=>$clientIds['CLI-2026-001'],'type'=>'produits','status'=>'partielle','issued_at'=>$now->copy()->subDays(15)->toDateString(),'subtotal'=>5000000,'paid_amount'=>4000000,'balance_due'=>1000000,'lines'=>json_encode([['label'=>'20 chaises restaurant','quantity'=>20,'unit_price'=>250000,'amount'=>5000000]])],
                ['number'=>'FF-MI-2026-001','order_id'=>$orderIds['MI-2026-001'],'client_id'=>$clientIds['CLI-2026-001'],'type'=>'frais','status'=>'provisoire','issued_at'=>$now->copy()->subDays(15)->toDateString(),'subtotal'=>1750000,'paid_amount'=>0,'balance_due'=>1750000,'lines'=>json_encode([['label'=>'Fret et commission','quantity'=>1,'unit_price'=>1750000,'amount'=>1750000]])],
                ['number'=>'FA-MI-2026-002','order_id'=>$orderIds['MI-2026-002'],'client_id'=>$clientIds['CLI-2026-002'],'type'=>'produits','status'=>'partielle','issued_at'=>$now->copy()->subDays(9)->toDateString(),'subtotal'=>6720000,'paid_amount'=>3000000,'balance_due'=>3720000,'lines'=>json_encode([['label'=>'40 caméras Wi-Fi','quantity'=>40,'unit_price'=>168000,'amount'=>6720000]])],
            ];
            foreach($invoices as &$invoice){$invoice['created_at']=$now;$invoice['updated_at']=$now;}
            unset($invoice);
            DB::table('invoices')->upsert($invoices,['number'],array_keys($invoices[0]));
            $invoiceIds=DB::table('invoices')->pluck('id','number');

            $payments=[
                ['client_id'=>$clientIds['CLI-2026-001'],'order_id'=>$orderIds['MI-2026-001'],'invoice_id'=>$invoiceIds['FA-MI-2026-001'],'paid_at'=>$now->copy()->subDays(14)->toDateString(),'amount'=>4000000,'allocated_amount'=>4000000,'method'=>'Mobile Money','reference'=>'MVOLA-829104','type'=>'acompte','status'=>'valide','notes'=>"[motif:acompte_commande]\nAcompte commande chaises"],
                ['client_id'=>$clientIds['CLI-2026-002'],'order_id'=>$orderIds['MI-2026-002'],'invoice_id'=>$invoiceIds['FA-MI-2026-002'],'paid_at'=>$now->copy()->subDays(8)->toDateString(),'amount'=>3250000,'allocated_amount'=>3000000,'method'=>'Virement bancaire','reference'=>'BOA-26081742','type'=>'acompte','status'=>'valide','notes'=>"[motif:acompte_commande]\nExcédent de 250 000 Ar conservé en crédit"],
            ];
            foreach($payments as &$payment){$payment['created_at']=$now;$payment['updated_at']=$now;}
            unset($payment);
            foreach($payments as $payment) DB::table('client_payments')->updateOrInsert(['reference'=>$payment['reference']],$payment);

            DB::table('shipments')->updateOrInsert(['order_id'=>$orderIds['MI-2026-001'],'tracking'=>'YT202608881'],['supplier_sent_at'=>$now->copy()->subDays(11)->toDateString(),'china_warehouse_at'=>$now->copy()->subDays(8)->toDateString(),'weight'=>164,'cbm'=>3.6,'cost'=>1250000,'mode'=>'maritime','forwarder'=>'SinoMada Cargo','china_departure_at'=>$now->copy()->subDays(4)->toDateString(),'expected_madagascar_at'=>$now->copy()->addDays(31)->toDateString(),'arrived_madagascar_at'=>null,'delivered_at'=>null,'status'=>'en_transit','created_at'=>$now,'updated_at'=>$now]);

            $stock=[
                ['reference'=>'PRD-001','name'=>'Caméra Wi-Fi 4MP','quantity'=>18,'purchase_price'=>112000,'sale_price'=>189000,'stock_value'=>2016000,'entered_at'=>$now->copy()->subMonths(2)->toDateString(),'exited_at'=>$now->copy()->subDays(2)->toDateString(),'alert_threshold'=>5],
                ['reference'=>'PRD-002','name'=>'Lampe solaire 300W','quantity'=>4,'purchase_price'=>78000,'sale_price'=>135000,'stock_value'=>312000,'entered_at'=>$now->copy()->subMonth()->toDateString(),'exited_at'=>$now->copy()->subDays(5)->toDateString(),'alert_threshold'=>5],
                ['reference'=>'PRD-003','name'=>'Robinet mitigeur noir','quantity'=>0,'purchase_price'=>95000,'sale_price'=>165000,'stock_value'=>0,'entered_at'=>$now->copy()->subMonths(3)->toDateString(),'exited_at'=>$now->copy()->subDays(1)->toDateString(),'alert_threshold'=>3],
                ['reference'=>'PRD-004','name'=>'Étagère métallique 5 niveaux','quantity'=>12,'purchase_price'=>145000,'sale_price'=>245000,'stock_value'=>1740000,'entered_at'=>$now->copy()->subDays(20)->toDateString(),'exited_at'=>null,'alert_threshold'=>4],
            ];
            foreach($stock as &$product){$product['reserved_quantity']=0;$product['available_quantity']=$product['quantity'];$product['cbm']=null;$product['freight']=0;$product['total_purchase_cost']=$product['quantity']*$product['purchase_price'];$product['sale_total']=$product['quantity']*$product['sale_price'];$product['created_at']=$now;$product['updated_at']=$now;}
            unset($product);
            DB::table('inventory_products')->upsert($stock,['reference'],array_keys($stock[0]));
            $productIds=DB::table('inventory_products')->pluck('id','reference');

            DB::table('local_sales')->updateOrInsert(['inventory_product_id'=>$productIds['PRD-001'],'sold_at'=>$now->copy()->subDays(2)->toDateString()],['quantity'=>2,'unit_price'=>189000,'total'=>378000,'paid_amount'=>300000,'balance_due'=>78000,'payment_method'=>'Orange Money','status'=>'partiel','buyer_name'=>'Toky Services','buyer_contact'=>'+261 32 11 222 33','notes'=>'Solde attendu vendredi','created_at'=>$now,'updated_at'=>$now]);

            $expenses=[
                ['category'=>'logistique','amount'=>1250000,'spent_at'=>$now->copy()->subDays(4)->toDateString(),'type'=>'business','description'=>'Fret maritime commande MI-2026-001','order_id'=>$orderIds['MI-2026-001'],'status'=>'paye','source_type'=>'shipment','source_id'=>1],
                ['category'=>'marketing','amount'=>280000,'spent_at'=>$now->copy()->subDays(6)->toDateString(),'type'=>'business','description'=>'Campagne Facebook août','order_id'=>null,'status'=>'paye','source_type'=>'manual','source_id'=>101],
                ['category'=>'transport','amount'=>95000,'spent_at'=>$now->copy()->subDays(3)->toDateString(),'type'=>'business','description'=>'Livraison locale Antananarivo','order_id'=>null,'status'=>'paye','source_type'=>'manual','source_id'=>102],
                ['category'=>'autre','amount'=>160000,'spent_at'=>$now->copy()->subDays(2)->toDateString(),'type'=>'personnel','description'=>'Avance personnelle manager','order_id'=>null,'status'=>'paye','source_type'=>'manual','source_id'=>103],
            ];
            foreach($expenses as &$expense){$expense['created_at']=$now;$expense['updated_at']=$now;}
            unset($expense);
            DB::table('expenses')->upsert($expenses,['source_type','source_id'],array_keys($expenses[0]));

            DB::table('employees')->updateOrInsert(['name'=>'Mialy Rasoanaivo'],['position'=>'Assistante opérations','monthly_salary'=>1200000,'irsa_mode'=>'pourcentage','irsa_value'=>5,'active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            $employeeId=DB::table('employees')->where('name','Mialy Rasoanaivo')->value('id');
            DB::table('salaries')->updateOrInsert(['employee_id'=>$employeeId,'month'=>$now->copy()->startOfMonth()->toDateString()],['gross_salary'=>1200000,'irsa_mode'=>'pourcentage','irsa_rate'=>5,'irsa_amount'=>60000,'net_salary'=>1140000,'paid_at'=>$now->copy()->subDays(1)->toDateString(),'status'=>'paye','created_at'=>$now,'updated_at'=>$now]);
            DB::table('tax_records')->updateOrInsert(['type'=>'impot_synthetique','period'=>'2026'],['fiscal_year'=>2026,'calculation_base'=>'ca_encaisse','base_amount'=>7250000,'rate'=>5,'calculated_amount'=>362500,'declared_amount'=>null,'due_at'=>'2027-03-31','declared_at'=>null,'paid_at'=>null,'status'=>'estimation','created_at'=>$now,'updated_at'=>$now]);

            DB::table('audit_logs')->insertOrIgnore([
                ['user_id'=>$manager->id,'event'=>'commande.creee','auditable_type'=>'order','auditable_id'=>$orderIds['MI-2026-001'],'old_values'=>null,'new_values'=>json_encode(['status'=>'en_transit']),'ip_address'=>'127.0.0.1','created_at'=>$now->copy()->subDays(15)],
                ['user_id'=>$manager->id,'event'=>'paiement.enregistre','auditable_type'=>'client_payment','auditable_id'=>1,'old_values'=>null,'new_values'=>json_encode(['amount'=>4000000]),'ip_address'=>'127.0.0.1','created_at'=>$now->copy()->subDays(14)],
            ]);
            foreach (['client'=>4,'quote'=>1,'order'=>3,'invoice'=>2,'fee_invoice'=>1,'product'=>4] as $type=>$lastNumber) {
                DB::table('number_sequences')->updateOrInsert(['type'=>$type,'year'=>2026],['last_number'=>$lastNumber,'created_at'=>$now,'updated_at'=>$now]);
            }
        });

        $this->call(DemoDataSeeder::class);
    }
}
