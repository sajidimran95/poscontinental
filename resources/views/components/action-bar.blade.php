@props([
    'title' => 'Action',
    'variant' => 'blue',
])

@php
    $hasMenu = isset($menu);
@endphp

<div
    {{ $attributes->class([
        'chief-action-bar',
        'chief-action-bar-green' => $variant === 'green',
        'has-action-menu' => $hasMenu,
    ]) }}
    role="toolbar"
    aria-label="{{ $title }} toolbar"
    @if ($hasMenu)
        x-data="{ open: false }"
        x-on:keydown.escape.window="open = false"
        x-on:click.outside="open = false"
        x-on:keydown.window="
            if (!$event.ctrlKey || $event.altKey || $event.metaKey) return;
            const k = ($event.key || '').toLowerCase();
            const tag = (document.activeElement && document.activeElement.tagName) || '';
            const typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (document.activeElement && document.activeElement.isContentEditable);
            if (typing && !['s', 'q', 'p'].includes(k)) return;
            const btn = $el.querySelector('.chief-action-menu [data-kbd=\'' + k + '\']');
            if (!btn || btn.disabled) return;
            $event.preventDefault();
            open = false;
            btn.click();
        "
    @endif
>
    <div class="chief-action-lead">
        @if ($hasMenu)
            <button
                type="button"
                class="chief-action-trigger"
                @click="open = !open"
                :aria-expanded="open"
                aria-haspopup="menu"
            >
                <span class="chief-action-dots" aria-hidden="true">⋮</span>
                <span class="font-medium">Action</span>
            </button>
            <div
                class="chief-action-menu"
                x-show="open"
                x-cloak
                x-transition.opacity.duration.100ms
                role="menu"
                @click="open = false"
            >
                {{ $menu }}
            </div>
        @else
            <span class="chief-action-dots" aria-hidden="true" title="More">⋮</span>
            <span class="font-medium">Action</span>
            {{ $slot }}
        @endif
    </div>
    <div class="flex items-center gap-2">
        {{ $trailing ?? '' }}
    </div>
</div>
