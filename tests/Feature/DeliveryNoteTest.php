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

class DeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->manager=User::where('email','manager@madina-import.mg')->firstOrFail();
    }

    public function test_manager_can_generate_delivery_note_with_packages_proof_and_pdf(): void
    {
        Storage::fake('persistent');
        $order=DB::table('orders')->where('number','MI-2026-002')->first();
        $item=DB::table('order_items')->where('order_id',$order->id)->first();

        $this->actingAs($this->manager)->get('/modules/bons-livraison/create?order_id='.$order->id)->assertInertia(fn(Assert $page)=>$page
            ->component('DeliveryNotes/Form')
            ->where('selectedOrderId',$order->id)
            ->where('orders.0.id',fn()=>true)
        );

        $this->actingAs($this->manager)->post('/modules/bons-livraison',[
            'order_id'=>$order->id,
            'delivered_at'=>'2026-09-01',
            'delivery_address'=>'Ivandry, Antananarivo',
            'package_count'=>4,
            'observations'=>'Deux cartons fragiles',
            'receiver_name'=>'Jean Rakoto',
            'order_status'=>'livraison_partielle',
            'items'=>[['order_item_id'=>$item->id,'delivered_quantity'=>10]],
            'proof'=>UploadedFile::fake()->image('preuve-livraison.jpg',800,600),
        ])->assertRedirect();

        $note=DB::table('delivery_notes')->first();
        $this->assertSame('BL-MI-2026-001',$note->number);
        $this->assertSame(4,$note->package_count);
        $this->assertDatabaseHas('delivery_note_items',['delivery_note_id'=>$note->id,'order_item_id'=>$item->id,'delivered_quantity'=>10]);
        $this->assertSame('livraison_partielle',DB::table('orders')->where('id',$order->id)->value('status'));
        Storage::disk('persistent')->assertExists($note->proof_path);

        $this->actingAs($this->manager)->get("/modules/bons-livraison/{$note->id}/pdf")->assertOk()->assertDownload('BL-MI-2026-001.pdf');
        Storage::disk('persistent')->assertExists('delivery-notes/BL-MI-2026-001.pdf');
    }

    public function test_delivery_note_accepts_drawn_signature_and_rejects_quantity_above_remainder(): void
    {
        Storage::fake('persistent');
        $order=DB::table('orders')->where('number','MI-2026-001')->first();
        $item=DB::table('order_items')->where('order_id',$order->id)->first();
        $signature='data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $payload=['order_id'=>$order->id,'delivered_at'=>'2026-09-01','delivery_address'=>'Analakely','package_count'=>2,'receiver_name'=>'Soa','order_status'=>'livraison_en_cours','items'=>[['order_item_id'=>$item->id,'delivered_quantity'=>5]],'signature_data'=>$signature];
        $this->actingAs($this->manager)->post('/modules/bons-livraison',$payload)->assertRedirect();
        $note=DB::table('delivery_notes')->first();
        $this->assertNotNull($note->signature_path);
        Storage::disk('persistent')->assertExists($note->signature_path);

        $payload['items'][0]['delivered_quantity']=(float)$item->quantity;
        $this->actingAs($this->manager)->post('/modules/bons-livraison',$payload)->assertSessionHasErrors('items.0.delivered_quantity');
    }
}
