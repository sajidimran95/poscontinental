@extends('store.layouts.store')
@section('title', 'Orders')
@section('content')
<div class="ecom-acc max-w-4xl mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Orders'])

    <h1 class="text-2xl font-semibold mb-6 ecom-acc-desktop-only">Order history</h1>

    <div class="ecom-acc-list ecom-acc-mobile-only">
        @forelse($orders as $order)
            @php $meta = $metas[$order->id] ?? null; @endphp
            <div class="ecom-acc-order">
                <div class="ecom-acc-order__row">
                    <span class="font-bold">{{ $order->order_number }}</span>
                    <span class="font-bold tabular-nums">${{ number_format($order->total, 2) }}</span>
                </div>
                <div class="ecom-acc-order__row text-[12px] text-black/45">
                    <span>{{ $order->status }}</span>
                    @if($meta)
                        <a class="text-brand font-bold" href="{{ route('ecommerce.track', ['token' => $meta->tracking_token]) }}">Track</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-sm text-center text-black/45">No orders yet.</div>
        @endforelse
    </div>

    <div class="space-y-3 ecom-acc-desktop-only">
        @foreach($orders as $order)
            @php $meta = $metas[$order->id] ?? null; @endphp
            <div class="rounded-2xl border bg-white p-4 text-sm flex flex-wrap justify-between gap-2">
                <div>
                    <div class="font-medium">{{ $order->order_number }}</div>
                    <div class="text-slate-500">{{ $order->status }}</div>
                </div>
                <div>${{ number_format($order->total, 2) }}</div>
                @if($meta)
                    <a class="text-brand font-semibold" href="{{ route('ecommerce.track', ['token' => $meta->tracking_token]) }}">Track</a>
                @endif
            </div>
        @endforeach
    </div>
    <div class="mt-6 px-3 lg:px-0">{{ $orders->links() }}</div>
</div>
@endsection
