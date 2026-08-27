<?php

namespace App\Http\Controllers;

use App\Exports\RegistreExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class RegistryController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $periode] = $this->resolvePeriode($request);
        $dossier = trim((string) $request->input('dossier'));
        $bloc = trim((string) $request->input('bloc', ''));
        $medecin = trim((string) $request->input('medecin', ''));

        $actes = $this->buildQuery($from, $to, $dossier, $bloc, $medecin)->get();

        $radios = $this->radiosPourActes($actes, $from, $to);
        $intervenants = $this->intervenantsPourActes($actes);
        $blocOptions = DB::table('app.vw_erp_blocs')->orderBy('LibBloc')->get(['CodBloc', 'LibBloc']);
        $medecinOptions = $this->medecinOptions();

        return view('registre.index', compact('actes', 'radios', 'intervenants', 'from', 'to', 'periode', 'dossier', 'bloc', 'medecin', 'blocOptions', 'medecinOptions'));
    }

    public function export(Request $request): Response
    {
        [$from, $to, ] = $this->resolvePeriode($request);
        $dossier = trim((string) $request->input('dossier'));
        $bloc = trim((string) $request->input('bloc', ''));
        $medecin = trim((string) $request->input('medecin', ''));

        $actes = $this->buildQuery($from, $to, $dossier, $bloc, $medecin)->get();

        return Excel::download(new RegistreExport($actes), 'registre_bloc_'.$from.'_au_'.$to.'.xlsx');
    }

    /**
     * Filtre "Période" : Aujourd'hui / Hier / Période (avec Du-Au libres).
     */
    private function resolvePeriode(Request $request): array
    {
        $periode = $request->input('periode', 'today');

        return match ($periode) {
            'yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString(), 'yesterday'],
            'custom' => [
                $request->input('du', now()->toDateString()),
                $request->input('au', now()->toDateString()),
                'custom',
            ],
            default => [now()->toDateString(), now()->toDateString(), 'today'],
        };
    }

    /**
     * Chirurgiens distincts apparaissant dans le registre, pour le filtre
     * "Médecin" (liste avec recherche, comme le filtre Intervenant de
     * Direction).
     */
    private function medecinOptions(): \Illuminate\Support\Collection
    {
        return DB::table('app.vw_registre_bloc_actes')
            ->whereNotNull('CodMed')
            ->whereNotNull('Chirurgien')
            ->distinct()
            ->orderBy('Chirurgien')
            ->get(['CodMed', 'Chirurgien'])
            ->unique('CodMed')
            ->values();
    }

    private function buildQuery(string $from, string $to, string $dossier, string $bloc, string $medecin): \Illuminate\Database\Query\Builder
    {
        // Dernière demande d'anapath par acte, ACTIVE OU ANNULÉE : sans ça,
        // une demande annulée devenait injoignable depuis l'écran principal
        // (plus aucun lien vers sa traçabilité).
        $derniereDemande = DB::table('app.anapath_demandes as ad1')
            ->select('ad1.*')
            ->whereRaw('ad1.id = (
                SELECT TOP 1 ad2.id FROM app.anapath_demandes ad2
                WHERE ad2.num_intv = ad1.num_intv AND ad2.num_doss = ad1.num_doss
                ORDER BY ad2.created_at DESC
            )');

        return DB::table('app.vw_registre_bloc_actes as a')
            // NumIntv seul est AMBIGU côté ERP (réutilisé d'une année sur
            // l'autre) : jumelé systématiquement avec NumDoss.
            ->leftJoinSub($derniereDemande, 'ad', function ($join): void {
                $join->on('ad.num_intv', '=', 'a.NumIntv')->on('ad.num_doss', '=', 'a.NumDoss');
            })
            ->whereDate('a.DateActe', '>=', $from)
            ->whereDate('a.DateActe', '<=', $to)
            ->when($bloc !== '', fn ($q) => $q->where('a.CodBloc', $bloc))
            ->when($medecin !== '', function ($q) use ($medecin): void {
                // Comme le filtre Intervenant de Direction : "CodMed — Nom"
                // choisi dans la liste -> code exact. CodMed est
                // alphanumérique (ex: "M0027"), pas numérique comme
                // CodInterv : on extrait tout ce qui précède le tiret,
                // pas seulement des chiffres.
                if (str_contains($medecin, ' — ')) {
                    [$code, ] = explode(' — ', $medecin, 2);
                    $q->where('a.CodMed', trim($code));
                } else {
                    $q->where('a.Chirurgien', 'like', '%'.$medecin.'%');
                }
            })
            ->when($dossier !== '', function ($q) use ($dossier): void {
                $q->where(function ($sub) use ($dossier): void {
                    $sub->where('a.NumDoss', 'like', '%'.$dossier.'%')
                        ->orWhere('a.Patient', 'like', '%'.$dossier.'%');
                });
            })
            ->select(
                'a.*', 'ad.id as AnapathId', 'ad.numero_demande as NumeroDemande',
                'ad.modifie_le as AnapathModifieLe', 'ad.annule_le as AnapathAnnuleLe'
            )
            ->orderByDesc('a.DateActe')->orderByDesc('a.NumIntv');
    }

    private function radiosPourActes($actes, string $from, string $to)
    {
        if ($actes->isEmpty()) {
            return collect();
        }

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->addDay()->startOfDay();

        // Par sécurité, on découpe aussi sur NumDoss pour éviter l'erreur 2100 paramètres.
        $rows = collect();
        foreach ($actes->pluck('NumDoss')->unique()->chunk(1000) as $lot) {
            $rows = $rows->merge(
                DB::table('app.vw_registre_bloc_radiologies')
                    ->whereIn('NumDoss', $lot->all())
                    ->whereRaw('DateValidation >= CONVERT(datetime, ?, 112)', [$fromDate->format('Ymd')])
                    ->whereRaw('DateValidation < CONVERT(datetime, ?, 112)', [$toDate->format('Ymd')])
                    ->get()
            );
        }

        return $rows->sortByDesc('DateValidation')->values()->groupBy('NumDoss');
    }

    /**
     * Équipe (intervenants extra bloc) par acte, regroupée par la paire
     * (NumIntv, NumDoss) — jamais NumIntv seul.
     */
    private function intervenantsPourActes($actes)
    {
        if ($actes->isEmpty()) {
            return collect();
        }

        // Traitement par lots pour rester sous la limite SQL Server de 2100 paramètres
        // (des plages de dates larges produisaient une erreur "The incoming request has
        // too many parameters"). Le découpage par couple (NumIntv, NumDoss) ne change
        // rien au regroupement final et n'alourdit pas l'ERP (lecture seule).
        $paires = $actes->map(fn ($a) => ['NumIntv' => $a->NumIntv, 'NumDoss' => $a->NumDoss])
            ->unique(fn ($p) => $p['NumIntv'].':'.$p['NumDoss'])
            ->values();

        // Limite SQL Server : 2100 paramètres par requête. Les deux IN
        // (NumIntv + NumDoss) comptent chacun leurs valeurs, donc on découpe
        // par budget : tant que NbNumIntv + NbNumDoss reste ≤ 800, on est
        // largement sous la limite, quelle que soit l'ampleur de la période.
        $MAX_PARAMS = 800;
        $rows = collect();
        $lotIntvs = [];
        $lotDoss = [];

        foreach ($paires as $p) {
            $nIntv = $lotIntvs;
            $nDoss = $lotDoss;
            if (! in_array($p['NumIntv'], $nIntv, true)) {
                $nIntv[] = $p['NumIntv'];
            }
            if (! in_array($p['NumDoss'], $nDoss, true)) {
                $nDoss[] = $p['NumDoss'];
            }

            // Ajouter cette paire ferait dépasser le budget : on exécute le lot courant.
            if (! empty($lotIntvs) && (count($nIntv) + count($nDoss) > $MAX_PARAMS)) {
                $rows = $rows->merge($this->fetchIntervenants($lotIntvs, $lotDoss));
                $lotIntvs = [$p['NumIntv']];
                $lotDoss = [$p['NumDoss']];
                continue;
            }

            $lotIntvs = $nIntv;
            $lotDoss = $nDoss;
        }

        if (! empty($lotIntvs)) {
            $rows = $rows->merge($this->fetchIntervenants($lotIntvs, $lotDoss));
        }

        return $rows->groupBy(fn ($i) => $i->NumIntv.':'.$i->NumDoss);
    }

    /**
     * Intervenants d'un lot de la paire (NumIntv, NumDoss), sous la limite
     * de paramètres SQL Server.
     */
    private function fetchIntervenants(array $intvs, array $doss)
    {
        return DB::table('app.vw_erp_acte_intervenants')
            ->whereIn('NumIntv', $intvs)
            ->whereIn('NumDoss', $doss)
            ->orderBy('RoleIntervenant')
            ->get();
    }
}
