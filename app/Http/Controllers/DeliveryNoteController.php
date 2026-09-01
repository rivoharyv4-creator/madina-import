<?php

namespace App\Http\Controllers;

use App\Services\NumberSequenceService;
use App\Services\PersistentStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeliveryNoteController extends Controller
{
    private const STATUSES=['a_livrer','livraison_en_cours','livree','livraison_partielle','annulee'];

    public function __construct(private readonly PersistentStorageService $storage)
    {
    }

    public function index(Request $request)
    {
        $query=DB::table('delivery_notes')
            ->join('orders','orders.id','=','delivery_notes.order_id')
            ->join('clients','clients.id','=','orders.client_id')
            ->select('delivery_notes.*','orders.number as order_number','clients.name as client_name')
            ->orderByDesc('delivery_notes.id');
        if($search=trim($request->string('q')->toString())) $query->where(function($q) use($search){$q->where('delivery_notes.number','like',"%$search%")->orWhere('orders.number','like',"%$search%")->orWhere('clients.name','like',"%$search%");});
        $notes=$query->paginate(20)->withQueryString();

        return Inertia::render('DeliveryNotes/Index',['notes'=>$notes,'query'=>$request->q]);
    }

    public function create(Request $request)
    {
        return Inertia::render('DeliveryNotes/Form',[
            'orders'=>$this->orderTemplates(),
            'selectedOrderId'=>$request->integer('order_id')?:null,
            'note'=>null,
            'statuses'=>$this->statusOptions(),
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers)
    {
        $data=$this->validated($request);
        $id=DB::transaction(function() use($request,$data,$numbers){
            $order=DB::table('orders')->whereNull('deleted_at')->lockForUpdate()->find($data['order_id']);
            abort_unless($order,404);
            $signature=$this->storage->storeSignatureData($data['signature_data']??null);
            $proof=$this->storage->storeDeliveryProof($data['proof']??null);
            if(!$signature&&!$proof) throw ValidationException::withMessages(['signature_data'=>'Ajoutez la signature du client ou une preuve de livraison.']);
            $id=DB::table('delivery_notes')->insertGetId([
                'number'=>$numbers->next('delivery_note'),'order_id'=>$order->id,'delivered_at'=>$data['delivered_at'],'delivery_address'=>$data['delivery_address'],'package_count'=>$data['package_count'],'observations'=>$data['observations']??null,'receiver_name'=>$data['receiver_name']??null,'signature_path'=>$signature,'proof_path'=>$proof,'status'=>$data['order_status'],'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->replaceItems($id,$order->id,$data['items']);
            DB::table('orders')->where('id',$order->id)->update(['status'=>$data['order_status'],'updated_at'=>now()]);
            $this->audit($request,'bons_livraison.cree',$id,null,$data);
            return $id;
        });

        return redirect()->route('delivery-notes.edit',$id)->with('success','Bon de livraison créé avec succès.');
    }

    public function edit(int $id)
    {
        $note=DB::table('delivery_notes')->find($id);
        abort_unless($note,404);
        $note->items=DB::table('delivery_note_items')->where('delivery_note_id',$id)->orderBy('id')->get()->map(fn($item)=>['order_item_id'=>$item->order_item_id,'name'=>$item->name,'ordered_quantity'=>(float)$item->ordered_quantity,'delivered_quantity'=>(float)$item->delivered_quantity])->all();
        $note->signature_url=$note->signature_path?route('secure-files.show',['category'=>'delivery','filename'=>basename($note->signature_path)]):null;
        $note->proof_url=$note->proof_path?route('secure-files.show',['category'=>'delivery','filename'=>basename($note->proof_path)]):null;

        return Inertia::render('DeliveryNotes/Form',[
            'orders'=>$this->orderTemplates($id),
            'selectedOrderId'=>$note->order_id,
            'note'=>$note,
            'statuses'=>$this->statusOptions(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $old=DB::table('delivery_notes')->find($id);
        abort_unless($old,404);
        $data=$this->validated($request);
        DB::transaction(function() use($request,$id,$old,$data){
            $order=DB::table('orders')->whereNull('deleted_at')->lockForUpdate()->find($data['order_id']);
            abort_unless($order,404);
            $signature=$this->storage->storeSignatureData($data['signature_data']??null)?:$old->signature_path;
            $proof=$this->storage->storeDeliveryProof($data['proof']??null)?:$old->proof_path;
            if(!$signature&&!$proof) throw ValidationException::withMessages(['signature_data'=>'Ajoutez la signature du client ou une preuve de livraison.']);
            DB::table('delivery_notes')->where('id',$id)->update(['order_id'=>$order->id,'delivered_at'=>$data['delivered_at'],'delivery_address'=>$data['delivery_address'],'package_count'=>$data['package_count'],'observations'=>$data['observations']??null,'receiver_name'=>$data['receiver_name']??null,'signature_path'=>$signature,'proof_path'=>$proof,'status'=>$data['order_status'],'updated_at'=>now()]);
            $this->replaceItems($id,$order->id,$data['items'],$id);
            DB::table('orders')->where('id',$order->id)->update(['status'=>$data['order_status'],'updated_at'=>now()]);
            $this->audit($request,'bons_livraison.modifie',$id,(array)$old,$data);
        });

        return redirect()->route('delivery-notes.edit',$id)->with('success','Bon de livraison modifié avec succès.');
    }

    public function pdf(int $id)
    {
        $note=DB::table('delivery_notes')
            ->join('orders','orders.id','=','delivery_notes.order_id')
            ->join('clients','clients.id','=','orders.client_id')
            ->where('delivery_notes.id',$id)
            ->select('delivery_notes.*','orders.number as order_number','clients.number as client_number','clients.name as client_name','clients.contact as client_contact','clients.address as client_address')
            ->first();
        abort_unless($note,404);
        $items=DB::table('delivery_note_items')->where('delivery_note_id',$id)->orderBy('id')->get();
        $signatureData=$this->storage->dataUri($note->signature_path);
        $proofData=$note->proof_path&&preg_match('/\.(jpe?g|png|webp)$/i',$note->proof_path)?$this->storage->dataUri($note->proof_path):null;
        $logoPath=public_path('brand/madina-import-logo-transparent.png');
        $logoData=file_exists($logoPath)?'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)):null;
        $company=config('madina.company');
        $contents=Pdf::loadView('pdf.delivery-note',compact('note','items','signatureData','proofData','logoData','company'))->setPaper('a4')->output();
        $filename=preg_replace('/[^A-Za-z0-9_-]+/','-',$note->number).'.pdf';
        $path=$this->storage->putDocumentPdf('delivery-notes',$filename,$contents);
        return $this->storage->download($path,$filename);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'order_id'=>['required',Rule::exists('orders','id')->whereNull('deleted_at')],
            'delivered_at'=>['required','date'],
            'delivery_address'=>['required','string','max:500'],
            'package_count'=>['required','integer','min:1','max:100000'],
            'observations'=>['nullable','string','max:5000'],
            'receiver_name'=>['nullable','string','max:255'],
            'signature_data'=>['nullable','string','max:3000000'],
            'proof'=>['nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
            'order_status'=>['required',Rule::in(self::STATUSES)],
            'items'=>['required','array','min:1'],
            'items.*.order_item_id'=>['required','integer','exists:order_items,id','distinct'],
            'items.*.delivered_quantity'=>['required','numeric','min:0'],
        ]);
    }

    private function replaceItems(int $noteId, int $orderId, array $lines, ?int $excludeNoteId=null): void
    {
        $orderItems=DB::table('order_items')->where('order_id',$orderId)->lockForUpdate()->get()->keyBy('id');
        $already=DB::table('delivery_note_items')->join('delivery_notes','delivery_notes.id','=','delivery_note_items.delivery_note_id')->where('delivery_notes.order_id',$orderId)->where('delivery_notes.status','!=','annulee')->when($excludeNoteId,fn($q)=>$q->where('delivery_notes.id','!=',$excludeNoteId))->select('delivery_note_items.order_item_id',DB::raw('sum(delivery_note_items.delivered_quantity) as delivered'))->groupBy('delivery_note_items.order_item_id')->pluck('delivered','order_item_id');
        $prepared=[];
        foreach($lines as $index=>$line){
            $item=$orderItems->get((int)$line['order_item_id']);
            if(!$item) throw ValidationException::withMessages(["items.$index.order_item_id"=>'Ce produit ne correspond pas à la commande.']);
            $quantity=(float)$line['delivered_quantity'];
            $remaining=max(0,(float)$item->quantity-(float)($already[$item->id]??0));
            if($quantity>$remaining) throw ValidationException::withMessages(["items.$index.delivered_quantity"=>"La quantité dépasse le reliquat à livrer ($remaining)."]);
            if($quantity>0) $prepared[]=['delivery_note_id'=>$noteId,'order_item_id'=>$item->id,'name'=>$item->name,'ordered_quantity'=>$item->quantity,'delivered_quantity'=>$quantity,'created_at'=>now(),'updated_at'=>now()];
        }
        if(!$prepared) throw ValidationException::withMessages(['items'=>'Indiquez au moins une quantité à livrer.']);
        DB::table('delivery_note_items')->where('delivery_note_id',$noteId)->delete();
        DB::table('delivery_note_items')->insert($prepared);
    }

    private function orderTemplates(?int $excludeNoteId=null): array
    {
        $currentOrderId=$excludeNoteId?DB::table('delivery_notes')->where('id',$excludeNoteId)->value('order_id'):null;
        return DB::table('orders')->join('clients','clients.id','=','orders.client_id')->whereNull('orders.deleted_at')->where(function($query) use($currentOrderId){$query->where('orders.status','!=','annulee');if($currentOrderId)$query->orWhere('orders.id',$currentOrderId);})->orderByDesc('orders.id')->select('orders.id','orders.number','orders.status','clients.name as client_name','clients.contact as client_contact','clients.address as client_address')->get()->map(function($order) use($excludeNoteId){
            $already=DB::table('delivery_note_items')->join('delivery_notes','delivery_notes.id','=','delivery_note_items.delivery_note_id')->where('delivery_notes.order_id',$order->id)->where('delivery_notes.status','!=','annulee')->when($excludeNoteId,fn($q)=>$q->where('delivery_notes.id','!=',$excludeNoteId))->select('delivery_note_items.order_item_id',DB::raw('sum(delivery_note_items.delivered_quantity) as delivered'))->groupBy('delivery_note_items.order_item_id')->pluck('delivered','order_item_id');
            $items=DB::table('order_items')->where('order_id',$order->id)->orderBy('id')->get()->map(fn($item)=>['order_item_id'=>$item->id,'name'=>$item->name,'ordered_quantity'=>(float)$item->quantity,'remaining_quantity'=>max(0,(float)$item->quantity-(float)($already[$item->id]??0))])->all();
            return [...(array)$order,'items'=>$items];
        })->all();
    }

    private function statusOptions(): array
    {
        return [['value'=>'a_livrer','label'=>'À livrer'],['value'=>'livraison_en_cours','label'=>'Livraison en cours'],['value'=>'livree','label'=>'Livrée'],['value'=>'livraison_partielle','label'=>'Livraison partielle'],['value'=>'annulee','label'=>'Annulée']];
    }

    private function audit(Request $request, string $event, int $id, ?array $old, array $new): void
    {
        $safeNew=array_diff_key($new,array_flip(['signature_data','proof']));
        DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'event'=>$event,'auditable_type'=>'delivery_notes','auditable_id'=>$id,'old_values'=>$old?json_encode($old):null,'new_values'=>json_encode($safeNew),'ip_address'=>$request->ip(),'created_at'=>now()]);
    }
}
