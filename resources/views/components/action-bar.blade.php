@props([
    'title' => 'Action',
    'variant' => 'blue',
])

<div {{ $attributes->class([
    'chief-action-bar',
    'chief-action-bar-green' => $variant === 'green',
]) }}>
    <div class="flex items-center gap-2">
        <span class="font-medium">{{ $title }}</span>
        <span class="opacity-70 text-xs">▾</span>
        {{ $slot }}
    </div>
    <div class="flex items-center gap-2">
        {{ $trailing ?? '' }}
    </div>
</div>
