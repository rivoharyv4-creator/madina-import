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

    public function test_pdf_download_requires_authentication(): void
    {
        $quoteId=DB::table('quotes')->value('id');
        $this->get("/modules/devis/{$quoteId}/pdf")->assertRedirect('/login');
    }
}
