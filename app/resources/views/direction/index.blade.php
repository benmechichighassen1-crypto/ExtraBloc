@php
    $statusLabels = \App\Http\Controllers\DirectionController::STATUS_LABELS;
    $filterableStatuses = ['SOUMIS' => 'En attente', 'PREVALIDE' => 'Prévalidé', 'VALIDE' => 'Validé', 'REJETE' => 'Refusé'];
@endphp
<x-layouts.app>
    <section class="card">
        <h1>{{ $canValidate ? 'Contrôle direction' : 'Déclarations validées (RH)' }}</h1>
        @if(!$canValidate)
            <p class="muted">Consultation en lecture seule des actes extra validés par la Direction, pour traitement de la paie.</p>
        @endif
        <form method="get" class="row"><label>Du <input type="date" name="date_debut" value="{{ $dateDebut }}"></label><label>Au <input type="date" name="date_fin" value="{{ $dateFin }}"></label>
        @if($canValidate)
            @foreach($filterableStatuses as $value => $label)<label><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>@endforeach
        @endif
        <label>Intervenant
            <span style="position:relative;display:inline-block">
                <input list="intervenant-options" id="intervenant-input" name="intervenant" value="{{ $intervenant }}" placeholder="Rechercher un intervenant…" autocomplete="off" style="min-width:220px;padding-right:28px">
                <button type="button" onclick="document.getElementById('intervenant-input').value='';document.getElementById('intervenant-input').form.submit()" title="Vider ce filtre" style="position:absolute;right:2px;top:50%;transform:translateY(-50%);width:22px;height:22px;padding:0;border:0;background:transparent;color:#8a99a6;font-size:16px;line-height:1;cursor:pointer">×</button>
            </span>
            <datalist id="intervenant-options">
                @foreach($intervenantOptions as $opt)<option value="{{ $opt->CodInterv }} — {{ $opt->DesInterv }}">@endforeach
            </datalist>
        </label>
        <label>Dossier / Patient
            <span style="position:relative;display:inline-block">
                <input type="text" id="recherche-input" name="recherche" value="{{ $recherche }}" placeholder="N° dossier ou nom patient…" style="min-width:180px;padding-right:28px">
                <button type="button" onclick="document.getElementById('recherche-input').value='';document.getElementById('recherche-input').form.submit()" title="Vider ce filtre" style="position:absolute;right:2px;top:50%;transform:translateY(-50%);width:22px;height:22px;padding:0;border:0;background:transparent;color:#8a99a6;font-size:16px;line-height:1;cursor:pointer">×</button>
            </span>
        </label>
        <label>Salle
            <select name="salles[]" multiple size="3" style="min-width:170px;vertical-align:top">
                @foreach($salleOptions as $salle)<option value="{{ $salle }}" @selected(in_array($salle, $salles, true))>{{ $salle }}</option>@endforeach
            </select>
        </label>
        <button>Filtrer</button>
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="{{ route('direction.export', request()->query()) }}" style="background:#16846a;color:#fff;text-decoration:none;border-radius:7px;padding:10px 14px;font:inherit;display:inline-block">Exporter Excel</a>
        </form>
    </section>
    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Patient</th><th>Médecins</th><th>Date acte</th><th>Heure planification</th><th>Heure anesthésie</th><th>Heure emploi</th><th>Heure pointage</th><th>Saisie</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
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
            @if($item->observation)<br><strong>Observation :</strong> {{ $item->observation }}@endif
        </td>
        <td>
            {{ $item->NomPatient }} {{ $item->PrenomPatient }}<br>
            <span class="muted">Dossier {{ $item->num_doss }}</span>
        </td>
        <td><strong>Chirurgien :</strong> {{ $item->Chirurgien }}<br><strong>Réanimateur :</strong> {{ $item->Reanimateur }}</td>
        <td>{{ \App\Support\Format::date($item->DatOpe) }}</td>
        <td style="background:#eaf3fb">
            {{ \App\Support\Format::timeRange($item->HDAnest, $item->HFAnest) ?? 'Non renseigné' }}
        </td>
        <td style="background:#fdf1e6">
            {{ \App\Support\Format::timeRange($item->Debut_Anesthesie, $item->Fin_Anesthesie) ?? 'Non renseigné' }}
        </td>
        <td>
            @if($item->Repos)
                <span class="badge" style="background:#e3edf4;color:#3a4a56">En repos</span>
            @elseif($item->HeureEmploiDebut1)
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
                <button type="button" class="pointage-info-btn" title="Détail des pointages du jour"
                        onclick="openPointageModal('{{ $item->MatriculePointeuse }}', '{{ \App\Support\Format::date($item->DatOpe) }}', '{{ addslashes($item->DesInterv ?? '') }}')">i</button>
            @endif
        </td>
        <td>{{ $item->declared_by_username }}<br><span class="muted">{{ \App\Support\Format::dateTime($item->declared_at) }}</span></td>
        <td><span class="badge status-{{ strtolower($item->statut) }}">{{ $statusLabels[$item->statut] ?? $item->statut }}</span></td>
        <td>
            @if($canValidate && in_array($item->statut, ['SOUMIS','PREVALIDE']))
                @if($item->statut === 'PREVALIDE')
                    <div class="muted" style="margin-bottom:6px">Prévalidé par <strong>{{ $item->prevalide_par_username }}</strong> le {{ \App\Support\Format::dateTime($item->prevalide_le) }}
                    @if($item->motif_prevalidation)<br>Motif : {{ $item->motif_prevalidation }}@endif
                    </div>
                @endif
                @php($validationBloquee = $prevalidationObligatoire && $item->statut !== 'PREVALIDE')
                @if($validationBloquee)
                    <p class="muted" style="margin:0 0 6px">En attente de pré-validation par le major du bloc avant validation finale.</p>
                @endif
                <form method="post" action="{{ route('direction.declarations.decide', $item->id) }}" class="row" onsubmit="return true">@csrf @method('PATCH')
                    <input name="motif" placeholder="Motif (obligatoire si rejet)" style="min-width:150px">
                    <button class="success" name="decision" value="VALIDE" @disabled($validationBloquee) title="{{ $validationBloquee ? 'Pré-validation requise avant validation finale' : '' }}" onclick="this.form.querySelector('[name=motif]').required=false">Valider</button>
                    <button class="danger" name="decision" value="REJETE" onclick="this.form.querySelector('[name=motif]').required=true">Rejeter</button>
                    <button type="button" class="btn-outline" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
                </form>
            @elseif($canValidate)
                <div><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
                <form method="post" action="{{ route('direction.declarations.invalidate', $item->id) }}" class="row" style="margin-top:8px" onsubmit="return confirm('Confirmer l’annulation de cette décision ? La déclaration repassera « En attente ».');">
                    @csrf @method('PATCH')
                    <input name="motif" placeholder="Motif de la correction" required style="min-width:130px">
                    <button type="submit" style="background:#c67c1f">Dévalider</button>
                    <button type="button" class="btn-outline" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
                </form>
            @else
                {{-- RH : lecture seule, aucune action possible --}}
                <div><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
                <button type="button" class="btn-outline" style="margin-top:8px" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
            @endif
        </td>
    </tr>@empty <tr><td colspan="11">Aucune déclaration.</td></tr>@endforelse
    </tbody></table><div style="margin-top:16px">{{ $declarations->links() }}</div></section>

    <div id="pointage-modal-overlay" class="modal-overlay">
        <div class="card" style="max-width:480px;width:92%;max-height:80vh;overflow:auto">
            <h2 style="margin-bottom:4px">Détail des pointages</h2>
            <p class="muted" id="pointage-modal-subtitle" style="margin-top:0"></p>
            <table><thead><tr><th>Heure</th><th>Nom</th></tr></thead>
                <tbody id="pointage-modal-body"><tr><td colspan="2" class="muted">Chargement…</td></tr></tbody>
            </table>
            <div class="row" style="margin-top:16px;justify-content:flex-end">
                <button type="button" onclick="closeModal('pointage-modal-overlay')">Fermer</button>
            </div>
        </div>
    </div>

    <div id="audit-modal-overlay" class="modal-overlay">
        <div class="card" style="max-width:560px;width:92%;max-height:80vh;overflow:auto">
            <h2 style="margin-bottom:4px">Traçabilité de la déclaration</h2>
            <p class="muted" style="margin-top:0">Historique complet depuis la création, du plus ancien au plus récent.</p>
            <table><thead><tr><th>Action</th><th>Utilisateur</th><th>Date</th><th>Motif</th></tr></thead>
                <tbody id="audit-modal-body"><tr><td colspan="4" class="muted">Chargement…</td></tr></tbody>
            </table>
            <div class="row" style="margin-top:16px;justify-content:flex-end">
                <button type="button" onclick="closeModal('audit-modal-overlay')">Fermer</button>
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
        .btn-outline { background:#fff; color:#1779ba; border:1px solid #ccd8e1 }
    </style>

    <script>
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

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

        function openAuditModal(declarationId) {
            const overlay = document.getElementById('audit-modal-overlay');
            const body = document.getElementById('audit-modal-body');
            body.innerHTML = '<tr><td colspan="4" class="muted">Chargement…</td></tr>';
            overlay.style.display = 'flex';

            fetch('{{ url('/direction/declarations') }}/' + encodeURIComponent(declarationId) + '/audits')
                .then(function (res) { return res.json(); })
                .then(function (rows) {
                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="4" class="muted">Aucun historique.</td></tr>';
                        return;
                    }
                    body.innerHTML = rows.map(function (r) {
                        return '<tr><td>' + r.action + '</td><td>' + (r.acteur || '') + '</td><td>' + r.date + '</td><td>' + (r.motif || '') + '</td></tr>';
                    }).join('');
                })
                .catch(function () {
                    body.innerHTML = '<tr><td colspan="4" class="error">Erreur de chargement.</td></tr>';
                });
        }
    </script>
</x-layouts.app>
