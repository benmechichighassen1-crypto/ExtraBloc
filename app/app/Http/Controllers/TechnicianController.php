<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function index(Request $request): View
    {
        $acts = collect();
        $participants = collect();
        $declarations = collect();
        $dossier = $request->string('dossier')->trim()->toString();

        if ($dossier !== '') {
            $acts = DB::table('app.vw_erp_actes_bloc_direction as a')
                ->where('a.NumDoss', $dossier)
                ->select('a.*')
                ->orderByDesc('a.HDAnest')
                ->get();
            $participants = DB::table('app.vw_erp_acte_intervenants')
                ->where('NumDoss', $dossier)
                ->orderBy('RoleIntervenant')
                ->get()
                ->groupBy('NumIntv');
            $declarations = DB::table('app.extra_declarations')
                ->where('num_doss', $dossier)
                ->get()
                ->groupBy('num_intv')
                ->map(fn ($items) => $items->keyBy('cod_interv'));
        }

        return view('technician.index', compact('dossier', 'acts', 'participants', 'declarations'));
    }

    /**
     * Historique personnel : chaque intervenant connecté ne voit que ses
     * propres déclarations, filtrables par date et par statut.
     *
     * Un même utilisateur ERP peut être rattaché à PLUSIEURS CodInterv
     * (ex: panseur ET instrumentiste) : on récupère donc tous les
     * CodInterv liés à son UserName, pas seulement le premier, pour que
     * l'historique remonte bien les dossiers de tous ses rôles.
     */
    public function history(Request $request): View
    {
        $username = (string) $request->user()->getAuthIdentifier();

        $intervs = DB::table('app.vw_erp_intervenants')
            ->whereRaw('LTRIM(RTRIM(UserName)) = ?', [trim($username)])
            ->get();
        $codInterv = $intervs->pluck('CodInterv')->all();

        $statusLabels = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
        $statuses = collect($request->input('statuts', ['SOUMIS', 'PREVALIDE', 'VALIDE', 'REJETE']))
            ->filter(fn ($status) => array_key_exists($status, $statusLabels))->all();
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());

        $declarations = collect();

        if ($codInterv !== []) {
            $declarations = DB::table('app.extra_declarations as d')
                ->leftJoin('app.vw_erp_actes_bloc_direction as a', 'd.num_intv', '=', 'a.NumIntv')
                ->whereIn('d.cod_interv', $codInterv)
                ->when($statuses, fn ($q) => $q->whereIn('d.statut', $statuses))
                ->whereDate('a.DatOpe', '>=', $dateDebut)
                ->whereDate('a.DatOpe', '<=', $dateFin)
                ->select(
                    'd.*',
                    'a.LibelleActe', 'a.DatOpe', 'a.DesignationSalle',
                    'a.NomPatient', 'a.PrenomPatient'
                )
                ->orderByDesc('a.DatOpe')
                ->paginate(25)
                ->withQueryString();
        }

        return view('technician.historique', compact('declarations', 'statuses', 'dateDebut', 'dateFin', 'intervs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'num_intv' => ['required', 'string', 'max:30'],
            'num_doss' => ['required', 'string', 'max:30'],
            'cod_interv' => ['required', 'array', 'min:1'],
            'cod_interv.*' => ['integer'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ]);

        $validParticipants = DB::table('app.vw_erp_acte_intervenants')
            ->where('NumIntv', $data['num_intv'])
            ->where('NumDoss', $data['num_doss'])
            ->whereIn('CodInterv', $data['cod_interv'])
            ->select('CodInterv', 'RoleIntervenant')
            ->get();

        abort_if($validParticipants->count() !== count(array_unique($data['cod_interv'])), 422, 'Un intervenant sélectionné ne fait pas partie de cet acte.');

        // Pour détecter un doublon inter-dossier (voir plus bas) : l'acte
        // concerné, avec l'identifiant patient stable (persiste même si la
        // facturation transfère l'acte vers un sous-dossier).
        $acte = DB::table('app.vw_erp_actes_bloc_direction')
            ->where('NumIntv', $data['num_intv'])
            ->select('CodeActe', 'IdentifiantPatient')
            ->first();

        $skippedDuplicates = [];

        $created = DB::transaction(function () use ($data, $validParticipants, $request, $acte, &$skippedDuplicates): int {
            $count = 0;
            foreach ($validParticipants as $participant) {
                $exists = DB::table('app.extra_declarations')
                    ->where('num_intv', $data['num_intv'])
                    ->where('cod_interv', $participant->CodInterv)
                    ->exists();
                if ($exists) {
                    continue;
                }

                // Doublon inter-dossier : même intervenant, même patient
                // (IdentifiantPatient, stable même après transfert vers un
                // sous-dossier par la facturation) et même acte, déjà
                // déclaré sur un AUTRE numéro de dossier et non refusé.
                if ($acte && $acte->IdentifiantPatient) {
                    $doublon = DB::table('app.extra_declarations as d2')
                        ->join('app.vw_erp_actes_bloc_direction as a2', 'd2.num_intv', '=', 'a2.NumIntv')
                        ->where('d2.cod_interv', $participant->CodInterv)
                        ->where('d2.statut', '<>', 'REJETE')
                        ->where('a2.IdentifiantPatient', $acte->IdentifiantPatient)
                        ->where('a2.CodeActe', $acte->CodeActe)
                        ->where('d2.num_doss', '<>', $data['num_doss'])
                        ->exists();

                    if ($doublon) {
                        $skippedDuplicates[] = $participant->CodInterv;
                        continue;
                    }
                }

                $id = DB::table('app.extra_declarations')->insertGetId([
                    'num_intv' => $data['num_intv'],
                    'num_doss' => $data['num_doss'],
                    'cod_interv' => $participant->CodInterv,
                    'role_intervenant' => $participant->RoleIntervenant,
                    'declared_by_username' => $request->user()->getAuthIdentifier(),
                    'observation' => $data['observation'],
                    'statut' => 'SOUMIS',
                ]);
                DB::table('app.extra_declaration_audits')->insert([
                    'declaration_id' => $id,
                    'action' => 'SOUMIS',
                    'acteur_username' => $request->user()->getAuthIdentifier(),
                    'donnees_apres' => json_encode(['cod_interv' => $participant->CodInterv, 'observation' => $data['observation']]),
                ]);
                $count++;
            }
            return $count;
        });

        $message = $created.' déclaration(s) transmise(s) à la direction.';
        if ($skippedDuplicates !== []) {
            $message .= ' '.count($skippedDuplicates).' intervenant(s) NON déclaré(s) : une déclaration existe déjà pour ce même patient et ce même acte sur un autre dossier (probable sous-dossier créé par la facturation). Vérifiez avant de resaisir.';
        }

        return redirect()->route('technician.index', ['dossier' => $data['num_doss']])
            ->with($skippedDuplicates !== [] ? 'warning' : 'success', $message);
    }
}
