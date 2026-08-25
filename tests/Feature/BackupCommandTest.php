<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    private string $root;
    private string $originalDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root=storage_path('framework/testing/backup-'.bin2hex(random_bytes(6)));
        (new Filesystem)->ensureDirectoryExists($this->root);
        $this->originalDatabase=(string)config('database.connections.sqlite.database');
        $database=$this->root.DIRECTORY_SEPARATOR.'source.sqlite';
        touch($database);
        config([
            'database.default'=>'sqlite',
            'database.connections.sqlite.database'=>$database,
            'madina.backup_path'=>$this->root.DIRECTORY_SEPARATOR.'copies',
            'madina.backup_retention_days'=>14,
        ]);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE deployment_markers (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        DB::table('deployment_markers')->insert(['id'=>1,'value'=>'survives-redeploy']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        config(['database.connections.sqlite.database'=>$this->originalDatabase]);
        DB::purge('sqlite');
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_backup_command_creates_an_integral_sqlite_copy(): void
    {
        $this->assertSame(0,Artisan::call('madina:backup'),Artisan::output());
        $backups=glob($this->root.DIRECTORY_SEPARATOR.'copies'.DIRECTORY_SEPARATOR.'madina-import-*.sqlite');

        $this->assertCount(1,$backups);
        $this->assertGreaterThan(0,filesize($backups[0]));
        $copy=new PDO('sqlite:'.$backups[0]);
        $this->assertSame('ok',$copy->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame('survives-redeploy',$copy->query('SELECT value FROM deployment_markers')->fetchColumn());
    }

    public function test_redeploy_migrations_preserve_existing_database_data(): void
    {
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'sqlite','--force'=>true]),Artisan::output());
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'sqlite','--force'=>true]),Artisan::output());

        $this->assertSame('survives-redeploy',DB::table('deployment_markers')->value('value'));
    }

    public function test_sqlite_production_pragmas_are_applied_and_verified(): void
    {
        $this->assertSame(0,Artisan::call('madina:sqlite-configure'),Artisan::output());
        $pdo=DB::connection('sqlite')->getPdo();

        $this->assertSame('wal',strtolower((string)$pdo->query('PRAGMA journal_mode')->fetchColumn()));
        $this->assertSame(1,(int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame(2,(int)$pdo->query('PRAGMA synchronous')->fetchColumn());
        $this->assertSame(5000,(int)$pdo->query('PRAGMA busy_timeout')->fetchColumn());
    }
}
