@props([
    'paginator',
])

@php
    $total = $paginator->total();
    $from = $paginator->firstItem();
    $to = $paginator->lastItem();
    $page = $paginator->currentPage();
    $last = $paginator->lastPage();
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
                class="desk-btn desk-btn-sm"
                wire:click="gotoPage(1)"
                @disabled($page <= 1)
            >First</button>
            <button
                type="button"
                class="desk-btn desk-btn-sm"
                wire:click="previousPage"
                @disabled($page <= 1)
            >Previous</button>
            <label class="desk-pager-jump">
                Page
                <input
                    type="number"
                    min="1"
                    max="{{ $last }}"
                    value="{{ $page }}"
                    class="desk-pager-input"
                    wire:keydown.enter="gotoPage($event.target.value)"
                    wire:blur="gotoPage($event.target.value)"
                    aria-label="Go to page"
                />
                of {{ number_format($last) }}
            </label>
            <button
                type="button"
                class="desk-btn desk-btn-sm"
                wire:click="nextPage"
                @disabled($page >= $last)
            >Next</button>
            <button
                type="button"
                class="desk-btn desk-btn-sm"
                wire:click="gotoPage({{ $last }})"
                @disabled($page >= $last)
            >Last</button>
        </div>
    @endif
</div>
