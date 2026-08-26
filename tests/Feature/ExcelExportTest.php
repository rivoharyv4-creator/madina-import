<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('persistent');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_manager_downloads_a_styled_excel_export_with_readable_relations(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();
        $response=$this->actingAs($manager)->get('/modules/commandes/export');

        $response->assertOk()->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path=collect(Storage::disk('persistent')->allFiles('exports'))->first(fn($file)=>str_ends_with($file,'.xlsx'));
        $this->assertNotNull($path);

        $temporary=tempnam(sys_get_temp_dir(),'madina-export-');
        file_put_contents($temporary,Storage::disk('persistent')->get($path));
        $sheet=IOFactory::load($temporary)->getActiveSheet();
        unlink($temporary);

        $this->assertSame('MADINA IMPORT · COMMANDES',$sheet->getCell('B1')->getValue());
        $this->assertSame('N° commande',$sheet->getCell('A4')->getValue());
        $clientNames=[];
        for($row=5;$row<=$sheet->getHighestRow();$row++) $clientNames[]=$sheet->getCell("B{$row}")->getValue();
        $this->assertContains('Hôtel Baobab Antananarivo',$clientNames);
        $this->assertSame('2F2F2F',$sheet->getStyle('A4')->getFill()->getStartColor()->getRGB());
        $this->assertSame('A5',$sheet->getFreezePane());
    }

    public function test_excel_export_requires_authentication(): void
    {
        $this->get('/modules/clients/export')->assertRedirect('/login');
    }

    public function test_premium_xlsx_export_only_contains_the_active_search_results(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();

        $response=$this->actingAs($manager)->get('/modules/commandes/export?q=MI-2026-001');

        $response->assertOk()->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path=collect(Storage::disk('persistent')->allFiles('exports'))->first(fn($file)=>str_ends_with($file,'.xlsx'));
        $temporary=tempnam(sys_get_temp_dir(),'madina-search-');
        file_put_contents($temporary,Storage::disk('persistent')->get($path));
        $sheet=IOFactory::load($temporary)->getActiveSheet();
        unlink($temporary);
        $this->assertSame('MI-2026-001',$sheet->getCell('A5')->getValue());
        $this->assertSame(5,$sheet->getHighestDataRow());
        $this->assertSame('2F2F2F',$sheet->getStyle('A4')->getFill()->getStartColor()->getRGB());
    }

    public function test_premium_xlsx_export_respects_the_active_filter(): void
    {
        $manager=User::where('email','manager@madina-import.mg')->firstOrFail();

        $response=$this->actingAs($manager)->get('/modules/commandes/export?filter_status=en_transit');

        $response->assertOk()->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path=collect(Storage::disk('persistent')->allFiles('exports'))->first(fn($file)=>str_ends_with($file,'.xlsx'));
        $temporary=tempnam(sys_get_temp_dir(),'madina-filter-');
        file_put_contents($temporary,Storage::disk('persistent')->get($path));
        $sheet=IOFactory::load($temporary)->getActiveSheet();
        unlink($temporary);
        $this->assertSame('En transit',$sheet->getCell('E5')->getValue());
        $this->assertSame(5,$sheet->getHighestDataRow());
        $this->assertCount(1,$sheet->getDrawingCollection());
    }

}
