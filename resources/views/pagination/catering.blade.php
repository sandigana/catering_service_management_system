{{-- resources/views/pagination/catering.blade.php : plain-HTML paginator (no Tailwind needed) --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="off">‹ Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="off">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="on">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
        @else
            <span class="off">Next ›</span>
        @endif

        <span class="pager-info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
    </nav>
@endif
