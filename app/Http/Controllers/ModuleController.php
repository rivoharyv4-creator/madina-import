<?php

namespace App\Http\Controllers;

use App\Exports\StyledModuleExport;
use App\Http\Requests\StoreModuleRequest;
use App\Services\BusinessCalculator;
use App\Services\NumberSequenceService;
use App\Services\PersistentStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ModuleController extends Controller
{
    public function __construct(private readonly PersistentStorageService $storage)
    {
    }

    private function config(string $module): array
    {
        return [
            'clients'=>['title'=>'Clients','table'=>'clients','primary'=>'Nouveau client','editable'=>true,'columns'=>['number'=>'N° client','name'=>'Client','contact'=>'Contact','type'=>'Type','credit_balance'=>'Crédit disponible','active'=>'Statut']],
            'devis'=>['title'=>'Devis','table'=>'quotes','primary'=>'Nouveau devis','editable'=>true,'columns'=>['number'=>'N° devis','created_at'=>'Date du devis','client_id'=>'Client','total'=>'Montant','valid_until'=>'Validité','status'=>'Statut']],
            'commandes'=>['title'=>'Commandes','table'=>'orders','primary'=>'Nouvelle commande','editable'=>true,'columns'=>['number'=>'N° commande','client_id'=>'Client','client_total'=>'Total client','ordered_at'=>'Date','status'=>'Statut']],
            'paiements'=>['title'=>'Paiements clients','table'=>'client_payments','primary'=>'Nouveau paiement','editable'=>true,'columns'=>['client_id'=>'Client','paid_at'=>'Date','amount'=>'Montant','method'=>'Mode','status'=>'Statut']],
            'factures'=>['title'=>'Factures','table'=>'invoices','primary'=>'Nouvelle facture','editable'=>true,'columns'=>['number'=>'N° facture','type'=>'Type','subtotal'=>'Total','issued_at'=>'Date','status'=>'Statut']],
            'fournisseurs'=>['title'=>'Fournisseurs','table'=>'suppliers','primary'=>'Nouveau fournisseur','editable'=>true,'columns'=>['name'=>'Fournisseur','category'=>'Catégorie','contact'=>'Contact','quality_rating'=>'Qualité','active'=>'Statut']],
            'achats'=>['title'=>'Achats fournisseurs','table'=>'supplier_payments','primary'=>'Nouveau paiement','editable'=>true,'columns'=>['supplier_id'=>'Fournisseur','paid_at'=>'Date','amount'=>'Montant','method'=>'Mode','proof_path'=>'Justificatif','status'=>'Statut']],
            'logistique'=>['title'=>'Logistique','table'=>'shipments','primary'=>'Nouvelle expédition','editable'=>true,'columns'=>['order_id'=>'Commande','tracking'=>'Tracking','mode'=>'Mode','expected_madagascar_at'=>'Arrivée prévue','status'=>'Statut']],
            'stock'=>['title'=>'Stock','table'=>'inventory_products','primary'=>'Ajouter un produit','editable'=>true,'columns'=>['photo_path'=>'Photo','reference'=>'Référence','name'=>'Produit','quantity'=>'Quantité','purchase_price'=>'Prix achat','stock_value'=>'Valeur stock']],
            'ventes'=>['title'=>'Ventes locales','table'=>'local_sales','primary'=>'Nouvelle vente','editable'=>true,'columns'=>['inventory_product_id'=>'Produit','sold_at'=>'Date','quantity'=>'Quantité','total'=>'Total','status'=>'Statut']],
            'depenses'=>['title'=>'Dépenses','table'=>'expenses','primary'=>'Nouvelle dépense','editable'=>true,'columns'=>['spent_at'=>'Date','category'=>'Catégorie','description'=>'Description','type'=>'Type','amount'=>'Montant']],
            'salaires'=>['title'=>'Salaires et IRSA','table'=>'salaries','primary'=>'Préparer un salaire','editable'=>true,'related_action'=>['label'=>'Gérer les employés','href'=>'/modules/employes'],'columns'=>['employee_id'=>'Employé','month'=>'Mois','gross_salary'=>'Brut','irsa_amount'=>'IRSA','net_salary'=>'Net']],
            'employes'=>['title'=>'Employés','table'=>'employees','primary'=>'Nouvel employé','editable'=>true,'related_action'=>['label'=>'Retour aux salaires','href'=>'/modules/salaires'],'columns'=>['name'=>'Nom et prénom','position'=>'Poste','monthly_salary'=>'Salaire habituel','irsa_mode'=>'Mode IRSA','active'=>'Statut']],
            'fiscalite'=>['title'=>'Fiscalité','table'=>'tax_records','primary'=>'Nouvelle estimation','editable'=>true,'columns'=>['type'=>'Impôt','period'=>'Période','calculation_base'=>'Base','calculated_amount'=>'Estimation','status'=>'Statut']],
            'rapports'=>['title'=>'Rapports','table'=>'audit_logs','primary'=>null,'columns'=>['event'=>'Opération','auditable_type'=>'Module','user_id'=>'Utilisateur','created_at'=>'Date']],
            'parametres'=>['title'=>'Paramètres','table'=>'users','primary'=>null,'columns'=>['name'=>'Manager','email'=>'E-mail','created_at'=>'Créé le']],
        ][$module] ?? abort(404);
    }

    public function index(Request $request, string $module)
    {
        $config=$this->config($module); $rows=[]; $pagination=['current_page'=>1,'last_page'=>1,'per_page'=>20,'total'=>0,'from'=>null,'to'=>null];
        $filterDefinitions=$this->filterDefinitions($module);
        $activeFilters=collect($filterDefinitions)->mapWithKeys(fn($label,$field)=>[$field=>$request->string('filter_'.$field)->toString()])->filter(fn($value)=>$value!=='')->all();
        $filterOptions=[];
        if (DB::getSchemaBuilder()->hasTable($config['table'])) {
            $q=$this->exportQuery($config,$request->string('q')->toString(),$activeFilters);
            $page=$q->paginate(20)->withQueryString();
            $rows=$this->decorate(collect($page->items())->map(fn($row)=>(array)$row)->all());
            if($module==='devis') $rows=array_map(fn($row)=>[...$row,'created_at'=>$this->chinaDate($row['created_at'])],$rows);
            $pagination=['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>$page->perPage(),'total'=>$page->total(),'from'=>$page->firstItem(),'to'=>$page->lastItem()];
            foreach($filterDefinitions as $field=>$label){
                $values=DB::table($config['table'])->whereNotNull($field)->distinct()->orderBy($field)->pluck($field)->map(fn($value)=>['value'=>(string)$value,'label'=>$field==='active'?((bool)$value?'Actif':'Inactif'):ucfirst(str_replace('_',' ',(string)$value))])->values()->all();
                $filterOptions[]=['field'=>$field,'label'=>$label,'options'=>$values];
            }
        }
        return Inertia::render('Module/Index',['module'=>$module,'config'=>$config,'rows'=>$rows,'pagination'=>$pagination,'query'=>$request->q,'filterOptions'=>$filterOptions,'activeFilters'=>$activeFilters,'flash'=>session('success')]);
    }

    public function create(Request $request, string $module)
    {
        $config=$this->config($module); abort_if(!$config['primary'],404);
        $prefill=$this->invoicePrefill($module,$request->integer('order_id'));
        if($module==='devis') $prefill['quote_date']=$this->quoteDate();
        return Inertia::render('Module/Form',['module'=>$module,'config'=>$config,'fields'=>$this->fields($module),'prefill'=>$prefill,'itemFields'=>$this->itemFields($module),'initialItems'=>$this->itemFields($module)?[$this->emptyItem($module)]:[],'initialPackages'=>[],'initialSupplierProducts'=>$module==='fournisseurs'?[]:null,'orderProducts'=>$this->orderProducts($module),'orderTemplates'=>$this->orderTemplates($module),'quoteTemplates'=>$this->quoteTemplates($module),'company'=>config('madina.company')]);
    }

    public function edit(string $module, int $id)
    {
        $config=$this->config($module); abort_unless($config['editable']??false,404); $query=DB::table($config['table']); if(DB::getSchemaBuilder()->hasColumn($config['table'],'deleted_at')) $query->whereNull('deleted_at'); $record=$query->find($id); abort_unless($record,404);
        $values=$this->editValues($module,$record);
        $fields=array_map(function($field) use($values){ if(array_key_exists($field['name'],$values)) $field['default']=$values[$field['name']]; if($field['type']==='file'&&!empty($values['photo_path'])) $field['preview']='/product-photo/'.basename($values['photo_path']); if($field['name']==='proof'&&!empty($values['proof_path'])) $field['preview']='/purchase-proof/'.basename($values['proof_path']); return $field; },$this->fields($module));
        return Inertia::render('Module/Form',['module'=>$module,'config'=>[...$config,'primary'=>'Modifier '.$config['title']],'fields'=>$fields,'recordId'=>$id,'prefill'=>$module==='devis'?['quote_date'=>$this->chinaDate($record->created_at)]:[],'itemFields'=>$this->itemFields($module),'initialItems'=>$this->existingItems($module,$id),'initialPackages'=>$this->existingPackages($module,$id),'initialSupplierProducts'=>$this->supplierProducts($module,$id),'orderProducts'=>$this->orderProducts($module),'orderTemplates'=>$this->orderTemplates($module),'quoteTemplates'=>$this->quoteTemplates($module),'company'=>config('madina.company')]);
    }

    public function show(string $module, int $id)
    {
        abort_unless($module === 'commandes', 404);

        $order=DB::table('orders')
            ->join('clients','clients.id','=','orders.client_id')
            ->join('users','users.id','=','orders.manager_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.id',$id)
            ->select('orders.*','clients.name as client_name','clients.contact as client_contact','clients.address as client_address','users.name as manager_name')
            ->first();
        abort_unless($order,404);

        $items=DB::table('order_items')
            ->leftJoin('suppliers','suppliers.id','=','order_items.supplier_id')
            ->where('order_items.order_id',$id)
            ->orderBy('order_items.id')
            ->select('order_items.*',DB::raw('COALESCE(order_items.supplier_name, suppliers.name) as supplier_name'))
            ->get()
            ->map(fn($item)=>[...(array)$item,'photo_url'=>$item->photo_path?'/product-photo/'.basename($item->photo_path):null])
            ->values();

        $packages=DB::table('order_packages')->where('order_id',$id)->orderBy('id')->get()->map(function($package){
            $contents=DB::table('order_package_items')
                ->join('order_items','order_items.id','=','order_package_items.order_item_id')
                ->where('order_package_items.order_package_id',$package->id)
                ->orderBy('order_package_items.id')
                ->get(['order_items.name','order_package_items.quantity']);
            return [...(array)$package,'items'=>$contents];
        })->values();

        return Inertia::render('Module/OrderShow',['order'=>$order,'items'=>$items,'packages'=>$packages,'company'=>config('madina.company')]);
    }

    public function pdf(string $module, int $id)
    {
        abort_unless(in_array($module,['devis','factures'],true),404);

        if($module==='devis'){
            $document=DB::table('quotes')
                ->join('clients','clients.id','=','quotes.client_id')
                ->whereNull('quotes.deleted_at')
                ->where('quotes.id',$id)
                ->select('quotes.*','clients.number as client_number',DB::raw('COALESCE(quotes.client_name, clients.name) as document_client_name'),'quotes.contact as client_contact','clients.address as client_address')
                ->first();
            abort_unless($document,404);
            $document->client_name=$document->document_client_name;
            $items=DB::table('quote_items')->where('quote_id',$id)->orderBy('id')->get()->map(function($item){$item->photo_data=$this->storage->dataUri($item->photo_path);return $item;});
            $title='DEVIS';
            $directory='quotes';
        }else{
            $document=DB::table('invoices')
                ->join('clients','clients.id','=','invoices.client_id')
                ->join('orders','orders.id','=','invoices.order_id')
                ->whereNull('invoices.deleted_at')
                ->where('invoices.id',$id)
                ->select('invoices.*','clients.number as client_number','clients.name as client_name','clients.contact as client_contact','clients.address as client_address','orders.number as order_number')
                ->first();
            abort_unless($document,404);
            $items=collect(json_decode($document->lines??'[]',false)?:[]);
            $document->products=DB::table('order_items')->where('order_id',$document->order_id)->orderBy('id')->get(['name','specifications','quantity','client_total']);
            $title='FACTURE';
            $directory='invoices';
        }

        $logoPath=public_path('brand/madina-import-logo-transparent.png');
        $logoData=file_exists($logoPath)?'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)):null;
        $company=config('madina.company');
        $contents=Pdf::loadView('pdf.document',compact('module','document','items','title','logoData','company'))->setPaper('a4')->output();
        $filename=preg_replace('/[^A-Za-z0-9_-]+/','-',$document->number).'.pdf';
        $path=$this->storage->putDocumentPdf($directory,$filename,$contents);

        return $this->storage->download($path,$filename);
    }

    public function paymentReceipt(int $id)
    {
        $payment=DB::table('client_payments')
            ->join('clients','clients.id','=','client_payments.client_id')
            ->leftJoin('orders','orders.id','=','client_payments.order_id')
            ->leftJoin('invoices','invoices.id','=','client_payments.invoice_id')
            ->where('client_payments.id',$id)
            ->select('client_payments.*','clients.number as client_number','clients.name as client_name','clients.contact as client_contact','clients.address as client_address','clients.credit_balance as client_credit_balance','orders.number as order_number','invoices.number as invoice_number')
            ->first();
        abort_unless($payment,404);

        $payment->receipt_number='REC-MI-'.date('Y',strtotime($payment->paid_at)).'-'.str_pad((string)$payment->id,5,'0',STR_PAD_LEFT);
        $payment->credit_amount=max(0,(float)$payment->amount-(float)$payment->allocated_amount);
        $payment->type_label=$this->paymentTypeLabel($payment->type);
        $logoPath=public_path('brand/madina-import-logo-transparent.png');
        $logoData=file_exists($logoPath)?'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)):null;
        $company=config('madina.company');
        $contents=Pdf::loadView('pdf.payment-receipt',compact('payment','logoData','company'))->setPaper('a4')->output();
        $filename=$payment->receipt_number.'.pdf';
        $path=$this->storage->putDocumentPdf('receipts',$filename,$contents);

        return $this->storage->download($path,$filename);
    }

    public function update(StoreModuleRequest $request, string $module, int $id, BusinessCalculator $calculator)
    {
        $config=$this->config($module); abort_unless($config['editable']??false,404); $query=DB::table($config['table']); if(DB::getSchemaBuilder()->hasColumn($config['table'],'deleted_at')) $query->whereNull('deleted_at'); $old=$query->find($id); abort_unless($old,404); $data=$request->validated();
        DB::transaction(function() use($request,$config,$module,$id,$old,$data,$calculator){ $this->applyUpdate($module,$id,$old,$data,$request->user()->id,$calculator); DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'event'=>$module.'.modifie','auditable_type'=>$config['table'],'auditable_id'=>$id,'old_values'=>json_encode($old),'new_values'=>json_encode($data),'ip_address'=>$request->ip(),'created_at'=>now()]); });
        return redirect()->route('modules.index',$module)->with('success',$config['title'].' : modifications enregistrées.');
    }

    public function destroyEmployee(Request $request, int $id)
    {
        $employee=DB::table('employees')->whereNull('deleted_at')->find($id); abort_unless($employee,404);
        DB::transaction(function() use($request,$employee,$id){ $leftAt=$request->input('left_at',today()->toDateString()); $reason=$request->input('departure_reason')?:'Départ de l’entreprise'; DB::table('employees')->where('id',$id)->update(['active'=>false,'left_at'=>$leftAt,'departure_reason'=>$reason,'deleted_at'=>now(),'updated_at'=>now()]); DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'event'=>'employes.depart','auditable_type'=>'employees','auditable_id'=>$id,'old_values'=>json_encode($employee),'new_values'=>json_encode(['active'=>false,'left_at'=>$leftAt,'departure_reason'=>$reason]),'ip_address'=>$request->ip(),'created_at'=>now()]); });
        return redirect()->route('modules.index','employes')->with('success','Employé retiré de l’entreprise. Son historique salarial est conservé.');
    }


    public function store(StoreModuleRequest $request, string $module, NumberSequenceService $numbers, BusinessCalculator $calculator)
    {
        $data=$request->validated();
        $result=DB::transaction(function() use($module,$data,$request,$numbers,$calculator){
            $now=now();
            $id=match($module){
                'clients'=>DB::table('clients')->insertGetId([...$data,'number'=>$numbers->next('client'),'active'=>$data['active']??true,'credit_balance'=>0,'created_at'=>$now,'updated_at'=>$now]),
                'fournisseurs'=>$this->createSupplier($data),
                'stock'=>$this->createStock($data,$numbers,$request->user()->id),
                'devis'=>$this->createQuote($data,$numbers),
                'commandes'=>$this->createOrder($data,$numbers,$request->user()->id),
                'paiements'=>$this->createClientPayment($data,$numbers),
                'factures'=>$this->createInvoice($data,$numbers),
                'achats'=>$this->createSupplierPayment($data),
                'logistique'=>DB::table('shipments')->insertGetId([...$data,'created_at'=>$now,'updated_at'=>$now]),
                'ventes'=>$this->createLocalSale($data,$request->user()->id),
                'depenses'=>DB::table('expenses')->insertGetId([...$data,'source_type'=>null,'source_id'=>null,'created_at'=>$now,'updated_at'=>$now]),
                'employes'=>DB::table('employees')->insertGetId([...$data,'active'=>$data['active']??true,'created_at'=>$now,'updated_at'=>$now]),
                'salaires'=>$this->createSalary($data,$calculator),
                'fiscalite'=>DB::table('tax_records')->insertGetId([...Arr::except($data,'rate'),'rate'=>$data['rate'],'calculated_amount'=>(float)$calculator->commission($data['base_amount'],$data['rate']),'created_at'=>$now,'updated_at'=>$now]),
                default=>abort(404),
            };
            DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'event'=>$module.'.cree','auditable_type'=>$this->config($module)['table'],'auditable_id'=>$id,'old_values'=>null,'new_values'=>json_encode($data),'ip_address'=>$request->ip(),'created_at'=>$now]);
            return $id;
        });
        return redirect()->route('modules.index',$module)->with('success',$this->config($module)['title'].' : enregistrement créé avec succès.');
    }

    private function createStock(array $data, NumberSequenceService $numbers, int $userId): int
    {
        $photo=$data['photo']??null; unset($data['photo']); $quantity=(float)$data['quantity'];
        $id=DB::table('inventory_products')->insertGetId([...$data,'photo_path'=>$this->storePhoto($photo),'reference'=>$numbers->next('product'),'stock_value'=>$quantity*(float)$data['purchase_price'],'entered_at'=>$quantity>0?today():null,'exited_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
        if($quantity>0) DB::table('stock_movements')->insert(['inventory_product_id'=>$id,'type'=>'entree','quantity'=>$quantity,'before_quantity'=>0,'after_quantity'=>$quantity,'notes'=>'Stock initial','moved_at'=>now(),'user_id'=>$userId]);
        return $id;
    }

    private function createSupplier(array $data): int
    {
        $products=$data['products']??[];
        $id=DB::table('suppliers')->insertGetId([...Arr::except($data,'products'),'active'=>$data['active']??true,'created_at'=>now(),'updated_at'=>now()]);
        $this->replaceSupplierProducts($id,$products);
        return $id;
    }

    private function replaceSupplierProducts(int $supplierId, array $products): void
    {
        $photos=DB::table('supplier_products')->where('supplier_id',$supplierId)->pluck('photo_path','id')->all();
        DB::table('supplier_products')->where('supplier_id',$supplierId)->delete();
        foreach($products as $product){
            $photo=$product['photo']??null;
            DB::table('supplier_products')->insert([...Arr::except($product,['id','photo']),'supplier_id'=>$supplierId,'photo_path'=>$photo?$this->storePhoto($photo):($photos[$product['id']??0]??null),'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    private function createQuote(array $data, NumberSequenceService $numbers): int
    {
        if(empty($data['client_id'])) $data['client_id']=DB::table('clients')->insertGetId(['number'=>$numbers->next('client'),'name'=>$data['client_name'],'contact'=>$data['client_contact'],'type'=>$data['client_type'],'address'=>null,'notes'=>'Créé directement depuis un devis','active'=>true,'credit_balance'=>0,'created_at'=>now(),'updated_at'=>now()]);
        $client=DB::table('clients')->find($data['client_id']); $supplierTotal=collect($data['items'])->sum(fn($item)=>(float)$item['supplier_price']*(float)$item['quantity']); $logistics=collect($data['items'])->sum(fn($item)=>(float)($item['china_delivery']??0)+(float)($item['packaging']??0)+(float)($item['freight']??0)); $margin=collect($data['items'])->sum(fn($item)=>(float)($item['margin']??0)); $total=collect($data['items'])->sum(fn($item)=>(float)$item['total']);
        $id=DB::table('quotes')->insertGetId(['number'=>$numbers->next('quote'),'client_id'=>$client->id,'client_name'=>$data['client_name'],'contact'=>$data['client_contact'],'client_type'=>$client->type,'sent_at'=>$data['status']==='brouillon'?null:today(),'valid_until'=>$data['valid_until'],'shipping_mode'=>$data['shipping_mode'],'shipping_delay'=>$data['shipping_delay']??null,'bank_details'=>$data['bank_details']??null,'payment_terms'=>$data['payment_terms']??null,'warranty'=>$data['warranty']??null,'status'=>$data['status'],'supplier_estimate'=>$supplierTotal,'logistics_estimate'=>$logistics,'margin'=>$margin,'total'=>$total,'currency'=>'MGA','notes'=>$data['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
        $this->insertQuoteItems($id,$data['items']);
        return $id;
    }

    private function createOrder(array $data, NumberSequenceService $numbers, int $managerId): int
    {
        if(!empty($data['new_client_name'])) $data['client_id']=DB::table('clients')->insertGetId(['number'=>$numbers->next('client'),'name'=>$data['new_client_name'],'contact'=>$data['new_client_contact'],'type'=>$data['new_client_type'],'address'=>$data['new_client_address']??null,'notes'=>'Créé directement depuis une commande','active'=>true,'credit_balance'=>0,'created_at'=>now(),'updated_at'=>now()]);
        $total=collect($data['items'])->sum(fn($item)=>(float)$item['client_total']); $deposit=(float)($data['deposit']??0); if($deposit>$total) throw ValidationException::withMessages(['deposit'=>'L’acompte ne peut pas dépasser le total client.']); $supplierTotal=collect($data['items'])->sum(fn($item)=>(float)$item['supplier_price']*(float)$item['quantity']); $freight=collect($data['items'])->sum(fn($item)=>(float)($item['freight']??0)); $margin=collect($data['items'])->sum(fn($item)=>(float)($item['margin']??0)); $cbm=collect($data['packages']??[])->sum(fn($package)=>(float)($package['volume_cbm']??0))?:collect($data['items'])->sum(fn($item)=>(float)($item['cbm']??0));
        $commissionEnabled=(bool)($data['commission_enabled']??false); $commission=$commissionEnabled?collect($data['items'])->sum(fn($item)=>(float)($item['commission']??0)):0;
        $id=DB::table('orders')->insertGetId(['number'=>$numbers->next('order'),'client_id'=>$data['client_id'],'quote_id'=>$data['quote_id']??null,'manager_id'=>$managerId,'origin'=>!empty($data['quote_id'])?'devis':'directe','ordered_at'=>$data['ordered_at'],'shipping_mode'=>$data['shipping_mode']??null,'cbm'=>$cbm,'freight'=>$freight,'supplier_total'=>$supplierTotal,'commission_enabled'=>$commissionEnabled,'commission_base'=>$supplierTotal,'commission_rate'=>$data['commission_rate']??8,'commission_amount'=>$commission,'margin'=>$margin,'client_total'=>$total,'deposit'=>$deposit,'balance_due'=>$total-$deposit,'status'=>$data['status'],'notes'=>$data['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
        $itemIds=$this->insertOrderItems($id,$data['items'],$commissionEnabled,$data['status']);
        $this->insertPackages($id,$data['packages']??[],$itemIds,$data['items']);
        return $id;
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        return $this->storage->storeProduct($photo);
    }

    private function insertQuoteItems(int $quoteId, array $items, array $existingPhotos=[]): void
    {
        foreach($items as $item) {
            $photo=$item['photo']??null; $existing=$existingPhotos[$item['id']??0]??null;
            $supplier=!empty($item['supplier_id'])?DB::table('suppliers')->find($item['supplier_id']):null;
            DB::table('quote_items')->insert(['quote_id'=>$quoteId,'supplier_id'=>$item['supplier_id']??null,'supplier_name'=>$item['supplier_name']??$supplier?->name,'supplier_contact'=>$item['supplier_contact']??$supplier?->contact,'name'=>$item['name'],'specifications'=>$item['specifications']??null,'quantity'=>$item['quantity'],'source_url'=>$item['source_url']??null,'photo_path'=>$photo?$this->storePhoto($photo):$existing,'supplier_price'=>$item['supplier_price'],'china_delivery'=>$item['china_delivery']??0,'packaging'=>$item['packaging']??0,'estimated_weight'=>$item['weight']??null,'estimated_cbm'=>$item['cbm']??null,'estimated_freight'=>$item['freight']??0,'margin'=>$item['margin']??0,'commission'=>$item['commission']??0,'total'=>$item['total'],'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    private function insertOrderItems(int $orderId, array $items, bool $commissionEnabled, string $status, array $existingPhotos=[]): array
    {
        $ids=[];
        foreach($items as $item) {
            $photo=$item['photo']??null; $quotePhoto=!empty($item['quote_item_id'])?DB::table('quote_items')->where('id',$item['quote_item_id'])->value('photo_path'):null; $existing=$existingPhotos[$item['id']??0]??$quotePhoto;
            $supplier=!empty($item['supplier_id'])?DB::table('suppliers')->find($item['supplier_id']):null;
            $ids[]=DB::table('order_items')->insertGetId(['order_id'=>$orderId,'supplier_id'=>$item['supplier_id']??null,'supplier_name'=>$item['supplier_name']??$supplier?->name,'supplier_contact'=>$item['supplier_contact']??$supplier?->contact,'name'=>$item['name'],'specifications'=>$item['specifications']??null,'quantity'=>$item['quantity'],'source_url'=>$item['source_url']??null,'photo_path'=>$photo?$this->storePhoto($photo):$existing,'supplier_price'=>$item['supplier_price'],'china_delivery'=>$item['china_delivery']??0,'packaging'=>$item['packaging']??0,'weight'=>$item['weight']??null,'cbm'=>$item['cbm']??null,'freight'=>$item['freight']??0,'margin'=>$item['margin']??0,'commission'=>$commissionEnabled?($item['commission']??0):0,'client_total'=>$item['client_total'],'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
        }
        return $ids;
    }

    private function insertPackages(int $orderId, array $packages, array $itemIds, array $items): void
    {
        $allocated=[]; $references=[];
        foreach($packages as $packageIndex=>$package) {
            $normalizedReference=mb_strtolower(trim($package['reference']));
            if(isset($references[$normalizedReference])) throw ValidationException::withMessages(["packages.$packageIndex.reference"=>'Chaque colis doit avoir une référence différente.']);
            $references[$normalizedReference]=true; $seen=[];
            $packageId=DB::table('order_packages')->insertGetId(['order_id'=>$orderId,'reference'=>$package['reference'],'billing_unit'=>$package['billing_unit'],'weight_kg'=>$package['weight_kg']??null,'volume_cbm'=>$package['volume_cbm']??null,'notes'=>$package['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
            foreach($package['items'] as $lineIndex=>$line) {
                $index=(int)$line['item_index'];
                if(!isset($itemIds[$index])) throw ValidationException::withMessages(["packages.$packageIndex.items.$lineIndex.item_index"=>'Produit sélectionné invalide.']);
                if(isset($seen[$index])) throw ValidationException::withMessages(["packages.$packageIndex.items.$lineIndex.item_index"=>'Ce produit est déjà présent dans ce colis.']);
                $seen[$index]=true;
                $allocated[$index]=($allocated[$index]??0)+(float)$line['quantity'];
                if($allocated[$index]>(float)$items[$index]['quantity']) throw ValidationException::withMessages(["packages.$packageIndex.items.$lineIndex.quantity"=>'La quantité répartie dans les colis dépasse la quantité commandée.']);
                DB::table('order_package_items')->insert(['order_package_id'=>$packageId,'order_item_id'=>$itemIds[$index],'quantity'=>$line['quantity'],'created_at'=>now(),'updated_at'=>now()]);
            }
        }
    }

    private function createClientPayment(array $data, NumberSequenceService $numbers): int
    {
        if(!empty($data['new_client_name'])) $data['client_id']=DB::table('clients')->insertGetId(['number'=>$numbers->next('client'),'name'=>$data['new_client_name'],'contact'=>$data['new_client_contact'],'type'=>$data['new_client_type'],'address'=>$data['new_client_address']??null,'notes'=>'Créé directement depuis un paiement','active'=>true,'credit_balance'=>0,'created_at'=>now(),'updated_at'=>now()]);
        $data=Arr::except($data,['new_client_name','new_client_contact','new_client_type','new_client_address']);
        $amount=(float)$data['amount']; $allocated=$this->paymentAllocation($data);
        $id=DB::table('client_payments')->insertGetId([...$data,'allocated_amount'=>$allocated,'status'=>'valide','created_at'=>now(),'updated_at'=>now()]);
        if($amount>$allocated) DB::table('clients')->where('id',$data['client_id'])->increment('credit_balance',$amount-$allocated);
        if(!empty($data['order_id'])) { $order=DB::table('orders')->lockForUpdate()->find($data['order_id']); DB::table('orders')->where('id',$order->id)->update(['deposit'=>(float)$order->deposit+$allocated,'balance_due'=>max(0,(float)$order->balance_due-$allocated),'updated_at'=>now()]); }
        if(!empty($data['invoice_id'])) { $invoice=DB::table('invoices')->lockForUpdate()->find($data['invoice_id']); $paid=(float)$invoice->paid_amount+$allocated; DB::table('invoices')->where('id',$invoice->id)->update(['paid_amount'=>$paid,'balance_due'=>max(0,(float)$invoice->subtotal-$paid),'status'=>$paid>=(float)$invoice->subtotal?'payee':'partielle','updated_at'=>now()]); }
        return $id;
    }

    private function createInvoice(array $data, NumberSequenceService $numbers): int
    {
        $order=DB::table('orders')->find($data['order_id']); $paid=(float)($data['paid_amount']??0); $subtotal=(float)$data['subtotal']; if($paid>$subtotal) throw ValidationException::withMessages(['paid_amount'=>'Le montant reçu ne peut pas dépasser le total.']);
        $orderItems=DB::table('order_items')->where('order_id',$order->id)->orderBy('id')->get();
        $orderItemsTotal=(float)$orderItems->sum('client_total');
        $factor=$orderItemsTotal>0?$subtotal/$orderItemsTotal:1;
        $lines=$data['type']==='produits'
            ? $orderItems->map(function($item) use($factor){
                $quantity=(float)$item->quantity;
                $amount=(float)$item->client_total*$factor;
                return ['label'=>$item->name,'quantity'=>$quantity,'unit_price'=>$quantity>0?$amount/$quantity:0,'amount'=>$amount];
            })->values()->all()
            : [['label'=>'Frais de commande','quantity'=>1,'unit_price'=>$subtotal,'amount'=>$subtotal]];
        return DB::table('invoices')->insertGetId(['number'=>$numbers->next($data['type']==='frais'?'fee_invoice':'invoice'),'order_id'=>$order->id,'client_id'=>$order->client_id,'type'=>$data['type'],'status'=>$paid>0&&$paid<$subtotal?'partielle':$data['status'],'issued_at'=>$data['issued_at'],'subtotal'=>$subtotal,'paid_amount'=>$paid,'balance_due'=>$subtotal-$paid,'lines'=>json_encode($lines),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function createSupplierPayment(array $data): int
    {
        $proof=$data['proof']??null;
        $id=DB::table('supplier_payments')->insertGetId([...Arr::except($data,['order_id','proof']),'proof_path'=>$this->storePurchaseProof($proof),'created_at'=>now(),'updated_at'=>now()]); DB::table('supplier_payment_allocations')->insert(['supplier_payment_id'=>$id,'order_id'=>$data['order_id'],'amount'=>$data['amount']]); return $id;
    }

    private function storePurchaseProof(?UploadedFile $proof): ?string
    {
        return $this->storage->storePaymentProof($proof);
    }

    private function createLocalSale(array $data, int $userId): int
    {
        $product=DB::table('inventory_products')->lockForUpdate()->find($data['inventory_product_id']); $quantity=(float)$data['quantity']; if($quantity>(float)$product->quantity) throw ValidationException::withMessages(['quantity'=>'Stock insuffisant. Quantité disponible : '.$product->quantity]);
        $total=$quantity*(float)$data['unit_price']; $paid=(float)($data['paid_amount']??0); if($paid>$total) throw ValidationException::withMessages(['paid_amount'=>'Le paiement ne peut pas dépasser le total de la vente.']); if($paid<$total&&empty($data['buyer_name'])) throw ValidationException::withMessages(['buyer_name'=>'Le nom de l’acheteur est obligatoire pour une vente non soldée.']); if($paid<$total&&empty($data['buyer_contact'])) throw ValidationException::withMessages(['buyer_contact'=>'Le contact est obligatoire pour une vente non soldée.']);
        $after=(float)$product->quantity-$quantity; DB::table('inventory_products')->where('id',$product->id)->update(['quantity'=>$after,'stock_value'=>$after*(float)$product->purchase_price,'exited_at'=>today(),'updated_at'=>now()]); DB::table('stock_movements')->insert(['inventory_product_id'=>$product->id,'type'=>'sortie','quantity'=>$quantity,'before_quantity'=>$product->quantity,'after_quantity'=>$after,'notes'=>'Vente locale','moved_at'=>now(),'user_id'=>$userId]);
        return DB::table('local_sales')->insertGetId([...$data,'total'=>$total,'balance_due'=>$total-$paid,'status'=>$paid>=$total?'paye':($paid>0?'partiel':'credit'),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function createSalary(array $data, BusinessCalculator $calculator): int
    {
        $calc=$calculator->salary($data['gross_salary'],$data['irsa_mode'],$data['irsa_value']??0); $id=DB::table('salaries')->insertGetId(['employee_id'=>$data['employee_id'],'month'=>date('Y-m-01',strtotime($data['month'])),'gross_salary'=>$data['gross_salary'],'irsa_mode'=>$data['irsa_mode'],'irsa_rate'=>$data['irsa_mode']==='pourcentage'?$data['irsa_value']:null,'irsa_amount'=>$calc['irsa'],'net_salary'=>$calc['net'],'paid_at'=>$data['paid_at']??null,'status'=>$data['status'],'created_at'=>now(),'updated_at'=>now()]);
        if($data['status']==='paye') { DB::table('expenses')->insert(['category'=>'salaire','amount'=>$calc['net'],'spent_at'=>$data['paid_at']??today(),'type'=>'business','description'=>'Salaire net automatique','order_id'=>null,'status'=>'paye','source_type'=>'salary','source_id'=>$id,'created_at'=>now(),'updated_at'=>now()]); DB::table('expenses')->insert(['category'=>'IRSA','amount'=>$calc['irsa'],'spent_at'=>$data['paid_at']??today(),'type'=>'business','description'=>'IRSA automatique','order_id'=>null,'status'=>'paye','source_type'=>'salary_irsa','source_id'=>$id,'created_at'=>now(),'updated_at'=>now()]); }
        return $id;
    }

    private function editValues(string $module, object $record): array
    {
        $values=(array)$record;
        if($module==='devis') { $values['client_contact']=$record->contact; $item=DB::table('quote_items')->where('quote_id',$record->id)->first(); if($item) $values=[...$values,'product_name'=>$item->name,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'commission'=>$item->commission]; }
        if($module==='commandes') { $item=DB::table('order_items')->where('order_id',$record->id)->first(); if($item) $values=[...$values,'product_name'=>$item->name,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'supplier_price'=>$record->supplier_total]; }
        if($module==='achats') $values['order_id']=DB::table('supplier_payment_allocations')->where('supplier_payment_id',$record->id)->value('order_id');
        if($module==='salaires') { $values['irsa_value']=$record->irsa_mode==='pourcentage'?$record->irsa_rate:$record->irsa_amount; $values['month']=date('Y-m',strtotime($record->month)); }
        return $values;
    }

    private function applyUpdate(string $module, int $id, object $old, array $data, int $userId, BusinessCalculator $calculator): void
    {
        if($module==='fournisseurs') { $products=$data['products']??[]; DB::table('suppliers')->where('id',$id)->update([...Arr::except($data,'products'),'updated_at'=>now()]); $this->replaceSupplierProducts($id,$products); return; }
        if(in_array($module,['clients','logistique','depenses','employes'],true)) { DB::table($this->config($module)['table'])->where('id',$id)->update([...$data,'updated_at'=>now()]); return; }
        if($module==='stock') { $photo=$data['photo']??null; unset($data['photo']); $before=(float)$old->quantity; $after=(float)$data['quantity']; DB::table('inventory_products')->where('id',$id)->update([...$data,'photo_path'=>$photo?$this->storePhoto($photo):$old->photo_path,'stock_value'=>$after*(float)$data['purchase_price'],'entered_at'=>$after>$before?today():$old->entered_at,'exited_at'=>$after<$before?today():$old->exited_at,'updated_at'=>now()]); if($after!==$before) DB::table('stock_movements')->insert(['inventory_product_id'=>$id,'type'=>'inventaire','quantity'=>abs($after-$before),'before_quantity'=>$before,'after_quantity'=>$after,'notes'=>'Correction manuelle auditée','moved_at'=>now(),'user_id'=>$userId]); return; }
        if($module==='devis') {
            $client=DB::table('clients')->find($data['client_id']); $supplierTotal=collect($data['items'])->sum(fn($item)=>(float)$item['supplier_price']*(float)$item['quantity']); $logistics=collect($data['items'])->sum(fn($item)=>(float)($item['china_delivery']??0)+(float)($item['packaging']??0)+(float)($item['freight']??0)); $margin=collect($data['items'])->sum(fn($item)=>(float)($item['margin']??0)); $total=collect($data['items'])->sum(fn($item)=>(float)$item['total']);
            DB::table('quotes')->where('id',$id)->update(['client_id'=>$client->id,'client_name'=>$data['client_name'],'contact'=>$data['client_contact'],'client_type'=>$client->type,'sent_at'=>$data['status']==='brouillon'?null:($old->sent_at??today()),'valid_until'=>$data['valid_until'],'shipping_mode'=>$data['shipping_mode'],'shipping_delay'=>$data['shipping_delay']??null,'bank_details'=>$data['bank_details']??null,'payment_terms'=>$data['payment_terms']??null,'warranty'=>$data['warranty']??null,'notes'=>$data['notes']??null,'status'=>$data['status'],'supplier_estimate'=>$supplierTotal,'logistics_estimate'=>$logistics,'margin'=>$margin,'total'=>$total,'updated_at'=>now()]);
            $photos=DB::table('quote_items')->where('quote_id',$id)->pluck('photo_path','id')->all(); DB::table('quote_items')->where('quote_id',$id)->delete(); $this->insertQuoteItems($id,$data['items'],$photos); return;
        }
        if($module==='commandes') {
            $total=collect($data['items'])->sum(fn($item)=>(float)$item['client_total']); $deposit=(float)($data['deposit']??0); if($deposit>$total) throw ValidationException::withMessages(['deposit'=>'L’acompte ne peut pas dépasser le total client.']); $supplierTotal=collect($data['items'])->sum(fn($item)=>(float)$item['supplier_price']*(float)$item['quantity']); $freight=collect($data['items'])->sum(fn($item)=>(float)($item['freight']??0)); $margin=collect($data['items'])->sum(fn($item)=>(float)($item['margin']??0)); $cbm=collect($data['packages']??[])->sum(fn($package)=>(float)($package['volume_cbm']??0))?:collect($data['items'])->sum(fn($item)=>(float)($item['cbm']??0)); $enabled=(bool)($data['commission_enabled']??false); $commission=$enabled?collect($data['items'])->sum(fn($item)=>(float)($item['commission']??0)):0;
            DB::table('orders')->where('id',$id)->update(['client_id'=>$data['client_id'],'quote_id'=>$data['quote_id']??null,'origin'=>!empty($data['quote_id'])?'devis':'directe','ordered_at'=>$data['ordered_at'],'shipping_mode'=>$data['shipping_mode']??null,'cbm'=>$cbm,'freight'=>$freight,'supplier_total'=>$supplierTotal,'commission_enabled'=>$enabled,'commission_base'=>$supplierTotal,'commission_rate'=>$data['commission_rate']??8,'commission_amount'=>$commission,'margin'=>$margin,'client_total'=>$total,'deposit'=>$deposit,'balance_due'=>$total-$deposit,'status'=>$data['status'],'notes'=>$data['notes']??null,'updated_at'=>now()]);
            $photos=DB::table('order_items')->where('order_id',$id)->pluck('photo_path','id')->all(); DB::table('order_packages')->where('order_id',$id)->delete(); DB::table('order_items')->where('order_id',$id)->delete(); $itemIds=$this->insertOrderItems($id,$data['items'],$enabled,$data['status'],$photos); $this->insertPackages($id,$data['packages']??[],$itemIds,$data['items']); return;
        }
        if($module==='paiements') { $data=Arr::except($data,['new_client_name','new_client_contact','new_client_type','new_client_address']); $amount=(float)$data['amount']; $allocated=$this->paymentAllocation($data); $this->reversePaymentEffects($old); DB::table('client_payments')->where('id',$id)->update([...$data,'allocated_amount'=>$allocated,'status'=>'valide','updated_at'=>now()]); $this->applyPaymentEffects((object)[...$data,'amount'=>$amount,'allocated_amount'=>$allocated]); return; }
        if($module==='factures') { $order=DB::table('orders')->find($data['order_id']); $subtotal=(float)$data['subtotal']; $paid=(float)($data['paid_amount']??0); if($paid>$subtotal) throw ValidationException::withMessages(['paid_amount'=>'Le montant reçu ne peut pas dépasser le total.']); DB::table('invoices')->where('id',$id)->update(['order_id'=>$order->id,'client_id'=>$order->client_id,'type'=>$data['type'],'issued_at'=>$data['issued_at'],'subtotal'=>$subtotal,'paid_amount'=>$paid,'balance_due'=>$subtotal-$paid,'status'=>$paid>=$subtotal?'payee':($paid>0?'partielle':$data['status']),'lines'=>json_encode([['label'=>$data['type']==='frais'?'Frais de commande':'Produits commandés','amount'=>$subtotal]]),'updated_at'=>now()]); return; }
        if($module==='achats') { $proof=$data['proof']??null; DB::table('supplier_payments')->where('id',$id)->update([...Arr::except($data,['order_id','proof']),'proof_path'=>$proof?$this->storePurchaseProof($proof):$old->proof_path,'updated_at'=>now()]); DB::table('supplier_payment_allocations')->where('supplier_payment_id',$id)->update(['order_id'=>$data['order_id'],'amount'=>$data['amount']]); return; }
        if($module==='ventes') { $this->updateLocalSale($id,$old,$data,$userId); return; }
        if($module==='salaires') { $calc=$calculator->salary($data['gross_salary'],$data['irsa_mode'],$data['irsa_value']??0); DB::table('salaries')->where('id',$id)->update(['employee_id'=>$data['employee_id'],'month'=>date('Y-m-01',strtotime($data['month'])),'gross_salary'=>$data['gross_salary'],'irsa_mode'=>$data['irsa_mode'],'irsa_rate'=>$data['irsa_mode']==='pourcentage'?$data['irsa_value']:null,'irsa_amount'=>$calc['irsa'],'net_salary'=>$calc['net'],'paid_at'=>$data['paid_at']??null,'status'=>$data['status'],'updated_at'=>now()]); DB::table('expenses')->where('source_type','salary')->where('source_id',$id)->update(['amount'=>$calc['net'],'spent_at'=>$data['paid_at']??today(),'updated_at'=>now()]); DB::table('expenses')->where('source_type','salary_irsa')->where('source_id',$id)->update(['amount'=>$calc['irsa'],'spent_at'=>$data['paid_at']??today(),'updated_at'=>now()]); return; }
        if($module==='fiscalite') { DB::table('tax_records')->where('id',$id)->update([...Arr::except($data,'rate'),'rate'=>$data['rate'],'calculated_amount'=>(float)$calculator->commission($data['base_amount'],$data['rate']),'updated_at'=>now()]); return; }
        abort(404);
    }

    private function reversePaymentEffects(object $payment): void
    {
        $unallocated=max(0,(float)$payment->amount-(float)$payment->allocated_amount); $client=DB::table('clients')->lockForUpdate()->find($payment->client_id); DB::table('clients')->where('id',$client->id)->update(['credit_balance'=>max(0,(float)$client->credit_balance-$unallocated),'updated_at'=>now()]);
        if($payment->order_id) { $order=DB::table('orders')->lockForUpdate()->find($payment->order_id); DB::table('orders')->where('id',$order->id)->update(['deposit'=>max(0,(float)$order->deposit-(float)$payment->allocated_amount),'balance_due'=>min((float)$order->client_total,(float)$order->balance_due+(float)$payment->allocated_amount),'updated_at'=>now()]); }
        if($payment->invoice_id) { $invoice=DB::table('invoices')->lockForUpdate()->find($payment->invoice_id); $paid=max(0,(float)$invoice->paid_amount-(float)$payment->allocated_amount); DB::table('invoices')->where('id',$invoice->id)->update(['paid_amount'=>$paid,'balance_due'=>(float)$invoice->subtotal-$paid,'status'=>$paid>0?'partielle':'finale','updated_at'=>now()]); }
    }

    private function paymentAllocation(array $data): float
    {
        $amount=(float)$data['amount'];
        if(empty($data['order_id'])&&empty($data['invoice_id'])) return 0;
        $allocated=(float)($data['allocated_amount']??0);
        if($allocated>$amount) throw ValidationException::withMessages(['allocated_amount'=>'Le montant affecté ne peut pas dépasser le montant reçu.']);
        if(!empty($data['order_id'])&&DB::table('orders')->where('id',$data['order_id'])->value('client_id')!=$data['client_id']) throw ValidationException::withMessages(['order_id'=>'Cette commande n’appartient pas au client sélectionné.']);
        if(!empty($data['invoice_id'])&&DB::table('invoices')->where('id',$data['invoice_id'])->value('client_id')!=$data['client_id']) throw ValidationException::withMessages(['invoice_id'=>'Cette facture n’appartient pas au client sélectionné.']);
        return $allocated;
    }

    private function paymentTypeLabel(string $type): string
    {
        return [
            'acompte_commande'=>'Acompte de commande',
            'solde_commande'=>'Solde de commande',
            'fournisseur_chine'=>'Paiement fournisseur en Chine',
            'fret_transport'=>'Frais de fret / transport',
            'frais_service'=>'Frais de service',
            'autre'=>'Autre',
            'acompte'=>'Acompte',
            'intermediaire'=>'Paiement intermédiaire',
            'solde'=>'Solde',
            'remboursement'=>'Remboursement',
        ][$type] ?? ucfirst(str_replace('_',' ',$type));
    }

    private function applyPaymentEffects(object $payment): void
    {
        $unallocated=max(0,(float)$payment->amount-(float)$payment->allocated_amount); if($unallocated>0) DB::table('clients')->where('id',$payment->client_id)->increment('credit_balance',$unallocated);
        if($payment->order_id) { $order=DB::table('orders')->lockForUpdate()->find($payment->order_id); DB::table('orders')->where('id',$order->id)->update(['deposit'=>(float)$order->deposit+(float)$payment->allocated_amount,'balance_due'=>max(0,(float)$order->balance_due-(float)$payment->allocated_amount),'updated_at'=>now()]); }
        if($payment->invoice_id) { $invoice=DB::table('invoices')->lockForUpdate()->find($payment->invoice_id); $paid=(float)$invoice->paid_amount+(float)$payment->allocated_amount; DB::table('invoices')->where('id',$invoice->id)->update(['paid_amount'=>$paid,'balance_due'=>max(0,(float)$invoice->subtotal-$paid),'status'=>$paid>=(float)$invoice->subtotal?'payee':'partielle','updated_at'=>now()]); }
    }

    private function updateLocalSale(int $id, object $old, array $data, int $userId): void
    {
        $oldProduct=DB::table('inventory_products')->lockForUpdate()->find($old->inventory_product_id); DB::table('inventory_products')->where('id',$oldProduct->id)->update(['quantity'=>(float)$oldProduct->quantity+(float)$old->quantity,'stock_value'=>((float)$oldProduct->quantity+(float)$old->quantity)*(float)$oldProduct->purchase_price,'updated_at'=>now()]);
        $product=DB::table('inventory_products')->lockForUpdate()->find($data['inventory_product_id']); $quantity=(float)$data['quantity']; if($quantity>(float)$product->quantity) throw ValidationException::withMessages(['quantity'=>'Stock insuffisant pour corriger cette vente.']); $total=$quantity*(float)$data['unit_price']; $paid=(float)($data['paid_amount']??0); if($paid>$total) throw ValidationException::withMessages(['paid_amount'=>'Le paiement ne peut pas dépasser le total.']); if($paid<$total&&(empty($data['buyer_name'])||empty($data['buyer_contact']))) throw ValidationException::withMessages(['buyer_name'=>'Nom et contact requis pour une vente non soldée.']); $after=(float)$product->quantity-$quantity; DB::table('inventory_products')->where('id',$product->id)->update(['quantity'=>$after,'stock_value'=>$after*(float)$product->purchase_price,'exited_at'=>today(),'updated_at'=>now()]); DB::table('local_sales')->where('id',$id)->update([...$data,'total'=>$total,'balance_due'=>$total-$paid,'status'=>$paid>=$total?'paye':($paid>0?'partiel':'credit'),'updated_at'=>now()]); DB::table('stock_movements')->insert(['inventory_product_id'=>$product->id,'type'=>'inventaire','quantity'=>$quantity,'before_quantity'=>$product->quantity,'after_quantity'=>$after,'notes'=>'Correction vente locale','moved_at'=>now(),'user_id'=>$userId]);
    }

    private function decorate(array $rows): array
    {
        $maps=['client_id'=>DB::table('clients')->pluck('name','id'),'supplier_id'=>DB::table('suppliers')->pluck('name','id'),'order_id'=>DB::table('orders')->pluck('number','id'),'inventory_product_id'=>DB::table('inventory_products')->pluck('name','id'),'employee_id'=>DB::table('employees')->pluck('name','id'),'user_id'=>DB::table('users')->pluck('name','id')];
        return array_map(function($row) use($maps){foreach($maps as $key=>$map) if(isset($row[$key])) $row[$key]=$map[$row[$key]]??$row[$key]; if(array_key_exists('proof_path',$row)) $row['proof_path']=$row['proof_path']?'/purchase-proof/'.basename($row['proof_path']):($row['proof_url']??null); return $row;},$rows);
    }

    private function fields(string $module): array
    {
        $select=fn($name,$label,$options,$required=true,$default=null)=>compact('name','label','options','required','default')+['type'=>'select']; $input=fn($name,$label,$type='text',$required=true,$default=null)=>compact('name','label','type','required','default');
        $clients=DB::table('clients')->where('active',true)->orderBy('name')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->name,'contact'=>$x->contact,'address'=>$x->address,'type'=>$x->type,'credit_balance'=>(float)$x->credit_balance])->all(); $suppliers=DB::table('suppliers')->where('active',true)->orderBy('name')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->name,'contact'=>$x->contact])->all(); $orders=DB::table('orders')->orderByDesc('id')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->number])->all(); $quotes=DB::table('quotes')->whereNull('deleted_at')->orderByDesc('id')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->number])->all(); $products=DB::table('inventory_products')->where('quantity','>',0)->orderBy('name')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->name.' ('.$x->quantity.')'])->all(); $employees=DB::table('employees')->where('active',true)->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->name])->all(); $invoices=DB::table('invoices')->orderByDesc('id')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->number])->all();
        $o=fn(array $values)=>array_map(fn($v)=>['value'=>$v,'label'=>ucfirst(str_replace('_',' ',$v))],$values); $today=today()->toDateString();
        return match($module){
            'clients'=>[$input('name','Nom ou raison sociale'),$input('contact','WhatsApp / téléphone'),$select('type','Type de client',$o(['revendeur','entrepreneur','particulier','hotel'])),$input('address','Adresse','text',false),$input('notes','Notes','textarea',false),$select('active','Statut',[['value'=>1,'label'=>'Actif'],['value'=>0,'label'=>'Inactif']],true,1)],
            'fournisseurs'=>[$input('name','Nom du fournisseur'),$input('category','Catégorie','text',false),$input('moq','MOQ','number',false),$input('production_days','Délai de production (jours)','number',false),$input('contact','Contact','text',false),$select('quality_rating','Qualité',$o(['1','2','3','4','5']),true,3),$input('notes','Notes','textarea',false)],
            'stock'=>[$input('name','Nom du produit'),$input('photo','Photo du produit (optionnelle)','file',false),$input('quantity','Quantité initiale','number',true,0),$input('purchase_price','Prix d’achat (Ar)','number'),$input('sale_price','Prix de vente (Ar)','number'),$input('alert_threshold','Seuil d’alerte','number',false)],
            'devis'=>[$select('client_id','Client enregistré (optionnel)',$clients,false),$input('client_name','Nom du client'),$input('client_contact','Contact du client'),$select('client_type','Type de client',$o(['revendeur','entrepreneur','particulier','hotel']),true,'particulier'),$input('valid_until','Valide jusqu’au','date'),$select('shipping_mode','Mode d’envoi',[['value'=>'maritime','label'=>'Maritime'],['value'=>'aerien','label'=>'Aérien']]),$input('shipping_delay','Délai d’expédition','text',false),$select('status','Statut',[['value'=>'brouillon','label'=>'Brouillon'],['value'=>'envoye','label'=>'Envoyé'],['value'=>'negociation','label'=>'Négociation'],['value'=>'accepte','label'=>'Accepté'],['value'=>'refuse','label'=>'Refusé'],['value'=>'sans_reponse','label'=>'Sans réponse'],['value'=>'relance_1','label'=>'Relance 1'],['value'=>'relance_2','label'=>'Relance 2']],true,'brouillon'),$input('bank_details','Informations bancaires / compte bancaire','textarea',false),$input('payment_terms','Conditions de paiement','textarea',false),$input('warranty','Garantie','textarea',false),$input('notes','Note / remarque','textarea',false)],
            'commandes'=>[$select('quote_id','Créer à partir du devis n°',$quotes,false),$select('client_id','Client',$clients),$select('commission_enabled','Appliquer une commission',[['value'=>0,'label'=>'Non'],['value'=>1,'label'=>'Oui']],true,0),$input('commission_rate','Taux commission (%)','number',false,8),$input('deposit','Acompte reçu (Ar)','number',false,0),$input('ordered_at','Date de commande','date',true,$today),$select('shipping_mode','Mode d’envoi',$o(['aerien','maritime']),false),$select('status','Statut',[['value'=>'brouillon','label'=>'Brouillon'],['value'=>'demande_recue','label'=>'Demande reçue'],['value'=>'attente_validation','label'=>'Attente validation'],['value'=>'confirmee','label'=>'Confirmée'],['value'=>'acompte_recu','label'=>'Acompte reçu'],['value'=>'achat_lance','label'=>'Achat lancé'],['value'=>'achat_effectue','label'=>'Achat effectué']],true,'brouillon'),$input('notes','Notes internes','textarea',false)],
            'paiements'=>[$select('client_id','Client',$clients),$select('order_id','Commande à créditer (optionnelle)',$orders,false),$select('invoice_id','Facture à créditer (optionnelle)',$invoices,false),$input('paid_at','Date','date',true,$today),$input('amount','Montant reçu (Ar)','number'),$input('allocated_amount','Montant affecté (Ar)','number',false,0),$select('method','Mode de paiement',$o(['Mobile Money','Virement bancaire','Espèces','Chèque'])),$input('reference','Référence','text',false),$select('type','Motif du paiement',[['value'=>'acompte_commande','label'=>'Acompte de commande'],['value'=>'solde_commande','label'=>'Solde de commande'],['value'=>'fournisseur_chine','label'=>'Paiement fournisseur en Chine'],['value'=>'fret_transport','label'=>'Frais de fret / transport'],['value'=>'frais_service','label'=>'Frais de service'],['value'=>'autre','label'=>'Autre']]),$input('notes','Précision / notes (obligatoire si Autre)','textarea',false)],
            'factures'=>[$select('order_id','Commande',$orders),$select('type','Type de facture',$o(['produits','frais'])),$input('issued_at','Date','date',true,$today),$input('subtotal','Total (Ar)','number'),$input('paid_amount','Montant déjà reçu (Ar)','number',false,0),$select('status','Statut',$o(['brouillon','provisoire','finale','payee','partielle']),true,'brouillon')],
            'achats'=>[$select('supplier_id','Fournisseur',$suppliers),$select('order_id','Commande concernée',$orders),$input('paid_at','Date','date',true,$today),$input('amount','Montant payé (Ar)','number'),$select('method','Mode',$o(['WeChat','Alipay','banque'])),$input('reference','Référence','text',false),$input('proof','Justificatif — capture (optionnelle)','file',false),$input('proof_url','Justificatif — lien (optionnel)','url',false),$select('status','Statut du paiement',$o(['paye','partiel','en_attente'])),$input('notes','Notes de suivi','textarea',false)],
            'logistique'=>[$select('order_id','Commande',$orders),$input('tracking','Tracking fournisseur','text',false),$select('mode','Mode',$o(['aerien','maritime'])),$input('weight','Poids (kg)','number',false),$input('cbm','Volume / CBM','number',false),$input('cost','Coût de livraison (Ar)','number',false,0),$input('forwarder','Transitaire','text',false),$input('china_departure_at','Départ de Chine','date',false),$input('expected_madagascar_at','Arrivée prévue','date',false),$select('status','Statut',$o(['en_attente','en_transit','arrive_en_chine','expedie','arrive_madagascar','remis_client']))],
            'ventes'=>[$select('inventory_product_id','Produit',$products),$input('sold_at','Date de vente','date',true,$today),$input('quantity','Quantité','number'),$input('unit_price','Prix unitaire (Ar)','number'),$input('paid_amount','Montant payé (Ar)','number',false,0),$select('payment_method','Mode de paiement',$o(['Espèces','Mobile Money','Virement']),false),$input('buyer_name','Nom acheteur si crédit','text',false),$input('buyer_contact','Contact acheteur si crédit','text',false),$input('notes','Note','textarea',false)],
            'depenses'=>[$select('category','Catégorie',[['value'=>'achat','label'=>'Achat'],['value'=>'logistique','label'=>'Logistique'],['value'=>'marketing','label'=>'Marketing'],['value'=>'transport','label'=>'Transport'],['value'=>'loyer_depot_chine','label'=>'Loyer dépôt en Chine'],['value'=>'loyer_depot_madagascar','label'=>'Loyer dépôt à Madagascar'],['value'=>'loyer_bureau','label'=>'Loyer de bureau'],['value'=>'services_publics','label'=>'Eau, électricité et services'],['value'=>'salaire','label'=>'Salaire'],['value'=>'IRSA','label'=>'IRSA'],['value'=>'autre','label'=>'Autre']]),$input('amount','Montant (Ar)','number'),$input('spent_at','Date','date',true,$today),$select('type','Type de dépense',[['value'=>'business','label'=>'Professionnelle'],['value'=>'personnel','label'=>'Personnelle']]),$input('description','Description','textarea'),$select('order_id','Commande liée (optionnelle)',$orders,false),$select('status','Statut',$o(['paye','en_attente']),true,'paye')],
            'employes'=>[$input('name','Nom et prénom'),$input('position','Poste'),$input('monthly_salary','Salaire mensuel habituel (Ar)','number'),$select('irsa_mode','Mode IRSA par défaut',$o(['pourcentage','fixe'])),$input('irsa_value','Taux (%) ou montant IRSA','number'),$select('active','Statut',[['value'=>1,'label'=>'Actif'],['value'=>0,'label'=>'Inactif']],true,1),$input('left_at','Date de départ','date',false),$input('departure_reason','Motif du départ','textarea',false)],
            'salaires'=>[$select('employee_id','Employé',$employees),$input('month','Mois','month'),$input('gross_salary','Salaire brut (Ar)','number'),$select('irsa_mode','Mode IRSA',$o(['pourcentage','fixe'])),$input('irsa_value','Taux (%) ou montant fixe','number'),$input('paid_at','Date de paiement','date',false),$select('status','Statut',$o(['a_payer','paye']))],
            'fiscalite'=>[$select('type','Type',$o(['IRSA','impot_synthetique'])),$input('period','Période'),$input('fiscal_year','Année fiscale','number',true,date('Y')),$select('calculation_base','Base',$o(['ca_facture','ca_encaisse','salaires_bruts'])),$input('base_amount','Montant de la base (Ar)','number'),$input('rate','Taux (%)','number'),$input('declared_amount','Montant déclaré (Ar)','number',false),$input('due_at','Date limite','date',false),$select('status','Statut',$o(['estimation','a_declarer','declare','paye']),true,'estimation')],
            default=>abort(404),
        };
    }

    private function itemFields(string $module): array
    {
        if(!in_array($module,['devis','commandes'],true)) return [];
        $suppliers=DB::table('suppliers')->where('active',true)->orderBy('name')->get()->map(fn($x)=>['value'=>$x->id,'label'=>$x->name,'contact'=>$x->contact])->all();
        $base=[['name'=>'name','label'=>'Nom du produit / article','type'=>'text','required'=>true]];
        $base[]=['name'=>'photo','label'=>$module==='devis'?'Photo du produit sur le devis':'Photo réelle du produit','type'=>'file','required'=>false];
        $base=[...$base,['name'=>'specifications','label'=>'Spécifications','type'=>'textarea','required'=>false],['name'=>'quantity','label'=>'Quantité','type'=>'number','required'=>true],['name'=>'supplier_id','label'=>'Sélectionner un fournisseur','type'=>'select','required'=>false,'options'=>$suppliers],['name'=>'supplier_name','label'=>'Nom du fournisseur (saisie manuelle)','type'=>'text','required'=>false],['name'=>'supplier_contact','label'=>'Contact fournisseur','type'=>'text','required'=>false],['name'=>'source_url','label'=>'Lien 1688 / Taobao','type'=>'url','required'=>false],['name'=>'supplier_price','label'=>'Prix fournisseur unitaire (Ar)','type'=>'number','required'=>true],['name'=>'china_delivery','label'=>'Livraison locale Chine (Ar)','type'=>'number','required'=>false],['name'=>'packaging','label'=>'Emballage (Ar)','type'=>'number','required'=>false],['name'=>'weight','label'=>'Poids estimé (kg)','type'=>'number','required'=>false],['name'=>'cbm','label'=>'CBM estimé','type'=>'number','required'=>false],['name'=>'freight','label'=>'Fret (Ar)','type'=>'number','required'=>false],['name'=>'margin','label'=>'Marge (Ar)','type'=>'number','required'=>false],['name'=>'commission','label'=>'Commission (Ar)','type'=>'number','required'=>false]];
        return [...$base,['name'=>$module==='devis'?'total':'client_total','label'=>$module==='devis'?'Total estimé de la ligne (Ar)':'Prix final client de la ligne (Ar)','type'=>'number','required'=>true]];
    }

    private function emptyItem(string $module): array
    {
        return collect($this->itemFields($module))->mapWithKeys(fn($field)=>[$field['name']=>in_array($field['name'],['quantity'],true)?1:(in_array($field['type'],['number'],true)?0:'')])->all();
    }

    private function existingItems(string $module, int $id): array
    {
        if($module==='devis') return DB::table('quote_items')->where('quote_id',$id)->orderBy('id')->get()->map(fn($item)=>['id'=>$item->id,'name'=>$item->name,'photo'=>null,'photo_url'=>$item->photo_path?'/product-photo/'.basename($item->photo_path):null,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'supplier_name'=>$item->supplier_name,'supplier_contact'=>$item->supplier_contact,'source_url'=>$item->source_url,'supplier_price'=>$item->supplier_price,'china_delivery'=>$item->china_delivery,'packaging'=>$item->packaging,'weight'=>$item->estimated_weight,'cbm'=>$item->estimated_cbm,'freight'=>$item->estimated_freight,'margin'=>$item->margin,'commission'=>$item->commission,'total'=>$item->total])->all();
        if($module==='commandes') return DB::table('order_items')->where('order_id',$id)->orderBy('id')->get()->map(fn($item)=>['id'=>$item->id,'name'=>$item->name,'photo'=>null,'photo_url'=>$item->photo_path?'/product-photo/'.basename($item->photo_path):null,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'supplier_name'=>$item->supplier_name,'supplier_contact'=>$item->supplier_contact,'source_url'=>$item->source_url,'supplier_price'=>$item->supplier_price,'china_delivery'=>$item->china_delivery,'packaging'=>$item->packaging,'weight'=>$item->weight,'cbm'=>$item->cbm,'freight'=>$item->freight,'margin'=>$item->margin,'commission'=>$item->commission,'client_total'=>$item->client_total])->all();
        return [];
    }

    private function existingPackages(string $module, int $id): array
    {
        if($module!=='commandes'||!DB::getSchemaBuilder()->hasTable('order_packages')) return [];
        $itemIndexes=DB::table('order_items')->where('order_id',$id)->orderBy('id')->pluck('id')->values()->flip();
        return DB::table('order_packages')->where('order_id',$id)->orderBy('id')->get()->map(function($package) use($itemIndexes){
            $items=DB::table('order_package_items')->where('order_package_id',$package->id)->orderBy('id')->get()->map(fn($line)=>['item_index'=>$itemIndexes[$line->order_item_id]??0,'quantity'=>$line->quantity])->all();
            return ['reference'=>$package->reference,'billing_unit'=>$package->billing_unit,'weight_kg'=>$package->weight_kg,'volume_cbm'=>$package->volume_cbm,'notes'=>$package->notes,'items'=>$items];
        })->all();
    }

    private function orderProducts(string $module): array
    {
        if(!in_array($module,['factures','logistique'],true)) return [];
        return DB::table('order_items')->orderBy('id')->get()->groupBy('order_id')->map(fn($items)=>$items->map(fn($item)=>['name'=>$item->name,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'client_total'=>$item->client_total,'unit_price'=>(float)$item->quantity>0?(float)$item->client_total/(float)$item->quantity:0,'photo_url'=>$item->photo_path?'/product-photo/'.basename($item->photo_path):null])->values()->all())->all();
    }

    private function orderTemplates(string $module): array
    {
        if($module!=='factures') return [];
        return DB::table('orders')->whereNull('deleted_at')->orderByDesc('id')->get()->map(fn($order)=>['id'=>$order->id,'number'=>$order->number,'client_id'=>$order->client_id,'subtotal'=>(float)$order->client_total,'paid_amount'=>(float)$order->deposit,'balance_due'=>(float)$order->balance_due])->keyBy('id')->all();
    }

    private function invoicePrefill(string $module, int $orderId): array
    {
        if($module!=='factures'||!$orderId) return [];
        $order=DB::table('orders')->whereNull('deleted_at')->find($orderId);
        if(!$order) return [];
        $paid=(float)$order->deposit; $total=(float)$order->client_total;
        return ['order_id'=>$order->id,'type'=>'produits','issued_at'=>today()->toDateString(),'subtotal'=>$total,'paid_amount'=>$paid,'status'=>$paid>=$total?'payee':($paid>0?'partielle':'brouillon')];
    }

    private function quoteTemplates(string $module): array
    {
        if($module!=='commandes') return [];
        return DB::table('quotes')->whereNull('deleted_at')->orderByDesc('id')->get()->map(function($quote){
            $client=DB::table('clients')->find($quote->client_id);
            $items=DB::table('quote_items')->where('quote_id',$quote->id)->orderBy('id')->get()->map(fn($item)=>['quote_item_id'=>$item->id,'name'=>$item->name,'photo'=>null,'photo_url'=>$item->photo_path?'/product-photo/'.basename($item->photo_path):null,'specifications'=>$item->specifications,'quantity'=>$item->quantity,'supplier_id'=>$item->supplier_id,'supplier_name'=>$item->supplier_name,'supplier_contact'=>$item->supplier_contact,'source_url'=>$item->source_url,'supplier_price'=>$item->supplier_price,'china_delivery'=>$item->china_delivery,'packaging'=>$item->packaging,'weight'=>$item->estimated_weight,'cbm'=>$item->estimated_cbm,'freight'=>$item->estimated_freight,'margin'=>$item->margin,'commission'=>$item->commission,'client_total'=>$item->total])->all();
            return ['id'=>$quote->id,'number'=>$quote->number,'client_id'=>$quote->client_id,'client_name'=>$quote->client_name?:$client?->name,'client_contact'=>$quote->contact?:$client?->contact,'shipping_mode'=>$quote->shipping_mode,'items'=>$items];
        })->keyBy('id')->all();
    }

    private function supplierProducts(string $module, int $id): ?array
    {
        if($module!=='fournisseurs') return null;
        return DB::table('supplier_products')->where('supplier_id',$id)->orderBy('id')->get()->map(fn($product)=>[...(array)$product,'photo'=>null,'photo_url'=>$product->photo_path?'/product-photo/'.basename($product->photo_path):null])->all();
    }

    public function export(Request $request, string $module)
    {
        $config=$this->config($module); abort_unless(DB::getSchemaBuilder()->hasTable($config['table']),404);
        $activeFilters=collect($this->filterDefinitions($module))->mapWithKeys(fn($label,$field)=>[$field=>$request->string('filter_'.$field)->toString()])->filter(fn($value)=>$value!=='')->all();
        $query=$this->exportQuery($config,$request->string('q')->toString(),$activeFilters);
        $rows=$this->decorate($query->get()->map(fn($row)=>(array)$row)->all());
        if($module==='devis') $rows=array_map(fn($row)=>[...$row,'created_at'=>$this->chinaDate($row['created_at'])],$rows);
        $columns=$config['columns'];
        if($module==='devis') [$columns,$rows]=$this->quoteExportData($rows);
        $contents=Excel::raw(new StyledModuleExport($module,$config['title'],$columns,$rows),ExcelFormat::XLSX);
        $filename=$module.'-'.now()->format('Y-m-d-His').'.xlsx'; $path=$this->storage->putExport($filename,$contents);
        return $this->storage->download($path,$filename);
    }

    private function quoteExportData(array $quotes): array
    {
        $columns=['number'=>'N° devis','created_at'=>'Date du devis','client_id'=>'Client','product_name'=>'Produit','photo_path'=>'Photo','quantity'=>'Quantité','product_total'=>'Total produit','valid_until'=>'Valide jusqu’au','status'=>'Statut'];
        $items=DB::table('quote_items')->whereIn('quote_id',array_column($quotes,'id'))->orderBy('id')->get()->groupBy('quote_id');
        $rows=[];
        foreach($quotes as $quote){
            $quoteItems=$items->get($quote['id'],collect());
            if($quoteItems->isEmpty()) $quoteItems=collect([null]);
            foreach($quoteItems as $item) $rows[]=[...$quote,'product_name'=>$item?->name,'photo_path'=>$item?->photo_path,'quantity'=>$item?->quantity,'product_total'=>$item?->total];
        }
        return [$columns,$rows];
    }

    private function quoteDate(): string
    {
        return now(config('madina.company.timezone','Asia/Shanghai'))->toDateString();
    }

    private function chinaDate(string $date): string
    {
        return Carbon::parse($date,config('app.timezone'))->timezone(config('madina.company.timezone','Asia/Shanghai'))->toDateString();
    }

    private function exportQuery(array $config, string $search='', array $filters=[])
    {
        $query=DB::table($config['table'])->orderByDesc($config['table'].'.id');
        if(DB::getSchemaBuilder()->hasColumn($config['table'],'deleted_at')) $query->whereNull($config['table'].'.deleted_at');
        if($search!=='') $query->where(function($nested) use($search,$config){
            foreach(array_keys($config['columns']) as $column) if(!str_contains($column,'_id')) $nested->orWhere($column,'like','%'.$search.'%');
        });
        foreach($filters as $field=>$value) $query->where($config['table'].'.'.$field,$value);
        return $query;
    }

    private function filterDefinitions(string $module): array
    {
        return match($module){
            'clients'=>['active'=>'Statut'],
            'devis'=>['status'=>'Statut'],
            'commandes'=>['status'=>'Statut'],
            'paiements'=>['type'=>'Type','status'=>'Statut'],
            'factures'=>['type'=>'Type','status'=>'Statut'],
            'fournisseurs'=>['active'=>'Statut'],
            'achats'=>['method'=>'Mode','status'=>'Statut'],
            'logistique'=>['mode'=>'Mode','status'=>'Statut'],
            'ventes'=>['status'=>'Statut','payment_method'=>'Paiement'],
            'depenses'=>['category'=>'Catégorie','type'=>'Type'],
            'salaires'=>['status'=>'Statut'],
            'employes'=>['active'=>'Statut'],
            'fiscalite'=>['type'=>'Type','status'=>'Statut'],
            'rapports'=>['event'=>'Opération'],
            default=>[],
        };
    }

}
