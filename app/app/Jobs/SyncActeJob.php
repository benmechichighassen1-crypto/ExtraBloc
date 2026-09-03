<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Rafraîchissement CIBLÉ d'une seule journée (ou d'un seul acte) du cache.
 *
 * Sert au cas « l'intervenant saisit une déclaration à tout moment » :
 * si l'acte vient d'être créé dans l'ERP et n'est pas encore dans le cache
 * (créé il y a moins de 10 minutes), on ne fait pas attendre l'utilisateur
 * la prochaine synchro globale — on synchronise juste sa journée.
 *
 * Coût : quelques dizaines de lignes, < 1 s.
 */
class SyncActeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public string $date,          // 'YYYY-MM-DD'
        public ?string $numActe = null
    ) {}

    /** Une seule synchro par journée en attente à la fois. */
    public function uniqueId(): string
    {
        return 'sync-acte-' . $this->date . '-' . ($this->numActe ?? 'all');
    }

    public function handle(): void
    {
        $sql = <<<'SQL'
        SET NOCOUNT ON;

        SELECT *
        INTO #a
        FROM ERP_LINK.[BaseERP].[dbo].[vw_erp_actes_bloc_direction]
        WHERE DatOpe >= ? AND DatOpe < DATEADD(DAY, 1, CAST(? AS DATE))
          AND (? IS NULL OR NumActe = ?);

        MERGE cache.erp_actes WITH (HOLDLOCK) AS t
        USING #a AS s ON t.NumActe = s.NumActe
        WHEN MATCHED THEN UPDATE SET
            t.DatOpe = s.DatOpe, t.Bloc = s.Bloc, t.Salle = s.Salle,
            t.NumDossier = s.NumDossier, t.Patient = s.Patient,
            t.LibelleActe = s.LibelleActe, t.CodeActe = s.CodeActe,
            t.Montant = s.Montant, t.HeureDebut = s.HeureDebut,
            t.HeureFin = s.HeureFin, t.synced_at = SYSDATETIME()
        WHEN NOT MATCHED BY TARGET THEN INSERT
            (NumActe, DatOpe, Bloc, Salle, NumDossier, Patient, LibelleActe,
             CodeActe, Montant, HeureDebut, HeureFin)
        VALUES
            (s.NumActe, s.DatOpe, s.Bloc, s.Salle, s.NumDossier, s.Patient,
             s.LibelleActe, s.CodeActe, s.Montant, s.HeureDebut, s.HeureFin);

        MERGE cache.erp_acte_intervenants WITH (HOLDLOCK) AS t
        USING (
            SELECT v.NumActe, v.CodeIntervenant, v.NomIntervenant, v.Role, v.Ordre
            FROM ERP_LINK.[BaseERP].[dbo].[vw_erp_acte_intervenants] v
            JOIN #a a ON a.NumActe = v.NumActe
        ) AS s
           ON t.NumActe = s.NumActe
          AND t.CodeIntervenant = s.CodeIntervenant
          AND t.Role = s.Role
        WHEN MATCHED THEN UPDATE SET
            t.NomIntervenant = s.NomIntervenant, t.Ordre = s.Ordre,
            t.synced_at = SYSDATETIME()
        WHEN NOT MATCHED BY TARGET THEN INSERT
            (NumActe, CodeIntervenant, NomIntervenant, Role, Ordre)
        VALUES (s.NumActe, s.CodeIntervenant, s.NomIntervenant, s.Role, s.Ordre);

        DROP TABLE #a;
        SQL;

        DB::connection('sqlsrv')->statement($sql, [
            $this->date, $this->date, $this->numActe, $this->numActe,
        ]);
    }
}
