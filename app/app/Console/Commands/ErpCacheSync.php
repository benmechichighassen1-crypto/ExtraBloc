<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rafraîchit le cache local des vues ERP (cache.erp_actes /
 * cache.erp_acte_intervenants) sur une fenêtre glissante.
 *
 * À planifier toutes les ~10 minutes dans routes/console.php :
 *
 *   Schedule::command('erp:cache-sync')->everyTenMinutes()
 *       ->withoutOverlapping()
 *       ->onOneServer();
 *
 * Usage manuel (ex. après une longue coupure du serveur lié) :
 *   php artisan erp:cache-sync --jours=180 --avant=14
 */
class ErpCacheSync extends Command
{
    protected $signature = 'erp:cache-sync {--jours=90 : Nombre de jours en arrière à synchroniser} {--avant=7 : Nombre de jours en avant à synchroniser}';

    protected $description = 'Synchronise localement (schéma cache.*) les données ERP utilisées par l\'écran Direction, pour éviter les requêtes lentes sur le serveur lié ERP_LINK.';

    public function handle(): int
    {
        $jours = (int) $this->option('jours');
        $avant = (int) $this->option('avant');

        $this->info(sprintf('Synchronisation du cache ERP (J-%d à J+%d)...', $jours, $avant));

        $debut = microtime(true);

        try {
            DB::statement('EXEC cache.usp_refresh_erp @JoursArriere = ?, @JoursAvant = ?', [$jours, $avant]);
        } catch (\Throwable $e) {
            $this->error('Échec de la synchronisation : ' . $e->getMessage());

            return self::FAILURE;
        }

        $duree = round(microtime(true) - $debut, 2);

        $dernierLog = DB::table('cache.sync_log')->orderByDesc('id')->first();

        $this->info(sprintf(
            'Terminé en %ss — %s actes, %s intervenants synchronisés.',
            $duree,
            $dernierLog->actes_count ?? '?',
            $dernierLog->intervenants_count ?? '?'
        ));

        return self::SUCCESS;
    }
}
