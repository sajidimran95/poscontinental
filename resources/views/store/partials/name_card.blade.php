@php
    $words = preg_split('/[^\pL\pN]+/u', (string) $name, -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(collect($words)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') ?: '#');
    $variant = $variant ?? 'light';
@endphp
<a href="{{ $href }}" class="gc-name-card gc-name-card--{{ $variant }}">
    <span class="gc-name-card__mark" aria-hidden="true">{{ $initials }}</span>
    <span class="gc-name-card__top">
        <span class="gc-name-card__badge">{{ $initials }}</span>
        @if(! empty($tag))
            <span class="gc-name-card__tag">{{ $tag }}</span>
        @endif
    </span>
    <span class="gc-name-card__name">{{ $name }}</span>
    @if(! empty($sub))
        <span class="gc-name-card__count">{{ $sub }}</span>
    @endif
    @if(isset($count))
        <span class="gc-name-card__count">{{ number_format((int) $count) }} products</span>
    @endif
    <span class="gc-name-card__cta">Shop now <span aria-hidden="true">→</span></span>
</a>
