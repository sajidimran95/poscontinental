@props([
    'show' => false,
    'title' => 'Lookup',
])

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:keydown.escape.window="$dispatch('close-lookup')">
        <div class="w-full max-w-3xl border border-slate-500 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="{{ $title }}">
            <div class="flex items-center justify-between bg-slate-700 px-3 py-2 text-white">
                <h2 class="font-semibold">{{ $title }} <span class="text-xs font-normal text-slate-300">(F2)</span></h2>
                <button type="button" class="px-2 hover:bg-slate-600" wire:click="$dispatch('close-lookup')" aria-label="Close">×</button>
            </div>
            <div class="p-3 max-h-[70vh] overflow-auto">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
