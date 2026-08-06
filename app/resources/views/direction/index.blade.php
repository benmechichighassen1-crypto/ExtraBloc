@php
    $statusLabels = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Contrôle direction</h1>
        <form method="get" class="row"><label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label><label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
        @foreach($statusLabels as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
        <button>Filtrer</button><button type="button" onclick="window.print()">Imprimer</button></form>
    </section>
    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Patient</th><th>Médecins</th><th>Heure planification</th><th>Heure anesthésie</th><th>Heure emploi</th><th>Heure pointage</th><th>Saisie</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
    @forelse($declarations as $item)<tr>
        <td>
            <strong>{{ $item->DesInterv ?? $item->cod_interv }}</strong><br>
            <span class="muted">{{ $item->DesTypInterv ?? 'Type non renseigné' }}</span><br>
            {{ $item->LibelleActe }}<br>
            <span class="muted">Dossier {{ $item->num_doss }} · {{ $item->DesignationSalle ?? 'Salle non renseignée' }}</span>
            @if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif
        </td>
        <td>
            {{ $item->NomPatient }} {{ $item->PrenomPatient }}<br>
            <span class="muted">Dossier {{ $item->num_doss }}</span>
        </td>
        <td><strong>Chirurgien :</strong> {{ $item->Chirurgien }}<br><strong>Réanimateur :</strong> {{ $item->Reanimateur }}</td>
        <td>
            {{ \App\Support\Format::dateTime($item->HDAnest) ?? 'Non renseigné' }}<br>
            {{ \App\Support\Format::dateTime($item->HFAnest) ?? '' }}
        </td>
        <td>
            {{ \App\Support\Format::dateTime($item->Debut_Anesthesie) ?? 'Non renseigné' }}<br>
            {{ \App\Support\Format::dateTime($item->Fin_Anesthesie) ?? '' }}
        </td>
        <td>
            @if($item->HeureEmploiDebut1)
                {{ \App\Support\Format::time($item->HeureEmploiDebut1) }} - {{ \App\Support\Format::time($item->HeureEmploiFin1) }}
                @if($item->HeureEmploiDebut2)
                    <br>{{ \App\Support\Format::time($item->HeureEmploiDebut2) }} - {{ \App\Support\Format::time($item->HeureEmploiFin2) }}
                @endif
            @else
                <span class="muted">Non renseigné</span>
            @endif
        </td>
        <td>
            @if($item->HeurePointageEntree)
                {{ \App\Support\Format::time($item->HeurePointageEntree) }}<br>
                {{ \App\Support\Format::time($item->HeurePointageSortie) }}
            @else
                <span class="muted">Aucun pointage</span>
            @endif
            @if($item->MatriculePointeuse)
                <button type="button" class="badge" title="Détail des pointages du jour"
                        style="cursor:pointer;border:1px solid #ccd8e1;background:#eef4f9;font-weight:bold"
                        onclick="openPointageModal('{{ $item->MatriculePointeuse }}', '{{ \App\Support\Format::date($item->DatOpe) }}', '{{ addslashes($item->DesInterv ?? '') }}')">i</button>
            @endif
        </td>
        <td>{{ $item->declared_by_username }}<br><span class="muted">{{ \App\Support\Format::dateTime($item->declared_at) }}</span></td>
        <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $statusLabels[$item->statut] ?? $item->statut }}</span></td>
        <td>@if(in_array($item->statut, ['SOUMIS','PREVALIDE']))<form method="post" action="{{ route('direction.declarations.decide', $item->id) }}" class="row">@csrf @method('PATCH')
            <input name="motif" placeholder="Motif (facultatif)"><button class="success" name="decision" value="VALIDE">Valider</button><button class="danger" name="decision" value="REJETE">Rejeter</button>
        </form>@else <strong>{{ $item->valide_par_username }}</strong><br><span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>@endif</td>
    </tr>@empty <tr><td colspan="10">Aucune déclaration.</td></tr>@endforelse
    </tbody></table><div style="margin-top:16px">{{ $declarations->links() }}</div></section>

    <div id="pointage-modal-overlay" style="display:none;position:fixed;inset:0;background:#12345480;z-index:50;align-items:center;justify-content:center">
        <div class="card" style="max-width:480px;width:92%;max-height:80vh;overflow:auto">
            <h2 style="margin-bottom:4px">Détail des pointages</h2>
            <p class="muted" id="pointage-modal-subtitle" style="margin-top:0"></p>
            <table><thead><tr><th>Heure</th><th>Nom</th></tr></thead>
                <tbody id="pointage-modal-body"><tr><td colspan="2" class="muted">Chargement…</td></tr></tbody>
            </table>
            <div class="row" style="margin-top:16px;justify-content:flex-end">
                <button type="button" onclick="closePointageModal()">Fermer</button>
            </div>
        </div>
    </div>

    <script>
        function openPointageModal(matricule, date, intervenant) {
            const overlay = document.getElementById('pointage-modal-overlay');
            const body = document.getElementById('pointage-modal-body');
            const subtitle = document.getElementById('pointage-modal-subtitle');
            subtitle.textContent = intervenant + ' · Matricule ' + matricule + ' · ' + date;
            body.innerHTML = '<tr><td colspan="2" class="muted">Chargement…</td></tr>';
            overlay.style.display = 'flex';

            fetch('{{ url('/direction/pointages') }}/' + encodeURIComponent(matricule) + '/' + encodeURIComponent(date))
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

        function closePointageModal() {
            document.getElementById('pointage-modal-overlay').style.display = 'none';
        }
    </script>
</x-layouts.app>
