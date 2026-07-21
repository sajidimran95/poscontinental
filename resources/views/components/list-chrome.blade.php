@props([
    'label' => 'Search',
    'placeholder' => '',
    'model' => 'search',
])

<div class="flex flex-wrap items-center gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
    <label class="text-sm text-slate-700 whitespace-nowrap">{{ $label }}</label>
    <input
        type="search"
        wire:model.live.debounce.300ms="{{ $model }}"
        placeholder="{{ $placeholder }}"
        class="chief-input w-64 max-w-full"
    />
    <button type="button" wire:click="$set('{{ $model }}', '')" class="chief-btn">New Search</button>
    {{ $slot }}
</div>
