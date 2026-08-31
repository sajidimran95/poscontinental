@props([
    'label',
    'kbd' => null,
    'disabled' => false,
    'sep' => false,
])

@if ($sep)
    <div class="chief-action-sep" role="separator"></div>
@endif
<button
    type="button"
    role="menuitem"
    @disabled($disabled)
    @if ($kbd)
        data-kbd="{{ strtolower(str_replace('Ctrl+', '', $kbd)) }}"
    @endif
    {{ $attributes->class(['chief-action-item', 'is-disabled' => $disabled]) }}
>
    <span>{{ $label }}</span>
    @if ($kbd)
        <span class="kbd">{{ $kbd }}</span>
    @endif
</button>
