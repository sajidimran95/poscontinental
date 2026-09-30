@php
    $products = $products ?? collect();
    $gridClass = $gridClass ?? 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4';
@endphp
<div class="{{ $gridClass }}">
    @forelse($products as $product)
        @php
            $onSale = $product->compare && $product->compare > $product->price;
        @endphp
        <article class="gc-card product-card flex flex-col bg-white">
            <a href="{{ route('ecommerce.product', $product->slug) }}" class="block relative aspect-[4/3] bg-mist overflow-hidden">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"
                     class="w-full h-full object-cover"
                     onerror="this.onerror=null;this.src='{{ asset('img/default.png') }}';">
                @if($product->promo_badge)
                    <span class="absolute top-2 left-2 rounded bg-[#b71c1c] text-white text-[10px] font-bold uppercase px-2 py-1 shadow">{{ $product->promo_badge }}</span>
                @elseif($product->is_new)
                    <span class="absolute top-2 left-2 rounded bg-[#e53935] text-white text-[10px] font-bold uppercase px-2 py-1">New</span>
                @elseif($onSale)
                    <span class="absolute top-2 left-2 rounded bg-[#b71c1c] text-white text-[10px] font-bold uppercase px-2 py-1">Sale</span>
                @endif
            </a>
            <div class="p-3 md:p-3.5 flex flex-col flex-1">
                @if($product->brand)
                    <a href="{{ route('ecommerce.shop', ['brand' => $product->brand]) }}" class="text-[11px] font-bold tracking-wide text-[#b71c1c] uppercase">{{ $product->brand }}</a>
                @endif
                <a href="{{ route('ecommerce.product', $product->slug) }}" class="font-semibold text-sm leading-snug line-clamp-2 min-h-[2.5rem] mt-0.5 hover:text-brand">
                    {{ $product->name }}
                </a>
                <div class="mt-1 text-[11px] text-black/45">{{ $product->pack }}</div>
                <div class="mt-1 text-[11px] text-black/40">SKU: {{ $product->sku }}</div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-extrabold tabular-nums text-ink">${{ number_format($product->price, 2) }}</span>
                    @if($onSale)
                        <span class="text-xs text-black/35 line-through tabular-nums">${{ number_format($product->compare, 2) }}</span>
                    @endif
                </div>
                <form action="{{ route('ecommerce.cart.add', absolute: false) }}" method="post" class="mt-auto pt-3 product-card-form">
                    @csrf
                    <input type="hidden" name="item_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="w-full rounded-lg bg-[#e53935] text-white text-xs font-bold py-2.5 hover:bg-[#c62828]"
                            aria-label="Add {{ $product->name }} to cart">Add to Cart</button>
                </form>
            </div>
        </article>
    @empty
        <div class="col-span-full rounded-xl bg-white border border-dashed border-line p-10 text-center text-sm text-black/50">
            No products found in this catalog.
        </div>
    @endforelse
</div>
