@props([
    'favorites' => [],
    'active' => null,
])

<aside {{ $attributes->merge(['class' => 'w-44 shrink-0 border border-slate-400 bg-slate-50']) }} aria-label="Favorite Lists">
    <div class="bg-slate-200 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600" id="favorite-lists-heading">Favorite Lists</div>
    <ul class="text-sm" role="list" aria-labelledby="favorite-lists-heading">
        @foreach ($favorites as $key => $label)
            <li>
                <button
                    type="button"
                    wire:click="$set('favorite', '{{ $key }}')"
                    aria-current="{{ $active === $key ? 'true' : 'false' }}"
                    @class([
                        'w-full text-left px-2 py-1.5 border-b border-slate-200',
                        'bg-sky-100 font-medium text-sky-900' => $active === $key,
                        'hover:bg-slate-100' => $active !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            </li>
        @endforeach
    </ul>
</aside>
