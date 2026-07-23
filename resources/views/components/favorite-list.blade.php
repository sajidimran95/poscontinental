@props([
    'favorites' => [],
    'active' => null,
    'levels' => [],
    'nodes' => null,
])

@php
    if (is_array($nodes) && $nodes !== []) {
        $rows = $nodes;
    } else {
        $rows = [];
        foreach ($favorites as $key => $label) {
            $rows[] = [
                'type' => 'item',
                'key' => $key,
                'label' => ltrim((string) $label),
                'level' => (int) ($levels[$key] ?? 0),
                'kind' => null,
            ];
        }
    }
@endphp

<aside {{ $attributes->merge(['class' => 'desk-sidebar']) }} aria-label="Favorite Lists">
    <div class="desk-sidebar-head" id="favorite-lists-heading">Favorite Lists</div>
    <ul class="desk-sidebar-list" role="list" aria-labelledby="favorite-lists-heading">
        @foreach ($rows as $row)
            @if (($row['type'] ?? 'item') === 'heading')
                <li class="desk-sidebar-row desk-sidebar-heading-row" role="presentation">
                    <div class="desk-sidebar-heading">{{ $row['label'] }}</div>
                </li>
            @else
                @php
                    $key = $row['key'] ?? '';
                    $level = max(0, min(3, (int) ($row['level'] ?? 0)));
                    $kind = $row['kind'] ?? null;
                    $label = $row['label'] ?? '';
                @endphp
                <li class="desk-sidebar-row">
                    <button
                        type="button"
                        wire:click="$set('favorite', '{{ $key }}')"
                        aria-current="{{ $active === $key ? 'true' : 'false' }}"
                        title="{{ $kind ? $kind.': '.$label : $label }}"
                        @class([
                            'desk-sidebar-item',
                            'is-active' => $active === $key,
                            'desk-sidebar-level-'.$level => $level > 0,
                            'desk-sidebar-kind-'.strtolower((string) $kind) => filled($kind),
                        ])
                    >
                        @if ($level > 0)
                            <span class="desk-sidebar-branch" aria-hidden="true">{{ $level === 1 ? '├─' : '└─' }}</span>
                        @endif
                        <span class="desk-sidebar-label">{{ $label }}</span>
                        @if (filled($kind))
                            <span class="desk-sidebar-kind">{{ $kind }}</span>
                        @endif
                    </button>
                </li>
            @endif
        @endforeach
    </ul>
</aside>
