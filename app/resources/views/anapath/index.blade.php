@php
    $statutBadge = [
        'attente'  => 'status-soumis',
        'resultat' => 'status-valide',
        'annulee'  => 'status-rejete',
    ];
    // Format jj/mm/aaaa pour les colonnes d'affichage.
    $d = fn ($x) => $x ? \Carbon\Carbon::parse($x)->format('d/m/Y') : '—';
@endphp
<x-layouts.app>
    <style>
        .anapath-filter { gap: 14px; align-items: flex-end; }
        .anapath-filter .filter-field { min-width: 0; }
        .anapath-filter select, .anapath-filter input[type=text] { width: 150px; }
        .anapath-filter input[type=date] { width: 132px; }
        .anapath-filter .field-retard select { width: 158px; }
        .anapath-filter .periode-dates { flex-wrap: nowrap; }
    </style>
    <section class="card">
        <h1>Suivi Anapath</h1>
        <p class="muted">Suivi des demandes d'anatomopathologie — résultat, compte rendu (PJ) et paiement.</p>

        <form method="get" class="filter-bar">
            <div class="filter-grid anapath-filter">
                <div class="filter-field">
                    <label class="filter-label">Période (date de la demande)</label>
                    <select name="periode" id="periode-select">
                        <option value="aujourdhui" @selected($periode === 'aujourdhui')>Aujourd'hui</option>
                        <option value="hier" @selected($periode === 'hier')>Hier</option>
                        <option value="personnalisee" @selected($periode === 'personnalisee')>Période</option>
                    </select>
                    <div id="periode-dates" class="row periode-dates" style="gap:6px;margin-top:6px">
                        <input type="date" name="date_debut" value="{{ $dateDebut }}">
                        <span class="muted">→</span>
                        <input type="date" name="date_fin" value="{{ $dateFin }}">
                    </div>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Dossier / Patient</label>
                    <input type="text" name="dossier" value="{{ $dossier }}" placeholder="N° dossier ou nom patient…">
                </div>
                <div class="filter-field">
                    <label class="filter-label">Médecin prescripteur</label>
                    <input list="medecin-options" name="medecin" value="{{ $medecin }}" placeholder="Rechercher…" autocomplete="off">
                    <datalist id="medecin-options">
                        @foreach($medecinOptions as $nom)
                            <option value="{{ $nom }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Laboratoire</label>
                    <select name="laboratoire">
                        <option value="">Tous les laboratoires</option>
                        @foreach($laboratoires as $opt)
                            <option value="{{ $opt->id }}" @selected($laboratoire == $opt->id)>{{ $opt->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Statut</label>
                    <select name="statut">
                        <option value="tous" @selected($statut === 'tous')>Tous</option>
                        <option value="attente" @selected($statut === 'attente')>En attente</option>
                        <option value="resultat" @selected($statut === 'resultat')>Résultat reçu</option>
                        <option value="annulee" @selected($statut === 'annulee')>Annulée</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Paiement</label>
                    <select name="statut_paiement">
                        <option value="tous" @selected($statutPaiement === 'tous')>Tous</option>
                        <option value="a_renseigner" @selected($statutPaiement === 'a_renseigner')>À renseigner</option>
                        <option value="laboratoire" @selected($statutPaiement === 'laboratoire')>Payé chez le laboratoire</option>
                        <option value="facture" @selected($statutPaiement === 'facture')>Inclus dans la facture patient</option>
                    </select>
                </div>
                <div class="filter-field field-retard">
                    <label class="filter-label">Résultat en retard</label>
                    <select name="en_retard">
                        <option value="tous" @selected($enRetard === 'tous')>Tous</option>
                        <option value="en_retard" @selected($enRetard === 'en_retard')>Uniquement en retard</option>
                    </select>
                </div>
            </div>
            <div class="row" style="gap:8px;margin-top:12px;flex-wrap:wrap">
                <button class="primary">Filtrer</button>
                <a class="button" href="{{ url('/anapath-suivi') }}">Réinitialiser</a>
                <button class="success" name="export" value="1">Exporter Excel</button>
            </div>
        </form>
    </section>

    @php($nbRetard = $rows->filter(fn ($r) => $r->en_retard)->count())
    @if($nbRetard > 0)
        <section class="card" style="border-color:#c0392b;background:#fdecea">
            <strong style="color:#c0392b">⚠ {{ $nbRetard }} résultat(s) en retard</strong> — dépassement de {{ $delaiAttendu }} jours depuis la demande.
            <div class="muted">Relancez le laboratoire concerné pour les demandes sans résultat reçu au-delà du délai attendu.</div>
        </section>
    @endif

    <section class="card"><table>
        <thead><tr>
            <th>N° demande · Date</th>
            <th>Patient / dossier</th>
            <th>Acte</th>
            <th>Prescripteur</th>
            <th>Laboratoire</th>
            <th>Saisie</th>
            <th>Statut</th>
            <th>Résultat</th>
            <th>Paiement</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td>
                    <strong>{{ $r->numero_demande }}</strong><br>
                    <span class="muted">{{ $d($r->created_at) }}</span>
                </td>
                <td>
                    <strong>{{ trim(($r->patient_nom ?? '').' '.($r->patient_prenom ?? '')) }}</strong><br>
                    <span class="muted">Dossier {{ $r->num_doss }}</span>
                </td>
                <td>
                    <strong>{{ $r->LibelleActe ?? ($r->code_examen_erp ?? '—') }}</strong>
                    @if($r->DateActe)<br><span class="muted">Acte du {{ $d($r->DateActe) }}</span>@endif
                    @if($r->nature_prelevement)<br><span class="muted">{{ $r->nature_prelevement }}</span>@endif
                </td>
                <td>{{ $r->medecin_prescripteur ?? '—' }}</td>
                <td>
                    {{ $r->laboratoire_nom ?? '—' }}
                    @if($r->en_retard)
                        <br><span class="badge status-rejete">En retard</span>
                    @endif
                </td>
                <td>
                    {{ $r->cree_par_username ?? '—' }}<br>
                    @if($r->created_at)
                        <span class="muted">{{ \App\Support\Format::dateTime($r->created_at) }}</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $statutBadge[$r->statut] ?? 'status-soumis' }}">{{ $r->statut_label }}</span>
                    <div class="muted" style="font-size:11px;margin-top:3px;white-space:nowrap">
                        @if($r->modifie_le !== null)
                            {{ $r->modifie_par_username ?? '—' }}<br>{{ $d($r->modifie_le) }}
                        @else
                            {{ $r->cree_par_username ?? '—' }}<br>{{ $d($r->created_at) }}
                        @endif
                    </div>
                    <button class="button" type="button" onclick="openTrace({{ $r->demande_id }})" style="padding:4px 8px;font-size:11px;margin-top:5px">Traçabilité</button>
                </td>
                <td style="white-space:nowrap">
                    <form method="post" action="{{ url('/anapath-suivi/'.$r->demande_id.'/resultat') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row" style="gap:5px;align-items:center;margin-bottom:6px">
                            <label class="recu-row" style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;font-weight:600;white-space:nowrap">
                                <input type="checkbox" name="resultat_recu" value="1" class="recu-toggle" @checked((bool)$r->resultat_recu)>
                                <span>Reçu</span>
                            </label>
                            <input type="date" name="resultat_recu_le" class="recu-date" value="{{ $r->resultat_recu_le ? \Carbon\Carbon::parse($r->resultat_recu_le)->format('Y-m-d') : '' }}" style="padding:4px;width:122px" title="Date de réception (par défaut : aujourd'hui)">
                            <label title="Ajouter des pièces jointes (plusieurs possibles)" style="padding:4px 8px;background:#eef3f8;border:1px solid #cbd7e3;border-radius:6px;font-size:12px;cursor:pointer;color:#123454;white-space:nowrap">📎 PJ
                                <input type="file" name="resultat_pj[]" multiple accept="application/pdf,image/*" style="display:none">
                            </label>
                        </div>
                        <button class="primary" style="padding:6px 10px;width:100%">Enregistrer</button>
                    </form>
                    @if($r->pj_list->isNotEmpty())
                        <div style="font-size:11px;margin-top:5px;line-height:1.6">
                            @foreach($r->pj_list as $pj)
                                <a href="{{ url('/anapath-suivi/pj/'.$pj->id) }}" target="_blank" style="display:block;white-space:normal">📄 {{ $pj->nom }}</a>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td style="white-space:nowrap">
                    <form method="post" action="{{ url('/anapath-suivi/'.$r->demande_id.'/paiement') }}">
                        @csrf
                        <select name="paiement_type" required style="display:block;padding:5px;width:170px">
                            <option value="" disabled @selected($r->paiement_type === null)>— Mode —</option>
                            <option value="laboratoire" @selected($r->paiement_type === 'laboratoire')>Payé chez le laboratoire</option>
                            <option value="facture" @selected($r->paiement_type === 'facture')>Inclus dans la facture patient</option>
                        </select>
                        <button class="primary" style="display:block;padding:6px 10px;width:170px;margin-top:6px">Enregistrer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">Aucune demande d'anapath ne correspond aux filtres.</td></tr>
        @endforelse
        </tbody>
    </table></section>

    <div id="trace-modal" class="modal-overlay" style="display:none;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
        <div class="card" style="max-width:600px;width:92%;max-height:80vh;overflow:auto">
            <h2 style="margin-bottom:4px">Traçabilité de la demande</h2>
            <p class="muted" style="margin-top:0">Historique complet des actions sur cette demande.</p>
            <table><thead><tr><th>Action</th><th>Utilisateur</th><th>Date</th><th>Détail</th></tr></thead>
                <tbody id="trace-body"><tr><td colspan="4" class="muted">Chargement…</td></tr></tbody>
            </table>
            <div class="row" style="margin-top:16px;justify-content:flex-end"><button type="button" onclick="document.getElementById('trace-modal').style.display='none'">Fermer</button></div>
        </div>
    </div>

    <script>
        (function () {
            var s = document.getElementById('periode-select');
            var d = document.getElementById('periode-dates');
            function toggle() { d.style.display = (s.value === 'personnalisee') ? 'flex' : 'none'; }
            if (s && d) { s.addEventListener('change', toggle); toggle(); }
            // Coche « Reçu » → date de réception du jour par défaut si vide.
            document.querySelectorAll('.recu-toggle').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    var form = cb.closest('form');
                    var date = form && form.querySelector('.recu-date');
                    if (cb.checked && date && !date.value) {
                        date.value = new Date().toISOString().slice(0, 10);
                    }
                });
            });
        })();
        function openTrace(id) {
            var overlay = document.getElementById('trace-modal');
            overlay.style.display = 'flex';
            var body = document.getElementById('trace-body');
            body.innerHTML = '<tr><td colspan="4" class="muted">Chargement…</td></tr>';
            fetch('/anapath-suivi/' + id + '/trace').then(function (r) { return r.json(); }).then(function (rows) {
                if (!rows.length) { body.innerHTML = '<tr><td colspan="4" class="muted">Aucun historique.</td></tr>'; return; }
                body.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + r.action + '</td><td>' + (r.acteur || '') + '</td><td>' + r.date + '</td><td>' + (r.details || '') + '</td></tr>';
                }).join('');
            }).catch(function () { body.innerHTML = '<tr><td colspan="4" class="muted">Erreur de chargement.</td></tr>'; });
        }
    </script>
</x-layouts.app>
