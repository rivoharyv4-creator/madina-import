<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('persistent');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_manager_can_generate_save_and_download_a_quote_pdf(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $quote=DB::table('quotes')->where('number','DV-MI-2026-001')->first();

        $response=$this->actingAs($manager)->get("/modules/devis/{$quote->id}/pdf");

        $response->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertStringContainsString('attachment; filename=DV-MI-2026-001.pdf',$response->headers->get('content-disposition'));
        Storage::disk('persistent')->assertExists('quotes/DV-MI-2026-001.pdf');
        $this->assertStringStartsWith('%PDF',Storage::disk('persistent')->get('quotes/DV-MI-2026-001.pdf'));
    }

    public function test_manager_can_generate_save_and_download_an_invoice_pdf(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $invoice=DB::table('invoices')->where('number','FA-MI-2026-001')->first();

        $response=$this->actingAs($manager)->get("/modules/factures/{$invoice->id}/pdf");

        $response->assertOk()->assertHeader('content-type','application/pdf');
        Storage::disk('persistent')->assertExists('invoices/FA-MI-2026-001.pdf');
        $this->assertStringStartsWith('%PDF',Storage::disk('persistent')->get('invoices/FA-MI-2026-001.pdf'));
    }

    public function test_client_document_displays_unit_prices_without_revealing_margin(): void
    {
        $document=DB::table('quotes')
            ->join('clients','clients.id','=','quotes.client_id')
            ->where('quotes.number','DV-MI-2026-001')
            ->select('quotes.*','clients.number as client_number','clients.name as client_name','quotes.contact as client_contact','clients.address as client_address')
            ->first();
        $items=DB::table('quote_items')->where('quote_id',$document->id)->get()->each(fn($item)=>$item->photo_data=null);
        $module='devis'; $title='DEVIS'; $logoData=null; $company=config('madina.company');

        $html=view('pdf.document',compact('module','document','items','title','logoData','company'))->render();

        $this->assertStringContainsString('Prix unitaire',$html);
        $this->assertStringContainsString('Lot IIB 106 Ambatomainty Antananarivo',$html);
        $this->assertStringContainsString('+261 34 98 732 08',$html);
        $this->assertStringContainsString('+86 158 0200 3702',$html);
        $this->assertStringContainsString('contactmadinaimport@gmail.com',$html);
        $this->assertStringContainsString('NIF : 4019196145',$html);
        $this->assertStringContainsString('RCS : 2025B00524',$html);
        $this->assertStringContainsString('STAT : 46101 11 2025 0 10528',$html);
        $this->assertStringNotContainsString('Prix total sans marge',$html);
        $this->assertStringNotContainsString('>Marge<',$html);
        $this->assertStringNotContainsString('Fournisseur :',$html);
    }

    public function test_pdf_download_requires_authentication(): void
    {
        $quoteId=DB::table('quotes')->value('id');
        $this->get("/modules/devis/{$quoteId}/pdf")->assertRedirect('/login');
    }
}
