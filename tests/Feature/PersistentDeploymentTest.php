<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersistentDeploymentTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryDirectories=[];

    protected function tearDown(): void
    {
        $files=new Filesystem;
        foreach($this->temporaryDirectories as $directory){
            if($files->isDirectory($directory)) $files->deleteDirectory($directory);
        }
        parent::tearDown();
    }

    public function test_private_product_files_require_authentication(): void
    {
        Storage::fake('persistent');
        Storage::disk('persistent')->put('products/private-product.jpg','photo-content');

        $this->get('/product-photo/private-product.jpg')->assertRedirect('/login');

        $user=User::factory()->create();
        $response=$this->actingAs($user)
            ->get('/product-photo/private-product.jpg')
            ->assertOk();
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
    }

    public function test_health_check_is_public_and_does_not_expose_configuration(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertDontSee(config('app.key'))
            ->assertDontSee((string)config('database.connections.sqlite.database'));
    }

    public function test_prepare_storage_is_idempotent_and_preserves_existing_files(): void
    {
        $root=$this->temporaryDirectory('persistent');
        $backup=$this->temporaryDirectory('backups');
        config([
            'filesystems.disks.persistent.root'=>$root,
            'madina.backup_path'=>$backup,
        ]);

        $this->assertSame(0,Artisan::call('madina:prepare-storage'));
        Storage::disk('persistent')->put('products/redeploy-marker.txt','must-survive');
        $this->assertSame(0,Artisan::call('madina:prepare-storage'));

        Storage::disk('persistent')->assertExists('products/redeploy-marker.txt');
        $this->assertSame('must-survive',Storage::disk('persistent')->get('products/redeploy-marker.txt'));
        foreach(config('madina.persistent_directories') as $directory){
            $this->assertTrue(Storage::disk('persistent')->directoryExists($directory));
        }
    }

    private function temporaryDirectory(string $name): string
    {
        $directory=storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(6)));
        (new Filesystem)->ensureDirectoryExists($directory);
        $this->temporaryDirectories[]=$directory;
        return $directory;
    }
}
