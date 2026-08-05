<x-layouts.app>
    <section class="card">
        <h1>Contrôle direction</h1>
        <form method="get" class="row"><label>État <select name="statut">@foreach(['SOUMIS','PREVALIDE','VALIDE','REJETE'] as $state)<option value="{{ $state }}" @selected($status === $state)>{{ $state }}</option>@endforeach</select></label><button>Filtrer</button></form>
    </section>
    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Anesthésie</th><th>Saisie / validation</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
    @forelse($declarations as $item)<tr>
        <td><strong>{{ $item->DesInterv ?? $item->cod_interv }}</strong><br>{{ $item->LibelleActe }}<br><span class="muted">Dossier {{ $item->num_doss }} · Salle {{ $item->Salle }}</span>@if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif</td>
        <td>{{ $item->HDAnest }}<br>{{ $item->HFAnest }}</td>
        <td><strong>Saisie :</strong> {{ $item->declared_by_username }}<br>@if($item->valide_par_username)<strong>Validé par :</strong> {{ $item->valide_par_username }}@else<span class="muted">En attente de validation</span>@endif</td>
        <td><span class="badge">{{ $item->statut }}</span></td>
        <td>@if(in_array($item->statut, ['SOUMIS','PREVALIDE']))<form method="post" action="{{ route('direction.declarations.decide', $item->id) }}" class="row">@csrf @method('PATCH')
            <input name="motif" placeholder="Motif (facultatif)"><button class="success" name="decision" value="VALIDE">Valider</button><button class="danger" name="decision" value="REJETE">Rejeter</button>
        </form>@else <span class="muted">{{ $item->valide_par_username }}</span>@endif</td>
    </tr>@empty <tr><td colspan="5">Aucune déclaration.</td></tr>@endforelse
    </tbody></table><div style="margin-top:16px">{{ $declarations->links() }}</div></section>
</x-layouts.app>
