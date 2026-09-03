<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rafraîchit le cache local des données ERP (serveur lié).
 *
 *   php artisan erp:cache-sync                 # fenêtre 90 jours (planifié /10 min)
 *   php artisan erp:cache-sync --jours=365     # premier remplissage
 */
class ErpCacheSync extends Command
{
    protected $signature = 'erp:cache-sync {--jours=90} {--avant=7}';

    protected $description = 'Synchronise cache.erp_actes / cache.erp_acte_intervenants depuis le serveur lié ERP';

    public function handle(): int
    {
        // Empêche deux synchros simultanées (la planification tourne toutes les 10 min).
        $lock = Cache::lock('erp:cache-sync', 900);

        if (! $lock->get()) {
            $this->warn('Synchronisation déjà en cours, abandon.');
            return self::SUCCESS;
        }

        $t0 = microtime(true);

        try {
            DB::connection('sqlsrv')
                ->statement('EXEC cache.usp_refresh_erp @JoursArriere = ?, @JoursAvant = ?', [
                    (int) $this->option('jours'),
                    (int) $this->option('avant'),
                ]);

            // Les compteurs des écrans de reporting deviennent obsolètes.
            Cache::tags(['reporting'])->flush();

            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->info("Cache ERP rafraîchi en {$ms} ms.");
            Log::info('erp:cache-sync ok', ['duration_ms' => $ms]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('erp:cache-sync échec', ['message' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }
}
