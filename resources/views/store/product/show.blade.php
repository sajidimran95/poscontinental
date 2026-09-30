@extends('store.layouts.store')
@section('title', $product->name.' | '.$shop['name'])
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($product->description ?? ''), 160))

@section('content')
@php
    $price = $product->price;
    $compare = $product->compare;
    $onSale = $compare && $compare > $price;
@endphp

<div class="max-w-[1400px] mx-auto px-4 py-8 md:py-10">
    <nav class="text-xs text-black/40 mb-5 flex flex-wrap gap-2">
        <a href="{{ url('/') }}" class="hover:text-brand">Home</a>
        <span>/</span>
        <a href="{{ route('ecommerce.shop') }}" class="hover:text-brand">Shop</a>
        @if($product->category_id)
            <span>/</span>
            <a href="{{ route('ecommerce.category', $product->category_id) }}" class="hover:text-brand">{{ $product->category_name }}</a>
        @endif
        <span>/</span>
        <span class="text-ink">{{ $product->name }}</span>
    </nav>

    <div class="grid lg:grid-cols-2 gap-8 lg:gap-12">
        <div>
            <div class="rounded-xl overflow-hidden bg-white border border-line aspect-square relative">
                <img id="mainImage" src="{{ $product->image_url }}" alt="{{ $product->name }}" class="absolute inset-0 w-full h-full object-cover"
                     onerror="this.onerror=null;this.src='{{ asset('img/default.png') }}';">
                @if($product->promo_badge)
                    <span class="absolute top-3 left-3 rounded bg-brand text-white text-[10px] font-bold uppercase px-2.5 py-1">{{ $product->promo_badge }}</span>
                @elseif($product->is_new)
                    <span class="absolute top-3 left-3 rounded bg-brand text-white text-[10px] font-bold uppercase px-2.5 py-1">New</span>
                @elseif($onSale)
                    <span class="absolute top-3 left-3 rounded bg-brand text-white text-[10px] font-bold uppercase px-2.5 py-1">Sale</span>
                @endif
            </div>
        </div>

        <div>
            @if($product->brand)
                <p class="text-[11px] uppercase tracking-[0.18em] text-black/40 font-bold mb-2">{{ $product->brand }}</p>
            @endif
            <h1 class="gc-serif text-3xl md:text-4xl leading-tight mb-2">{{ $product->name }}</h1>
            <p class="text-sm text-black/45 mb-4">SKU {{ $product->sku }}@if($product->upc) · UPC {{ $product->upc }}@endif</p>

            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-3xl font-extrabold text-ink tabular-nums">${{ number_format($price, 2) }}</span>
                <span class="text-xs font-semibold text-black/45 uppercase">{{ $customer_pricing ? 'your price' : 'wholesale' }}</span>
                @if($onSale)
                    <span class="text-lg text-black/35 line-through tabular-nums">${{ number_format($compare, 2) }}</span>
                @endif
            </div>
            <div class="gc-price-box text-sm mb-5">
                <p class="text-black/60">{{ $product->pack }}@if(! $customer_pricing) · <a href="{{ route('ecommerce.login') }}" class="font-semibold text-brand">Log in</a> for your account pricing @endif</p>
            </div>

            <p class="text-sm mb-4 {{ $product->in_stock ? 'text-emerald-700' : 'text-rose-600' }}">
                {{ $product->in_stock ? '● In stock — ready to ship' : 'Out of stock' }}
            </p>

            @if($product->short_description)
                <p class="text-black/65 leading-relaxed mb-6">{{ $product->short_description }}</p>
            @endif

            @if($product->can_order)
            <div class="mb-2">
                <div class="text-sm font-medium text-ink">Unit: {{ $product->uom ?: 'Each' }}</div>
                <div class="mt-2 border-t border-line"></div>
            </div>
            <form action="{{ route('ecommerce.cart.add', absolute: false) }}" method="post" class="flex flex-wrap items-center gap-3 mb-5 mt-4" id="addToCartForm">
                @csrf
                <input type="hidden" name="item_id" value="{{ $product->id }}">

                <div class="inline-flex items-center gap-2 rounded-full bg-[#f0f0f0] px-1.5 py-1.5">
                    <button type="button" id="qtyMinus"
                            class="h-9 w-9 rounded-full bg-white border border-[#ddd] text-ink text-lg leading-none flex items-center justify-center hover:bg-mist"
                            aria-label="Decrease quantity">−</button>
                    <input type="number" name="quantity" id="qtyInput" value="1" min="1" step="1"
                           class="w-10 bg-transparent border-0 text-center text-sm font-medium tabular-nums outline-none appearance-none [-moz-appearance:textfield]">
                    <button type="button" id="qtyPlus"
                            class="h-9 w-9 rounded-full bg-white border border-[#ddd] text-ink text-lg leading-none flex items-center justify-center hover:bg-mist"
                            aria-label="Increase quantity">+</button>
                </div>

                <button type="submit" class="rounded-lg bg-[#e53935] text-white px-8 py-3 text-sm font-semibold hover:bg-[#c62828] transition">
                    Add to Cart
                </button>
                <button type="submit" name="buy_now" value="1" class="rounded-lg bg-[#c62828] text-white px-6 py-3 text-sm font-semibold hover:bg-[#3b0d0d] transition">
                    Buy Now
                </button>
            </form>
            @endif

            @auth('customer')
            <form action="{{ route('ecommerce.wishlist.toggle', absolute: false) }}" method="post" class="mb-6">
                @csrf
                <input type="hidden" name="item_id" value="{{ $product->id }}">
                <button class="text-sm font-bold text-brand">{{ $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist' }}</button>
            </form>
            @endauth

            @if($product->description)
                <div class="rounded-xl bg-white border border-line p-5 text-sm text-black/70 leading-relaxed whitespace-pre-line">{{ $product->description }}</div>
            @endif
        </div>
    </div>
</div>

@if($related->count())
<section class="max-w-[1400px] mx-auto px-4 pb-10">
    <h2 class="text-2xl font-extrabold mb-5">You may also like</h2>
    @include('store.partials.product_grid', ['products' => $related])
</section>
@endif

@if($recently_viewed->count())
<section class="max-w-[1400px] mx-auto px-4 pb-14">
    <h2 class="text-2xl font-extrabold mb-5">Recently viewed</h2>
    @include('store.partials.product_grid', ['products' => $recently_viewed])
</section>
@endif

@push('head')
<style>
#qtyInput::-webkit-outer-spin-button,
#qtyInput::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#qtyInput { -moz-appearance: textfield; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const input = document.getElementById('qtyInput');
    const minus = document.getElementById('qtyMinus');
    const plus = document.getElementById('qtyPlus');
    if (!input || !minus || !plus) return;

    function setQty(v) {
        const n = Math.max(1, parseInt(v, 10) || 1);
        input.value = n;
    }

    minus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) - 1));
    plus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) + 1));
    input.addEventListener('change', () => setQty(input.value));
})();
</script>
@endpush
@endsection
