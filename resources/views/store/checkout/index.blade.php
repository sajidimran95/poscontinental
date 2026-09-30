@extends('store.layouts.store')
@section('title', 'Checkout')
@section('content')
@php
    $field = 'border border-black/10 rounded-xl px-4 py-2.5 text-sm bg-mist/40 focus:bg-white outline-none focus:ring-2 focus:ring-brand/30';
@endphp
<div class="max-w-6xl mx-auto px-4 py-10 md:py-14">
    <p class="text-[11px] uppercase tracking-[0.22em] text-black/40 mb-2">Secure</p>
    <h1 class="font-display text-4xl md:text-5xl mb-8">Checkout</h1>

    <div class="grid lg:grid-cols-5 gap-8">
        <form action="{{ route('ecommerce.checkout.place') }}" method="post" class="lg:col-span-3 space-y-5">
            @csrf
            <section class="rounded-2xl bg-white border border-black/5 p-6 space-y-4">
                <h2 class="font-display text-2xl">Contact</h2>
                <div class="grid md:grid-cols-2 gap-3">
                    <input name="email" type="email" required value="{{ old('email', $customer->loginEmail()) }}" placeholder="Email" class="{{ $field }}">
                    <input name="phone" value="{{ old('phone', $customer->mobile ?: $customer->telephone) }}" placeholder="Phone" class="{{ $field }}">
                </div>
            </section>

            <section class="rounded-2xl bg-white border border-black/5 p-6 space-y-4">
                <h2 class="font-display text-2xl">Shipping address</h2>
                @if($addresses->count())
                    <select name="ship_to_address_id" id="shipToSelect" class="w-full {{ $field }}">
                        <option value="0">Main address — {{ $customer->address }} {{ $customer->city }}</option>
                        @foreach($addresses as $addr)
                            <option value="{{ $addr->id }}" @selected((int) old('ship_to_address_id', $default_address_id) === $addr->id)>{{ $addr->name ?: 'Location' }} — {{ $addr->address }} {{ $addr->city }}</option>
                        @endforeach
                        <option value="new" @selected(old('ship_to_address_id') === 'new')>+ New address</option>
                    </select>
                @else
                    <input type="hidden" name="ship_to_address_id" value="new">
                @endif
                <div id="shipNewFields" class="space-y-3">
                    <div class="grid md:grid-cols-2 gap-3">
                        <input name="shipping[first_name]" value="{{ old('shipping.first_name', $name_parts[0]) }}" placeholder="First name" class="{{ $field }}">
                        <input name="shipping[last_name]" value="{{ old('shipping.last_name', $name_parts[1]) }}" placeholder="Last name" class="{{ $field }}">
                    </div>
                    <input name="shipping[company]" value="{{ old('shipping.company', $customer->company_name) }}" placeholder="Company (optional)" class="w-full {{ $field }}">
                    <input name="shipping[address_line_1]" value="{{ old('shipping.address_line_1', $customer->address) }}" placeholder="Address line 1" class="w-full {{ $field }}">
                    <input name="shipping[address_line_2]" value="{{ old('shipping.address_line_2') }}" placeholder="Address line 2" class="w-full {{ $field }}">
                    <div class="grid md:grid-cols-3 gap-3">
                        <input name="shipping[city]" value="{{ old('shipping.city', $customer->city) }}" placeholder="City" class="{{ $field }}">
                        <select name="shipping[state]" class="{{ $field }}">
                            <option value="">State</option>
                            @foreach($us_states as $code => $name)
                                <option value="{{ $code }}" @selected(old('shipping.state', strtoupper((string) $customer->state)) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <input name="shipping[zip_code]" value="{{ old('shipping.zip_code', $customer->zip_code) }}" placeholder="ZIP" class="{{ $field }}">
                    </div>
                    <input type="hidden" name="shipping[country]" value="United States">
                </div>
            </section>

            <section class="rounded-2xl bg-white border border-black/5 p-6 space-y-3">
                <h2 class="font-display text-2xl">Payment</h2>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="payment_method" value="cod" checked> {{ $payment_label }}</label>
                <textarea name="note" rows="2" placeholder="Order note" class="w-full {{ $field }}">{{ old('note') }}</textarea>
                <button class="w-full rounded-full bg-ink text-white py-3.5 mt-2 text-sm font-semibold hover:bg-brand transition">Place order</button>
            </section>
        </form>

        <aside class="lg:col-span-2">
            <div class="rounded-2xl bg-white border border-black/5 p-6 sticky top-28 space-y-3 text-sm">
                <h2 class="font-display text-2xl mb-2">Order summary</h2>
                @foreach($lines as $item)
                    <div class="flex justify-between gap-3">
                        <span class="text-black/70">{{ $item->product->name }} × {{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</span>
                        <span class="tabular-nums">${{ number_format($item->line_total, 2) }}</span>
                    </div>
                @endforeach
                <div class="border-t border-black/5 pt-3 space-y-1">
                    <div class="flex justify-between"><span class="text-black/50">Subtotal</span><span class="tabular-nums">${{ number_format($totals['subtotal'], 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-black/50">Delivery</span><span class="tabular-nums">${{ number_format($totals['shipping'], 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-black/50">Tax (est.)</span><span class="tabular-nums">${{ number_format($totals['tax'], 2) }}</span></div>
                    <div class="flex justify-between font-semibold text-base pt-2"><span>Total</span><span class="tabular-nums">${{ number_format($totals['total'], 2) }}</span></div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('shipToSelect');
    const box = document.getElementById('shipNewFields');
    if (!sel || !box) return;
    const sync = () => { box.style.display = sel.value === 'new' ? '' : 'none'; };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
