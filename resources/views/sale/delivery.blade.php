@extends('sale.layout')
@section('title', 'Routes')
@section('header', 'Routes')
@section('content')
@php
    $routes = $routes ?? collect();
    $stopCount = $routes->sum(fn ($list) => $list->count());
@endphp
<div class="sale-page-tool">
    <span class="sale-chip">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
        {{ $stopCount }} stop{{ $stopCount === 1 ? '' : 's' }} · from POS · near → far
    </span>
</div>

<form method="GET" action="{{ route('sale.delivery') }}" class="sale-card !p-3 mb-3 space-y-2">
    <input type="search" name="q" class="sale-input" value="{{ $q ?? '' }}" placeholder="Search customer / order / area" autocomplete="off">
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Start Date</label>
            <input type="date" name="start_date" class="sale-input" value="{{ $start }}">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">End Date</label>
            <input type="date" name="end_date" class="sale-input" value="{{ $end }}">
        </div>
    </div>
    <button type="submit" class="sale-btn-sm !w-full justify-center">Apply filter</button>
</form>

@forelse($routes as $area => $stops)
    <div class="sale-route-area">
        <div class="sale-route-area__head">
            <div>
                <div class="sale-route-area__name">{{ $area }}</div>
                <div class="sale-route-area__sub">{{ $stops->count() }} stop{{ $stops->count() === 1 ? '' : 's' }} · nearest to POS first</div>
            </div>
        </div>
        <div class="sale-route-line">
            @foreach($stops as $i => $order)
                @php
                    $customer = optional($order->contact)->supplier_business_name
                        ?: optional($order->contact)->name
                        ?: 'Customer';
                    $addr = trim((string) ($order->shipping_address ?: ''));
                    if ($addr === '') {
                        $addr = trim(implode(', ', array_filter([
                            optional($order->contact)->address_line_1,
                            optional($order->contact)->zip_code,
                        ])));
                    }
                    $ship = $order->shipping_status ?: 'ordered';
                    $nearFar = $i === 0 ? 'Nearest' : ($i === ($stops->count() - 1) && $stops->count() > 1 ? 'Farthest' : 'Stop '.($i + 1));
                @endphp
                <a href="{{ route('sale.orders.show', $order->id) }}" class="sale-route-stop">
                    <div class="sale-route-stop__rail" aria-hidden="true">
                        <span class="sale-route-stop__dot">{{ $i + 1 }}</span>
                    </div>
                    <div class="sale-route-stop__card">
                        <div class="sale-route-stop__tag">{{ $nearFar }}</div>
                        <div class="font-extrabold text-[15px] truncate">{{ $customer }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">Order # {{ $order->invoice_no }}</div>
                        @if($addr !== '')
                            <div class="sale-route-stop__addr">{{ $addr }}</div>
                        @endif
                        <div class="sale-route-stop__foot">
                            <span>${{ number_format((float) $order->final_total, 2) }}</span>
                            <em>{{ ucfirst($ship) }}</em>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@empty
    <div class="sale-card sale-empty">
        <div class="sale-empty__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
        </div>
        <p class="text-slate-500 text-sm mb-1">No delivery orders</p>
        <p class="text-slate-400 text-xs">Stops are ordered from your POS location, nearest first.</p>
    </div>
@endforelse
@endsection
