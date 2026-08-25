<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature='madina:backup';
    protected $description='Crée une sauvegarde SQLite cohérente avec VACUUM INTO';

    public function handle(Filesystem $files): int
    {
        if(config('database.default')!=='sqlite'){
            $this->error('La sauvegarde Madina nécessite DB_CONNECTION=sqlite.');
            return self::FAILURE;
        }

        $directory=(string)config('madina.backup_path');
        $files->ensureDirectoryExists($directory,0770,true);
        $target=$directory.DIRECTORY_SEPARATOR.'madina-import-'.now()->format('Y-m-d_H-i-s-u').'.sqlite';

        try{
            $pdo=DB::connection('sqlite')->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($target));
            if(!$files->exists($target)||$files->size($target)===0) throw new \RuntimeException('Le fichier de sauvegarde est absent ou vide.');
            $backup=new PDO('sqlite:'.$target);
            if($backup->query('PRAGMA integrity_check')->fetchColumn()!=='ok') throw new \RuntimeException("La vérification d'intégrité SQLite a échoué.");
            $this->removeExpiredBackups($files,$directory);
            Log::info('Sauvegarde SQLite créée.',['path'=>$target,'size'=>$files->size($target)]);
            $this->info("Sauvegarde créée : {$target}");
            return self::SUCCESS;
        }catch(Throwable $exception){
            if($files->exists($target)) $files->delete($target);
            Log::error('Échec de la sauvegarde SQLite.',['target'=>$target,'error'=>$exception->getMessage()]);
            $this->error('Échec de la sauvegarde SQLite : '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function removeExpiredBackups(Filesystem $files, string $directory): void
    {
        $retention=max(1,(int)config('madina.backup_retention_days',14));
        $cutoff=now()->subDays($retention)->getTimestamp();
        foreach($files->glob($directory.DIRECTORY_SEPARATOR.'madina-import-*.sqlite') as $backup){
            if($files->lastModified($backup)<$cutoff) $files->delete($backup);
        }
    }
}
