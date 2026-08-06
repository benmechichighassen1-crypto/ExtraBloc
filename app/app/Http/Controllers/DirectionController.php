<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DirectionController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = collect($request->input('statuts', ['SOUMIS', 'PREVALIDE']))
            ->filter(fn ($status) => in_array($status, ['SOUMIS', 'PREVALIDE', 'VALIDE', 'REJETE'], true))->all();
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());
        $query = DB::table('app.extra_declarations as d')
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
        $declarations = $query
            ->paginate(25)
            ->withQueryString();

        return view('direction.index', compact('declarations', 'statuses', 'dateDebut', 'dateFin'));
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
