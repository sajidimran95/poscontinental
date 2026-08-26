@extends('sale.layout')
@section('title', 'Delivery')
@section('header', 'Delivery')
@section('content')
{{-- Only this sales representative's own sales orders (created_by) --}}
<div class="sale-page-tool">
    <span class="sale-chip">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
        {{ $orders->total() }} my delivery
    </span>
    <a href="{{ route('sale.orders') }}" class="text-xs font-bold text-sale no-underline">All my orders</a>
</div>

<form method="GET" action="{{ route('sale.delivery') }}" class="sale-card !p-3 mb-3 space-y-2">
    <input type="search" name="q" class="sale-input" value="{{ $q ?? '' }}" placeholder="Search my customer / order no" autocomplete="off">
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

<div class="space-y-2">
    @forelse($orders as $order)
        @php
            $customer = optional($order->contact)->supplier_business_name
                ?: optional($order->contact)->name
                ?: 'Customer';
            $addr = trim((string) ($order->shipping_address ?: ''));
            if ($addr === '') {
                $addr = trim(implode(', ', array_filter([
                    optional($order->contact)->address_line_1,
                    optional($order->contact)->city,
                ])));
            }
            $ship = $order->shipping_status ?: 'ordered';
            $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $customer) ?: 'C', 0, 2));
        @endphp
        <a href="{{ route('sale.orders.show', $order->id) }}" class="sale-order-row">
            <span class="sale-order-row__ico !rounded-full !text-sm !font-extrabold" style="background:#ccfbf1;color:#0f766e;">
                {{ $initials }}
            </span>
            <div class="sale-order-row__body">
                <div class="font-extrabold text-[15px] truncate">{{ $customer }}</div>
                <div class="text-xs text-slate-500 mt-0.5 truncate">
                    Order # {{ $order->invoice_no }}
                    @if($addr !== '') · {{ \Illuminate\Support\Str::limit($addr, 36) }}@endif
                </div>
                <div class="text-xs text-slate-400 mt-1">
                    {{ \Carbon\Carbon::parse($order->transaction_date)->format('M j, Y') }}
                </div>
            </div>
            <div class="sale-order-row__meta">
                <div class="font-extrabold tabular-nums">${{ number_format((float) $order->final_total, 2) }}</div>
                <span class="sale-badge sale-badge--ordered mt-1">{{ ucfirst($ship) }}</span>
            </div>
        </a>
    @empty
        <div class="sale-card sale-empty">
            <div class="sale-empty__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </div>
            <p class="text-slate-500 text-sm mb-1">No delivery orders</p>
            <p class="text-slate-400 text-xs">Only orders you created as this sales rep are shown here.</p>
        </div>
    @endforelse
</div>

@if($orders->hasPages())
    <div class="mt-3">
        @include('sale.partials.pagination', ['paginator' => $orders])
    </div>
@endif
@endsection
