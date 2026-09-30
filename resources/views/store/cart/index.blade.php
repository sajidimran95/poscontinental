@extends('store.layouts.store')
@section('title', 'Cart')
@section('content')
<div class="max-w-[1400px] mx-auto px-4 py-10 md:py-12">
    <p class="text-[11px] uppercase tracking-[0.18em] text-black/40 font-bold mb-2">Bag</p>
    <h1 class="text-3xl md:text-4xl font-extrabold mb-8">Your cart</h1>

    @if($lines->isEmpty())
        <div class="rounded-xl bg-white border border-line p-12 text-center">
            <p class="text-black/50 mb-6">Your cart is empty.</p>
            <a href="{{ route('ecommerce.shop') }}" class="inline-flex rounded-full bg-ink text-white px-7 py-3 text-sm font-semibold">Continue shopping</a>
        </div>
    @else
        <div class="grid lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 space-y-4">
                @foreach($lines as $item)
                    @php $product = $item->product; @endphp
                    <div class="rounded-xl bg-white border border-line p-4 flex flex-wrap gap-4 items-center">
                        <a href="{{ route('ecommerce.product', $product->slug) }}" class="w-20 h-20 rounded-xl overflow-hidden bg-mist shrink-0">
                            <img src="{{ $product->image_url }}" alt="" class="w-full h-full object-cover"
                                 onerror="this.onerror=null;this.src='{{ asset('img/default.png') }}';">
                        </a>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm truncate">{{ $product->name }}</div>
                            <div class="text-xs text-black/45 mt-0.5">${{ number_format($item->unit_price, 2) }} each · {{ $product->sku }}</div>
                            @if($item->warning)
                                <div class="text-xs text-rose-600 mt-0.5">{{ $item->warning }}</div>
                            @endif

                            <form action="{{ route('ecommerce.cart.update', absolute: false) }}" method="post" class="cart-qty-form flex items-center gap-2 mt-3">
                                @csrf
                                <input type="hidden" name="item_id" value="{{ $item->id }}">
                                <div class="inline-flex items-center gap-2 rounded-full bg-[#f0f0f0] px-1.5 py-1.5">
                                    <button type="button" class="qty-minus h-9 w-9 rounded-full bg-white border border-[#ddd] text-ink text-lg leading-none flex items-center justify-center hover:bg-mist" aria-label="Decrease quantity">−</button>
                                    <input type="number" name="quantity" value="{{ (int) $item->quantity }}" min="1" step="1"
                                           class="qty-input w-10 bg-transparent border-0 text-center text-sm font-medium tabular-nums outline-none appearance-none [-moz-appearance:textfield]">
                                    <button type="button" class="qty-plus h-9 w-9 rounded-full bg-white border border-[#ddd] text-ink text-lg leading-none flex items-center justify-center hover:bg-mist" aria-label="Increase quantity">+</button>
                                </div>
                            </form>
                        </div>
                        <div class="text-right shrink-0 ml-auto">
                            <div class="font-extrabold tabular-nums">${{ number_format($item->quantity * $item->unit_price, 2) }}</div>
                            <form action="{{ route('ecommerce.cart.remove', absolute: false) }}" method="post" class="mt-2">
                                @csrf
                                <input type="hidden" name="item_id" value="{{ $item->id }}">
                                <button class="text-xs text-rose-600 font-medium">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="lg:col-span-2">
                <div class="rounded-xl bg-white border border-line p-6 sticky top-28 space-y-4">
                    <h2 class="text-xl font-extrabold">Summary</h2>
                    <div class="space-y-2 text-sm border-t border-line pt-4">
                        <div class="flex justify-between"><span class="text-black/50">Items</span><span class="tabular-nums">{{ rtrim(rtrim(number_format($totals['qty'], 2), '0'), '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-black/50">Subtotal</span><span class="tabular-nums">${{ number_format($totals['subtotal'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-black/50">Delivery</span><span class="tabular-nums">${{ number_format($totals['shipping'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-black/50">Tax (est.)</span><span class="tabular-nums">${{ number_format($totals['tax'], 2) }}</span></div>
                        <div class="flex justify-between font-extrabold text-base pt-2 border-t border-line"><span>Total</span><span class="tabular-nums">${{ number_format($totals['total'], 2) }}</span></div>
                    </div>
                    @if(! $customer)
                        <p class="text-xs text-black/55">Prices shown are list prices. <a href="{{ route('ecommerce.login') }}" class="font-semibold text-brand">Log in</a> to see your account pricing and check out.</p>
                    @elseif($approval !== 'approved')
                        <p class="text-xs rounded-lg bg-amber-50 border border-amber-200 text-amber-900 p-3">Your wholesale account is {{ $approval }}. Checkout unlocks after admin approval.</p>
                    @endif
                    <a href="{{ route('ecommerce.checkout') }}" class="block text-center rounded-full bg-ink text-white py-3.5 text-sm font-semibold hover:bg-black transition">Checkout</a>
                    <a href="{{ route('ecommerce.shop') }}" class="block text-center text-sm font-semibold text-brand">Continue shopping</a>
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection

@push('head')
<style>
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; }
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.cart-qty-form').forEach(form => {
    const input = form.querySelector('.qty-input');
    const minus = form.querySelector('.qty-minus');
    const plus = form.querySelector('.qty-plus');
    if (!input || !minus || !plus) return;

    function setQty(v) {
        const n = Math.max(1, parseInt(v, 10) || 1);
        input.value = n;
        form.submit();
    }

    minus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) - 1));
    plus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) + 1));
    input.addEventListener('change', () => setQty(input.value));
});
</script>
@endpush
