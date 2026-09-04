<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resynchronise IMMÉDIATEMENT le cache local (cache.erp_actes /
 * cache.erp_acte_intervenants) pour un acte précis, sans attendre le
 * prochain passage planifié de `erp:cache-sync` (~20 min).
 *
 * Deux façons de le déclencher :
 *
 *   1. Manuellement, en ligne de commande (voir app/Console/Commands/ErpSyncActe.php) :
 *        php artisan erp:sync-acte 1234
 *
 *   2. Automatiquement, juste après la création/modification d'une
 *      déclaration dans app.extra_declarations, pour que la personne qui
 *      vient de saisir voie immédiatement les bonnes infos ERP sur l'écran
 *      Direction :
 *        \App\Jobs\SyncActeJob::dispatch($declaration->num_intv);
 *      (à ajouter dans le contrôleur/service qui crée la déclaration — je
 *      ne l'ai pas trouvé dans le zip fourni, donc à brancher manuellement).
 */
class SyncActeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private readonly int $numIntv)
    {
    }

    public function handle(): void
    {
        // La procédure filtre elle-même sur cet acte précis (paramètre
        // @NumIntvCible) : les dates ne servent qu'à limiter la portée d'un
        // éventuel nettoyage des lignes disparues, donc une plage large et
        // sans conséquence ici (voir cache.usp_refresh_erp_range).
        DB::statement(
            'EXEC cache.usp_refresh_erp_range @DateDebut = ?, @DateFin = ?, @NumIntvCible = ?',
            [now()->subYear()->toDateString(), now()->addYear()->toDateString(), $this->numIntv]
        );
    }

    public function failed(\Throwable $exception): void
    {
        // Non bloquant pour l'utilisateur : le prochain passage planifié de
        // erp:cache-sync rattrapera cet acte de toute façon. On journalise
        // simplement pour pouvoir investiguer si ça échoue de façon répétée.
        Log::warning('SyncActeJob a échoué pour NumIntv=' . $this->numIntv, [
            'exception' => $exception->getMessage(),
        ]);
    }
}
