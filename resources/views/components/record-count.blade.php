@props([
    'count' => 0,
    'note' => null,
])

<div class="flex items-center justify-between px-2 py-1 text-xs text-slate-600 border-t border-slate-300 bg-slate-50">
    <span>{{ $note ?? ("Total record count: {$count}") }}</span>
    <div>{{ $slot }}</div>
</div>
