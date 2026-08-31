@props([
    'field',
    'label',
    'align' => 'left',
    'resize' => false,
])
@php
    $active = ($sortField ?? '') === $field;
    $dir = $sortDir ?? 'asc';
@endphp
<th
    data-col="{{ $field }}"
    {{ $attributes->class([
        'desk-sort-th',
        'is-sorted' => $active,
        'text-right' => $align === 'right',
        'text-center' => $align === 'center',
        'desk-money' => $align === 'money',
    ]) }}
>
    <button type="button" class="desk-sort-btn" wire:click="sortBy({{ \Illuminate\Support\Js::from($field) }})" title="Sort by {{ $label }}">
        <span>{{ $label }}</span>
        <span class="desk-sort-ico" aria-hidden="true">
            @if ($active && $dir === 'asc')
                ▲
            @elseif ($active && $dir === 'desc')
                ▼
            @else
                ⇅
            @endif
        </span>
    </button>
    @if ($resize)
        <span class="desk-col-resizer" title="Drag to make this column wider or narrower" aria-hidden="true"></span>
    @endif
</th>
