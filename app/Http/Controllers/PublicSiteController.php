<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicContactRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSiteController extends Controller
{
    public function home(): Response
    {
        $products=DB::table('inventory_products')->whereNull('deleted_at')->where('is_published',true)->where('is_featured',true)->latest('updated_at')->limit(4)->get()->map(fn($product)=>$this->catalogProduct($product));
        return Inertia::render('Public/Home',['products'=>$products,'publicConfig'=>$this->publicConfig()]);
    }

    public function catalog(Request $request): Response
    {
        $query=DB::table('inventory_products')->whereNull('deleted_at')->where('is_published',true);
        if($search=trim($request->string('q')->toString())) $query->where(fn($q)=>$q->where('name','like','%'.$search.'%')->orWhere('reference','like','%'.$search.'%')->orWhere('category','like','%'.$search.'%'));
        if($category=$request->string('category')->toString()) $query->where('category',$category);
        $page=$query->orderByDesc('is_featured')->orderBy('name')->paginate(12)->withQueryString();
        $products=collect($page->items())->map(fn($product)=>$this->catalogProduct($product));
        $categories=DB::table('inventory_products')->whereNull('deleted_at')->where('is_published',true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        return Inertia::render('Public/Catalog',['products'=>$products,'categories'=>$categories,'filters'=>['q'=>$search??'','category'=>$category??''],'pagination'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage()],'publicConfig'=>$this->publicConfig()]);
    }

    public function product(string $slug): Response
    {
        $product=DB::table('inventory_products')->whereNull('deleted_at')->where('is_published',true)->where('slug',$slug)->first();
        abort_unless($product,404);
        $gallery=collect(json_decode($product->gallery_paths??'[]',true)?:[])->keys()->map(fn($index)=>route('public.catalog.image',[$slug,$index+1]))->values();
        return Inertia::render('Public/Product',['product'=>[...$this->catalogProduct($product),'description'=>$product->catalog_description,'gallery'=>$gallery],'publicConfig'=>$this->publicConfig()]);
    }

    public function productImage(string $slug, int $index=0): StreamedResponse
    {
        $product=DB::table('inventory_products')->whereNull('deleted_at')->where('is_published',true)->where('slug',$slug)->first();
        abort_unless($product,404);
        $path=$index===0?$product->photo_path:(json_decode($product->gallery_paths??'[]',true)[$index-1]??null);
        $disk=Storage::disk('persistent');
        abort_unless($path&&$disk->exists($path),404);
        return $disk->response($path,basename($path),['Cache-Control'=>'public, max-age=86400','X-Content-Type-Options'=>'nosniff']);
    }

    public function tracking(): Response
    {
        return Inertia::render('Public/Tracking',['tracking'=>null,'publicConfig'=>$this->publicConfig()]);
    }

    public function lookupTracking(Request $request): Response
    {
        $orderNumber=Str::upper(trim($request->string('order_number')->toString()));
        $trackingNumber=trim($request->string('tracking_number')->toString());
        if($orderNumber===''||$trackingNumber===''||mb_strlen($orderNumber)>50||mb_strlen($trackingNumber)>255) return $this->invalidTrackingLookup($request,$orderNumber);

        $order=DB::table('orders')
            ->join('shipments','shipments.order_id','=','orders.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.number',$orderNumber)
            ->whereRaw('LOWER(shipments.tracking) = ?',[Str::lower($trackingNumber)])
            ->select('orders.*')
            ->first();
        if(!$order){
            return $this->invalidTrackingLookup($request,$orderNumber);
        }
        return Inertia::render('Public/Tracking',['tracking'=>$this->trackingPayload($order,$trackingNumber),'publicConfig'=>$this->publicConfig()]);
    }

    private function invalidTrackingLookup(Request $request, string $orderNumber): Response
    {
        Log::warning('Public tracking lookup failed',['ip_hash'=>hash_hmac('sha256',(string)$request->ip(),(string)config('app.key')),'order_hash'=>hash('sha256',$orderNumber)]);
        return Inertia::render('Public/Tracking',['tracking'=>null,'lookupError'=>'Tracking number invalide ou suivi pas encore trouvé.','publicConfig'=>$this->publicConfig()]);
    }

    public function trackingLink(string $token): Response
    {
        $order=DB::table('orders')->whereNull('deleted_at')->where('public_tracking_code',$token)->where('public_tracking_enabled',true)->first();
        abort_unless($order,404);
        return Inertia::render('Public/Tracking',['tracking'=>$this->trackingPayload($order),'publicConfig'=>$this->publicConfig()]);
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact',['publicConfig'=>$this->publicConfig()]);
    }

    public function submitContact(PublicContactRequest $request)
    {
        DB::table('contact_requests')->insert([...$request->safe()->except('website'),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Merci. Votre demande a bien été transmise à notre équipe.');
    }

    private function catalogProduct(object $product): array
    {
        $stock=(float)$product->quantity;
        $threshold=(float)($product->alert_threshold??0);
        return ['slug'=>$product->slug,'reference'=>$product->reference,'name'=>$product->name,'category'=>$product->category,'short_description'=>$product->short_description,'availability'=>$stock<=0?'Indisponible':($threshold>0&&$stock<=$threshold?'Stock limité':'Disponible'),'price'=>$product->show_price?(float)$product->sale_price:null,'image_url'=>$product->photo_path?route('public.catalog.image',[$product->slug,0]):$this->catalogMockup($product->reference)];
    }

    private function catalogMockup(?string $reference): ?string
    {
        return match ($reference) {
            'PRD-001' => '/catalog/products/camera-wifi-4mp.png',
            'PRD-002' => '/catalog/products/lampe-solaire-300w.png',
            'PRD-003' => '/catalog/products/robinet-mitigeur-noir.png',
            'PRD-004' => '/catalog/products/etagere-metallique-5-niveaux.png',
            default => null,
        };
    }

    private function trackingPayload(object $order, ?string $matchedTracking=null): array
    {
        $items=DB::table('order_items')->where('order_id',$order->id)->orderBy('id')->get(['name','status']);
        $shipments=DB::table('shipments')->where('order_id',$order->id)->orderBy('id')->get(['tracking','mode','forwarder','container_reference','weight','cbm','package_count','carton_count','supplier_sent_at','china_warehouse_at','china_departure_at','expected_madagascar_at','arrived_madagascar_at','delivered_at','status','updated_at']);
        $rank=match(true){$shipments->contains(fn($s)=>$s->delivered_at!==null),$order->status==='livre'=>6,$shipments->contains(fn($s)=>$s->arrived_madagascar_at!==null)=>5,$shipments->contains(fn($s)=>$s->china_departure_at!==null),$order->status==='en_transit'=>4,$shipments->contains(fn($s)=>$s->supplier_sent_at!==null)=>3,$shipments->contains(fn($s)=>$s->china_warehouse_at!==null)=>2,in_array($order->status,['achat_effectue','achat_lance'],true)=>1,default=>0};
        $labels=['Commande confirmée','Achat effectué','Arrivée à l’entrepôt en Chine','Expédition préparée','En transit','Arrivée à Madagascar','Remise au client'];
        return ['number'=>$order->number,'matched_tracking'=>$matchedTracking,'status'=>str_replace('_',' ',$order->status),'shipping_mode'=>$order->shipping_mode,'updated_at'=>$order->updated_at,'items'=>$items,'shipments'=>$shipments,'steps'=>collect($labels)->map(fn($label,$index)=>['label'=>$label,'state'=>$index<$rank?'complete':($index===$rank?'current':'upcoming')])];
    }

    private function publicConfig(): array
    {
        return config('madina.public');
    }
}
