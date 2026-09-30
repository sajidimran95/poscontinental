@extends('store.layouts.store')
@section('title', 'Order confirmed')
@section('content')
<div class="max-w-2xl mx-auto px-4 py-16 text-center">
    <h1 class="text-3xl font-semibold mb-3">Thank you</h1>
    <p class="text-black/60 mb-6">Your order <strong>{{ $order->order_number }}</strong> has been received.</p>
    <p class="text-sm text-black/50 mb-8">Tracking token: <code class="bg-mist px-2 py-1 rounded">{{ $meta->tracking_token }}</code></p>
    <div class="flex justify-center gap-3">
        <a href="{{ route('ecommerce.track', ['token' => $meta->tracking_token]) }}" class="rounded-full bg-ink text-white px-6 py-2.5 text-sm">Track order</a>
        <a href="{{ route('ecommerce.shop') }}" class="rounded-full border px-6 py-2.5 text-sm">Continue shopping</a>
    </div>
</div>
@endsection
