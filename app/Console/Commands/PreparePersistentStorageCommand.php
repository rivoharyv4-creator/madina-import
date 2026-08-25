<?php

namespace App\Console\Commands;

use App\Services\PersistentStorageService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PreparePersistentStorageCommand extends Command
{
    protected $signature='madina:prepare-storage';
    protected $description='Crée et vérifie les dossiers persistants Madina Import';

    public function handle(PersistentStorageService $storage, Filesystem $files): int
    {
        $storage->ensureDirectories();
        $backupPath=(string)config('madina.backup_path');
        $files->ensureDirectoryExists($backupPath,0770,true);
        if(!is_writable($backupPath)){
            $this->error("Le dossier de sauvegarde n'est pas inscriptible : {$backupPath}");
            return self::FAILURE;
        }
        $this->info('Dossiers persistants prêts.');
        return self::SUCCESS;
    }
}
