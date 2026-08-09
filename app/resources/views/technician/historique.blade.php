@php
    $statusLabels = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
    $filterableStatuses = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Historique de mes déclarations</h1>
        @if($intervs->isEmpty())
            <p class="error">Votre identifiant ERP n’est associé à aucun intervenant (CodInterv introuvable). Contactez la direction pour vérifier votre compte.</p>
        @else
            <p class="muted">{{ $intervs->first()->DesInterv }}
                @foreach($intervs as $role)<span class="badge">{{ $role->DesTypInterv ?? $role->TypInterv }}</span>@endforeach
            </p>
            <form method="get" class="row">
                <label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label>
                <label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
                @foreach($filterableStatuses as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
                <button>Filtrer</button>
            </form>
        @endif
    </section>

    @if($intervs->isNotEmpty())
        <section class="card">
            <table><thead><tr><th>Date de l’acte</th><th>Acte</th><th>Patient</th><th>Salle</th><th>Rôle</th><th>Statut</th><th>Motif décision</th></tr></thead>
            <tbody>
            @forelse($declarations as $item)
                <tr>
                    <td>{{ \App\Support\Format::date($item->DatOpe) }}</td>
                    <td>{{ $item->LibelleActe }}<br><span class="muted">Dossier {{ $item->num_doss }}</span></td>
                    <td>{{ $item->NomPatient }} {{ $item->PrenomPatient }}</td>
                    <td>{{ $item->DesignationSalle ?? 'Non renseignée' }}</td>
                    <td>{{ \App\Support\Format::role($item->role_intervenant) }}</td>
                    <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $statusLabels[$item->statut] ?? $item->statut }}</span>
                        @if($item->statut === 'PREVALIDE')<br><span class="muted">{{ $item->prevalide_par_username }} · {{ \App\Support\Format::dateTime($item->prevalide_le) }}</span>@endif
                        @if($item->statut === 'VALIDE' || $item->statut === 'REJETE')<br><span class="muted">{{ $item->valide_par_username }} · {{ \App\Support\Format::dateTime($item->valide_le) }}</span>@endif
                    </td>
                    <td>{{ $item->motif_decision ?? $item->motif_prevalidation ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune déclaration sur cette période.</td></tr>
            @endforelse
            </tbody></table>
            <div style="margin-top:16px">{{ $declarations->links() }}</div>
        </section>
    @endif
</x-layouts.app>
