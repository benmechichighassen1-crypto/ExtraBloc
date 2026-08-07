<?php

namespace App\Http\Controllers;

use App\Exports\DeclarationsExport;
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
        'VALIDE' => 'Validation',
        'REJETE' => 'Rejet',
        'DEVALIDE' => 'Dévalidation (correction)',
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
        $statuses = $this->resolveStatuses($request);
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());

        $declarations = $this->buildQuery($statuses, $dateDebut, $dateFin)
            ->paginate(25)
            ->withQueryString();

        return view('direction.index', compact('declarations', 'statuses', 'dateDebut', 'dateFin'));
    }

    /**
     * Export Excel (.xlsx) des déclarations filtrées, avec les mêmes filtres
     * (dates + statuts) que l'écran. Voir App\Exports\DeclarationsExport.
     */
    public function exportExcel(Request $request): Response
    {
        $statuses = $this->resolveStatuses($request);
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());

        $rows = $this->buildQuery($statuses, $dateDebut, $dateFin)->get();

        $filename = 'controle_direction_' . $dateDebut . '_au_' . $dateFin . '.xlsx';

        return Excel::download(new DeclarationsExport($rows), $filename);
    }

    private function resolveStatuses(Request $request): array
    {
        return collect($request->input('statuts', ['SOUMIS', 'VALIDE', 'REJETE']))
            ->filter(fn ($status) => in_array($status, ['SOUMIS', 'PREVALIDE', 'VALIDE', 'REJETE'], true))->all();
    }

    private function buildQuery(array $statuses, string $dateDebut, string $dateFin)
    {
        return DB::table('app.extra_declarations as d')
            ->leftJoin('app.vw_erp_actes_bloc_direction as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoin('app.vw_erp_acte_intervenants as i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')->on('d.cod_interv', '=', 'i.CodInterv');
            })
            ->when($statuses, fn ($q) => $q->whereIn('d.statut', $statuses))
            ->whereDate('a.DatOpe', '>=', $dateDebut)
            ->whereDate('a.DatOpe', '<=', $dateFin)
            ->select(
                'd.*',
                'a.LibelleActe', 'a.DatOpe', 'a.DesignationSalle', 'a.Chirurgien', 'a.Reanimateur', 'a.HDAnest', 'a.HFAnest', 'a.Debut_Anesthesie', 'a.Fin_Anesthesie',
                'a.NomPatient', 'a.PrenomPatient',
                'i.DesInterv', 'i.DesTypInterv', 'i.LoginErp', 'i.MatriculePointeuse',
                'i.HeureEmploiDebut1', 'i.HeureEmploiFin1', 'i.HeureEmploiDebut2', 'i.HeureEmploiFin2',
                'i.HeurePointageEntree', 'i.HeurePointageSortie'
            )
            ->orderByDesc('a.DatOpe');
    }

    public function decide(Request $request, int $declaration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:VALIDE,REJETE'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($declaration, $data, $request): void {
            $item = DB::table('app.extra_declarations')->where('id', $declaration)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if(! in_array($item->statut, ['SOUMIS', 'PREVALIDE'], true), 422, 'Cette déclaration est déjà traitée.');

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
     * Corrige une décision prise par erreur (validation ou rejet) : la
     * déclaration repasse "En attente" pour être retraitée. Un motif est
     * obligatoire et la traçabilité (qui / quand / pourquoi) est conservée
     * dans app.extra_declaration_audits, sans rien effacer de l'historique.
     */
    public function invalidate(Request $request, int $declaration): RedirectResponse
    {
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
