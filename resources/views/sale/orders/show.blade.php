@extends('sale.layout')
@section('title', $order->invoice_no)
@section('header', 'Order detail')
@section('content')
<a href="{{ route('sale.orders') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-sale mb-3 lg:mb-4">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
    Back to orders
</a>

@php
    $displayStatus = $order->sale_status ?? 'sale';
    $badge = $displayStatus === 'invoiced' ? 'sale-badge--completed' : ($displayStatus === 'return' ? 'sale-badge--draft' : 'sale-badge--ordered');
    $badgeLabel = $displayStatus === 'invoiced' ? 'Invoiced' : ($displayStatus === 'return' ? 'Return' : 'Sale');
@endphp

<div class="sale-layout-2 space-y-3 lg:space-y-0">
    <div class="sale-stack">
        <div class="sale-card">
            <div class="flex justify-between gap-3 items-start">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="sale-order-row__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Sales order</div>
                        <div class="text-xl lg:text-2xl font-extrabold truncate">{{ $order->invoice_no }}</div>
                        @if(!empty($order->converted_invoice_no))
                            <div class="text-sm font-bold text-sale mt-1">Invoice {{ $order->converted_invoice_no }}</div>
                        @endif
                        <div class="text-sm text-slate-500 mt-1 flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            {{ \Carbon\Carbon::parse($order->transaction_date)->format('M j, Y g:i A') }}
                        </div>
                    </div>
                </div>
                <span class="sale-badge {{ $badge }} shrink-0">{{ $badgeLabel }}</span>
            </div>
            @if(($order->sale_status ?? '') === 'invoiced' && !empty($order->invoice_pay_status))
                <div class="mt-2">
                    <span class="sale-badge {{ $order->invoice_pay_status === 'PAID' ? 'sale-badge--completed' : ($order->invoice_pay_status === 'PARTIAL' ? 'sale-badge--ordered' : 'sale-badge--draft') }}">{{ $order->invoice_pay_status }}</span>
                </div>
            @endif
        </div>

        <div class="sale-card">
            <div class="sale-sec-title">
                <span class="sale-sec-title__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg>
                </span>
                Items
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($order->sell_lines as $line)
                    @php
                        $lineQty = (float) $line->quantity;
                        $linePrice = (float) $line->unit_price_inc_tax;
                        $lineTax = (float) ($line->item_tax ?? 0);
                        $lineDisc = (float) ($line->line_discount_amount ?? 0);
                    @endphp
                    <div class="py-3 flex justify-between gap-3 text-sm">
                        <div class="min-w-0 flex items-start gap-2">
                            <span class="mt-0.5 w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="font-semibold">{{ optional($line->product)->name }}</div>
                                <div class="text-xs text-slate-400">Qty {{ number_format($lineQty, 2) }} × ${{ number_format($linePrice, 2) }}</div>
                                @if($lineDisc > 0)
                                    <div class="text-xs text-slate-400">Item discount ${{ number_format($lineDisc, 2) }}</div>
                                @endif
                                @if($lineTax > 0)
                                    <div class="text-xs text-slate-400">Item tax ${{ number_format($lineTax * $lineQty, 2) }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="font-bold tabular-nums shrink-0">${{ number_format($lineQty * $linePrice, 2) }}</div>
                    </div>
                @endforeach
            </div>
            @php $a = $amounts ?? []; @endphp
            <div class="border-t border-slate-100 mt-1 pt-3 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="tabular-nums font-semibold">${{ number_format($a['subtotal'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">{{ $a['discount_label'] ?? 'Discount' }}</span>
                    <span class="tabular-nums font-semibold">−${{ number_format($a['discount'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Tax</span>
                    <span class="tabular-nums font-semibold">${{ number_format($a['tax'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Shipping charge</span>
                    <span class="tabular-nums font-semibold">${{ number_format($a['shipping'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">{{ $a['packing_label'] ?? 'Packing charge' }}</span>
                    <span class="tabular-nums font-semibold">${{ number_format($a['packing'] ?? 0, 2) }}</span>
                </div>
                @foreach(($a['extras'] ?? []) as $extra)
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">{{ $extra['label'] }}</span>
                        <span class="tabular-nums font-semibold">${{ number_format($extra['amount'] ?? 0, 2) }}</span>
                    </div>
                @endforeach
                @if(!empty($a['round_off']))
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Round off</span>
                        <span class="tabular-nums font-semibold">${{ number_format($a['round_off'], 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-3 font-extrabold text-base lg:text-lg pt-2 border-t border-slate-100">
                    <span>Total</span>
                    <span class="tabular-nums text-sale">${{ number_format($a['total'] ?? ($order->sale_display_total ?? $order->final_total), 2) }}</span>
                </div>
                @if(!empty($a['show_paid']))
                    <div class="flex justify-between gap-3 pt-1">
                        <span class="text-slate-500">Paid</span>
                        <span class="tabular-nums font-semibold">${{ number_format($a['paid'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Due</span>
                        <span class="tabular-nums font-semibold">${{ number_format($a['due'] ?? 0, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if($order->additional_notes)
            <div class="sale-card">
                <div class="sale-sec-title !mb-2">
                    <span class="sale-sec-title__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                    </span>
                    Note
                </div>
                <div class="text-sm text-slate-600">{{ $order->additional_notes }}</div>
            </div>
        @endif

        @if($order->shipping_address || $order->shipping_details || $order->shipping_status)
            <div class="sale-card">
                <div class="sale-sec-title !mb-2">
                    <span class="sale-sec-title__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </span>
                    Shipping
                </div>
                @if($order->shipping_status)
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Status</div>
                    <div class="text-sm font-bold mb-3">{{ ucfirst($order->shipping_status) }}</div>
                @endif
                @if($order->shipping_address)
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Address</div>
                    <div class="text-sm text-slate-700 whitespace-pre-line mb-3">{{ $order->shipping_address }}</div>
                @endif
                @if($order->shipping_details)
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Details</div>
                    <div class="text-sm text-slate-700 whitespace-pre-line mb-3">{{ $order->shipping_details }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="sale-stack">
        <div class="sale-card sale-sticky-panel">
            <div class="sale-sec-title">
                <span class="sale-sec-title__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
                </span>
                Customer
            </div>
            <div class="font-bold text-base">{{ optional($order->contact)->supplier_business_name ?: optional($order->contact)->name }}</div>
            @if(optional($order->contact)->mobile)
                <div class="text-sm text-slate-500 mt-2 flex items-center gap-1.5">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z"/></svg>
                    {{ $order->contact->mobile }}
                </div>
            @endif
            @if(optional($order->contact)->email)
                <div class="text-sm text-slate-500 mt-1">{{ $order->contact->email }}</div>
            @endif

            <div class="sale-order-actions !mt-4 !flex-col !items-stretch !mb-2">
                @if(!empty($order->can_show_edit) || !empty($order->can_edit))
                    @if(!empty($order->can_edit))
                        <a href="{{ route('sale.orders.edit', $order->id) }}" class="sale-btn !w-full inline-flex items-center justify-center gap-2">
                            Edit order
                        </a>
                    @else
                        <span class="sale-btn is-disabled !w-full inline-flex items-center justify-center gap-2" title="Invoiced — cannot edit" aria-disabled="true">
                            Edit order
                        </span>
                    @endif
                @endif
                <a href="{{ route('sale.orders.invoice', $order->id) }}" target="_blank" class="sale-btn !w-full inline-flex items-center justify-center gap-2 {{ (!empty($order->can_show_edit) || !empty($order->can_edit)) ? 'mt-2' : '' }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                    Invoice
                </a>
                <form method="POST" action="{{ route('sale.orders.destroy', $order->id) }}" class="mt-2" onsubmit="return confirm('Delete order {{ $order->invoice_no }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sale-btn-ghost !w-full !text-rose-600 !border-rose-200">Delete order</button>
                </form>
            </div>
        </div>
        <p class="text-xs text-slate-400 px-1">
            Sales order — same as admin Sales Orders. Admin converts it to invoice when ready.
        </p>
    </div>
</div>
@endsection
