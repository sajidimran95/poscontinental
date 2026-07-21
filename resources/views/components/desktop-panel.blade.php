<div {{ $attributes->merge(['class' => 'border border-slate-400 bg-white shadow-sm']) }}>
    @if (isset($title) || isset($actions))
        <div class="flex items-center justify-between gap-2 border-b border-slate-300 bg-slate-100 px-3 py-2">
            <div class="font-semibold text-slate-800">{{ $title ?? '' }}</div>
            <div class="flex items-center gap-2">{{ $actions ?? '' }}</div>
        </div>
    @endif
    <div class="{{ $bodyClass ?? 'p-3' }}">
        {{ $slot }}
    </div>
</div>
