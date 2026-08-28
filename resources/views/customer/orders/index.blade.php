@extends('customer.layout')
@section('title', 'My Orders')
@section('content')
@php $initials = strtoupper(substr($contact->displayName(), 0, 1)); @endphp
<div class="mb-5">
    <h1 class="ca-page-title">My Orders</h1>
    <div class="ca-page-sub">{{ $initials }}</div>
</div>

<div class="space-y-3">
@forelse($orders as $order)
    <div class="ca-card !p-0 overflow-hidden">
        <div class="p-4">
            <div class="font-extrabold text-red-600">{{ $initials }}</div>
            <div class="text-sm text-slate-600 mt-1">{{ \Carbon\Carbon::parse($order->transaction_date)->format('m/d/Y') }}</div>
            <div class="text-sm text-slate-600">Order #{{ $order->invoice_no }}</div>
            <div class="mt-1"><span class="ca-badge ca-badge--partial">{{ $order->sourceLabel() }}</span></div>
            <div class="text-sm font-bold mt-1 tabular-nums">${{ number_format($order->final_total, 2) }}</div>
        </div>
        <div class="grid grid-cols-3 border-t border-slate-100 text-sm font-bold">
            <a href="{{ route('customer.orders.show', $order->id) }}" class="py-3 text-center text-white font-extrabold" style="background:linear-gradient(135deg,#e11d48,#be123c)">VIEW</a>
            <span class="py-3 text-center text-slate-400 border-l border-slate-100">—</span>
            <a href="{{ route('customer.orders.show', $order->id) }}" class="py-3 text-center text-slate-600 border-l border-slate-100">OPEN</a>
        </div>
    </div>
@empty
    <div class="ca-card text-center text-slate-400 py-10 text-sm">No orders yet</div>
@endforelse
</div>

@if($orders->hasPages())
    <div class="mt-4">{{ $orders->onEachSide(1)->links() }}</div>
@endif
@endsection
