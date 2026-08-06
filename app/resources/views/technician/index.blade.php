@php
    $statusLabels = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Saisie des actes extra</h1>
        <p class="muted">Saisissez un dossier, puis sélectionnez les intervenants de l’acte à déclarer. Votre identifiant ERP sera conservé comme auteur de la saisie.</p>
        <form method="get" class="row"><input name="dossier" value="{{ $dossier }}" placeholder="Numéro de dossier" required><button>Rechercher</button></form>
    </section>
    @if($dossier !== '')
        <section class="card">
            <h2>Actes du dossier {{ $dossier }}</h2>
            @if($acts->isEmpty()) <p>Aucun acte bloc attribué à cet intervenant.</p>
            @else <table><thead><tr><th>Acte</th><th>Patient</th><th>Médecin chirurgien</th><th>Salle</th><th>Heure planification</th><th>Heure anesthésie</th><th>Intervenants de l’acte</th><th>Action</th></tr></thead><tbody>
            @foreach($acts as $act)<tr>
                <td><strong>{{ $act->LibelleActe }}</strong><br><span class="muted">{{ $act->TypeActe }} · {{ $act->CodeActe }}</span></td>
                <td>{{ $act->NomPatient }} {{ $act->PrenomPatient }}<br><span class="muted">Dossier {{ $act->NumDoss }}</span></td>
                <td>{{ $act->Chirurgien ?? 'Non renseigné' }}</td>
                <td>{{ $act->DesignationSalle ?? $act->Salle }}</td>
                <td>{{ \App\Support\Format::dateTime($act->HDAnest) ?? 'Non renseigné' }}<br>{{ \App\Support\Format::dateTime($act->HFAnest) ?? '' }}</td>
                <td>{{ \App\Support\Format::dateTime($act->Debut_Anesthesie) ?? 'Non renseigné' }}<br>{{ \App\Support\Format::dateTime($act->Fin_Anesthesie) ?? '' }}</td>
                <td>
                    <form method="post" action="{{ route('technician.declarations.store') }}">@csrf
                    <input type="hidden" name="num_intv" value="{{ $act->NumIntv }}"><input type="hidden" name="num_doss" value="{{ $act->NumDoss }}">
                    @forelse($participants->get($act->NumIntv, collect()) as $participant)
                        @php($already = $declarations->get($act->NumIntv, collect())->get($participant->CodInterv))
                        <label style="display:block;margin-bottom:5px"><input type="checkbox" name="cod_interv[]" value="{{ $participant->CodInterv }}" @disabled($already)> {{ $participant->DesInterv }} <span class="muted">({{ $participant->DesTypInterv ?? $participant->TypInterv }})</span> @if($already)<span class="badge">{{ $statusLabels[$already->statut] ?? $already->statut }}</span>@endif</label>
                    @empty <span class="muted">Aucun intervenant lié dans l’ERP.</span>@endforelse
                    <label style="display:block;margin-top:8px">Observation facultative<br><textarea name="observation" rows="2" maxlength="1000" style="width:100%" placeholder="Précision utile pour la Direction"></textarea></label>
                </td><td><button class="success">Déclarer la sélection</button></form></td>
            </tr>@endforeach
            </tbody></table>@endif
        </section>
    @endif
</x-layouts.app>
