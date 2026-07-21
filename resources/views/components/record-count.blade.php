@props([
    'count' => 0,
    'note' => null,
])

<div {{ $attributes->merge(['class' => 'desk-footer']) }}>
    <span>{{ $note ?? ("Total record count: {$count}") }}</span>
    <div class="desk-footer-actions">{{ $slot }}</div>
</div>
