<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->manager=User::where('email','manager@madina-import.mg')->firstOrFail();
    }

    public function test_internal_quote_request_is_separate_from_public_requests(): void
    {
        $publicRequestCount=DB::table('contact_requests')->count();
        $this->actingAs($this->manager)->post('/modules/demandes-devis',[
            'client_name'=>'Client interne',
            'client_contact'=>'+261 34 00 000 00',
            'source'=>'facebook',
            'description'=>'Deux pompes solaires avec accessoires',
            'quantity'=>2,
            'budget'=>2000000,
            'desired_deadline'=>'urgent',
            'shipping_mode'=>'maritime',
            'status'=>'nouveau',
            'sourcing_priority'=>'urgent',
            'destination'=>'Toamasina',
            'assigned_to'=>$this->manager->id,
            'internal_note'=>'À analyser cette semaine',
        ])->assertRedirect('/modules/demandes-devis');

        $this->assertDatabaseHas('quote_requests',[
            'client_name'=>'Client interne',
            'quantity'=>2,
            'request_date'=>today()->toDateString(),
            'status'=>'nouveau',
            'sourcing_priority'=>'urgent',
            'destination'=>'Toamasina',
        ]);
        $this->assertSame($publicRequestCount,DB::table('contact_requests')->count());
    }

    public function test_quote_creation_is_prefilled_and_links_back_to_the_internal_request(): void
    {
        $requestId=DB::table('quote_requests')->insertGetId([
            'client_name'=>'Entreprise Test','client_contact'=>'test@example.com','source'=>'bouche_a_oreille',
            'description'=>'Groupe électrogène silencieux 10 kVA','quantity'=>3,'budget'=>5000000,
            'desired_deadline'=>'15 octobre','shipping_mode'=>'aerien','status'=>'a_qualifier',
            'sourcing_priority'=>'normal','destination'=>'Antananarivo',
            'request_date'=>today(),'assigned_to'=>$this->manager->id,'internal_note'=>'Vérifier la garantie',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($this->manager)->get('/modules/devis/create?quote_request_id='.$requestId)
            ->assertInertia(fn(Assert $page)=>$page
                ->where('module','devis')
                ->where('prefill.quote_request_id',$requestId)
                ->where('prefill.client_name','Entreprise Test')
                ->where('prefill.client_contact','test@example.com')
                ->where('prefill.shipping_mode','aerien')
                ->where('initialItems.0.name','Groupe électrogène silencieux 10 kVA')
                ->where('initialItems.0.quantity',3)
            );

        $this->actingAs($this->manager)->post('/modules/devis',[
            'quote_request_id'=>$requestId,
            'client_name'=>'Entreprise Test','client_contact'=>'test@example.com','client_type'=>'entrepreneur',
            'valid_until'=>today()->addMonth()->toDateString(),'shipping_mode'=>'aerien','status'=>'brouillon',
            'items'=>[ ['name'=>'Groupe électrogène silencieux 10 kVA','specifications'=>'Modèle demandé','quantity'=>3,'supplier_name'=>'Fournisseur test','supplier_price'=>1000000,'china_delivery'=>0,'packaging'=>0,'freight'=>0,'margin'=>0,'commission'=>0,'total'=>3000000] ],
        ])->assertRedirect('/modules/devis');

        $quote=DB::table('quotes')->latest('id')->first();
        $this->assertDatabaseHas('quote_requests',['id'=>$requestId,'quote_id'=>$quote->id,'status'=>'devis_envoye']);

        $this->actingAs($this->manager)->get('/modules/demandes-devis')->assertInertia(fn(Assert $page)=>$page
            ->where('module','demandes-devis')
            ->where('rows.0.quote_id',$quote->number)
            ->where('rows.0.quote_record_id',$quote->id)
        );
    }

    public function test_quote_request_form_exposes_prospect_status_priority_and_destination(): void
    {
        $this->actingAs($this->manager)->get('/modules/demandes-devis/create')
            ->assertInertia(fn(Assert $page)=>$page
                ->where('module','demandes-devis')
                ->where('fields',fn($fields)=>collect($fields)->contains(fn($field)=>$field['name']==='destination')
                    &&collect($fields)->contains(fn($field)=>$field['name']==='sourcing_priority'&&$field['default']==='normal'&&count($field['options'])===4)
                    &&collect($fields)->contains(fn($field)=>$field['name']==='status'&&$field['default']==='nouveau'&&count($field['options'])===9))
            );
    }
}
