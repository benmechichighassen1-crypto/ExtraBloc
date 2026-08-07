@php
    $statusLabels = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Historique de mes déclarations</h1>
        @if(!$interv)
            <p class="error">Votre identifiant ERP n’est associé à aucun intervenant (CodInterv introuvable). Contactez la direction pour vérifier votre compte.</p>
        @else
            <p class="muted">{{ $interv->DesInterv }} <span class="badge">{{ $interv->DesTypInterv ?? $interv->TypInterv }}</span></p>
            <form method="get" class="row">
                <label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label>
                <label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
                @foreach($statusLabels as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
                <button>Filtrer</button>
            </form>
        @endif
    </section>

    @if($interv)
        <section class="card">
            <table><thead><tr><th>Date de l’acte</th><th>Acte</th><th>Patient</th><th>Salle</th><th>Rôle</th><th>Statut</th><th>Motif décision</th></tr></thead>
            <tbody>
            @forelse($declarations as $item)
                <tr>
                    <td>{{ \App\Support\Format::date($item->DatOpe) }}</td>
                    <td>{{ $item->LibelleActe }}<br><span class="muted">Dossier {{ $item->num_doss }}</span></td>
                    <td>{{ $item->NomPatient }} {{ $item->PrenomPatient }}</td>
                    <td>{{ $item->DesignationSalle ?? 'Non renseignée' }}</td>
                    <td>{{ $item->role_intervenant }}</td>
                    <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $statusLabels[$item->statut] ?? $item->statut }}</span>
                        @if($item->statut === 'VALIDE' || $item->statut === 'REJETE')<br><span class="muted">{{ $item->valide_par_username }} · {{ \App\Support\Format::dateTime($item->valide_le) }}</span>@endif
                    </td>
                    <td>{{ $item->motif_decision ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune déclaration sur cette période.</td></tr>
            @endforelse
            </tbody></table>
            <div style="margin-top:16px">{{ $declarations->links() }}</div>
        </section>
    @endif
</x-layouts.app>
