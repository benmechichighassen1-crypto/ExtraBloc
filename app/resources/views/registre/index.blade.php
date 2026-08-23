<x-layouts.app>
    <section class="card">
        <h1>Registre bloc opératoire</h1>
        <p class="muted">Registre médico-légal — données ERP en lecture seule, demandes d'anatomopathologie et audit dans la base applicative.</p>
        <form method="get" class="filter-bar">
            <div class="filter-grid">
                <div class="filter-field">
                    <label class="filter-label" for="periode-select">Période</label>
                    <select id="periode-select" name="periode" onchange="document.getElementById('periode-custom').style.display = this.value === 'custom' ? 'flex' : 'none'; if (this.value !== 'custom') { this.form.submit(); }">
                        <option value="today" @selected($periode === 'today')>Aujourd'hui</option>
                        <option value="yesterday" @selected($periode === 'yesterday')>Hier</option>
                        <option value="custom" @selected($periode === 'custom')>Période…</option>
                    </select>
                    <div id="periode-custom" class="row" style="gap:6px;margin-top:6px;{{ $periode === 'custom' ? '' : 'display:none' }}">
                        <input type="date" name="du" value="{{ $from }}">
                        <span class="muted">→</span>
                        <input type="date" name="au" value="{{ $to }}">
                    </div>
                </div>
                <div class="filter-field">
                    <label class="filter-label" for="dossier-input">Dossier / Patient</label>
                    <span style="position:relative;display:inline-block">
                        <input type="text" id="dossier-input" name="dossier" value="{{ $dossier }}" placeholder="N° dossier ou nom patient…" style="min-width:200px;padding-right:28px">
                        <button type="button" onclick="document.getElementById('dossier-input').value='';document.getElementById('dossier-input').form.submit()" title="Vider ce filtre" class="clear-field-btn">×</button>
                    </span>
                </div>
                <div class="filter-field">
                    <label class="filter-label" for="medecin-input">Médecin chirurgien</label>
                    <span style="position:relative;display:inline-block">
                        <input list="medecin-options" id="medecin-input" name="medecin" value="{{ $medecin }}" placeholder="Rechercher un médecin…" autocomplete="off" style="min-width:220px;padding-right:28px">
                        <button type="button" onclick="document.getElementById('medecin-input').value='';document.getElementById('medecin-input').form.submit()" title="Vider ce filtre" class="clear-field-btn">×</button>
                    </span>
                    <datalist id="medecin-options">
                        @foreach($medecinOptions as $opt)<option value="{{ $opt->CodMed }} — {{ $opt->Chirurgien }}">@endforeach
                    </datalist>
                </div>
                <div class="filter-field">
                    <label class="filter-label">Bloc</label>
                    <select name="bloc" style="min-width:180px">
                        <option value="">Tous les blocs</option>
                        @foreach($blocOptions as $opt)<option value="{{ $opt->CodBloc }}" @selected($bloc === $opt->CodBloc)>{{ $opt->LibBloc }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button>Filtrer</button>
                <button type="button" onclick="window.print()">Imprimer</button>
                <a href="{{ route('registry.export', request()->query()) }}" style="background:#16846a;color:#fff;text-decoration:none;border-radius:7px;padding:10px 14px;font:inherit;display:inline-block">Exporter Excel</a>
            </div>
        </form>
    </section>

    <section class="card"><table><thead><tr><th>Date acte</th><th>Heure planification</th><th>Patient</th><th>Acte / Équipe</th><th>Médecins</th><th>Compte rendu opératoire</th><th>Radiologie</th><th>Anapath</th></tr></thead><tbody>
    @forelse($actes as $acte)
        @php($equipe = $intervenants->get($acte->NumIntv.':'.$acte->NumDoss, collect()))
    <tr>
        <td>{{ \App\Support\Format::date($acte->DateActe) }}</td>
        <td>{{ \App\Support\Format::timeRange($acte->HDAnest, $acte->HFAnest) ?? 'Non renseigné' }}</td>
        <td>
            <strong>{{ $acte->Patient }}</strong><br>
            <span class="muted">Dossier {{ $acte->NumDoss }}</span><br>
            <span class="muted">{{ $acte->DesignationSalle ?? $acte->Salle ?? 'Salle non renseignée' }}</span>
        </td>
        <td>
            <strong>{{ $acte->LibelleActe }}</strong>
            @if($equipe->isNotEmpty())
                <br><span class="muted">
                    @foreach($equipe as $membre){{ $membre->DesInterv }} ({{ \App\Support\Format::role($membre->RoleIntervenant) }}){{ !$loop->last ? ', ' : '' }}@endforeach
                </span>
            @endif
        </td>
        <td><strong>Chirurgien :</strong> {{ $acte->Chirurgien ?? 'Non renseigné' }}<br><strong>Réanimateur :</strong> {{ $acte->Reanimateur ?? 'Non renseigné' }}</td>
        <td>@if($acte->StatutCompteRendu === 'VALIDE')<span class="badge status-valide">Validé</span><br><span class="muted">{{ \App\Support\Format::dateTime($acte->CompteRenduValideLe) }}</span>@elseif($acte->StatutCompteRendu === 'EN_COURS')<span class="badge status-soumis">Non validé</span>@else<span class="muted">Absent</span>@endif</td>
        <td>@php($patientRadios = $radios->get($acte->NumDoss, collect())) @if($patientRadios->isNotEmpty())<details><summary><span class="badge status-valide">{{ $patientRadios->count() }} examen(s)</span></summary><table><thead><tr><th>Examen</th><th>Statut</th></tr></thead><tbody>@foreach($patientRadios as $radio)<tr><td>{{ $radio->Designation }}</td><td>{{ $radio->Statut }}</td></tr>@endforeach</tbody></table></details>@else<span class="muted">Aucun examen</span>@endif</td>
        <td>
            @if($acte->AnapathId && !$acte->AnapathAnnuleLe)
                <span class="badge status-valide">Demande existante</span>
                @if($acte->AnapathModifieLe)<span class="badge" style="background:#fff0c7;color:#805900" title="Modifiée le {{ \App\Support\Format::dateTime($acte->AnapathModifieLe) }}">Modifiée</span>@endif
                <br><a href="{{ route('registry.anapath.show', $acte->AnapathId) }}">Consulter</a> ·
                <a href="{{ route('registry.anapath.edit', $acte->AnapathId) }}">Modifier</a> ·
                <a href="{{ route('registry.anapath.print', $acte->AnapathId) }}" target="_blank">Imprimer</a>
            @elseif($acte->AnapathId && $acte->AnapathAnnuleLe)
                <span class="badge status-rejete">Demande annulée</span>
                <br><a href="{{ route('registry.anapath.show', $acte->AnapathId) }}">Voir (traçabilité)</a>
                <br><a class="button" href="{{ route('registry.anapath.create', ['acte' => $acte->NumIntv, 'num_doss' => $acte->NumDoss]) }}">Saisir une nouvelle demande</a>
            @else
                <a class="button" href="{{ route('registry.anapath.create', ['acte' => $acte->NumIntv, 'num_doss' => $acte->NumDoss]) }}">Saisir demande anapath</a>
            @endif
        </td>
    </tr>
    @empty<tr><td colspan="8" class="muted">Aucun acte trouvé pour ces critères.</td></tr>@endforelse
    </tbody></table></section>
</x-layouts.app>
