<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MajorController extends Controller
{
    /** Libellés lisibles des statuts (identiques à DirectionController). */
    public const STATUS_LABELS = [
        'SOUMIS' => 'En attente',
        'PREVALIDE' => 'Prévalidé',
        'VALIDE' => 'Validé',
        'REJETE' => 'Refusé',
    ];

    public function index(Request $request): View
    {
        $statuses = collect($request->input('statuts', ['SOUMIS', 'PREVALIDE', 'REJETE']))
            ->filter(fn ($status) => array_key_exists($status, self::STATUS_LABELS))->all();
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());

        $declarations = DB::table('app.extra_declarations as d')
            ->leftJoin('app.vw_erp_actes_bloc_direction as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoin('app.vw_erp_acte_intervenants as i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')->on('d.cod_interv', '=', 'i.CodInterv');
            })
            ->when($statuses, fn ($q) => $q->whereIn('d.statut', $statuses))
            ->whereDate('a.DatOpe', '>=', $dateDebut)
            ->whereDate('a.DatOpe', '<=', $dateFin)
            ->select(
                'd.*',
                'a.LibelleActe', 'a.DatOpe', 'a.DesignationSalle', 'a.Chirurgien', 'a.Reanimateur',
                'a.HDAnest', 'a.HFAnest', 'a.Debut_Anesthesie', 'a.Fin_Anesthesie',
                'a.NomPatient', 'a.PrenomPatient',
                'i.DesInterv', 'i.DesTypInterv', 'i.MatriculePointeuse',
                'i.HeureEmploiDebut1', 'i.HeureEmploiFin1', 'i.HeureEmploiDebut2', 'i.HeureEmploiFin2', 'i.Repos',
                'i.HeurePointageEntree', 'i.HeurePointageSortie'
            )
            ->orderByDesc('a.DatOpe')
            ->paginate(25)
            ->withQueryString();

        return view('major.index', compact('declarations', 'statuses', 'dateDebut', 'dateFin'));
    }

    /**
     * Pré-validation ou rejet précoce par le major du bloc. N'agit que sur
     * les déclarations encore "En attente" (SOUMIS) : la validation finale
     * reste réservée à la Direction (voir DirectionController::decide()).
     */
    public function decide(Request $request, int $declaration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:PREVALIDE,REJETE'],
            'motif' => [$request->input('decision') === 'REJETE' ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($declaration, $data, $request): void {
            $item = DB::table('app.extra_declarations')->where('id', $declaration)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if($item->statut !== 'SOUMIS', 422, 'Seule une déclaration « En attente » peut être pré-validée ou rejetée par le major.');

            $username = $request->user()->getAuthIdentifier();

            if ($data['decision'] === 'PREVALIDE') {
                DB::table('app.extra_declarations')->where('id', $declaration)->update([
                    'statut' => 'PREVALIDE',
                    'prevalide_par_username' => $username,
                    'prevalide_le' => now(),
                    'prevalidation_auto' => 0,
                    'motif_prevalidation' => $data['motif'],
                ]);
            } else {
                DB::table('app.extra_declarations')->where('id', $declaration)->update([
                    'statut' => 'REJETE',
                    'valide_par_username' => $username,
                    'valide_le' => now(),
                    'motif_decision' => $data['motif'],
                ]);
            }

            DB::table('app.extra_declaration_audits')->insert([
                'declaration_id' => $declaration,
                'action' => $data['decision'],
                'acteur_username' => $username,
                'donnees_avant' => json_encode(['statut' => $item->statut]),
                'donnees_apres' => json_encode(['statut' => $data['decision'], 'motif' => $data['motif']]),
            ]);
        });

        return back()->with('success', 'Pré-validation enregistrée et journalisée.');
    }
}
