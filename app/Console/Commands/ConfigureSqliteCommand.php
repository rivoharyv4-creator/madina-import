<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfigureSqliteCommand extends Command
{
    protected $signature='madina:sqlite-configure';
    protected $description='Configure et vérifie les réglages SQLite persistants';

    public function handle(): int
    {
        if(config('database.default')!=='sqlite'){
            $this->error('DB_CONNECTION doit être sqlite.');
            return self::FAILURE;
        }

        $pdo=DB::connection('sqlite')->getPdo();
        $current=strtolower((string)$pdo->query('PRAGMA journal_mode')->fetchColumn());
        if($current!=='wal'){
            $mode=strtolower((string)$pdo->query('PRAGMA journal_mode=WAL')->fetchColumn());
            if($mode!=='wal'){
                $this->error("Impossible d'activer SQLite WAL (mode obtenu : {$mode}).");
                return self::FAILURE;
            }
            $this->info('SQLite WAL activé.');
        }else{
            $this->line('SQLite WAL déjà actif.');
        }

        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA synchronous=FULL');
        $pdo->exec('PRAGMA busy_timeout=5000');
        $foreignKeys=(int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $synchronous=(int)$pdo->query('PRAGMA synchronous')->fetchColumn();
        $busyTimeout=(int)$pdo->query('PRAGMA busy_timeout')->fetchColumn();
        if($foreignKeys!==1||$synchronous!==2||$busyTimeout!==5000){
            $this->error("Réglages SQLite invalides (foreign_keys={$foreignKeys}, synchronous={$synchronous}, busy_timeout={$busyTimeout}).");
            return self::FAILURE;
        }

        $this->info('SQLite vérifié : foreign_keys=ON, journal_mode=WAL, synchronous=FULL, busy_timeout=5000.');
        return self::SUCCESS;
    }
}
