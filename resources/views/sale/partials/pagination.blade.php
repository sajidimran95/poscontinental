@if ($paginator->hasPages())
    <nav class="sale-pager" role="navigation" aria-label="Pagination">
        <div class="sale-pager__meta">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </div>
        <div class="sale-pager__btns">
            @if ($paginator->onFirstPage())
                <span class="sale-pager__btn sale-pager__btn--disabled" aria-disabled="true">‹ Prev</span>
            @else
                <a class="sale-pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="sale-pager__btn sale-pager__btn--disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sale-pager__btn sale-pager__btn--active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="sale-pager__btn" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="sale-pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
            @else
                <span class="sale-pager__btn sale-pager__btn--disabled" aria-disabled="true">Next ›</span>
            @endif
        </div>
    </nav>
@endif
