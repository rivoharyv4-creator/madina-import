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
        $this->assertSame('Hôtel Baobab Antananarivo',$sheet->getCell('B7')->getValue());
        $this->assertSame('2F2F2F',$sheet->getStyle('A4')->getFill()->getStartColor()->getRGB());
        $this->assertSame('A5',$sheet->getFreezePane());
    }

    public function test_excel_export_requires_authentication(): void
    {
        $this->get('/modules/clients/export')->assertRedirect('/login');
    }
}
