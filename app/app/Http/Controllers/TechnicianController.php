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

        $created = DB::transaction(function () use ($data, $validParticipants, $request): int {
            $count = 0;
            foreach ($validParticipants as $participant) {
                $exists = DB::table('app.extra_declarations')
                    ->where('num_intv', $data['num_intv'])
                    ->where('cod_interv', $participant->CodInterv)
                    ->exists();
                if ($exists) {
                    continue;
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

        return redirect()->route('technician.index', ['dossier' => $data['num_doss']])
            ->with('success', $created.' déclaration(s) transmise(s) à la direction.');
    }
}
