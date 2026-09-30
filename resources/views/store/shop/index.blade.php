@extends('store.layouts.store')
@section('title', 'Shop')
@section('content')
@php
    $filterCount = 0;
    if (request()->filled('category')) $filterCount++;
    if (request()->filled('sub')) $filterCount++;
    if (request()->boolean('new')) $filterCount++;
    if (request()->boolean('in_stock')) $filterCount++;
    if (request()->filled('brand')) $filterCount++;
    if (request()->filled('min_price')) $filterCount++;
    if (request()->filled('max_price')) $filterCount++;
    if (request()->filled('sort') && request('sort') !== 'newest') $filterCount++;
@endphp

<section class="bg-white border-b border-line">
    <div class="max-w-[1400px] mx-auto px-4 py-5 md:py-10 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-[11px] uppercase tracking-[0.18em] text-black/40 font-bold mb-1 hidden md:block">Wholesale catalog</p>
            <h1 class="gc-serif text-3xl md:text-5xl">{{ $heading }}</h1>
            @if(! empty($heading_sub))
                <p class="text-base text-black/70 mt-1 md:mt-2 font-semibold">{{ $heading_sub }}</p>
            @endif
            <p class="text-sm text-black/50 mt-1 md:mt-2">{{ $products->total() }} products · live POS inventory</p>
        </div>
    </div>
    @if(is_array($promotion_cards ?? null))
        <div class="max-w-[1400px] mx-auto px-4 pb-6 md:pb-8">
            @if(count($promotion_cards))
                <div class="flex flex-wrap gap-2 mb-4 text-sm font-semibold">
                    <a href="{{ route('ecommerce.promotions') }}" @class(['rounded-full px-4 py-1.5 border', 'bg-[#b71c1c] text-white border-[#b71c1c]' => ! $active_promotion_id, 'border-line text-black/70 hover:border-[#e53935]' => $active_promotion_id])>All deals</a>
                    @foreach($promotion_cards as $p)
                        <a href="{{ $p['href'] }}" @class(['rounded-full px-4 py-1.5 border', 'bg-[#b71c1c] text-white border-[#b71c1c]' => $active_promotion_id === $p['id'], 'border-line text-black/70 hover:border-[#e53935]' => $active_promotion_id !== $p['id']])>{{ $p['title'] }}</a>
                    @endforeach
                </div>
                @unless($active_promotion_id)
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        @foreach($promotion_cards as $p)
                            @include('store.partials.name_card', ['href' => $p['href'], 'name' => $p['title'], 'sub' => $p['sub'], 'count' => $p['count'], 'tag' => $p['tag'], 'variant' => 'red'])
                        @endforeach
                    </div>
                @endunless
            @else
                <div class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-black/55">
                    No promotions are running right now — check back soon or <a href="{{ route('ecommerce.shop') }}" class="font-semibold text-[#b71c1c]">browse the full catalog</a>.
                </div>
            @endif
        </div>
    @endif
</section>

{{-- Mobile app-style filter trigger (shop only) --}}
<div class="lg:hidden sticky top-[calc(3.5rem+env(safe-area-inset-top,0px))] z-40 bg-white/95 backdrop-blur border-b border-line">
    <div class="px-3 py-2.5 flex items-center gap-2">
        <button type="button" id="openShopFilter" class="flex-1 h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-ink text-white font-bold text-sm active:opacity-90">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M7 12h10M10 18h4"/>
            </svg>
            Filter
            @if($filterCount > 0)
                <span class="min-w-[20px] h-5 px-1.5 rounded-full bg-brand text-[11px] font-extrabold inline-flex items-center justify-center">{{ $filterCount }}</span>
            @endif
        </button>
        <form method="get" action="{{ url()->current() }}" class="flex-1 min-w-0">
            @foreach(request()->except(['sort', 'page']) as $key => $value)
                @if(is_scalar($value) && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <select name="sort" class="w-full h-11 rounded-xl border border-line bg-mist px-3 text-sm font-semibold outline-none" onchange="this.form.submit()">
                <option value="newest" @selected(($sort ?? request('sort', 'newest')) === 'newest')>Newest</option>
                <option value="price_asc" @selected(($sort ?? '') === 'price_asc')>Price ↑</option>
                <option value="price_desc" @selected(($sort ?? '') === 'price_desc')>Price ↓</option>
            </select>
        </form>
    </div>
</div>

<div class="max-w-[1400px] mx-auto px-3 sm:px-4 py-5 md:py-8">
    <div class="grid lg:grid-cols-12 gap-6">
        {{-- Desktop sidebar filters --}}
        <aside class="hidden lg:block lg:col-span-3">
            <form method="get" class="rounded-xl bg-white border border-line p-5 space-y-4 sticky top-28 text-sm">
                @include('store.shop.partials.filter_fields', ['sort' => $sort ?? request('sort', 'newest')])
                <button type="submit" class="w-full btn-brand">Apply filters</button>
            </form>
        </aside>

        <div class="lg:col-span-9">
            @include('store.partials.product_grid', ['products' => $products])
            <div class="mt-8">
                {{ $products->links('store.partials.pagination') }}
            </div>
        </div>
    </div>
</div>

{{-- Mobile filter bottom sheet --}}
<div id="shopFilterSheet" class="app-sheet app-mobile-only" aria-hidden="true">
    <div class="app-sheet__backdrop" data-close-shop-filter></div>
    <div class="app-sheet__panel" role="dialog" aria-label="Filters">
        <div class="app-sheet__handle"></div>
        <div class="px-4 pb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-extrabold">Filters</h2>
                <button type="button" class="h-9 w-9 rounded-full bg-mist inline-flex items-center justify-center text-lg" data-close-shop-filter aria-label="Close">✕</button>
            </div>
            <form method="get" class="space-y-4 text-sm">
                @include('store.shop.partials.filter_fields', ['sort' => $sort ?? request('sort', 'newest')])
                <div class="grid grid-cols-2 gap-2 pt-1 sticky bottom-0 bg-white pb-[env(safe-area-inset-bottom,0)]">
                    <a href="{{ route('ecommerce.shop') }}" class="h-12 inline-flex items-center justify-center rounded-xl border border-line font-bold text-sm bg-mist">Clear</a>
                    <button type="submit" class="h-12 btn-brand rounded-xl">Apply filters</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sheet = document.getElementById('shopFilterSheet');
    const openBtn = document.getElementById('openShopFilter');
    if (!sheet || !openBtn) return;

    const open = () => {
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    const close = () => {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    openBtn.addEventListener('click', open);
    sheet.querySelectorAll('[data-close-shop-filter]').forEach((el) => {
        el.addEventListener('click', close);
    });
})();
</script>
@endpush
