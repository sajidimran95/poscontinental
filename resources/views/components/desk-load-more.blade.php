@props([
    'hasMore' => false,
])
@if ($hasMore)
    <div class="desk-load-more-wrap" style="display:flex;justify-content:center;padding:0.35rem 0 0.55rem;gap:0.5rem;align-items:center;">
        <span
            class="desk-title-meta"
            wire:loading
            wire:target="loadMoreList"
            style="font-size:11px;color:#64748b;"
        >Loading more…</span>
        <button
            type="button"
            class="desk-btn"
            wire:click="loadMoreList"
            wire:loading.attr="disabled"
            wire:target="loadMoreList"
            {{ $attributes }}
            style="opacity:0.85;"
        >
            <span wire:loading.remove wire:target="loadMoreList">Load more</span>
            <span wire:loading wire:target="loadMoreList">Loading…</span>
        </button>
    </div>
@endif
