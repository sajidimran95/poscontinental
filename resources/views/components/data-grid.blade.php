<div class="overflow-auto border border-slate-300">
    <table {{ $attributes->merge(['class' => 'min-w-full text-sm']) }}>
        @isset($head)
            <thead class="bg-slate-200 text-left sticky top-0">
                {{ $head }}
            </thead>
        @endisset
        <tbody class="bg-white divide-y divide-slate-200">
            {{ $slot }}
        </tbody>
    </table>
</div>
