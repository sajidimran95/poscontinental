@if ($paginator->hasPages())
    <nav class="flex items-center justify-center gap-2 text-sm" role="navigation">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1.5 rounded-full text-slate-300 border border-slate-200">Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1.5 rounded-full border border-slate-200 hover:border-brand hover:text-brand bg-white">Prev</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-3 py-1.5 rounded-full bg-ink text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 rounded-full border border-slate-200 hover:border-brand hover:text-brand bg-white">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1.5 rounded-full border border-slate-200 hover:border-brand hover:text-brand bg-white">Next</a>
        @else
            <span class="px-3 py-1.5 rounded-full text-slate-300 border border-slate-200">Next</span>
        @endif
    </nav>
@endif
