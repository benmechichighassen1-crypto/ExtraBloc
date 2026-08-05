<x-layouts.app>
    <section class="card">
        <h1>Contrôle direction</h1>
        <form method="get" class="row"><label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label><label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
        @foreach(['SOUMIS'=>'En attente','PREVALIDE'=>'Prévalidé','VALIDE'=>'Validé','REJETE'=>'Refusé'] as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
        <button>Filtrer</button><button type="button" onclick="window.print()">Imprimer</button></form>
    </section>
    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Médecins</th><th>Heures anesthésie</th><th>Saisie / validation</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
    @forelse($declarations as $item)<tr>
        <td><strong>{{ $item->DesInterv ?? $item->cod_interv }}</strong><br><span class="muted">{{ $item->DesTypInterv ?? 'Type non renseigné' }}</span><br>{{ $item->LibelleActe }}<br><span class="muted">Dossier {{ $item->num_doss }} · {{ $item->DesignationSalle ?? 'Salle non renseignée' }}</span>@if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif</td>
        <td><strong>Chirurgien :</strong> {{ $item->Chirurgien }}<br><strong>Réanimateur :</strong> {{ $item->Reanimateur }}</td>
        <td>{{ $item->Debut_Anesthesie ?? 'Non renseigné' }}<br>{{ $item->Fin_Anesthesie ?? 'Non renseigné' }}</td>
        <td><strong>Saisie :</strong> {{ $item->declared_by_username }}<br>@if($item->valide_par_username)<strong>Validé par :</strong> {{ $item->valide_par_username }}@else<span class="muted">En attente de validation</span>@endif</td>
        <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $item->statut }}</span></td>
        <td>@if(in_array($item->statut, ['SOUMIS','PREVALIDE']))<form method="post" action="{{ route('direction.declarations.decide', $item->id) }}" class="row">@csrf @method('PATCH')
            <input name="motif" placeholder="Motif (facultatif)"><button class="success" name="decision" value="VALIDE">Valider</button><button class="danger" name="decision" value="REJETE">Rejeter</button>
        </form>@else <span class="muted">{{ $item->valide_par_username }}</span>@endif</td>
    </tr>@empty <tr><td colspan="6">Aucune déclaration.</td></tr>@endforelse
    </tbody></table><div style="margin-top:16px">{{ $declarations->links() }}</div></section>
</x-layouts.app>
