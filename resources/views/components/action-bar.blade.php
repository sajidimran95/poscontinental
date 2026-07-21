@props([
    'title' => 'Action',
    'variant' => 'blue',
])

<div
    {{ $attributes->class([
        'chief-action-bar',
        'chief-action-bar-green' => $variant === 'green',
    ]) }}
    role="toolbar"
    aria-label="{{ $title }} toolbar"
>
    <div class="flex items-center gap-1.5">
        <span class="chief-action-dots" aria-hidden="true" title="More">⋮</span>
        <span class="font-medium">{{ $title }}</span>
        {{ $slot }}
    </div>
    <div class="flex items-center gap-2">
        {{ $trailing ?? '' }}
    </div>
</div>
