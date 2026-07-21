@props([
    'label' => 'Search',
    'placeholder' => '',
    'model' => 'search',
])

<div {{ $attributes->merge(['class' => 'desk-toolbar']) }}>
    <label class="desk-toolbar-label">{{ $label }}</label>
    <input
        type="search"
        wire:model.live.debounce.300ms="{{ $model }}"
        placeholder="{{ $placeholder }}"
        class="desk-search"
        aria-label="{{ $label }}"
    />
    <button type="button" wire:click="$set('{{ $model }}', '')" class="desk-btn">Clear</button>
    {{ $slot }}
</div>
