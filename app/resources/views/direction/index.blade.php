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
        @if($errors->any())
            <div style="background:#fdecea;border:1px solid #f2b8b1;color:#a02818;padding:10px 14px;border-radius:8px;margin-bottom:14px">{{ $errors->first() }}</div>
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

            @if($canValidate)
                <div class="filter-field">
                    <label class="filter-label">Statut</label>
                    <div class="filter-statuses">
                        @foreach($filterableStatuses as $value => $label)
                            <label class="status-toggle"><input type="checkbox" name="statuts[]" value="{{ $value }}" @checked(in_array($value, $statuses, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="filter-actions">
                <button>Filtrer</button>
                <button type="button" onclick="window.print()">Imprimer</button>
                <a href="{{ route('direction.export', request()->query()) }}" style="background:#16846a;color:#fff;text-decoration:none;border-radius:7px;padding:10px 14px;font:inherit;display:inline-block">Exporter Excel</a>
            </div>
        </form>
    </section>

    <section class="card" style="display:flex;flex-wrap:wrap;gap:16px;padding:18px">
        <div class="stat" style="background:var(--navy,#123454);color:#fff">
            <div class="num">{{ $stats['total'] }}</div>
            <div>Total demandes (période)</div>
        </div>
        <div class="stat"><div class="num">{{ $stats['enAttente'] }}</div><div>En attente</div></div>
        <div class="stat"><div class="num">{{ $stats['prevalide'] }}</div><div>Prévalidées</div></div>
        <div class="stat"><div class="num">{{ $stats['valide'] }}</div><div>Validées</div></div>
        <div class="stat"><div class="num">{{ $stats['refusee'] }}</div><div>Refusées</div></div>
        <div class="stat" style="background:#16846a;color:#fff">
            <div class="num">{{ number_format($stats['montantTotal'], 0, ',', ' ') }}</div>
            <div>Montant total (prévalidées + validées)</div>
        </div>
    </section>

    <style>
        .stat { flex:1; min-width:130px; background:#eef4f9; border-radius:10px; padding:14px 16px }
        .stat .num { font-size:26px; font-weight:700; line-height:1.1 }
        .stat > div:last-child { font-size:12px; margin-top:4px; opacity:.85 }
        .filter-bar { display:flex; flex-direction:column; gap:18px }
        .filter-grid { display:flex; flex-wrap:wrap; gap:22px; align-items:flex-start }
        .filter-field { display:flex; flex-direction:column; gap:6px }
        .filter-label { font-size:12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#3a4a56 }
        .filter-statuses { display:flex; flex-wrap:wrap; gap:8px 14px; align-items:center }
        .status-toggle { font-weight:400; text-transform:none; letter-spacing:normal; color:#1c2d3b; display:inline-flex; align-items:center; gap:5px; background:#f3f7fa; border:1px solid #e6edf2; border-radius:20px; padding:6px 12px 6px 10px }
        .filter-actions { display:flex; flex-wrap:wrap; gap:10px; padding-top:6px; border-top:1px solid #e6edf2 }
        .clear-field-btn { position:absolute; right:2px; top:50%; transform:translateY(-50%); width:22px; height:22px; padding:0; border:0; background:transparent; color:#8a99a6; font-size:16px; line-height:1; cursor:pointer }
    </style>
    <section class="card"><table><thead><tr><th>Intervenant / acte</th><th>Patient</th><th>Médecins</th><th>Date acte</th><th>Heure planification</th><th>Heure anesthésie</th><th>Heure emploi</th><th>Heure pointage</th><th>Saisie</th><th>Statut</th><th>Montant</th><th>Décision</th></tr></thead><tbody>
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
            @if($canValidate && in_array($item->statut, ['SOUMIS','PREVALIDE','VALIDE'], true))
                <form method="post" action="{{ route('direction.declarations.montant', $item->id) }}" data-ajax-form onchange="this.requestSubmit()" title="Choisir ou corriger le montant (enregistré automatiquement et journalisé)">
                    @csrf @method('PATCH')
                    <label class="muted" style="display:block;font-size:11px;margin-bottom:3px">Montant</label>
                    <select name="montant" required style="width:100px">
                        <option value="" disabled @selected($item->montant === null || $item->montant === '')>—</option>
                        @foreach([100,150,200,250,300] as $m)<option value="{{ $m }}" @selected((int) $item->montant === $m)>{{ $m }}</option>@endforeach
                    </select>
                </form>
            @else
                @if($item->montant)
                    <span class="badge" style="background:#e8f6ec;color:#1c6b36">{{ $item->montant }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            @endif
        </td>
        <td style="text-align:right">
            @if($canValidate && in_array($item->statut, ['SOUMIS','PREVALIDE']))
                @if($item->statut === 'PREVALIDE')
                    <div class="muted" style="margin-bottom:6px;text-align:left">Prévalidé par <strong>{{ $item->prevalide_par_username }}</strong> le {{ \App\Support\Format::dateTime($item->prevalide_le) }}
                    @if($item->motif_prevalidation)<br>Motif : {{ $item->motif_prevalidation }}@endif
                    </div>
                @endif
                @php($validationBloquee = $prevalidationObligatoire && $item->statut !== 'PREVALIDE')
                @if($validationBloquee)
                    <p class="muted" style="margin:0 0 6px;text-align:left">En attente de pré-validation par le major du bloc avant validation finale.</p>
                @endif
                <form method="post" action="{{ route('direction.declarations.decide', $item->id) }}" class="row" style="justify-content:flex-end;flex-wrap:nowrap" data-ajax-form>@csrf @method('PATCH')
                    <input name="motif" placeholder="Motif (obligatoire si refus)" style="min-width:150px">
                    <button class="success" name="decision" value="VALIDE" @disabled($validationBloquee) title="{{ $validationBloquee ? 'Pré-validation requise avant validation finale' : '' }}" onclick="this.form.querySelector('[name=motif]').required=false">Valider</button>
                    <button class="danger" name="decision" value="REJETE" onclick="this.form.querySelector('[name=motif]').required=true">Refuser</button>
                    <button type="button" class="btn-outline" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
                </form>
            @elseif($canValidate)
                <div style="text-align:left"><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
                <form method="post" action="{{ route('direction.declarations.invalidate', $item->id) }}" class="row" style="margin-top:8px;justify-content:flex-end;flex-wrap:nowrap" onsubmit="return confirm('Confirmer l’annulation de cette décision ? La déclaration repassera « En attente ».');">
                    @csrf @method('PATCH')
                    <input name="motif" placeholder="Motif de la correction" required style="min-width:130px">
                    <button type="submit" style="background:#c67c1f">Dévalider</button>
                    <button type="button" class="btn-outline" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
                </form>
            @else
                {{-- RH : lecture seule, aucune action possible --}}
                <div style="text-align:left"><strong>{{ $item->valide_par_username }}</strong><br>
                <span class="muted">{{ \App\Support\Format::dateTime($item->valide_le) }}</span>
                @if($item->motif_decision)<br><span class="muted">Motif : {{ $item->motif_decision }}</span>@endif</div>
                <button type="button" class="btn-outline" style="margin-top:8px" onclick="openAuditModal({{ $item->id }})">Traçabilité</button>
            @endif
        </td>
    </tr>@empty <tr><td colspan="12">Aucune déclaration.</td></tr>@endforelse
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
            <table><thead><tr><th>Action</th><th>Utilisateur</th><th>Date</th><th>Montant</th><th>Motif</th></tr></thead>
                <tbody id="audit-modal-body"><tr><td colspan="5" class="muted">Chargement…</td></tr></tbody>
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

        #toast-container { position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:100; display:flex; flex-direction:column; gap:8px; align-items:center }
        .toast {
            padding:12px 20px; border-radius:8px; font-size:14px; font-weight:600; box-shadow:0 4px 14px rgba(0,0,0,.18);
            opacity:0; transform:translateY(-12px); transition:opacity .2s ease, transform .2s ease; max-width:90vw;
        }
        .toast.show { opacity:1; transform:translateY(0) }
        .toast-success { background:#e8f6ec; color:#1c6b36; border:1px solid #b6e0c3 }
        .toast-error   { background:#fdecea; color:#a02818; border:1px solid #f2b8b1 }
    </style>

    <div id="toast-container"></div>

    <script>
        function showToast(message, type) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
            toast.textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(function () { toast.classList.add('show'); });
            setTimeout(function () {
                toast.classList.remove('show');
                setTimeout(function () { toast.remove(); }, 250);
            }, 4000);
        }

        // Intercepte tous les formulaires marqués data-ajax-form : envoie en
        // AJAX (Accept: application/json), affiche une notification en haut
        // au lieu de recharger la page. En cas de succès, on recharge quand
        // même après un court délai pour que les compteurs/statuts affichés
        // restent synchronisés avec la base — mais l'utilisateur voit
        // d'abord la confirmation, sans l'attente d'un rechargement complet.
        // En cas d'erreur (ex. "Veuillez renseigner ce champ"), AUCUN
        // rechargement : seule la notification s'affiche.
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!form.matches('[data-ajax-form]')) {
                return;
            }
            event.preventDefault();

            const submitter = event.submitter;
            const formData = new FormData(form);
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value);
            }

            const buttons = form.querySelectorAll('button');
            buttons.forEach(function (b) { b.disabled = true; });

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            })
                .then(function (res) {
                    return res.json().then(function (body) { return { status: res.status, body: body }; });
                })
                .then(function (result) {
                    if (result.status >= 200 && result.status < 300) {
                        showToast(result.body.message || 'Enregistré.', 'success');
                        setTimeout(function () { window.location.reload(); }, 900);
                    } else {
                        const firstError = result.body.errors
                            ? Object.values(result.body.errors)[0][0]
                            : (result.body.message || 'Une erreur est survenue.');
                        showToast(firstError, 'error');
                        buttons.forEach(function (b) { b.disabled = false; });
                    }
                })
                .catch(function () {
                    showToast('Erreur réseau, veuillez réessayer.', 'error');
                    buttons.forEach(function (b) { b.disabled = false; });
                });
        });

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
                        body.innerHTML = '<tr><td colspan="5" class="muted">Aucun historique.</td></tr>';
                        return;
                    }
                    body.innerHTML = rows.map(function (r) {
                        return '<tr><td>' + r.action + '</td><td>' + (r.acteur || '') + '</td><td>' + r.date + '</td><td>' + (r.montant ? '→ ' + r.montant : '') + '</td><td>' + (r.motif || '') + '</td></tr>';
                    }).join('');
                })
                .catch(function () {
                    body.innerHTML = '<tr><td colspan="4" class="error">Erreur de chargement.</td></tr>';
                });
        }
    </script>
</x-layouts.app>
