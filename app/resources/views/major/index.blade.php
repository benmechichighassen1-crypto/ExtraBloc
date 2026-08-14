@php
    $statusLabels = \App\Http\Controllers\MajorController::STATUS_LABELS;
    $filterableStatuses = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Pré-validation (major du bloc)</h1>
        <p class="muted">Vérifiez les déclarations « En attente » avant transmission à la Direction pour validation finale.</p>
        <form method="get" class="row">
            <label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label>
            <label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
            @foreach($filterableStatuses as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
            <button>Filtrer</button>
        </form>
    </section>

    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Patient</th><th>Chirurgien</th><th>Heure planification</th><th>Heure anesthésie</th><th>Heure pointage</th><th>Saisie</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
    @forelse($declarations as $item)<tr>
        <td>
            <strong>{{ $item->DesInterv ?? $item->cod_interv }}</strong><br>
            <span class="muted">{{ \App\Support\Format::role($item->role_intervenant) ?? $item->DesTypInterv ?? 'Type non renseigné' }}
                @if($item->DesTypInterv && \App\Support\Format::role($item->role_intervenant) !== $item->DesTypInterv) (fiche : {{ $item->DesTypInterv }})@endif
            </span><br>
            {{ $item->LibelleActe }}<br>
            <span class="muted">{{ $item->DesignationSalle ?? 'Salle non renseignée' }}</span>
            @if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif
        </td>
        <td>
            {{ $item->NomPatient }} {{ $item->PrenomPatient }}<br>
            <span class="muted">Dossier {{ $item->num_doss }}</span>
        </td>
        <td>{{ $item->Chirurgien }}</td>
        <td>
            {{ \App\Support\Format::dateTime($item->HDAnest) ?? 'Non renseigné' }}<br>
            {{ \App\Support\Format::dateTime($item->HFAnest) ?? '' }}
        </td>
        <td>
            {{ \App\Support\Format::dateTime($item->Debut_Anesthesie) ?? 'Non renseigné' }}<br>
            {{ \App\Support\Format::dateTime($item->Fin_Anesthesie) ?? '' }}
        </td>
        <td>
            @if($item->HeurePointageEntree)
                {{ \App\Support\Format::time($item->HeurePointageEntree) }}<br>
                {{ \App\Support\Format::time($item->HeurePointageSortie) }}
            @else
                <span class="muted">Aucun pointage</span>
            @endif
            @if($item->MatriculePointeuse)
                <button type="button" class="pointage-info-btn" title="Détail des pointages du jour"
                        onclick="openPointageModal('{{ $item->MatriculePointeuse }}', '{{ \App\Support\Format::date($item->DatOpe) }}', '{{ addslashes($item->DesInterv ?? '') }}')">i</button>
            @endif
        </td>
        <td>{{ $item->declared_by_username }}<br><span class="muted">{{ \App\Support\Format::dateTime($item->declared_at) }}</span></td>
        <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $statusLabels[$item->statut] ?? $item->statut }}</span></td>
        <td style="text-align:right">
            @if($item->statut === 'SOUMIS')
                <form method="post" action="{{ route('major.declarations.decide', $item->id) }}" class="row" style="justify-content:flex-end;flex-wrap:nowrap">@csrf @method('PATCH')
                    <input name="motif" placeholder="Motif (obligatoire si refus)" style="min-width:150px">
                    <button class="success" name="decision" value="PREVALIDE" onclick="this.form.querySelector('[name=motif]').required=false">Prévalider</button>
                    <button class="danger" name="decision" value="REJETE" onclick="this.form.querySelector('[name=motif]').required=true">Refuser</button>
                </form>
            @elseif($item->statut === 'PREVALIDE')
                <div style="text-align:left"><strong>{{ $item->prevalide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->prevalide_le) }}</span>
                @if($item->motif_prevalidation)<br><span class="muted">Motif : {{ $item->motif_prevalidation }}</span>@endif
                <br><span class="muted">Transmis à la Direction.</span></div>
            @else
                <div style="text-align:left"><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
            @endif
        </td>
    </tr>@empty <tr><td colspan="9">Aucune déclaration.</td></tr>@endforelse
    </tbody></table><div style="margin-top:16px">{{ $declarations->links() }}</div></section>

    <div id="pointage-modal-overlay" class="modal-overlay">
        <div class="card" style="max-width:480px;width:92%;max-height:80vh;overflow:auto">
            <h2 style="margin-bottom:4px">Détail des pointages</h2>
            <p class="muted" id="pointage-modal-subtitle" style="margin-top:0"></p>
            <table><thead><tr><th>Heure</th><th>Nom</th></tr></thead>
                <tbody id="pointage-modal-body"><tr><td colspan="2" class="muted">Chargement…</td></tr></tbody>
            </table>
            <div class="row" style="margin-top:16px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('pointage-modal-overlay').style.display='none'">Fermer</button>
            </div>
        </div>
    </div>

    <style>
        .modal-overlay { display:none; position:fixed; inset:0; background:#12345480; z-index:50; align-items:center; justify-content:center }
        .pointage-info-btn {
            cursor:pointer; border:none; background:#dcebf7; color:#175d8e; font-weight:700;
            border-radius:50%; width:20px; height:20px; line-height:19px; padding:0; font-size:12px;
            margin-left:6px; display:inline-block; text-align:center; box-shadow:inset 0 0 0 1px #a9cbe4;
        }
        .pointage-info-btn:hover { background:#c7e0f2; color:#0e3f61; box-shadow:inset 0 0 0 1px #7fb0d6 }
    </style>

    <script>
        function openPointageModal(matricule, date, intervenant) {
            const overlay = document.getElementById('pointage-modal-overlay');
            const body = document.getElementById('pointage-modal-body');
            const subtitle = document.getElementById('pointage-modal-subtitle');
            subtitle.textContent = intervenant + ' · Matricule ' + matricule + ' · ' + date;
            body.innerHTML = '<tr><td colspan="2" class="muted">Chargement…</td></tr>';
            overlay.style.display = 'flex';

            fetch('{{ url('/prevalidation/pointages') }}/' + encodeURIComponent(matricule) + '/' + encodeURIComponent(date))
                .then(function (res) { return res.json(); })
                .then(function (rows) {
                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="2" class="muted">Aucun pointage ce jour.</td></tr>';
                        return;
                    }
                    body.innerHTML = rows.map(function (r) {
                        return '<tr><td>' + r.heure + '</td><td>' + r.nom + '</td></tr>';
                    }).join('');
                })
                .catch(function () {
                    body.innerHTML = '<tr><td colspan="2" class="error">Erreur de chargement.</td></tr>';
                });
        }
    </script>
</x-layouts.app>
