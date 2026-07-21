@props([
    'width' => 'max-content',
])

<div {{ $attributes->merge(['class' => 'desktop-form']) }} style="width: {{ $width }}; max-width: 100%;">
    {{ $slot }}
</div>
