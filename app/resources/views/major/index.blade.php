@php
    $statusLabels = \App\Http\Controllers\MajorController::STATUS_LABELS;
    $filterableStatuses = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>Pré-validation (major du bloc)</h1>
        <p class="muted">Vérifiez les déclarations « En attente » avant transmission à la Direction pour validation finale.</p>
        @if($errors->any())
            <div style="background:#fdecea;border:1px solid #f2b8b1;color:#a02818;padding:12px 14px;border-radius:8px;margin-bottom:14px">
                <strong>Le montant est requis pour prévalider.</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @if(session('success'))
            <div style="background:#e8f6ec;border:1px solid #b6e0c3;color:#1c6b36;padding:12px 14px;border-radius:8px;margin-bottom:14px">{{ session('success') }}</div>
        @endif
        <form method="get" class="filter-bar">
            <div class="filter-grid">
                <div class="filter-field">
                    <label class="filter-label">Période</label>
                    <div class="row" style="gap:6px;flex-wrap:nowrap">
                        <input type="date" name="date_debut" value="{{ $dateDebut }}">
                        <span class="muted">→</span>
                        <input type="date" name="date_fin" value="{{ $dateFin }}">
                    </div>
                </div>
                <div class="filter-field">
                    <label class="filter-label" for="intervenant-input">Intervenant</label>
                    <span style="position:relative;display:inline-block">
                        <input list="intervenant-options" id="intervenant-input" name="intervenant" value="{{ $intervenant }}" placeholder="Rechercher un intervenant…" autocomplete="off" style="min-width:220px;padding-right:28px">
                        <button type="button" onclick="document.getElementById('intervenant-input').value='';document.getElementById('intervenant-input').form.submit()" title="Vider ce filtre" class="clear-field-btn">×</button>
                    </span>
                    <datalist id="intervenant-options">
                        @foreach($intervenantOptions as $opt)<option value="{{ $opt->CodInterv }} — {{ $opt->DesInterv }}">@endforeach
                    </datalist>
                </div>
                <div class="filter-field">
                    <label class="filter-label" for="recherche-input">Dossier / Patient</label>
                    <span style="position:relative;display:inline-block">
                        <input type="text" id="recherche-input" name="recherche" value="{{ $recherche }}" placeholder="N° dossier ou nom patient…" style="min-width:180px;padding-right:28px">
                        <button type="button" onclick="document.getElementById('recherche-input').value='';document.getElementById('recherche-input').form.submit()" title="Vider ce filtre" class="clear-field-btn">×</button>
                    </span>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Bloc</label>
                    <select name="bloc" style="min-width:180px">
                        <option value="">Tous les blocs</option>
                        @foreach($blocOptions as $opt)<option value="{{ $opt->CodBloc }}" @selected($bloc === $opt->CodBloc)>{{ $opt->LibBloc }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="filter-field">
                <label class="filter-label">Statut</label>
                <div class="filter-statuses">
                    @foreach($filterableStatuses as $value => $label)
                        <label class="status-toggle"><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            <div class="filter-actions">
                <button>Filtrer</button>
            </div>
        </form>
    </section>

    <style>
        .filter-bar { display:flex; flex-direction:column; gap:18px }
        .filter-grid { display:flex; flex-wrap:wrap; gap:22px; align-items:flex-start }
        .filter-field { display:flex; flex-direction:column; gap:6px }
        .filter-label { font-size:12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#3a4a56 }
        .filter-statuses { display:flex; flex-wrap:wrap; gap:8px 14px; align-items:center }
        .status-toggle { font-weight:400; text-transform:none; letter-spacing:normal; color:#1c2d3b; display:inline-flex; align-items:center; gap:5px; background:#f3f7fa; border:1px solid #e6edf2; border-radius:20px; padding:6px 12px 6px 10px }
        .filter-actions { display:flex; flex-wrap:wrap; gap:10px; padding-top:6px; border-top:1px solid #e6edf2 }
        .clear-field-btn { position:absolute; right:2px; top:50%; transform:translateY(-50%); width:22px; height:22px; padding:0; border:0; background:transparent; color:#8a99a6; font-size:16px; line-height:1; cursor:pointer }
        .pointage-info-btn {
            cursor:pointer; border:none; background:#dcebf7; color:#175d8e; font-weight:700;
            border-radius:50%; width:20px; height:20px; line-height:19px; padding:0; font-size:12px;
            margin-left:6px; display:inline-block; text-align:center; box-shadow:inset 0 0 0 1px #a9cbe4;
        }
        .pointage-info-btn:hover { background:#c7e0f2; color:#0e3f61; box-shadow:inset 0 0 0 1px #7fb0d6 }
        .modal-overlay { display:none; position:fixed; inset:0; background:#12345480; z-index:50; align-items:center; justify-content:center }
    </style>

    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Patient</th><th>Chirurgien</th><th>Date acte</th><th>Heure planification</th><th>Heure anesthésie</th><th>Heure pointage</th><th>Saisie</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
    @forelse($declarations as $item)<tr>
        <td>
            <strong>{{ $item->DesInterv ?? $item->cod_interv }}</strong><br>
            <span class="muted">{{ \App\Support\Format::role($item->role_intervenant) ?? $item->DesTypInterv ?? 'Type non renseigné' }}
                @if($item->DesTypInterv && \App\Support\Format::role($item->role_intervenant) !== $item->DesTypInterv) (fiche : {{ $item->DesTypInterv }})@endif
            </span><br>
            {{ $item->LibelleActe }}<br>
            <span class="muted">{{ $item->DesignationSalle ?? 'Salle non renseignée' }}</span>
            @if($item->chevauchement)
                <br><span class="badge" style="background:#fde3d0;color:#8a3b0a" title="Cet intervenant a une autre déclaration le même jour sur une plage horaire qui se chevauche">⚠ Chevauchement horaire</span>
            @endif
            @if($item->doublon_sous_dossier)
                <br><span class="badge" style="background:#fde3d0;color:#8a3b0a" title="Même patient, même acte et même intervenant déjà déclaré sur un autre numéro de dossier (probable sous-dossier créé par la facturation)">⚠ Doublon inter-dossier</span>
            @endif
            @if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif
        </td>
        <td>
            {{ $item->NomPatient }} {{ $item->PrenomPatient }}<br>
            <span class="muted">Dossier {{ $item->num_doss }}</span>
        </td>
        <td>{{ $item->Chirurgien }}</td>
        <td>{{ \App\Support\Format::date($item->DatOpe) }}</td>
        <td style="background:#eaf3fb">{{ \App\Support\Format::timeRange($item->HDAnest, $item->HFAnest) ?? 'Non renseigné' }}</td>
        <td style="background:#fdf1e6">{{ \App\Support\Format::timeRange($item->Debut_Anesthesie, $item->Fin_Anesthesie) ?? 'Non renseigné' }}</td>
        <td>
            @if($item->Repos)
                <span class="badge" style="background:#e3edf4;color:#3a4a56">En repos</span>
            @elseif($item->HeurePointageEntree)
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
                    <select name="montant" required onchange="this.required = (document.activeElement && document.activeElement.value==='PREVALIDE')">
                        <option value="">Montant…</option>
                        @foreach([100,150,200,250,300,350,400] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                    </select>
                    <input name="motif" placeholder="Motif (obligatoire si refus)" style="min-width:150px">
                    <button class="success" name="decision" value="PREVALIDE" onclick="this.form.querySelector('[name=motif]').required=false;this.form.querySelector('[name=montant]').required=true">Prévalider</button>
                    <button class="danger" name="decision" value="REJETE" onclick="this.form.querySelector('[name=motif]').required=true;this.form.querySelector('[name=montant]').required=false">Refuser</button>
                </form>
            @elseif($item->statut === 'PREVALIDE')
                <div style="text-align:left"><strong>{{ $item->prevalide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->prevalide_le) }}</span>
                @if($item->montant)<br><span class="badge" style="background:#e8f6ec;color:#1c6b36">Montant : {{ $item->montant }}</span>@endif
                @if($item->motif_prevalidation)<br><span class="muted">Motif : {{ $item->motif_prevalidation }}</span>@endif
                <br><span class="muted">Transmis à la Direction.</span></div>
            @else
                <div style="text-align:left"><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
            @endif
        </td>
    </tr>@empty <tr><td colspan="10">Aucune déclaration.</td></tr>@endforelse
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
