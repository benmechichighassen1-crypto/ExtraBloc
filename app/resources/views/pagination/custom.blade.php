@if ($paginator->hasPages())
    <nav class="row" style="justify-content:space-between;flex-wrap:wrap;gap:14px" aria-label="Pagination">
        <span class="muted">
            Affichage de {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ $paginator->total() }} résultats
        </span>
        <span class="row" style="gap:5px;flex-wrap:wrap">
            {{-- Précédent --}}
            @if ($paginator->onFirstPage())
                <span class="muted" style="padding:6px 11px;border:1px solid #e6edf2;border-radius:7px">&laquo; Précédent</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" style="padding:6px 11px;border:1px solid #ccd8e1;border-radius:7px;text-decoration:none;color:#1779ba">&laquo; Précédent</a>
            @endif

            {{-- Numéros de page --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="muted" style="padding:6px 6px">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span style="padding:6px 11px;border-radius:7px;background:#1779ba;color:#fff;font-weight:700">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" style="padding:6px 11px;border:1px solid #ccd8e1;border-radius:7px;text-decoration:none;color:#1779ba">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Suivant --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" style="padding:6px 11px;border:1px solid #ccd8e1;border-radius:7px;text-decoration:none;color:#1779ba">Suivant &raquo;</a>
            @else
                <span class="muted" style="padding:6px 11px;border:1px solid #e6edf2;border-radius:7px">Suivant &raquo;</span>
            @endif
        </span>
    </nav>
@endif
