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
        // Filet de sécurité uniquement : la vraie correction est la
        // restriction de declarationsBase() ci-dessous. Sans elle, ceci ne
        // suffit pas car IIS coupe la requête FastCGI de son côté
        // (voir "Activity Timeout" dans IIS Manager > FastCGI Settings).
        set_time_limit(120);

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

        // CORRECTIF PERF 3/3 (bug 500 en page 2 / grandes périodes) :
        // ->paginate() sur une requête contenant les deux EXISTS de badges
        // oblige Laravel à ré-exécuter TOUTE la requête (badges compris)
        // une deuxième fois, juste pour compter le total. paginateDeclarations()
        // sépare : 1) un COUNT rapide sans badges, 2) les 25 lignes de la
        // page, 3) les badges calculés uniquement pour ces 25 lignes.
        $declarations = $this->paginateDeclarations($statuses, $dateDebut, $dateFin, $intervenant, $recherche, $bloc, (int) $request->input('page', 1))
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
        // Même dé-duplication que la liste : une seule déclaration retenue par
        // (dossier, intervenant, acte, jour) — celle au statut le plus avancé,
        // puis la plus récente. Sans ça, un acte modifié dans l'ERP qui a créé
        // deux déclarations gonflait les compteurs de la barre de statistiques.
        //
        // IMPORTANT : on utilise declarationsBase() (SANS les colonnes
        // "chevauchement"/"doublon_sous_dossier") et UNE SEULE requête
        // groupée, au lieu de 5 requêtes qui recalculaient chacune les deux
        // EXISTS corrélés pour rien (ces colonnes ne servent qu'à l'affichage
        // de la liste, jamais aux compteurs).
        $inner = $this->declarationsBase($dateDebut, $dateFin, $intervenant, $recherche, $bloc, []);

        $row = (array) DB::query()->fromSub($inner, 't')
            ->where('t.rn', 1)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN t.statut = 'SOUMIS' THEN 1 ELSE 0 END) AS enAttente,
                SUM(CASE WHEN t.statut = 'PREVALIDE' THEN 1 ELSE 0 END) AS prevalide,
                SUM(CASE WHEN t.statut = 'VALIDE' THEN 1 ELSE 0 END) AS valide,
                SUM(CASE WHEN t.statut = 'REJETE' THEN 1 ELSE 0 END) AS refusee,
                SUM(CASE WHEN t.statut IN ('PREVALIDE', 'VALIDE') AND t.montant IS NOT NULL THEN t.montant ELSE 0 END) AS montantTotal
            ")
            ->first();

        return [
            'total'        => (int) ($row['total'] ?? 0),
            'enAttente'    => (int) ($row['enAttente'] ?? 0),
            'prevalide'    => (int) ($row['prevalide'] ?? 0),
            'valide'       => (int) ($row['valide'] ?? 0),
            'refusee'      => (int) ($row['refusee'] ?? 0),
            'montantTotal' => $row['montantTotal'] ?? 0,
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

    /**
     * Requête de base des déclarations (jointures + filtres + colonne "rn"
     * de dé-duplication), SANS les colonnes "chevauchement"/"doublon_sous_dossier".
     *
     * CORRECTIF PERF 1/2 (bug de timeout sur /direction) : cette sous-requête
     * joignait TOUT cache.erp_acte_intervenants à TOUT cache.erp_actes (à
     * l'origine : app.vw_erp_acte_intervenants / app.vw_erp_actes_bloc_direction,
     * des vues qui interrogeaient ERP_LINK en direct), sans aucun filtre —
     * soit tout l'historique ERP depuis toujours, recalculé à chaque
     * chargement de la page. C'était la requête à ~11-13s relancée 5 fois
     * par page (cf. "Maximum execution time of 60 seconds exceeded").
     * On la restreint aux NumIntv qui ont RÉELLEMENT une déclaration dans
     * app.extra_declarations (univers bien plus petit que tout l'historique
     * ERP, et le seul qui compte pour cet écran).
     *
     * CORRECTIF PERF 2/2 : a/i pointent maintenant sur cache.erp_actes /
     * cache.erp_acte_intervenants — des tables LOCALES et INDEXÉES,
     * synchronisées toutes les ~10 min depuis ERP_LINK par la commande
     * `php artisan erp:cache-sync` (voir app/Console/Commands/ErpCacheSync.php
     * et database/sql/30_cache_erp.sql). Avant ce cache, chaque ligne de
     * badge (chevauchement/doublon) redéclenchait une jointure sur les vues
     * live ERP_LINK — d'où les 34-38s observées même avec seulement 306
     * déclarations en base.
     *
     * Une seule ligne est retenue par (dossier, intervenant, acte, jour) —
     * la clé d'un doublon réel :
     *
     *   un acte modifié dans l'ERP (numéro d'intervention changé, sous-dossier
     *   créé par la facturation, etc.) produit parfois DEUX déclarations pour
     *   le même intervenant + même patient + même acte + même jour, avec des
     *   heures de pointage différentes. On garde la déclaration au statut le
     *   plus avancé (Validé > Prévalidé > En attente > Refusé), puis la plus
     *   récente.
     *
     * À la consultation, les colonnes sélectionnées sont dédupliquées par la
     * colonne "rn" (= 1 pour la ligne conservée).
     */
    private function declarationsBase(string $dateDebut, string $dateFin, string $intervenant, string $recherche, string $bloc, array $statuses): \Illuminate\Database\Query\Builder
    {
        // La vue des intervenants peut renvoyer PLUSIEURS lignes pour un
        // même (NumIntv, CodInterv) — par ex. un pointage parasite daté
        // d'une autre année/mois/jour que l'acte. Jointure directe = chaque
        // déclaration apparaissait en DOUBLE à l'écran Direction. On ne
        // garde donc qu'UNE ligne par (NumIntv, CodInterv) : celle dont la
        // date de pointage est la PLUS PROCHE de la date de l'acte (le bon
        // pointage, quelle que soit l'année).
        $acteIntervenants = DB::table('cache.erp_acte_intervenants as i')
            ->leftJoin('cache.erp_actes as a', 'i.NumIntv', '=', 'a.NumIntv')
            ->whereIn('i.NumIntv', function ($q): void {
                $q->select('num_intv')->from('app.extra_declarations');
            })
            ->selectRaw("i.*, ROW_NUMBER() OVER (
                PARTITION BY i.NumIntv, i.CodInterv
                ORDER BY ISNULL(ABS(DATEDIFF(day, i.HeurePointageEntree, a.DatOpe)), 999999) ASC,
                         i.HeurePointageEntree DESC
            ) AS i_rn");

        return DB::table('app.extra_declarations as d')
            ->leftJoin('cache.erp_actes as a', 'd.num_intv', '=', 'a.NumIntv')
            ->leftJoinSub($acteIntervenants, 'i', function ($join): void {
                $join->on('d.num_intv', '=', 'i.NumIntv')
                    ->on('d.cod_interv', '=', 'i.CodInterv')
                    ->on('i.i_rn', '=', DB::raw('1'));
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
            // Dé-duplication : on classe les doublons (même dossier + même
            // intervenant + même acte + même jour) et on ne conserve que la
            // ligne "rn = 1".
            ->selectRaw("ROW_NUMBER() OVER (
                PARTITION BY d.num_doss, d.cod_interv, a.CodeActe, CONVERT(date, a.DatOpe)
                ORDER BY CASE d.statut WHEN 'VALIDE' THEN 0 WHEN 'PREVALIDE' THEN 1 WHEN 'SOUMIS' THEN 2 ELSE 3 END,
                         d.declared_at DESC, d.id DESC
            ) AS rn");
    }

    /**
     * declarationsBase() + les deux colonnes "badges" (chevauchement /
     * doublon_sous_dossier). Ces deux EXISTS corrélés sont coûteux —
     * utilisés UNIQUEMENT pour l'affichage de la liste (buildQuery), jamais
     * pour les compteurs (statistiques), qui n'en ont pas besoin.
     */
    private function declarationsInner(string $dateDebut, string $dateFin, string $intervenant, string $recherche, string $bloc, array $statuses): \Illuminate\Database\Query\Builder
    {
        return $this->declarationsBase($dateDebut, $dateFin, $intervenant, $recherche, $bloc, $statuses)
            // Détecte une AUTRE déclaration du même intervenant, le même
            // jour, dont la plage horaire de planification chevauche celle
            // de cette ligne (hors déclarations déjà refusées) — pour
            // signaler visuellement les doublons/chevauchements à
            // contrôler (ex: 10:00-16:00 et 11:00-15:30 le même jour).
            ->selectRaw("CASE WHEN EXISTS (
                SELECT 1 FROM app.extra_declarations d2
                INNER JOIN cache.erp_actes a2 ON d2.num_intv = a2.NumIntv
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
                INNER JOIN cache.erp_actes a3 ON d3.num_intv = a3.NumIntv
                WHERE d3.cod_interv = d.cod_interv
                  AND d3.id <> d.id
                  AND d3.statut <> 'REJETE'
                  AND a3.CodeActe = a.CodeActe
                  AND a3.NumDoss <> a.NumDoss
                  AND (
                        (a.CinPatient IS NOT NULL AND a3.CinPatient = a.CinPatient)
                     OR (a.CinPatient IS NULL AND a.IdentifiantPatient IS NOT NULL AND a3.IdentifiantPatient = a.IdentifiantPatient)
                  )
            ) THEN 1 ELSE 0 END AS doublon_sous_dossier");
    }

    public function buildQuery(array $statuses, string $dateDebut, string $dateFin, string $intervenant = '', string $recherche = '', string $bloc = ''): \Illuminate\Database\Query\Builder
    {
        $inner = $this->declarationsInner($dateDebut, $dateFin, $intervenant, $recherche, $bloc, $statuses);

        // Ne garde qu'une ligne par doublon, puis trie par intervenant et
        // date d'acte (facilite le contrôle d'un même intervenant sur
        // plusieurs jours/actes d'affilée).
        return DB::query()
            ->fromSub($inner, 't')
            ->where('t.rn', 1)
            ->orderBy('t.DesInterv')
            ->orderBy('t.DatOpe');
    }

    /**
     * Pagination de l'écran Direction, en 3 étapes volontairement séparées
     * pour ne jamais payer le coût des badges (chevauchement/doublon) sur
     * autre chose que les 25 lignes réellement affichées :
     *
     *   1. total = COUNT sur declarationsBase() (SANS badges)
     *   2. items = les 25 lignes de la page demandée (SANS badges non plus)
     *   3. badges = un aller-retour ciblé sur les seuls `id` de ces 25 lignes
     *
     * C'est ce qui manquait à un simple ->paginate() : celui-ci ré-exécute
     * la requête complète (badges compris) une deuxième fois rien que pour
     * compter le total, ce qui doublait le coût et provoquait le 500 sur la
     * page 2 / les périodes larges (le total ET la page dépassaient le
     * timeout).
     */
    private function paginateDeclarations(array $statuses, string $dateDebut, string $dateFin, string $intervenant, string $recherche, string $bloc, int $page, int $perPage = 25): \Illuminate\Pagination\LengthAwarePaginator
    {
        $page = max(1, $page);
        $base = $this->declarationsBase($dateDebut, $dateFin, $intervenant, $recherche, $bloc, $statuses);

        $total = DB::query()->fromSub($base, 't')->where('t.rn', 1)->count();

        $items = DB::query()->fromSub($base, 't')
            ->where('t.rn', 1)
            ->orderBy('t.DesInterv')
            ->orderBy('t.DatOpe')
            ->forPage($page, $perPage)
            ->get();

        $ids = $items->pluck('id')->all();

        if ($ids !== []) {
            // Un id (=une déclaration) identifie sans ambiguïté la ligne :
            // NumIntv seul ne suffit pas, un même acte peut avoir plusieurs
            // intervenants donc plusieurs déclarations distinctes.
            $badges = DB::table('app.extra_declarations as d')
                ->leftJoin('cache.erp_actes as a', 'd.num_intv', '=', 'a.NumIntv')
                ->whereIn('d.id', $ids)
                ->selectRaw("d.id,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM app.extra_declarations d2
                        INNER JOIN cache.erp_actes a2 ON d2.num_intv = a2.NumIntv
                        WHERE d2.cod_interv = d.cod_interv
                          AND d2.id <> d.id
                          AND d2.statut <> 'REJETE'
                          AND a2.HDAnest IS NOT NULL AND a2.HFAnest IS NOT NULL
                          AND a.HDAnest IS NOT NULL AND a.HFAnest IS NOT NULL
                          AND CAST(a2.DatOpe AS date) = CAST(a.DatOpe AS date)
                          AND a2.HDAnest < a.HFAnest AND a2.HFAnest > a.HDAnest
                    ) THEN 1 ELSE 0 END AS chevauchement,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM app.extra_declarations d3
                        INNER JOIN cache.erp_actes a3 ON d3.num_intv = a3.NumIntv
                        WHERE d3.cod_interv = d.cod_interv
                          AND d3.id <> d.id
                          AND d3.statut <> 'REJETE'
                          AND a3.CodeActe = a.CodeActe
                          AND a3.NumDoss <> a.NumDoss
                          AND (
                                (a.CinPatient IS NOT NULL AND a3.CinPatient = a.CinPatient)
                             OR (a.CinPatient IS NULL AND a.IdentifiantPatient IS NOT NULL AND a3.IdentifiantPatient = a.IdentifiantPatient)
                          )
                    ) THEN 1 ELSE 0 END AS doublon_sous_dossier
                ")
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $item->chevauchement = (int) ($badges[$item->id]->chevauchement ?? 0);
                $item->doublon_sous_dossier = (int) ($badges[$item->id]->doublon_sous_dossier ?? 0);
            }
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Support\Facades\URL::current(), 'query' => request()->query()]
        );
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
