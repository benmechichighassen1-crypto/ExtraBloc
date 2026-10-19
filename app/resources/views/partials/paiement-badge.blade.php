{{-- Badge « Payé / Non payé » d'une déclaration (paiement = déclaration VALIDE réglée). --}}
@if((int) ($item->paye ?? 0) === 1)
    <span class="badge" style="background:#dff5e7;color:#12643e">Payé</span><br>
    <span class="muted">le {{ \App\Support\Format::date($item->paye_le) }}@if($item->paye_par_username ?? null) · {{ $item->paye_par_username }}@endif</span>
@elseif(($item->statut ?? null) === 'VALIDE')
    <span class="badge" style="background:#fff0c7;color:#805900">Non payé</span>
@else
    <span class="muted">—</span>
@endif
