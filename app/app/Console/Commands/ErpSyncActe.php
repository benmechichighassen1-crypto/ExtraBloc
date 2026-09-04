<?php

namespace App\Console\Commands;

use App\Jobs\SyncActeJob;
use Illuminate\Console\Command;

/**
 * Resynchronise IMMÉDIATEMENT le cache local pour UN SEUL acte (NumIntv),
 * sans attendre le prochain passage planifié de `erp:cache-sync` et sans
 * refaire une synchro complète sur 90 jours (qui génère beaucoup plus de
 * trafic sur ERP_LINK qu'un seul acte).
 *
 * Usage :
 *   php artisan erp:sync-acte 1234
 *
 * Utile juste après la création d'une déclaration pour un acte encore
 * absent du cache, ou pour rafraîchir un cas précis sans attendre.
 */
class ErpSyncActe extends Command
{
    protected $signature = 'erp:sync-acte {numIntv : Le NumIntv (numéro d\'intervention ERP) à resynchroniser}';

    protected $description = 'Resynchronise immédiatement le cache ERP pour un seul acte (NumIntv), sans attendre la synchro planifiée.';

    public function handle(): int
    {
        $numIntv = (int) $this->argument('numIntv');

        $this->info("Synchronisation immédiate de l'acte NumIntv={$numIntv}...");

        try {
            // dispatchSync() = exécution immédiate, dans ce même processus
            // artisan (pas de file d'attente à surveiller) : on voit le
            // résultat tout de suite.
            SyncActeJob::dispatchSync($numIntv);
        } catch (\Throwable $e) {
            $this->error('Échec : ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Terminé. Vérifiez cache.sync_log pour le détail.');

        return self::SUCCESS;
    }
}
