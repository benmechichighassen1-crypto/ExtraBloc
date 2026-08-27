<?php

namespace App\Http\Controllers;

use App\Exports\DeclarationsExport;
use App\Support\AccessControl;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class DirectionController extends Controller
{
    /** Libellés lisibles des actions journalisées dans app.extra_declaration_audits. */
    private const ACTION_LABELS = [
        'SOUMIS' => 'Création (saisie intervenant)',
        'PREVALIDE' => 'Pré-validation (major du bloc)',
        'VALIDE' => 'Validation',
        'REJETE' => 'Rejet',
        'DEVALIDE' => 'Dévalidation (correction)',
        'MONTANT_CORRIGE' => 'Correction du montant',
    ];

    /** Libellés lisibles des statuts de déclaration (utilisés aussi dans la vue et l'export). */
    public const STATUS_LABELS = [
        'SOUMIS' => 'En attente',
        'PREVALIDE' => 'Prévalidé',
        'VALIDE' => 'Validé',
        'REJETE' => 'Refusé',
    ];

    public function index(Request $request): View
    {
        $username = $request->user()->getAuthIdentifier();
        $canValidate = AccessControl::hasDirectionAccess($username);

        // RH : lecture seule, limité aux déclarations validées (pour la paie).
        // Direction : par défaut, seules les déclarations "Prévalidé" sont
        // chargées (au lieu de tout charger) — réduit le nombre de lignes,
        // donc le nombre d'allers-retours vers l'ERP par ligne (planning,
        // pointage). L'utilisateur peut toujours élargir via les filtres.
        $statuses = $canValidate ? $this->resolveStatuses($request) : ['VALIDE'];
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());
        $intervenant = trim((string) $request->input('intervenant', ''));
        $recherche = trim((string) $request->input('recherche', ''));
        $bloc = trim((string) $request->input('bloc', ''));

        $declarations = $this->buildQuery($statuses, $dateDebut, $dateFin, $intervenant, $recherche, $bloc)
            ->paginate(25)
            ->withQueryString();

        $intervenantOptions = $this->intervenantOptions();
        $blocOptions = $this->blocOptions();
        $prevalidationObligatoire = (bool) config('etrasbloc.prevalidation_obligatoire');
        $stats = $this->statistiques($dateDebut, $dateFin, $intervenant, $recherche, $bloc);

        return view('direction.index', compact('declarations', 'statuses', 'dateDebut', 'dateFin', 'intervenant', 'recherche', 'bloc', 'intervenantOptions', 'blocOptions', 'prevalidationObligatoire', 'canValidate', 'stats'));
    }

    /**
     * Récapitulatif de la période (mêmes filtres que la liste) : total des
     * demandes et répartition par statut + somme des montants. Calculé sans
     * le filtre de statut pour afficher la synthèse complète de la période.
     */
    private function statistiques(string $dateDebut, string $dateFin, string $intervenant, string $recherche, string $bloc): array
    {
        $q = DB::table('app.extra_declarations as d')
            ->leftJoin('app.vw_erp_actes_bloc_direction as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoin('app.vw_erp_acte_intervenants as i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')->on('d.cod_interv', '=', 'i.CodInterv');
            })
            ->whereDate('a.DatOpe', '>=', $dateDebut)
            ->whereDate('a.DatOpe', '<=', $dateFin)
            ->when($bloc !== '', fn ($q) => $q->where('a.CodBloc', $bloc))
            ->when($intervenant !== '', function ($q) use ($intervenant): void {
                if (preg_match('/^(\d+)/', $intervenant, $m)) {
                    $q->where('d.cod_interv', (int) $m[1]);
                } else {
                    $q->where('i.DesInterv', 'like', '%'.$intervenant.'%');
                }
            })
            ->when($recherche !== '', function ($q) use ($recherche): void {
                $q->where(function ($sub) use ($recherche): void {
                    $sub->where('d.num_doss', 'like', '%'.$recherche.'%')
                        ->orWhere('a.NomPatient', 'like', '%'.$recherche.'%')
                        ->orWhere('a.PrenomPatient', 'like', '%'.$recherche.'%');
                });
            });

        return [
            'total'      => (clone $q)->count(),
            'enAttente'  => (clone $q)->where('d.statut', 'SOUMIS')->count(),
            'prevalide'  => (clone $q)->where('d.statut', 'PREVALIDE')->count(),
            'valide'     => (clone $q)->where('d.statut', 'VALIDE')->count(),
            'refusee'    => (clone $q)->where('d.statut', 'REJETE')->count(),
            'montantTotal' => (clone $q)->whereIn('d.statut', ['PREVALIDE', 'VALIDE'])->whereNotNull('d.montant')->sum('d.montant'),
        ];
    }

    /**
     * Blocs opératoires (ex: "Bloc Opératoire" regroupant les salles 1 à
     * 5), pour le filtre "Bloc" (choix unique — la direction valide
     * bloc par bloc, un multi-choix sur les salles n'était pas pratique).
     */
    public function blocOptions(): \Illuminate\Support\Collection
    {
        return DB::table('app.vw_erp_blocs')
            ->orderBy('LibBloc')
            ->get(['CodBloc', 'LibBloc']);
    }

    /**
     * Liste des intervenants ayant au moins une déclaration, pour le
     * filtre "Intervenant" (recherche dans une liste) de l'écran Direction.
     */
    public function intervenantOptions(): \Illuminate\Support\Collection
    {
        return DB::table('app.vw_erp_intervenants as e')
            ->whereIn('e.CodInterv', function ($q): void {
                $q->select('cod_interv')->from('app.extra_declarations')->distinct();
            })
            ->orderBy('e.DesInterv')
            ->get(['e.CodInterv', 'e.DesInterv'])
            ->unique('CodInterv')
            ->values();
    }

    /**
     * Export Excel (.xlsx) des déclarations filtrées, avec les mêmes filtres
     * (dates + statuts) que l'écran. Voir App\Exports\DeclarationsExport.
     */
    public function exportExcel(Request $request): Response
    {
        $username = $request->user()->getAuthIdentifier();
        $canValidate = AccessControl::hasDirectionAccess($username);

        $statuses = $canValidate ? $this->resolveStatuses($request) : ['VALIDE'];
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());
        $intervenant = trim((string) $request->input('intervenant', ''));
        $recherche = trim((string) $request->input('recherche', ''));
        $bloc = trim((string) $request->input('bloc', ''));

        $rows = $this->buildQuery($statuses, $dateDebut, $dateFin, $intervenant, $recherche, $bloc)->get();

        $filename = 'controle_direction_' . $dateDebut . '_au_' . $dateFin . '.xlsx';

        return Excel::download(new DeclarationsExport($rows), $filename);
    }

    private function resolveStatuses(Request $request): array
    {
        return collect($request->input('statuts', ['PREVALIDE']))
            ->filter(fn ($status) => in_array($status, ['SOUMIS', 'PREVALIDE', 'VALIDE', 'REJETE'], true))->all();
    }

    public function buildQuery(array $statuses, string $dateDebut, string $dateFin, string $intervenant = '', string $recherche = '', string $bloc = ''): \Illuminate\Database\Query\Builder
    {
        return DB::table('app.extra_declarations as d')
            ->leftJoin('app.vw_erp_actes_bloc_direction as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoin('app.vw_erp_acte_intervenants as i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')->on('d.cod_interv', '=', 'i.CodInterv');
            })
            ->when($statuses, fn ($q) => $q->whereIn('d.statut', $statuses))
            ->whereDate('a.DatOpe', '>=', $dateDebut)
            ->whereDate('a.DatOpe', '<=', $dateFin)
            ->when($bloc !== '', fn ($q) => $q->where('a.CodBloc', $bloc))
            ->when($intervenant !== '', function ($q) use ($intervenant): void {
                // Le champ "Intervenant" est rempli via une liste avec recherche
                // (datalist) au format "CodInterv — Nom" ; si l'utilisateur a
                // choisi une entrée de la liste, on filtre par code exact.
                // Sinon (texte libre non reconnu), on retombe sur une
                // recherche par nom, pour rester tolérant.
                if (preg_match('/^(\d+)/', $intervenant, $m)) {
                    $q->where('d.cod_interv', (int) $m[1]);
                } else {
                    $q->where('i.DesInterv', 'like', '%'.$intervenant.'%');
                }
            })
            ->when($recherche !== '', function ($q) use ($recherche): void {
                $q->where(function ($sub) use ($recherche): void {
                    $sub->where('d.num_doss', 'like', '%'.$recherche.'%')
                        ->orWhere('a.NomPatient', 'like', '%'.$recherche.'%')
                        ->orWhere('a.PrenomPatient', 'like', '%'.$recherche.'%');
                });
            })
            ->select(
                'd.*',
                'a.LibelleActe', 'a.DatOpe', 'a.DesignationSalle', 'a.Chirurgien', 'a.Reanimateur', 'a.HDAnest', 'a.HFAnest', 'a.Debut_Anesthesie', 'a.Fin_Anesthesie',
                'a.NomPatient', 'a.PrenomPatient',
                'i.DesInterv', 'i.DesTypInterv', 'i.LoginErp', 'i.MatriculePointeuse',
                'i.HeureEmploiDebut1', 'i.HeureEmploiFin1', 'i.HeureEmploiDebut2', 'i.HeureEmploiFin2', 'i.Repos',
                'i.HeurePointageEntree', 'i.HeurePointageSortie'
            )
            // Détecte une AUTRE déclaration du même intervenant, le même
            // jour, dont la plage horaire de planification chevauche celle
            // de cette ligne (hors déclarations déjà refusées) — pour
            // signaler visuellement les doublons/chevauchements à
            // contrôler (ex: 10:00-16:00 et 11:00-15:30 le même jour).
            ->selectRaw("CASE WHEN EXISTS (
                SELECT 1 FROM app.extra_declarations d2
                INNER JOIN app.vw_erp_actes_bloc_direction a2 ON d2.num_intv = a2.NumIntv
                WHERE d2.cod_interv = d.cod_interv
                  AND d2.id <> d.id
                  AND d2.statut <> 'REJETE'
                  AND a2.HDAnest IS NOT NULL AND a2.HFAnest IS NOT NULL
                  AND a.HDAnest IS NOT NULL AND a.HFAnest IS NOT NULL
                  AND CAST(a2.DatOpe AS date) = CAST(a.DatOpe AS date)
                  AND a2.HDAnest < a.HFAnest AND a2.HFAnest > a.HDAnest
            ) THEN 1 ELSE 0 END AS chevauchement")
            // Doublon inter-dossier : même intervenant, même patient et même
            // acte déjà déclaré sur un AUTRE dossier — cas d'un acte
            // transféré en sous-dossier par la facturation après la saisie
            // initiale. Le rapprochement se fait par CinPatient (numéro de
            // CIN, stable même quand la facturation crée un sous-dossier),
            // avec IdentifiantPatient en repli si le CIN n'est pas renseigné.
            ->selectRaw("CASE WHEN EXISTS (
                SELECT 1 FROM app.extra_declarations d3
                INNER JOIN app.vw_erp_actes_bloc_direction a3 ON d3.num_intv = a3.NumIntv
                WHERE d3.cod_interv = d.cod_interv
                  AND d3.id <> d.id
                  AND d3.statut <> 'REJETE'
                  AND a3.CodeActe = a.CodeActe
                  AND a3.NumDoss <> a.NumDoss
                  AND (
                        (a.CinPatient IS NOT NULL AND a3.CinPatient = a.CinPatient)
                     OR (a.CinPatient IS NULL AND a.IdentifiantPatient IS NOT NULL AND a3.IdentifiantPatient = a.IdentifiantPatient)
                  )
            ) THEN 1 ELSE 0 END AS doublon_sous_dossier")
            // Regroupe par intervenant, puis trie chaque groupe par date et
            // heure d'acte : facilite le contrôle d'un même intervenant sur
            // plusieurs jours/actes d'affilée (demande Direction).
            ->orderBy('i.DesInterv')
            ->orderBy('a.DatOpe');
    }

    public function decide(Request $request, int $declaration): RedirectResponse
    {
        abort_unless(AccessControl::hasDirectionAccess($request->user()->getAuthIdentifier()), 403, 'Accès en lecture seule : la validation est réservée à la direction.');

        $data = $request->validate([
            'decision' => ['required', 'in:VALIDE,REJETE'],
            'motif' => [$request->input('decision') === 'REJETE' ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($declaration, $data, $request): void {
            $item = DB::table('app.extra_declarations')->where('id', $declaration)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if(! in_array($item->statut, ['SOUMIS', 'PREVALIDE'], true), 422, 'Cette déclaration est déjà traitée.');

            // Certains services n'attendent pas la pré-validation du major :
            // la direction valide directement. Dans ce cas le montant doit
            // être renseigné (via la colonne « Montant ») avant la validation.
            if ($data['decision'] === 'VALIDE' && $item->montant === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['montant' => 'Veuillez renseigner ce champ.']);
            }

            if ($data['decision'] === 'VALIDE' && config('etrasbloc.prevalidation_obligatoire') && $item->statut !== 'PREVALIDE') {
                abort(422, 'La pré-validation par le major du bloc est obligatoire avant la validation finale.');
            }

            DB::table('app.extra_declarations')->where('id', $declaration)->update([
                'statut' => $data['decision'],
                'valide_par_username' => $request->user()->getAuthIdentifier(),
                'valide_le' => now(),
                'motif_decision' => $data['motif'],
            ]);
            DB::table('app.extra_declaration_audits')->insert([
                'declaration_id' => $declaration,
                'action' => $data['decision'],
                'acteur_username' => $request->user()->getAuthIdentifier(),
                'donnees_avant' => json_encode(['statut' => $item->statut]),
                'donnees_apres' => json_encode(['statut' => $data['decision'], 'motif' => $data['motif']]),
            ]);
        });

        return back()->with('success', 'Décision enregistrée et journalisée.');
    }

    /**
     * Corrige le montant d'une déclaration (action réservée à la direction).
     * Le montant est obligatoire et la correction est journalisée dans
     * app.extra_declaration_audits (action "MONTANT_CORRIGE").
     */
    public function updateMontant(Request $request, int $declaration): RedirectResponse
    {
        abort_unless(AccessControl::hasDirectionAccess($request->user()->getAuthIdentifier()), 403, 'Accès en lecture seule : la correction du montant est réservée à la direction.');

        $data = $request->validate([
            'montant' => ['required', 'integer', 'in:100,150,200,250,300'],
            'motif'   => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($declaration, $data, $request): void {
            $item = DB::table('app.extra_declarations')->where('id', $declaration)->lockForUpdate()->first();
            abort_unless($item, 404);

            DB::table('app.extra_declarations')->where('id', $declaration)->update(['montant' => $data['montant']]);
            DB::table('app.extra_declaration_audits')->insert([
                'declaration_id'  => $declaration,
                'action'          => 'MONTANT_CORRIGE',
                'acteur_username' => $request->user()->getAuthIdentifier(),
                'donnees_avant'   => json_encode(['montant' => $item->montant]),
                'donnees_apres'   => json_encode(['montant' => $data['montant'], 'motif' => $data['motif']]),
            ]);
        });

        return back()->with('success', 'Montant corrigé et journalisé.');
    }

    /**
     * Corrige une décision prise par erreur (validation ou rejet) : la
     * déclaration repasse "En attente" pour être retraitée. Un motif est
     * obligatoire et la traçabilité (qui / quand / pourquoi) est conservée
     * dans app.extra_declaration_audits, sans rien effacer de l'historique.
     */
    public function invalidate(Request $request, int $declaration): RedirectResponse
    {
        abort_unless(AccessControl::hasDirectionAccess($request->user()->getAuthIdentifier()), 403, 'Accès en lecture seule : la dévalidation est réservée à la direction.');

        $data = $request->validate([
            'motif' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($declaration, $data, $request): void {
            $item = DB::table('app.extra_declarations')->where('id', $declaration)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_unless(in_array($item->statut, ['VALIDE', 'REJETE'], true), 422, 'Seule une déclaration validée ou refusée peut être dévalidée.');

            DB::table('app.extra_declarations')->where('id', $declaration)->update([
                'statut' => 'SOUMIS',
                'valide_par_username' => null,
                'valide_le' => null,
                'motif_decision' => null,
            ]);
            DB::table('app.extra_declaration_audits')->insert([
                'declaration_id' => $declaration,
                'action' => 'DEVALIDE',
                'acteur_username' => $request->user()->getAuthIdentifier(),
                'donnees_avant' => json_encode([
                    'statut' => $item->statut,
                    'valide_par_username' => $item->valide_par_username,
                    'valide_le' => $item->valide_le,
                    'motif_decision' => $item->motif_decision,
                ]),
                'donnees_apres' => json_encode(['statut' => 'SOUMIS', 'motif' => $data['motif']]),
            ]);
        });

        return back()->with('success', 'Décision annulée : la déclaration repasse en attente et reste tracée dans l’historique.');
    }

    /**
     * Historique complet d'une déclaration (création, validation, rejet,
     * dévalidation…) pour le bouton "Traçabilité".
     */
    public function audits(int $declaration): JsonResponse
    {
        $exists = DB::table('app.extra_declarations')->where('id', $declaration)->exists();
        abort_unless($exists, 404);

        $audits = DB::table('app.extra_declaration_audits')
            ->where('declaration_id', $declaration)
            ->orderBy('created_at')
            ->get(['action', 'acteur_username', 'created_at', 'donnees_apres']);

        return response()->json(
            $audits->map(function ($row) {
                $apres = json_decode($row->donnees_apres ?? '{}', true) ?: [];

                return [
                    'action' => self::ACTION_LABELS[$row->action] ?? $row->action,
                    'acteur' => $row->acteur_username,
                    'date' => Carbon::parse($row->created_at)->format('d/m/Y H:i:s'),
                    'motif' => $apres['motif'] ?? $apres['observation'] ?? null,
                    'montant' => $apres['montant'] ?? null,
                ];
            })->values()
        );
    }

    /**
     * Détail brut des pointages d'un matricule pour une journée donnée.
     * Utilisé par l'icône "i" à côté de la colonne Heure pointage.
     */
    public function pointageDetail(string $matricule, string $date): JsonResponse
    {
        $rows = DB::table('app.vw_erp_pointages')
            ->whereRaw('LTRIM(RTRIM(Matricule)) = ?', [trim($matricule)])
            ->whereDate('PointeLe', $date)
            ->orderBy('PointeLe')
            ->get(['PointeLe', 'NomComplet']);

        return response()->json(
            $rows->map(fn ($row) => [
                'heure' => Carbon::parse($row->PointeLe)->format('H:i:s'),
                'nom' => $row->NomComplet,
            ])->values()
        );
    }
}
