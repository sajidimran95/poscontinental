@props([
    'tabs' => [],
    'active' => null,
    'model' => 'activeTab',
])

<div {{ $attributes->merge(['class' => 'so-mode-tabs']) }} role="tablist" aria-label="Form modes">
    @foreach ($tabs as $key => $label)
        <button
            type="button"
            role="tab"
            id="mode-tab-{{ $key }}"
            aria-selected="{{ $active === $key ? 'true' : 'false' }}"
            aria-controls="mode-panel-{{ $key }}"
            wire:click="$set('{{ $model }}', '{{ $key }}')"
            @class(['so-mode-tab', 'so-mode-tab-active' => $active === $key])
        >
            @if ($active === $key)
                <span class="so-mode-check" aria-hidden="true">●</span>
            @endif
            {{ $label }}
        </button>
    @endforeach
</div>
