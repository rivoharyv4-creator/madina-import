<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('persistent');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_site_does_not_expose_the_management_login_url(): void
    {
        $privateLoginPath = route('login', absolute: false);

        $this->get('/')->assertOk()->assertDontSee(ltrim($privateLoginPath, '/'));
        $this->get('/login')->assertNotFound();
        $this->get('/gestion')->assertNotFound();
        $this->get('/dashboard')->assertNotFound();
        $this->get($privateLoginPath)->assertOk();
    }

    public function test_home_and_catalog_only_expose_published_stock_information(): void
    {
        $published=DB::table('inventory_products')->first();
        DB::table('inventory_products')->where('id',$published->id)->update(['slug'=>'chaise-disponible','is_published'=>true,'is_featured'=>true,'show_price'=>true,'category'=>'Mobilier','short_description'=>'Une chaise disponible.']);
        $hidden=DB::table('inventory_products')->where('id','!=',$published->id)->first();
        DB::table('inventory_products')->where('id',$hidden->id)->update(['slug'=>'produit-cache','is_published'=>false]);

        $this->get('/')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Public/Home')->has('products',1)->where('products.0.slug','chaise-disponible')
            ->where('products.0.image_url','/catalog/products/camera-wifi-4mp.png')
            ->missing('products.0.purchase_price')->missing('products.0.stock_value')
        );
        $this->get('/catalogue')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Public/Catalog')->has('products',1)->where('products.0.price',(int)$published->sale_price)
        );
        $this->get('/catalogue/produit-cache')->assertNotFound();
    }

    public function test_contact_form_is_validated_and_saved_for_management(): void
    {
        $payload=['name'=>'Entreprise Test','contact'=>'+261340000000','client_type'=>'entreprise','need'=>'Machine de production','message'=>'Nous souhaitons étudier une machine pour notre atelier.','consent'=>true,'website'=>''];
        $this->post('/contact',$payload)->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('contact_requests',['name'=>'Entreprise Test','status'=>'nouvelle']);
        $requestId=DB::table('contact_requests')->where('name','Entreprise Test')->value('id');
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $this->actingAs($manager)->get("/modules/demandes/{$requestId}")->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Module/PublicRequestShow')
            ->where('request.name','Entreprise Test')
            ->where('request.need','Machine de production')
        );
        $this->post('/contact',[...$payload,'website'=>'spam.example'])->assertSessionHasErrors('website');
    }

    public function test_tracking_requires_matching_order_and_tracking_numbers_and_never_exposes_sensitive_fields(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $order=DB::table('orders')->first();
        $trackingNumber='TRACK-PUBLIC-TEST-001';
        DB::table('shipments')->where('order_id',$order->id)->update(['tracking'=>$trackingNumber]);
        $this->actingAs($manager)->post("/modules/commandes/{$order->id}/tracking/regenerate")->assertRedirect();
        $order=DB::table('orders')->find($order->id);
        $this->assertSame(32,strlen($order->public_tracking_code));

        $this->post('/suivi',['order_number'=>$order->number,'tracking_number'=>'WRONG-TRACKING'])->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Public/Tracking')->where('tracking',null)->where('lookupError','Tracking number invalide ou suivi pas encore trouvé.')
        );
        $this->post('/suivi',['order_number'=>$order->number,'tracking_number'=>''])->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Public/Tracking')->where('tracking',null)->where('lookupError','Tracking number invalide ou suivi pas encore trouvé.')
        );
        $this->post('/suivi',['order_number'=>$order->number,'tracking_number'=>strtolower($trackingNumber)])->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Public/Tracking')->where('tracking.number',$order->number)->has('tracking.items')
            ->missing('tracking.supplier_total')->missing('tracking.client_id')->missing('tracking.items.0.supplier_price')->missing('tracking.items.0.quantity')
        );

        $this->actingAs($manager)->patch("/modules/commandes/{$order->id}/tracking")->assertRedirect();
        $this->get('/suivi/securise/'.$order->public_tracking_code)->assertNotFound();
    }

    public function test_manager_can_publish_stock_with_catalog_fields(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $this->actingAs($manager)->post('/modules/stock',[
            'name'=>'Machine à glaçons','quantity'=>2,'purchase_price'=>1000000,'sale_price'=>1350000,'alert_threshold'=>1,
            'category'=>'Équipement','short_description'=>'Machine compacte pour activité professionnelle.','catalog_description'=>'Disponible sur demande de confirmation.','is_published'=>1,'is_featured'=>1,'show_price'=>1,
        ])->assertRedirect('/modules/stock');
        $this->assertDatabaseHas('inventory_products',['name'=>'Machine à glaçons','slug'=>'machine-a-glacons','is_published'=>1,'show_price'=>1]);
        $this->actingAs($manager)->get('/modules/demandes')->assertOk();
    }
}
