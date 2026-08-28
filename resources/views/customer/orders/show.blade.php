@extends('customer.layout')
@section('title', 'View Order')
@section('content')
@php
    $initials = strtoupper(substr(optional($order->contact)->displayName() ?: 'C', 0, 1));
    $a = $amounts ?? [];
@endphp
<div class="flex items-start justify-between gap-3 mb-3">
    <div>
        <h1 class="text-2xl font-extrabold">View Order</h1>
        <div class="text-sm text-slate-500 font-semibold">{{ $initials }}</div>
        <div class="text-xs text-slate-400 font-semibold mt-0.5">#{{ $order->invoice_no }} · {{ \Carbon\Carbon::parse($order->transaction_date)->format('M d, Y') }}</div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('customer.orders.invoice', $order->id) }}" class="inline-flex w-10 h-10 items-center justify-center rounded-full bg-rose-50 text-rose-600" title="Invoice">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/></svg>
        </a>
        <a href="{{ route('customer.orders') }}" class="inline-flex w-10 h-10 items-center justify-center rounded-full bg-slate-100 text-slate-600" title="Back">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
    </div>
</div>

<div class="flex items-end justify-between mb-3">
    <div>
        <div class="font-extrabold text-lg">Added Items</div>
        <div class="text-sm text-slate-500 font-semibold">Total : <span class="text-slate-800 font-extrabold tabular-nums">${{ number_format($a['subtotal'] ?? $order->final_total, 2) }}</span></div>
    </div>
    <div class="text-right text-xs text-slate-400 font-semibold">
        Order total<br>
        <span class="text-base text-red-600 font-extrabold tabular-nums">${{ number_format((float) $order->final_total, 2) }}</span>
    </div>
</div>

<div class="space-y-3 mb-6">
@forelse($order->sell_lines as $line)
    @php
        $qty = (float) $line->quantity;
        $price = (float) $line->unit_price_inc_tax;
    @endphp
    <div class="ca-card !p-3">
        <div class="font-extrabold text-sm leading-snug uppercase">{{ optional($line->product)->name }}</div>
        <div class="mt-1.5 text-[11px] text-slate-500 font-semibold">SKU ({{ $line->item_code ?? '—' }})</div>
        <div class="mt-2 text-xs font-bold text-slate-700">{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }} items</div>
        <div class="mt-2 font-extrabold text-red-600 tabular-nums">${{ number_format($qty * $price, 2) }}</div>
    </div>
@empty
    <div class="ca-card text-sm text-slate-400 text-center py-8">No items</div>
@endforelse
</div>

<div class="ca-card space-y-2 text-sm">
    <div class="flex justify-between"><span class="text-slate-500 font-semibold">Subtotal</span><span class="font-bold tabular-nums">${{ number_format($a['subtotal'] ?? 0, 2) }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500 font-semibold">Tax</span><span class="font-bold tabular-nums">${{ number_format($a['tax'] ?? 0, 2) }}</span></div>
    <div class="flex justify-between font-extrabold text-base pt-2 border-t border-slate-100">
        <span>Total</span><span class="text-red-600 tabular-nums">${{ number_format($a['total'] ?? $order->final_total, 2) }}</span>
    </div>
</div>
@endsection
