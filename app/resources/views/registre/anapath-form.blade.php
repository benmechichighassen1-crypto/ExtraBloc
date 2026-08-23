<x-layouts.app>
    <section class="card" style="max-width:850px">
        <h1>{{ $demande ? 'Modifier la demande '.$demande->numero_demande : 'Demande d’anatomopathologie' }}</h1>
        <p><strong>{{ $demande ? trim(($demande->patient_nom ?? '').' '.($demande->patient_prenom ?? '')) : $acte->Patient }}</strong> — Dossier {{ $demande->num_doss ?? $acte->NumDoss }}
        @if(!$demande){{ $acte->LibelleActe }}@endif</p>

        <form method="post" action="{{ $demande ? route('registry.anapath.update', $demande->id) : route('registry.anapath.store', $acte->NumIntv ?? '') }}">
            @csrf
            @if($demande) @method('PATCH') @else
                <input type="hidden" name="num_doss" value="{{ $acte->NumDoss }}">
            @endif

            <div class="row">
                <label style="flex:1">Médecin prescripteur<br><input name="medecin_prescripteur" required style="width:100%" value="{{ old('medecin_prescripteur', $demande->medecin_prescripteur ?? $acte->Chirurgien ?? '') }}"></label>
            </div>

            <div class="row"><label style="flex:1">Nature du prélèvement<br><input name="nature_prelevement" required style="width:100%" value="{{ old('nature_prelevement', $demande->nature_prelevement ?? '') }}"></label><label style="flex:1">Site anatomique<br><input name="site_anatomique" required style="width:100%" value="{{ old('site_anatomique', $demande->site_anatomique ?? '') }}"></label></div>

            <div class="row">
                <label>Nombre de flacons<br><input type="number" name="nombre_flacons" min="1" value="{{ old('nombre_flacons', $demande->nombre_flacons ?? 1) }}" required></label>
                <label>Fixateur<br><input name="fixateur" value="{{ old('fixateur', $demande->fixateur ?? 'Formol tamponné') }}"></label>
                <label>Laboratoire<br>
                    <select name="laboratoire_id">
                        <option value="">— Non renseigné —</option>
                        @foreach($laboratoires as $labo)<option value="{{ $labo->id }}" @selected(old('laboratoire_id', $demande->laboratoire_id ?? null) == $labo->id)>{{ $labo->nom }}</option>@endforeach
                    </select>
                </label>
                <label><input type="checkbox" name="urgence" value="1" @checked(old('urgence', $demande->urgence ?? false))> Urgent</label>
            </div>

            <label style="display:block;margin-top:14px">Renseignements cliniques / diagnostic suspecté<br><textarea name="renseignements_cliniques" required rows="7" style="width:100%">{{ old('renseignements_cliniques', $demande->renseignements_cliniques ?? '') }}</textarea></label>

            @if($demande && !\App\Support\AccessControl::hasAnapathEditAccess(auth()->user()->getAuthIdentifier()))
                <label style="display:block;margin-top:14px">Code de sécurité (modification)<br><input type="password" name="code_securite" required style="width:250px"></label>
            @endif

            <p><button class="success">{{ $demande ? 'Enregistrer les modifications' : 'Enregistrer la demande' }}</button> <a href="{{ route('registry.index') }}">Annuler</a></p>
        </form>

        @if($demande)
            <hr>
            <h2>Annuler cette demande</h2>
            <form method="post" action="{{ route('registry.anapath.cancel', $demande->id) }}" onsubmit="return confirm('Confirmer l’annulation de cette demande ?')">
                @csrf @method('DELETE')
                <label style="display:block">Motif d'annulation<br><textarea name="motif_annulation" required rows="2" style="width:100%"></textarea></label>
                @if(!\App\Support\AccessControl::hasAnapathEditAccess(auth()->user()->getAuthIdentifier()))
                    <label style="display:block;margin-top:8px">Code de sécurité<br><input type="password" name="code_securite" required style="width:250px"></label>
                @endif
                <p><button class="danger">Annuler la demande</button></p>
            </form>
        @endif
    </section>
</x-layouts.app>
