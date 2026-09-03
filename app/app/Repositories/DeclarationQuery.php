<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Requêtes des écrans Direction / Prévalidation / Registre bloc.
 *
 * Règle d'or :
 *   - les ACTES viennent du CACHE LOCAL (cache.erp_actes)  -> aucun accès au serveur lié
 *   - les DÉCLARATIONS viennent de la table LOCALE en direct (app.extra_declarations)
 *     -> une saisie d'intervenant est visible IMMÉDIATEMENT, sans attendre les 10 minutes.
 *
 * ADAPTER les noms de colonnes de app.extra_declarations.
 */
class DeclarationQuery
{
    /** Requête de base, sans pagination : 1 ligne par acte (rn = 1). */
    public function inner(
        string $dateDebut,
        string $dateFin,
        ?string $intervenant = null,
        ?string $recherche = null,
        ?string $bloc = null
    ) {
        // Intervenant principal de l'acte, calculé sur le cache local et
        // BORNÉ PAR LA PÉRIODE (c'était la sous-requête non filtrée qui coûtait 15-20 s).
        $intervenants = DB::connection('sqlsrv')->table('cache.erp_acte_intervenants as i')
            ->join('cache.erp_actes as a2', 'a2.NumActe', '=', 'i.NumActe')
            ->whereBetween('a2.DatOpeJour', [$dateDebut, $dateFin])
            ->selectRaw('i.NumActe, i.CodeIntervenant, i.NomIntervenant, i.Role,
                         ROW_NUMBER() OVER (PARTITION BY i.NumActe ORDER BY i.Ordre) AS rn');

        $q = DB::connection('sqlsrv')->table('cache.erp_actes as a')
            ->joinSub($intervenants, 'i', fn ($j) => $j->on('i.NumActe', '=', 'a.NumActe')->where('i.rn', 1))
            ->leftJoin('app.extra_declarations as d', 'd.num_acte', '=', 'a.NumActe')
            // >>> comparaison de DATE sur colonne persistée : index utilisable (plus de whereDate/CAST)
            ->whereBetween('a.DatOpeJour', [$dateDebut, $dateFin])
            ->select([
                'a.NumActe', 'a.DatOpe', 'a.Bloc', 'a.Salle', 'a.NumDossier',
                'a.Patient', 'a.LibelleActe', 'a.CodeActe', 'a.Montant',
                'i.CodeIntervenant', 'i.NomIntervenant',
                'd.id as declaration_id',
                DB::raw("ISNULL(d.statut, 'NON_DECLARE') as statut"),
                'd.montant as montant_declare',
                'd.updated_at as declare_le',
            ]);

        if ($bloc) {
            $q->where('a.Bloc', $bloc);
        }

        if ($intervenant) {
            $q->where('i.CodeIntervenant', $intervenant);
        }

        if ($recherche) {
            $q->where(function ($w) use ($recherche) {
                $w->where('a.Patient', 'like', $recherche . '%')      // 'x%' est indexable, '%x%' ne l'est pas
                  ->orWhere('a.NumDossier', 'like', $recherche . '%')
                  ->orWhere('a.NumActe', 'like', $recherche . '%');
            });
        }

        return $q;
    }

    /** Page de résultats. Les badges EXISTS sont calculés APRÈS pagination (25 lignes au lieu de N). */
    public function page(array $f, int $perPage = 25)
    {
        $p = $this->inner($f['dateDebut'], $f['dateFin'], $f['intervenant'] ?? null,
                          $f['recherche'] ?? null, $f['bloc'] ?? null)
            ->orderBy('a.DatOpe', 'desc')
            ->orderBy('a.NumActe', 'desc')   // tri déterministe : sinon la page 2 peut répéter des lignes
            ->paginate($perPage)
            ->withQueryString();

        return $this->ajouterBadges($p);
    }

    /** Chevauchement horaire / doublon : uniquement sur les 25 lignes affichées. */
    private function ajouterBadges($p)
    {
        $numActes = collect($p->items())->pluck('NumActe')->all();

        if (empty($numActes)) {
            return $p;
        }

        $flags = DB::connection('sqlsrv')->table('cache.erp_actes as a')
            ->whereIn('a.NumActe', $numActes)
            ->selectRaw("a.NumActe,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM cache.erp_acte_intervenants i1
                    JOIN cache.erp_acte_intervenants i2 ON i2.CodeIntervenant = i1.CodeIntervenant
                                                       AND i2.NumActe <> i1.NumActe
                    JOIN cache.erp_actes b ON b.NumActe = i2.NumActe
                    WHERE i1.NumActe = a.NumActe
                      AND b.HeureDebut < a.HeureFin AND b.HeureFin > a.HeureDebut
                ) THEN 1 ELSE 0 END AS chevauchement,
                CASE WHEN EXISTS (
                    SELECT 1 FROM cache.erp_actes c
                    WHERE c.NumDossier = a.NumDossier
                      AND c.CodeActe   = a.CodeActe
                      AND c.NumActe   <> a.NumActe
                      AND c.DatOpeJour = a.DatOpeJour
                ) THEN 1 ELSE 0 END AS doublon")
            ->get()->keyBy('NumActe');

        foreach ($p->items() as $row) {
            $row->chevauchement = (bool) ($flags[$row->NumActe]->chevauchement ?? false);
            $row->doublon       = (bool) ($flags[$row->NumActe]->doublon ?? false);
        }

        return $p;
    }

    /** Les 6 compteurs en UNE seule requête, mis en cache 60 s (identiques page 1 / page 2). */
    public function statistiques(array $f): array
    {
        $key = 'stats:' . md5(json_encode($f));

        return Cache::tags(['reporting'])->remember($key, 60, function () use ($f) {
            $inner = $this->inner($f['dateDebut'], $f['dateFin'], $f['intervenant'] ?? null,
                                  $f['recherche'] ?? null, $f['bloc'] ?? null);

            $r = DB::connection('sqlsrv')->query()->fromSub($inner, 't')
                ->selectRaw("
                    COUNT(*) AS total,
                    SUM(CASE WHEN t.statut = 'SOUMIS'      THEN 1 ELSE 0 END) AS enAttente,
                    SUM(CASE WHEN t.statut = 'PREVALIDE'   THEN 1 ELSE 0 END) AS prevalide,
                    SUM(CASE WHEN t.statut = 'VALIDE'      THEN 1 ELSE 0 END) AS valide,
                    SUM(CASE WHEN t.statut = 'REJETE'      THEN 1 ELSE 0 END) AS refusee,
                    SUM(CASE WHEN t.statut = 'NON_DECLARE' THEN 1 ELSE 0 END) AS nonDeclare,
                    ISNULL(SUM(CASE WHEN t.statut IN ('PREVALIDE','VALIDE') THEN t.montant_declare END), 0) AS montantTotal
                ")->first();

            return (array) $r;
        });
    }
}
