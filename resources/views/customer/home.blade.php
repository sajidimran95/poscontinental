@extends('customer.layout')
@section('title', 'Home')
@section('content')
<div class="mb-5">
    <p class="text-xs font-bold uppercase tracking-wider text-brand mb-1">Welcome back</p>
    <h1 class="ca-page-title">{{ $business->name ?? config('app.name') }}</h1>
    <div class="ca-page-sub">{{ $contact->contact_id ?? '' }}</div>
    @if(!empty($location))
        <div class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/80 border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
            <span class="text-brand">📍</span>
            {{ $location->name }}@if(!empty($business->city)) · {{ $business->city }}@endif
        </div>
    @endif
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
    <div class="ca-card relative overflow-hidden">
        <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full" style="background:rgba(225,29,72,.10)"></div>
        <div class="text-sm font-semibold text-slate-500 relative">Current Balance</div>
        <div class="text-3xl font-extrabold mt-2 tabular-nums tracking-tight relative ca-display">${{ number_format($due, 2) }}</div>
    </div>
    <div class="ca-card">
        <div class="flex justify-between text-sm items-center">
            <span class="font-semibold text-slate-500">Due Balance</span>
            <span class="font-extrabold tabular-nums text-brand text-lg">${{ number_format($due, 2) }}</span>
        </div>
        <div class="border-t border-slate-100 my-3"></div>
        <div class="flex justify-between text-sm">
            <span class="font-semibold text-slate-500">Customer ID</span>
            <span class="font-extrabold">{{ $contact->contact_id ?? $contact->id }}</span>
        </div>
    </div>
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="ca-section-title">New Arrivals</h2>
    <a href="{{ route('customer.orders.create') }}" class="ca-link">SEE ALL</a>
</div>
<div class="space-y-3 mb-6">
    @forelse($newArrivals as $p)
        <div class="ca-card flex gap-3 items-center !p-3">
            <img src="{{ $p['image'] }}" alt="" class="w-16 h-16 rounded-2xl object-cover bg-slate-100 flex-shrink-0 ring-1 ring-slate-100">
            <div class="min-w-0 flex-1">
                <div class="font-bold text-sm leading-snug line-clamp-2">{{ $p['name'] }}</div>
                <div class="text-xs text-slate-500 mt-0.5 font-semibold">SKU: {{ $p['sku'] }}</div>
                <div class="text-brand font-extrabold mt-1 ca-display">${{ number_format($p['price'], 2) }}</div>
            </div>
            <a href="{{ route('customer.orders.create', ['add' => $p['variation_id']]) }}"
               class="w-10 h-10 rounded-full text-white flex items-center justify-center text-xl font-bold flex-shrink-0 shadow-lg"
               style="background:linear-gradient(135deg,#f43f5e,#be123c);box-shadow:0 10px 20px rgba(225,29,72,.3)">+</a>
        </div>
    @empty
        <div class="ca-card text-sm text-slate-400 text-center py-8 font-semibold">No products yet</div>
    @endforelse
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="ca-section-title">Top Products</h2>
    <a href="{{ route('customer.orders.create') }}" class="ca-link">SEE ALL</a>
</div>
<div class="grid grid-cols-2 gap-3">
    @foreach($topProducts as $p)
        <a href="{{ route('customer.orders.create', ['add' => $p['variation_id']]) }}" class="ca-card block !p-2.5 group">
            <div class="overflow-hidden rounded-2xl mb-2 ring-1 ring-slate-100">
                <img src="{{ $p['image'] }}" alt="" class="w-full aspect-square object-cover bg-slate-100 transition duration-300 group-hover:scale-105">
            </div>
            <div class="font-bold text-xs leading-snug line-clamp-2 px-1">{{ $p['name'] }}</div>
            <div class="text-brand font-extrabold text-sm px-1 mt-1 ca-display">${{ number_format($p['price'], 2) }}</div>
        </a>
    @endforeach
</div>
@endsection
