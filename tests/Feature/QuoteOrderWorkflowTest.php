<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('persistent');
        $this->seed(DatabaseSeeder::class);
        $this->manager=User::where('email','manager@madina-import.mg')->firstOrFail();
    }

    public function test_quote_stores_custom_client_supplier_shipping_terms_status_and_photo(): void
    {
        $this->travelTo(Carbon::parse('2026-08-27 17:30:00','UTC'));
        $client=DB::table('clients')->first();
        $this->actingAs($this->manager)->get('/modules/devis/create')->assertInertia(fn(Assert $page)=>$page->where('prefill.quote_date','2026-08-28'));
        $this->actingAs($this->manager)->post('/modules/devis',[
            'client_id'=>$client->id,'client_name'=>'Nom personnalisé','client_contact'=>'+261 34 99 999 99','quote_date'=>'2020-01-01','valid_until'=>'2026-10-30','shipping_mode'=>'maritime','shipping_delay'=>'45 à 60 jours','bank_details'=>'BOA 00001','payment_terms'=>'50 % commande, 50 % livraison','warranty'=>'12 mois','notes'=>'Couleur à confirmer','status'=>'relance_1','items'=>[[
                'name'=>'Table sur mesure','photo'=>UploadedFile::fake()->image('table.jpg'),'quantity'=>2,'supplier_name'=>'Atelier manuel','supplier_contact'=>'WeChat atelier-88','supplier_price'=>1000000,'china_delivery'=>100000,'packaging'=>50000,'freight'=>300000,'margin'=>400000,'commission'=>0,'total'=>2850000,
            ]],
        ])->assertRedirect('/modules/devis');

        $quote=DB::table('quotes')->where('client_name','Nom personnalisé')->first();
        $this->assertSame('relance_1',$quote->status);
        $this->assertSame('2026-08-28',Carbon::parse($quote->created_at,config('app.timezone'))->timezone(config('madina.company.timezone'))->toDateString());
        $this->assertSame('maritime',$quote->shipping_mode);
        $this->assertSame('BOA 00001',$quote->bank_details);
        $item=DB::table('quote_items')->where('quote_id',$quote->id)->first();
        $this->assertSame('Atelier manuel',$item->supplier_name);
        $this->assertNotNull($item->photo_path);
        Storage::disk('persistent')->assertExists($item->photo_path);
    }

    public function test_quote_can_create_its_client_without_selecting_a_registered_client(): void
    {
        $this->actingAs($this->manager)->post('/modules/devis',[
            'client_name'=>'Nouveau client devis','client_contact'=>'+261 34 55 555 55','client_type'=>'revendeur','valid_until'=>'2026-10-30','shipping_mode'=>'aerien','status'=>'brouillon','items'=>[[
                'name'=>'Article test','quantity'=>1,'supplier_name'=>'Fournisseur libre','supplier_price'=>100000,'total'=>150000,
            ]],
        ])->assertRedirect('/modules/devis');

        $client=DB::table('clients')->where('name','Nouveau client devis')->first();
        $this->assertNotNull($client);
        $this->assertDatabaseHas('quotes',['client_id'=>$client->id,'client_name'=>'Nouveau client devis','contact'=>'+261 34 55 555 55']);
    }

    public function test_order_form_exposes_quote_templates_and_order_keeps_quote_link_and_photo(): void
    {
        $quote=DB::table('quotes')->where('number','DV-MI-2026-001')->first();
        $quoteItem=DB::table('quote_items')->where('quote_id',$quote->id)->first();
        DB::table('quote_items')->where('id',$quoteItem->id)->update(['photo_path'=>'products/from-quote.jpg','supplier_name'=>'Fournisseur devis','supplier_contact'=>'WeChat 123']);
        $orderLineTotal=((float)$quoteItem->supplier_price+(float)$quoteItem->china_delivery+(float)$quoteItem->packaging+(float)$quoteItem->estimated_freight+(float)$quoteItem->margin)*(float)$quoteItem->quantity;

        $this->actingAs($this->manager)->get('/modules/commandes/create')->assertInertia(fn(Assert $page)=>$page
            ->where('quoteTemplates.'.$quote->id.'.number',$quote->number)
            ->where('quoteTemplates.'.$quote->id.'.items.0.client_total',(int)$orderLineTotal)
        );

        $this->actingAs($this->manager)->post('/modules/commandes',[
            'quote_id'=>$quote->id,'client_id'=>$quote->client_id,'ordered_at'=>'2026-08-25','shipping_mode'=>'maritime','status'=>'acompte_recu','deposit'=>1000000,'commission_enabled'=>1,'commission_rate'=>8,'items'=>[[
                'quote_item_id'=>$quoteItem->id,'name'=>$quoteItem->name,'quantity'=>$quoteItem->quantity,'supplier_id'=>$quoteItem->supplier_id,'supplier_name'=>'Fournisseur devis','supplier_contact'=>'WeChat 123','supplier_price'=>$quoteItem->supplier_price,'china_delivery'=>$quoteItem->china_delivery,'packaging'=>$quoteItem->packaging,'weight'=>$quoteItem->estimated_weight,'cbm'=>$quoteItem->estimated_cbm,'freight'=>$quoteItem->estimated_freight,'margin'=>$quoteItem->margin,'commission'=>$quoteItem->commission,'client_total'=>(float)$quoteItem->total/(float)$quoteItem->quantity,
            ]],
        ])->assertRedirect('/modules/commandes');

        $order=DB::table('orders')->latest('id')->first();
        $this->assertSame($quote->id,$order->quote_id);
        $this->assertSame('devis',$order->origin);
        $this->assertSame('acompte_recu',$order->status);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'photo_path'=>'products/from-quote.jpg','supplier_name'=>'Fournisseur devis']);
    }

    public function test_manager_can_create_a_client_inside_a_new_order(): void
    {
        $this->actingAs($this->manager)->post('/modules/commandes',[
            'new_client_name'=>'Client express','new_client_contact'=>'+261 32 11 222 33','new_client_type'=>'entrepreneur','new_client_address'=>'Antsirabe','ordered_at'=>'2026-08-25','status'=>'achat_effectue','commission_enabled'=>0,'deposit'=>0,'items'=>[[
                'name'=>'Article express','quantity'=>1,'supplier_price'=>100000,'client_total'=>150000,
            ]],
        ])->assertRedirect('/modules/commandes');

        $client=DB::table('clients')->where('name','Client express')->first();
        $this->assertNotNull($client);
        $this->assertDatabaseHas('orders',['client_id'=>$client->id,'status'=>'achat_effectue']);
    }

    public function test_manager_can_start_a_prefilled_invoice_from_an_order(): void
    {
        $order=DB::table('orders')->where('number','MI-2026-001')->first();

        $this->actingAs($this->manager)->get('/modules/factures/create?order_id='.$order->id)->assertInertia(fn(Assert $page)=>$page
            ->where('module','factures')
            ->where('prefill.order_id',$order->id)
            ->where('prefill.subtotal',(int)$order->client_total)
            ->where('prefill.paid_amount',(int)$order->deposit)
            ->where('orderTemplates.'.$order->id.'.number',$order->number)
        );

        $this->actingAs($this->manager)->post('/modules/factures',[
            'order_id'=>$order->id,'type'=>'produits','issued_at'=>'2026-08-25','subtotal'=>$order->client_total,'paid_amount'=>$order->deposit,'status'=>'partielle',
        ])->assertRedirect('/modules/factures');

        $this->assertDatabaseHas('invoices',['order_id'=>$order->id,'client_id'=>$order->client_id,'subtotal'=>$order->client_total,'paid_amount'=>$order->deposit]);
        $invoice=DB::table('invoices')->where('order_id',$order->id)->latest('id')->first();
        $line=collect(json_decode($invoice->lines,true))->first();
        $this->assertArrayHasKey('quantity',$line);
        $this->assertArrayHasKey('unit_price',$line);
    }
}
