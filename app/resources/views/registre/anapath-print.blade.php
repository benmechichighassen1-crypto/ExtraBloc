<!doctype html><html lang="fr"><meta charset="utf-8"><title>{{ $demande->numero_demande }}</title>
<style>
    body{font:14px Arial;margin:35px}
    h1{text-align:center;font-size:20px}
    table{width:100%;border-collapse:collapse;margin-top:25px}
    td{border:1px solid #222;padding:10px;vertical-align:top}
    .footer{position:fixed;bottom:20px;font-size:11px;color:#555;width:90%;border-top:1px solid #999;padding-top:8px}
    .entete{text-align:center;border-bottom:2px solid #123454;padding-bottom:14px;margin-bottom:20px}
    .entete img{max-width:3.5cm;max-height:2.2cm;object-fit:contain;margin-bottom:6px}
    .entete .nom-clinique{font-size:18px;font-weight:bold;color:#123454}
    .entete .adresse{font-size:12px;color:#555}
    .espace-entete{height:4cm}
    .annulee{background:#fee1e1;color:#9d2424;text-align:center;padding:8px;font-weight:bold;margin-bottom:14px}
    @page { size: auto; margin: 1.2cm }
</style>
<body>

@if($entete)
    <div class="entete">
        <img src="{{ asset('images/logo-clinique.jpeg') }}" alt="Logo Clinique Internationale Ibn Sina Tanger">
        <div class="nom-clinique">Clinique Internationale Ibn Sina Tanger</div>
        <div class="adresse">Service d'Anatomopathologie</div>
    </div>
@else
    <div class="espace-entete"></div>
@endif

@if($demande->annule_le)
    <div class="annulee">DEMANDE ANNULÉE le {{ \App\Support\Format::dateTime($demande->annule_le) }} par {{ $demande->annule_par_username }}<br>Motif : {{ $demande->motif_annulation }}</div>
@endif

<h1>DEMANDE D'EXAMEN ANATOMOPATHOLOGIQUE</h1>
<p><strong>N° demande :</strong> {{ $demande->numero_demande }}<br><strong>N° dossier :</strong> {{ $demande->num_doss }}</p>

<table>
    <tr>
        <td><strong>Patient</strong><br>{{ trim(($demande->patient_nom ?? '').' '.($demande->patient_prenom ?? '')) ?: 'Non renseigné' }}</td>
        <td><strong>Date de naissance / Sexe</strong><br>{{ \App\Support\Format::date($demande->patient_date_naissance) ?? 'Non renseignée' }} / {{ $demande->patient_sexe ?? 'Non renseigné' }}</td>
    </tr>
    <tr>
        <td><strong>Médecin prescripteur</strong><br>{{ $demande->medecin_prescripteur }}</td>
        <td></td>
    </tr>
    <tr>
        <td><strong>Nature du prélèvement</strong><br>{{ $demande->nature_prelevement }}</td>
        <td><strong>Site anatomique</strong><br>{{ $demande->site_anatomique }}</td>
    </tr>
    <tr>
        <td><strong>Nombre de flacons</strong><br>{{ $demande->nombre_flacons }}</td>
        <td><strong>Fixateur / urgence</strong><br>{{ $demande->fixateur }} / {{ $demande->urgence ? 'URGENT' : 'Normal' }}</td>
    </tr>
    <tr><td colspan="2"><strong>Renseignements cliniques</strong><br>{!! nl2br(e($demande->renseignements_cliniques)) !!}</td></tr>
</table>

<div class="footer">
    Document généré par Registre Bloc — saisi par {{ $demande->cree_par_username }} le {{ \App\Support\Format::dateTime($demande->created_at) }}.
    @if($demande->modifie_le) · Modifié par {{ $demande->modifie_par_username }} le {{ \App\Support\Format::dateTime($demande->modifie_le) }} @endif
</div>

<script>
    // Impression directe ; ?entete=0 en query string pour imprimer sans en-tête.
    window.print();
</script>
</body></html>
