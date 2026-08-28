@props([
    'paginator',
    'side' => 4,
])

@php
    $total = $paginator->total();
    $from = $paginator->firstItem();
    $to = $paginator->lastItem();
    $page = $paginator->currentPage();
    $last = $paginator->lastPage();
    $side = max(1, (int) $side);

    $pages = [];
    if ($last > 0) {
        $pages[] = 1;
        $start = max(2, $page - $side);
        $end = min($last - 1, $page + $side);
        if ($start > 2) {
            $pages[] = '...';
        }
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        if ($end < $last - 1) {
            $pages[] = '...';
        }
        if ($last > 1) {
            $pages[] = $last;
        }
    }
@endphp

<div {{ $attributes->class('desk-pager') }}>
    <span class="desk-pager-meta">
        @if ($total === 0)
            No records
        @else
            Showing {{ number_format($from) }}–{{ number_format($to) }} of {{ number_format($total) }}
        @endif
    </span>
    @if ($last > 1)
        <div class="desk-pager-nav" role="navigation" aria-label="Pagination">
            <button
                type="button"
                class="desk-btn desk-btn-sm desk-pager-num"
                wire:click="previousPage"
                @disabled($page <= 1)
                aria-label="Previous page"
            >&lt;</button>
            @foreach ($pages as $item)
                @if ($item === '...')
                    <span class="desk-pager-ellipsis">…</span>
                @elseif ((int) $item === $page)
                    <span class="desk-pager-num is-current" aria-current="page">{{ $item }}</span>
                @else
                    <button
                        type="button"
                        class="desk-btn desk-btn-sm desk-pager-num"
                        wire:click="gotoPage({{ (int) $item }})"
                    >{{ $item }}</button>
                @endif
            @endforeach
            <button
                type="button"
                class="desk-btn desk-btn-sm desk-pager-num"
                wire:click="nextPage"
                @disabled($page >= $last)
                aria-label="Next page"
            >&gt;</button>
        </div>
    @endif
</div>
