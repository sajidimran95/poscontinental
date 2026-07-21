@props([
    'favorites' => [],
    'active' => null,
])

<aside {{ $attributes->merge(['class' => 'desk-sidebar']) }} aria-label="Favorite Lists">
    <div class="desk-sidebar-head" id="favorite-lists-heading">Favorite Lists</div>
    <ul class="desk-sidebar-list" role="list" aria-labelledby="favorite-lists-heading">
        @foreach ($favorites as $key => $label)
            <li>
                <button
                    type="button"
                    wire:click="$set('favorite', '{{ $key }}')"
                    aria-current="{{ $active === $key ? 'true' : 'false' }}"
                    @class([
                        'desk-sidebar-item',
                        'is-active' => $active === $key,
                    ])
                >
                    {{ $label }}
                </button>
            </li>
        @endforeach
    </ul>
</aside>
