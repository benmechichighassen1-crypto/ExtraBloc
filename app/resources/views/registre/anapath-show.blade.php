<x-layouts.app>
    <section class="card" style="max-width:850px">
        <h1>Demande {{ $demande->numero_demande }}</h1>
        @if($demande->annule_le)
            <p class="error">Cette demande a été annulée le {{ \App\Support\Format::dateTime($demande->annule_le) }} par {{ $demande->annule_par_username }}.<br>Motif : {{ $demande->motif_annulation }}</p>
        @endif
        <p>
            <strong>Patient :</strong> {{ trim(($demande->patient_nom ?? '').' '.($demande->patient_prenom ?? '')) ?: 'Non renseigné' }}
            @if($demande->patient_date_naissance) — né(e) le {{ \App\Support\Format::date($demande->patient_date_naissance) }} @endif
            @if($demande->patient_sexe) ({{ $demande->patient_sexe }}) @endif
            <br><strong>Dossier :</strong> {{ $demande->num_doss }}<br>
            <strong>Médecin prescripteur :</strong> {{ $demande->medecin_prescripteur }}<br>
            <strong>Prélèvement :</strong> {{ $demande->nature_prelevement }} — {{ $demande->site_anatomique }}<br>
            <strong>Flacons :</strong> {{ $demande->nombre_flacons }} · <strong>Urgent :</strong> {{ $demande->urgence ? 'Oui' : 'Non' }}
        </p>
        <p><strong>Renseignements cliniques</strong><br>{!! nl2br(e($demande->renseignements_cliniques)) !!}</p>
        <p class="muted">
            Saisie par {{ $demande->cree_par_username }}, le {{ \App\Support\Format::dateTime($demande->created_at) }}.
            @if($demande->modifie_le)<br>Modifiée par {{ $demande->modifie_par_username }}, le {{ \App\Support\Format::dateTime($demande->modifie_le) }}.@endif
        </p>

        <div class="row">
            @if($peutModifier)
                <a href="{{ route('registry.anapath.edit', $demande->id) }}" class="button">Modifier</a>
            @endif
            <a href="{{ route('registry.anapath.print', $demande->id) }}" target="_blank"><button>Imprimer avec en-tête</button></a>
            <a href="{{ route('registry.anapath.print', ['demande' => $demande->id, 'entete' => 0]) }}" target="_blank"><button class="btn-outline">Imprimer sans en-tête</button></a>
        </div>

        <h2 style="margin-top:24px">Traçabilité</h2>
        <table><thead><tr><th>Action</th><th>Utilisateur</th><th>Date</th></tr></thead><tbody>
        @foreach($historique as $ligne)
            <tr><td>{{ $ligne->action }}</td><td>{{ $ligne->acteur_username }}</td><td>{{ \App\Support\Format::dateTime($ligne->created_at) }}</td></tr>
        @endforeach
        </tbody></table>
    </section>
</x-layouts.app>
