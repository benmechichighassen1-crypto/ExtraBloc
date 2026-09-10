<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MajorController extends Controller
{
    /** Libellés lisibles des statuts (identiques à DirectionController). */
    public const STATUS_LABELS = DirectionController::STATUS_LABELS;

    /**
     * Réutilise DirectionController::buildQuery() (filtres bloc, intervenant,
     * dossier/patient, tri par intervenant, badges chevauchement et doublon
     * inter-dossier) plutôt que de dupliquer la requête. Par défaut, seules
     * les déclarations "En attente" sont chargées — réduit le nombre de
     * lignes, donc le nombre d'allers-retours vers l'ERP par ligne
     * (planning, pointage), à l'ouverture de l'écran.
     */
    public function index(Request $request): View
    {
        $direction = new DirectionController();

        $statuses = collect($request->input('statuts', ['SOUMIS']))
            ->filter(fn ($status) => array_key_exists($status, self::STATUS_LABELS))->all();
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());
        $intervenant = trim((string) $request->input('intervenant', ''));
        $recherche = trim((string) $request->input('recherche', ''));
        $bloc = trim((string) $request->input('bloc', ''));

        $declarations = $direction->buildQuery($statuses, $dateDebut, $dateFin, $intervenant, $recherche, $bloc)
            ->paginate(25)
            ->withQueryString();

        $intervenantOptions = $direction->intervenantOptions();
        $blocOptions = $direction->blocOptions();

        return view('major.index', compact('declarations', 'statuses', 'dateDebut', 'dateFin', 'intervenant', 'recherche', 'bloc', 'intervenantOptions', 'blocOptions'));
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
            'montant'  => [$request->input('decision') === 'PREVALIDE' ? 'required' : 'nullable', 'integer', 'in:100,150,200,250,300,350,400'],
            'motif'    => [$request->input('decision') === 'REJETE' ? 'required' : 'nullable', 'string', 'max:500'],
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
                    'montant' => $data['montant'],
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
                'donnees_apres' => json_encode(['statut' => $data['decision'], 'montant' => $data['montant'] ?? null, 'motif' => $data['motif']]),
            ]);
        });

        return back()->with('success', 'Pré-validation enregistrée et journalisée.');
    }
}
