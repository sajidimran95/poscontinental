@extends('sale.layout')
@section('title', 'Main')
@section('header', config('app.name', 'Sales'))
@section('content')
@php
    $todayOrders = (int) ($stats['today_orders'] ?? 0);
    $todayTotal = (float) ($stats['today_total'] ?? 0);
    $monthOrders = max(1, (int) ($stats['month_orders'] ?? 0));
    $orderPct = min(100, round(($todayOrders / $monthOrders) * 100));
    $monthTotal = (float) ($stats['month_total'] ?? 0);
    $payPct = $monthTotal > 0 ? (int) min(100, round(($todayTotal / $monthTotal) * 100)) : 0;
@endphp

<div class="sale-home-welcome">
    <h1>Welcome</h1>
    <div class="font-bold text-lg mt-2">{{ $userName }}</div>
</div>

<div class="flex justify-between items-end mb-2">
    <div class="font-extrabold">Today</div>
    <div class="text-xs text-slate-400">{{ now()->format('l j F Y') }}</div>
</div>
<div class="sale-home-today mb-5">
    <div class="sale-card text-center">
        <div class="text-lg font-bold text-violet-600">{{ $todayOrders }} <span class="text-sm font-bold text-slate-500">Orders</span></div>
        <div class="sale-gauge mt-2" style="--p: {{ $orderPct }}"></div>
        <div class="text-xs text-amber-500 mt-1">{{ $orderPct }}% of month</div>
        <div class="text-xs text-slate-400 mt-1">{{ (int) ($stats['due_orders'] ?? 0) }} Pending</div>
    </div>
    <div class="sale-card text-center">
        <div class="text-lg font-bold text-sale">${{ number_format($todayTotal, 0) }} <span class="text-sm font-bold text-slate-500">Sales</span></div>
        <div class="sale-gauge mt-2" style="--p: {{ $payPct }}; background: conic-gradient(#e53935 calc(var(--p, 0) * 1%), #e5e7eb 0);"></div>
        <div class="text-xs text-sale mt-1">{{ $payPct }}% of month</div>
        <div class="text-xs text-slate-400 mt-1">Month ${{ number_format((float) ($stats['month_total'] ?? 0), 0) }}</div>
    </div>
</div>

<div class="flex justify-between items-center mb-2">
    <div class="font-extrabold">New Products</div>
    <a href="{{ route('sale.products') }}" class="sale-viewmore">View More &gt;</a>
</div>
<div class="pp-grid mb-5">
    @forelse($newProducts as $p)
        <a class="pp-card {{ empty($p['in_stock']) ? 'is-oos' : '' }}" href="{{ $p['variation_id'] ? route('sale.orders.create', ['add' => $p['variation_id']]) : route('sale.products') }}">
            <div class="pp-img">
                @if(!empty($p['has_image']))
                    <img src="{{ $p['image'] }}" alt="">
                @endif
            </div>
            <div class="pp-body">
                <div class="pp-name">{{ $p['name'] }}</div>
                @if(!empty($p['sku']))<div class="pp-code">Code: {{ $p['sku'] }}</div>@endif
                <div class="pp-price">${{ number_format((float) $p['price'], 2) }}</div>
                @if(empty($p['in_stock']))<div class="pp-oos">OUT OF STOCK</div>@endif
            </div>
        </a>
    @empty
        <div class="text-sm text-slate-400 col-span-2">No products</div>
    @endforelse
</div>

<div class="flex justify-between items-center mb-2">
    <div class="font-extrabold">Top Sellers</div>
    <a href="{{ route('sale.products') }}" class="sale-viewmore">View More &gt;</a>
</div>
<div class="pp-grid">
    @forelse($topProducts as $p)
        <a class="pp-card {{ empty($p->in_stock) ? 'is-oos' : '' }}" href="{{ !empty($p->variation_id) ? route('sale.orders.create', ['add' => $p->variation_id]) : route('sale.products') }}">
            <div class="pp-img">
                @if(!empty($p->has_image))
                    <img src="{{ $p->image_url }}" alt="">
                @endif
            </div>
            <div class="pp-body">
                <div class="pp-name">{{ $p->name }}</div>
                @if(!empty($p->sku))<div class="pp-code">Code: {{ $p->sku }}</div>@endif
                <div class="pp-price">${{ number_format((float) $p->price, 2) }}</div>
                @if(empty($p->in_stock))<div class="pp-oos">OUT OF STOCK</div>@endif
            </div>
        </a>
    @empty
        <div class="text-sm text-slate-400 col-span-2">No sales yet</div>
    @endforelse
</div>
@endsection
