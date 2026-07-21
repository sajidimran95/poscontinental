@props([
    'tabs' => [],
    'active' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col h-full']) }}>
    <div class="flex-1 border border-slate-400 border-b-0 bg-white p-3 overflow-auto">
        {{ $slot }}
    </div>
    <div class="flex border border-slate-400 bg-slate-100">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                wire:click="$set('activeTab', '{{ $key }}')"
                @class([
                    'px-4 py-1.5 text-sm border-r border-slate-300',
                    'bg-white font-semibold text-sky-800' => $active === $key,
                    'text-slate-600 hover:bg-slate-200' => $active !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
