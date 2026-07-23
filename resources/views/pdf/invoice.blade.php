<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $order = $invoice->salesOrder;
    $isPaid = $invoice->status === 'PAID';
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Wholesale Invoice</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">INVOICE</div>
                <div class="doc-meta">
                    #{{ $invoice->invoice_number }}
                    &nbsp;·&nbsp;
                    {{ optional($invoice->invoice_date)?->format('M j, Y') }}
                </div>
                <div class="status {{ $isPaid ? 'status-paid' : 'status-open' }}">
                    {{ $invoice->status === 'ORDER' ? 'SALES ORDER' : $invoice->status }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card" style="width:33%">
            <div class="card-title">Bill To</div>
            <strong>{{ $order?->bill_to_name ?: ($invoice->customer?->company_name ?: $invoice->customer?->contact ?: '—') }}</strong>
            @if ($invoice->customer?->customer_id)
                <div class="line muted mono">ID: {{ $invoice->customer->customer_id }}</div>
            @endif
            <div class="line">{{ $order?->bill_to_address }}</div>
            @if ($order?->bill_to_city || $order?->bill_to_state || $order?->bill_to_zip)
                <div class="line">{{ collect([$order?->bill_to_city, $order?->bill_to_state, $order?->bill_to_zip])->filter()->implode(', ') }}</div>
            @endif
            @if ($order?->bill_to_phone)
                <div class="line">{{ $order->bill_to_phone }}</div>
            @endif
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">Ship To</div>
            <strong>{{ $order?->ship_to_name ?: ($order?->bill_to_name ?: '—') }}</strong>
            <div class="line">{{ $order?->ship_to_address ?: $order?->bill_to_address }}</div>
            @php
                $shipCity = collect([
                    $order?->ship_to_city ?: $order?->bill_to_city,
                    $order?->ship_to_state ?: $order?->bill_to_state,
                    $order?->ship_to_zip ?: $order?->bill_to_zip,
                ])->filter()->implode(', ');
            @endphp
            @if ($shipCity !== '')
                <div class="line">{{ $shipCity }}</div>
            @endif
            @if ($order?->ship_to_phone)
                <div class="line">{{ $order->ship_to_phone }}</div>
            @endif
        </td>
        <td class="card" style="width:34%">
            <div class="card-title">Invoice Details</div>
            <div class="line"><span class="muted">Order No:</span> <strong class="mono">{{ $order?->order_number ?: '—' }}</strong></div>
            <div class="line"><span class="muted">Order Date:</span> {{ optional($order?->order_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Terms:</span> {{ $order?->paymentTerm?->name ?: '—' }}</div>
            <div class="line"><span class="muted">Sales Rep:</span> {{ $order?->salesRep?->name ?: '—' }}</div>
            @if (filled($invoice->driver))
                <div class="line"><span class="muted">Driver:</span> {{ $invoice->driver }}</div>
            @endif
            @if (filled($order?->customer_po_no))
                <div class="line"><span class="muted">Customer PO:</span> {{ $order->customer_po_no }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:28px">#</th>
            <th style="width:90px">Item</th>
            <th>Description</th>
            <th class="right" style="width:60px">Qty</th>
            <th class="right" style="width:70px">Price</th>
            <th class="right" style="width:70px">Discount</th>
            <th class="right" style="width:80px">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($order?->lines ?? collect()) as $line)
            <tr>
                <td class="muted">{{ $line->line_no }}</td>
                <td class="mono">{{ $line->item_code }}</td>
                <td>{{ $line->description }}</td>
                <td class="right">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                <td class="right">${{ number_format((float) $line->price, 2) }}</td>
                <td class="right">${{ number_format((float) $line->discount, 2) }}</td>
                <td class="right"><strong>${{ number_format((float) $line->line_total, 2) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="empty">No line items on the linked sales order.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            <div>Thank you for your business.</div>
            @if ($invoice->payments->count())
                <div style="margin-top:8px">
                    Payments received: {{ $invoice->payments->count() }}
                    ({{ $invoice->payments->pluck('payment_method')->unique()->filter()->implode(', ') }})
                </div>
            @endif
            @if ($invoice->credits->count())
                <div>Credits applied: {{ $invoice->credits->count() }}</div>
            @endif
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="right">${{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Trade Discount</td>
                    <td class="right">${{ number_format((float) $invoice->trade_discount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Freight</td>
                    <td class="right">${{ number_format((float) $invoice->freight, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Miscellaneous</td>
                    <td class="right">${{ number_format((float) $invoice->miscellaneous, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Tax</td>
                    <td class="right">${{ number_format((float) $invoice->tax, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td>Invoice Total</td>
                    <td class="right">${{ number_format((float) $invoice->invoice_total, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Payments</td>
                    <td class="right">${{ number_format((float) $invoice->total_payments, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Credits</td>
                    <td class="right">${{ number_format((float) $invoice->total_credits, 2) }}</td>
                </tr>
                <tr class="balance">
                    <td>Balance Due</td>
                    <td class="right">${{ number_format((float) $invoice->invoice_balance, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Invoice {{ $invoice->invoice_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
