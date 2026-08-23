<?php

namespace App\Http\Controllers;

use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnapathRequestController extends Controller
{
    public function create(Request $request, string $acte): View|RedirectResponse
    {
        $numDoss = trim((string) $request->query('num_doss', ''));
        abort_if($numDoss === '', 422, 'Numéro de dossier manquant.');

        $acteRow = $this->resolveActe($acte, $numDoss);

        $existante = DB::table('app.anapath_demandes')
            ->where('num_intv', $acte)->where('num_doss', $numDoss)->whereNull('annule_le')->first();
        if ($existante) {
            return redirect()->route('registry.anapath.show', $existante->id);
        }

        $laboratoires = $this->laboratoires();

        return view('registre.anapath-form', ['acte' => $acteRow, 'laboratoires' => $laboratoires, 'demande' => null]);
    }

    public function store(Request $request, string $acte): RedirectResponse
    {
        $data = $this->validated($request);
        $acteRow = $this->resolveActe($acte, $data['num_doss']);
        $username = (string) $request->user()->getAuthIdentifier();
        $urgence = $request->boolean('urgence');

        $id = DB::transaction(function () use ($acte, $data, $acteRow, $username, $urgence): int {
            $dejaActive = DB::table('app.anapath_demandes')
                ->where('num_intv', $acte)->where('num_doss', $data['num_doss'])
                ->whereNull('annule_le')->lockForUpdate()->exists();
            abort_if($dejaActive, 422, 'Une demande active existe déjà pour cet acte.');

            $numeroDemande = $this->prochainNumero();

            $id = DB::table('app.anapath_demandes')->insertGetId([
                'numero_demande' => $numeroDemande,
                'num_intv' => $acte,
                'num_doss' => $data['num_doss'],
                'code_examen_erp' => $acteRow->Code_Examen ?? null,
                'nature_prelevement' => $data['nature_prelevement'],
                'site_anatomique' => $data['site_anatomique'],
                'nombre_flacons' => $data['nombre_flacons'],
                'fixateur' => $data['fixateur'] ?? null,
                'laboratoire_id' => $data['laboratoire_id'] ?? null,
                'renseignements_cliniques' => $data['renseignements_cliniques'],
                'medecin_prescripteur' => $data['medecin_prescripteur'],
                'urgence' => $urgence,
                'cree_par_username' => $username,
                // Patient figé au moment de la demande (fiable même si l'ERP
                // modifie ensuite le dossier).
                'patient_nom' => $acteRow->NomPatient ?? null,
                'patient_prenom' => $acteRow->PrenomPatient ?? null,
                'patient_date_naissance' => $acteRow->DateNaissancePatient ?? null,
                'patient_sexe' => $acteRow->SexePatient ?? null,
            ]);

            DB::table('app.anapath_audits')->insert([
                'demande_id' => $id,
                'action' => 'CREATION',
                'acteur_username' => $username,
                'donnees_apres' => json_encode(array_merge($data, ['urgence' => $urgence, 'numero_demande' => $numeroDemande])),
            ]);

            return $id;
        });

        return redirect()->route('registry.anapath.show', $id)->with('success', 'Demande d’anapath enregistrée.');
    }

    public function show(int $demande): View
    {
        $demandeRow = $this->findActive($demande, allowCancelled: true);

        $historique = DB::table('app.anapath_audits')
            ->where('demande_id', $demande)
            ->orderBy('created_at')
            ->get();

        $peutModifier = $demandeRow->annule_le === null;

        return view('registre.anapath-show', compact('demandeRow', 'historique', 'peutModifier') + ['demande' => $demandeRow]);
    }

    public function edit(Request $request, int $demande): View
    {
        $demandeRow = $this->findActive($demande);
        $laboratoires = $this->laboratoires();

        return view('registre.anapath-form', [
            'acte' => (object) [
                'Patient' => trim(($demandeRow->patient_nom ?? '').' '.($demandeRow->patient_prenom ?? '')),
                'NumDoss' => $demandeRow->num_doss,
                'LibelleActe' => null,
            ],
            'laboratoires' => $laboratoires,
            'demande' => $demandeRow,
        ]);
    }

    public function update(Request $request, int $demande): RedirectResponse
    {
        $demandeRow = $this->findActive($demande);
        $this->verifierAutorisation($request);

        $data = $this->validated($request, requireNumDoss: false);
        $username = (string) $request->user()->getAuthIdentifier();
        $urgence = $request->boolean('urgence');

        $avant = (array) $demandeRow;

        DB::transaction(function () use ($demande, $data, $username, $urgence, $avant): void {
            DB::table('app.anapath_demandes')->where('id', $demande)->update([
                'nature_prelevement' => $data['nature_prelevement'],
                'site_anatomique' => $data['site_anatomique'],
                'nombre_flacons' => $data['nombre_flacons'],
                'fixateur' => $data['fixateur'] ?? null,
                'laboratoire_id' => $data['laboratoire_id'] ?? null,
                'renseignements_cliniques' => $data['renseignements_cliniques'],
                'medecin_prescripteur' => $data['medecin_prescripteur'],
                'urgence' => $urgence,
                'modifie_par_username' => $username,
                'modifie_le' => now(),
            ]);

            DB::table('app.anapath_audits')->insert([
                'demande_id' => $demande,
                'action' => 'MODIFICATION',
                'acteur_username' => $username,
                'donnees_avant' => json_encode($avant),
                'donnees_apres' => json_encode(array_merge($data, ['urgence' => $urgence])),
            ]);
        });

        return redirect()->route('registry.anapath.show', $demande)->with('success', 'Demande modifiée.');
    }

    public function cancel(Request $request, int $demande): RedirectResponse
    {
        $demandeRow = $this->findActive($demande);
        $this->verifierAutorisation($request);

        $data = $request->validate(['motif_annulation' => ['required', 'string', 'max:500']]);
        $username = (string) $request->user()->getAuthIdentifier();

        DB::transaction(function () use ($demande, $data, $username): void {
            DB::table('app.anapath_demandes')->where('id', $demande)->update([
                'annule_par_username' => $username,
                'annule_le' => now(),
                'motif_annulation' => $data['motif_annulation'],
            ]);

            DB::table('app.anapath_audits')->insert([
                'demande_id' => $demande,
                'action' => 'ANNULATION',
                'acteur_username' => $username,
                'donnees_apres' => json_encode($data),
            ]);
        });

        return redirect()->route('registry.index')->with('warning', 'Demande annulée.');
    }

    public function print(int $demande): View
    {
        $demandeRow = $this->findActive($demande, allowCancelled: true);
        $entete = request()->boolean('entete', true);

        return view('registre.anapath-print', ['demande' => $demandeRow, 'entete' => $entete]);
    }

    /**
     * Confirme le droit de modifier/annuler : accès permanent
     * (app.anapath_editeurs) OU code de sécurité correct. Dans tous les
     * cas, l'action reste tracée (acteur_username réel dans l'audit).
     */
    private function verifierAutorisation(Request $request): void
    {
        $username = (string) $request->user()->getAuthIdentifier();

        if (AccessControl::hasAnapathEditAccess($username)) {
            return;
        }

        $codeAttendu = config('registrebloc.code_securite');
        $codeSaisi = (string) $request->input('code_securite', '');

        abort_if(! $codeAttendu || $codeSaisi === '' || ! hash_equals((string) $codeAttendu, $codeSaisi), 403, 'Code de sécurité incorrect ou manquant.');
    }

    private function validated(Request $request, bool $requireNumDoss = true): array
    {
        return $request->validate([
            'num_doss' => $requireNumDoss ? ['required', 'string', 'max:30'] : ['sometimes', 'string', 'max:30'],
            'nature_prelevement' => ['required', 'string', 'max:250'],
            'site_anatomique' => ['required', 'string', 'max:250'],
            'nombre_flacons' => ['required', 'integer', 'min:1', 'max:99'],
            'fixateur' => ['nullable', 'string', 'max:100'],
            // "exists:app.table,col" est mal interprété par Laravel : le
            // texte avant le premier point est lu comme un nom de CONNEXION
            // (pas un schéma), et "app" n'en est pas une -> erreur "Database
            // connection [app] not configured". On valide donc par closure.
            'laboratoire_id' => ['nullable', 'integer', function ($attribute, $value, $fail): void {
                if ($value && ! DB::table('app.anapath_laboratoires')->where('id', $value)->where('actif', 1)->exists()) {
                    $fail('Laboratoire invalide.');
                }
            }],
            'renseignements_cliniques' => ['required', 'string', 'max:4000'],
            'medecin_prescripteur' => ['required', 'string', 'max:200'],
        ]);
    }

    private function laboratoires(): \Illuminate\Support\Collection
    {
        return DB::table('app.anapath_laboratoires')->where('actif', 1)->orderBy('nom')->get();
    }

    private function prochainNumero(): string
    {
        $annee = now()->format('Y');
        $sequence = DB::table('app.anapath_sequences')->where('annee', $annee)->lockForUpdate()->first();
        $numero = $sequence ? $sequence->dernier_numero + 1 : 1;

        if ($sequence) {
            DB::table('app.anapath_sequences')->where('annee', $annee)->update(['dernier_numero' => $numero]);
        } else {
            DB::table('app.anapath_sequences')->insert(['annee' => $annee, 'dernier_numero' => $numero]);
        }

        return 'ANAPATH-'.$annee.'-'.str_pad((string) $numero, 5, '0', STR_PAD_LEFT);
    }

    private function findActive(int $demande, bool $allowCancelled = false): object
    {
        $query = DB::table('app.anapath_demandes')->where('id', $demande);
        if (! $allowCancelled) {
            $query->whereNull('annule_le');
        }
        $row = $query->first();
        abort_unless($row, 404);

        return $row;
    }

    /**
     * Vérifie que (NumIntv, NumDoss) désigne bien un acte réel — jamais
     * NumIntv seul (ambigu, réutilisé d'une année sur l'autre côté ERP).
     */
    private function resolveActe(string $numIntv, string $numDoss): object
    {
        $acteRow = DB::table('app.vw_registre_bloc_actes')
            ->where('NumIntv', $numIntv)->where('NumDoss', $numDoss)->first();

        abort_unless($acteRow, 404, 'Acte introuvable pour ce dossier.');

        return $acteRow;
    }
}
