@props([
    'hasMore' => false,
])
@if ($hasMore)
    <button
        type="button"
        class="desk-btn"
        wire:click="loadMoreList"
        wire:loading.attr="disabled"
        wire:target="loadMoreList"
        {{ $attributes }}
    >
        <span wire:loading.remove wire:target="loadMoreList">Load more</span>
        <span wire:loading wire:target="loadMoreList">Loading…</span>
    </button>
@endif
