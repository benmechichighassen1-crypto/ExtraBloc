<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DirectionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('statut', 'SOUMIS')->upper()->toString();
        $declarations = DB::table('app.extra_declarations as d')
            ->leftJoin('app.vw_erp_actes_bloc as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoin('app.vw_erp_acte_intervenants as i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')->on('d.cod_interv', '=', 'i.CodInterv');
            })
            ->where('d.statut', $status)
            ->select('d.*', 'a.LibelleActe', 'a.HDAnest', 'a.HFAnest', 'a.Salle', 'i.DesInterv')
            ->orderBy('d.declared_at')
            ->paginate(25)
            ->withQueryString();

        return view('direction.index', compact('declarations', 'status'));
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
}
