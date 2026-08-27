<?php

namespace App\Http\Controllers;

use App\Exports\AnapathSuiviExport;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnapathSuiviController extends Controller
{
    /**
     * Délai maximal (en jours) entre la saisie de la demande et la réception
     * du résultat avant signalement « résultat en retard ».
     */
    public const DELAI_ATTENDU_JOURS = 5;

    public function index(Request $request): View
    {
        $periode = (string) $request->input('periode', 'personnalisee');
        if ($periode === 'aujourdhui') {
            $dateDebut = $dateFin = today()->toDateString();
        } elseif ($periode === 'hier') {
            $dateDebut = $dateFin = today()->subDay()->toDateString();
        } else {
            $dateDebut = $request->input('date_debut', today()->subDays(8)->toDateString());
            $dateFin = $request->input('date_fin', today()->toDateString());
        }
        $dossier = trim((string) $request->input('dossier'));
        $medecin = trim((string) $request->input('medecin'));
        $laboratoire = (string) $request->input('laboratoire', '');
        $statut = (string) $request->input('statut', 'tous');
        $statutResultat = $request->input('statut_resultat', 'tous');
        $statutPaiement = $request->input('statut_paiement', 'tous');
        $enRetard = $request->input('en_retard', 'tous');

        $rows = $this->buildQuery($dateDebut, $dateFin, $dossier, $medecin, $laboratoire)
            ->get()
            ->map(fn ($r) => $this->decorate($r))
            ->filter(function ($r) use ($statut, $statutResultat, $statutPaiement, $enRetard) {
                if ($statut === 'annulee' && $r->annule_le === null) return false;
                if ($statut === 'resultat' && ! $r->resultat_recu) return false;
                if ($statut === 'attente' && ($r->resultat_recu || $r->annule_le !== null)) return false;
                if ($statut === 'retard' && ! $r->en_retard) return false;
                if ($statutResultat === 'avec' && ! $r->resultat_recu) return false;
                if ($statutResultat === 'sans' && $r->resultat_recu) return false;
                if ($statutPaiement === 'laboratoire' && $r->paiement_type !== 'laboratoire') return false;
                if ($statutPaiement === 'facture' && $r->paiement_type !== 'facture') return false;
                if ($statutPaiement === 'a_renseigner' && $r->paiement_type !== null) return false;
                if ($enRetard === 'en_retard' && ! $r->en_retard) return false;
                return true;
            })
            ->values();

        $rows = $this->attachPjs($rows);

        return view('anapath.index', [
            'rows'           => $rows,
            'periode'        => $periode,
            'dateDebut'      => $dateDebut,
            'dateFin'        => $dateFin,
            'dossier'        => $dossier,
            'medecin'        => $medecin,
            'laboratoire'    => $laboratoire,
            'statut'         => $statut,
            'statutResultat' => $statutResultat,
            'statutPaiement' => $statutPaiement,
            'enRetard'       => $enRetard,
            'medecinOptions' => $this->medecinOptions(),
            'laboratoires'   => $this->laboratoires(),
            'delaiAttendu'   => self::DELAI_ATTENDU_JOURS,
        ]);
    }

    /**
     * Réceptionne le résultat anapath (+ compte rendu/PJ éventuel).
     */
    public function recevoirResultat(Request $request, int $demande): RedirectResponse
    {
        $data = $request->validate([
            'resultat_recu'    => ['nullable', 'boolean'],
            'resultat_recu_le' => ['nullable', 'date'],
            'resultat_pj'      => ['nullable', 'array'],
            'resultat_pj.*'    => ['file', 'max:10240'],
        ]);

        $resultatRecu = (bool) ($data['resultat_recu'] ?? false);
        $recuLe = $resultatRecu ? Carbon::parse($data['resultat_recu_le'] ?? now()) : null;

        DB::table('app.anapath_demandes')->where('id', $demande)->update([
            'resultat_recu'    => $resultatRecu,
            'resultat_recu_le' => $recuLe,
            'modifie_par_username' => auth()->user()?->getAuthIdentifier(),
            'modifie_le' => now(),
        ]);

        // Chaque PJ est conservée (plus d'écrasement) : une ligne par fichier.
        $pjNoms = [];
        foreach ($data['resultat_pj'] ?? [] as $fichier) {
            if (! $fichier) {
                continue;
            }
            DB::table('app.anapath_demandes_pj')->insert([
                'demande_id' => $demande,
                'nom' => $fichier->getClientOriginalName(),
                'path' => $fichier->store('anapath_comptes_rendus', 'local'),
                'uploaded_by_username' => auth()->user()?->getAuthIdentifier(),
                'created_at' => now(),
            ]);
            $pjNoms[] = $fichier->getClientOriginalName();
        }

        $this->logAction($demande, 'RESULTAT_MAJ',
            ($resultatRecu ? 'Résultat reçu le '.($recuLe?->toDateString() ?? now()->toDateString()) : 'Résultat marqué en attente')
            .($pjNoms ? ' · PJ ajoutées : '.implode(', ', $pjNoms) : ''));

        return back()->with('success', 'Résultat anapath enregistré'.($pjNoms ? ' ('.count($pjNoms).' PJ enregistrée(s)).' : '.'));
    }

    /**
     * Renseigne / corrige le mode de paiement de l'anapath.
     */
    public function setPaiement(Request $request, int $demande): RedirectResponse
    {
        $data = $request->validate([
            'paiement_type' => ['required', 'in:laboratoire,facture'],
        ]);

        DB::table('app.anapath_demandes')->where('id', $demande)->update([
            'paiement_type' => $data['paiement_type'],
            'paiement_date' => now(),
            'modifie_par_username' => auth()->user()?->getAuthIdentifier(),
            'modifie_le' => now(),
        ]);

        $this->logAction($demande, 'PAIEMENT_MAJ',
            $data['paiement_type'] === 'laboratoire' ? 'Payé chez le laboratoire' : 'Inclus dans la facture patient');

        return back()->with('success', 'Paiement anapath mis à jour.');
    }

    /**
     * Historique complet (traçabilité) d'une demande.
     */
    public function trace(Request $request, int $demande): \Illuminate\Http\JsonResponse
    {
        $labels = [
            'RESULTAT_MAJ' => 'Mise à jour du résultat',
            'PAIEMENT_MAJ' => 'Mise à jour du paiement',
            'PJ_AJOUT'     => 'Ajout de pièce jointe',
        ];

        $rows = DB::table('app.anapath_demandes_audits')
            ->where('demande_id', $demande)
            ->orderBy('action_date')
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'action'  => $labels[$r->action] ?? $r->action,
            'acteur'  => $r->acteur_username,
            'date'    => Carbon::parse($r->action_date)->format('d/m/Y H:i:s'),
            'details' => $r->details,
        ])->values());
    }

    /**
     * Sert une pièce jointe (compte rendu) pour consultation / téléchargement.
     */
    public function consulterPj(Request $request, int $pj): BinaryFileResponse
    {
        $row = DB::table('app.anapath_demandes_pj')->where('id', $pj)->first(['nom', 'path']);
        abort_unless($row && $row->path, 404, 'Compte rendu (PJ) introuvable.');

        $full = Storage::disk('local')->path($row->path);
        abort_unless(is_file($full), 404, 'Fichier introuvable.');

        return response()->file($full, ['Content-Disposition' => 'inline']);
    }

    /**
     * Journalise une action sur la demande (table app.anapath_demandes_audits).
     */
    private function logAction(int $demande, string $action, ?string $details = null): void
    {
        DB::table('app.anapath_demandes_audits')->insert([
            'demande_id' => $demande,
            'action' => $action,
            'acteur_username' => auth()->user()?->getAuthIdentifier(),
            'action_date' => now(),
            'details' => $details,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $periode = (string) $request->input('periode', 'personnalisee');
        if ($periode === 'aujourdhui') {
            $dateDebut = $dateFin = today()->toDateString();
        } elseif ($periode === 'hier') {
            $dateDebut = $dateFin = today()->subDay()->toDateString();
        } else {
            $dateDebut = $request->input('date_debut', today()->subDays(8)->toDateString());
            $dateFin = $request->input('date_fin', today()->toDateString());
        }
        $dossier = trim((string) $request->input('dossier'));
        $medecin = trim((string) $request->input('medecin'));
        $laboratoire = (string) $request->input('laboratoire', '');
        $statut = (string) $request->input('statut', 'tous');
        $statutPaiement = $request->input('statut_paiement', 'tous');
        $enRetard = $request->input('en_retard', 'tous');

        $rows = $this->buildQuery($dateDebut, $dateFin, $dossier, $medecin, $laboratoire)
            ->get()->map(fn ($r) => $this->decorate($r))
            ->filter(function ($r) use ($statut, $statutPaiement, $enRetard) {
                if ($statut === 'annulee' && $r->annule_le === null) return false;
                if ($statut === 'resultat' && ! $r->resultat_recu) return false;
                if ($statut === 'attente' && ($r->resultat_recu || $r->annule_le !== null)) return false;
                if ($statut === 'retard' && ! $r->en_retard) return false;
                if ($statutPaiement === 'laboratoire' && $r->paiement_type !== 'laboratoire') return false;
                if ($statutPaiement === 'facture' && $r->paiement_type !== 'facture') return false;
                if ($statutPaiement === 'a_renseigner' && $r->paiement_type !== null) return false;
                if ($enRetard === 'en_retard' && ! $r->en_retard) return false;
                return true;
            })
            ->values();

        $rows = $this->attachPjs($rows);

        return Excel::download(new AnapathSuiviExport($rows), 'suivi_anapath.xlsx');
    }

    /**
     * Liste des demandes d'anapath saisies (table app.anapath_demandes),
     * enrichies du nom du laboratoire. Chaque ligne correspond à UNE demande,
     * pas à un acte du registre.
     */
    private function buildQuery(string $from, string $to, string $dossier, string $medecin, string $laboratoire)
    {
        return DB::table('app.anapath_demandes as d')
            ->leftJoin('app.anapath_laboratoires as lab', 'lab.id', '=', 'd.laboratoire_id')
            ->whereDate('d.created_at', '>=', $from)
            ->whereDate('d.created_at', '<=', $to)
            ->when($laboratoire !== '', fn ($q) => $q->where('d.laboratoire_id', $laboratoire))
            ->when($dossier !== '', function ($q) use ($dossier): void {
                $q->where(function ($sub) use ($dossier): void {
                    $sub->where('d.num_doss', 'like', '%'.$dossier.'%')
                        ->orWhere('d.patient_nom', 'like', '%'.$dossier.'%')
                        ->orWhere('d.patient_prenom', 'like', '%'.$dossier.'%');
                });
            })
            ->when($medecin !== '', fn ($q) => $q->where('d.medecin_prescripteur', 'like', '%'.$medecin.'%'))
            ->select(
                'd.id as demande_id',
                'd.numero_demande',
                'd.num_doss',
                'd.code_examen_erp', 'd.nature_prelevement', 'd.site_anatomique',
                'd.patient_nom', 'd.patient_prenom',
                'd.medecin_prescripteur',
                'd.cree_par_username', 'd.created_at',
                'd.resultat_recu', 'd.resultat_recu_le',
                'd.paiement_type', 'd.paiement_date',
                'd.annule_le', 'd.annule_par_username',
                'd.modifie_par_username', 'd.modifie_le',
                'lab.nom as laboratoire_nom',
                // Acte du registre de bloc : même libellé que la liste « Registre bloc » + date de l'acte.
                // NumIntv seul est AMBIGU côté ERP (réutilisé d'une année sur l'autre) :
                // on jumelle avec NumDoss (dossier patient) et on retient l'acte dont la
                // date est la plus proche de la date de la demande.
                DB::raw('(SELECT TOP 1 a2.LibelleActe FROM app.vw_registre_bloc_actes a2 WHERE a2.NumIntv = d.num_intv AND a2.NumDoss = d.num_doss ORDER BY ABS(DATEDIFF(day, a2.DateActe, d.created_at)), a2.DateActe) as LibelleActe'),
                DB::raw('(SELECT TOP 1 a2.DateActe FROM app.vw_registre_bloc_actes a2 WHERE a2.NumIntv = d.num_intv AND a2.NumDoss = d.num_doss ORDER BY ABS(DATEDIFF(day, a2.DateActe, d.created_at)), a2.DateActe) as DateActe'),
            )
            ->orderByDesc('d.created_at');
    }

    private function decorate($r)
    {
        // « En retard » = pas encore de résultat reçu, non annulée, et la demande a
        // atteint le délai attendu (DELAI_ATTENDU_JOURS) compté en jours entiers
        // depuis la date de la demande. Exemple DELAI=1 : une demande créée hier ou
        // avant est en retard ; créée aujourd'hui elle ne l'est pas encore.
        $r->en_retard = ! $r->resultat_recu
            && $r->annule_le === null
            && $r->created_at
            && Carbon::parse($r->created_at)->startOfDay()
                ->lt(now()->startOfDay()->subDays(self::DELAI_ATTENDU_JOURS));

        if ($r->annule_le !== null) {
            $r->statut = 'annulee';
            $r->statut_label = 'Annulée';
        } elseif ($r->resultat_recu) {
            $r->statut = 'resultat';
            $r->statut_label = 'Résultat reçu';
        } else {
            $r->statut = 'attente';
            $r->statut_label = 'En attente';
        }

        return $r;
    }

    /**
     * Rattache la liste complète des pièces jointes à chaque demande
     * (app.anapath_demandes_pj). Une demande peut avoir plusieurs PJ.
     */
    private function attachPjs($rows)
    {
        $ids = $rows->pluck('demande_id')->filter()->values();
        $byDemande = $ids->isEmpty()
            ? collect()
            : DB::table('app.anapath_demandes_pj')
                ->whereIn('demande_id', $ids)
                ->orderBy('created_at')->orderBy('id')
                ->get()
                ->groupBy('demande_id');

        foreach ($rows as $r) {
            $r->pj_list = $byDemande->get($r->demande_id, collect());
        }

        return $rows;
    }

    private function medecinOptions(): \Illuminate\Support\Collection
    {
        return DB::table('app.anapath_demandes')
            ->whereNotNull('medecin_prescripteur')->where('medecin_prescripteur', '<>', '')
            ->distinct()->orderBy('medecin_prescripteur')
            ->get(['medecin_prescripteur'])->pluck('medecin_prescripteur')->filter()->values();
    }

    private function laboratoires(): \Illuminate\Support\Collection
    {
        return DB::table('app.anapath_laboratoires')->orderBy('nom')->get(['id', 'nom']);
    }
}
