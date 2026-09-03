@props([
    'catalog' => [],
    'keys' => [],
])
@foreach ($keys as $colKey)
    @php $col = $catalog[$colKey] ?? ['label' => $colKey, 'type' => 'text']; @endphp
    <x-desk-sort-th
        :field="$colKey"
        :label="$col['label'] ?? $colKey"
        resize
        :align="($col['type'] ?? '') === 'money' ? 'money' : (($col['type'] ?? '') === 'center' ? 'center' : 'left')"
    />
@endforeach
