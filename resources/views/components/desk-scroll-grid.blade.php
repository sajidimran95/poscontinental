@props([
    'hasMore' => false,
])
<div
    {{ $attributes->merge(['class' => 'desk-grid']) }}
    x-data="{ loadingMore: false }"
    @scroll="
        if (loadingMore || ! {{ $hasMore ? 'true' : 'false' }}) return;
        const el = $event.target;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 140) {
            loadingMore = true;
            $wire.loadMoreList().finally(() => loadingMore = false);
        }
    "
>
    {{ $slot }}
</div>
