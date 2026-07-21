<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
        }
        .brand-bar {
            background: #1e3a5f;
            color: #fff;
            padding: 14px 16px;
            margin: 0 0 16px;
        }
        .brand-bar table { width: 100%; border-collapse: collapse; }
        .brand-bar td { border: none; padding: 0; vertical-align: middle; color: #fff; }
        .brand-name { font-size: 16px; font-weight: bold; letter-spacing: 0.02em; }
        .brand-sub { font-size: 9px; color: #cbd5e1; margin-top: 3px; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { font-size: 9.5px; text-align: right; color: #e2e8f0; margin-top: 4px; }
        .status {
            display: inline-block;
            margin-top: 6px;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.04em;
            background: #dbeafe;
            color: #1e3a8a;
        }
        .status-paid { background: #bbf7d0; color: #14532d; }
        .status-open { background: #fecaca; color: #7f1d1d; }
        .section { margin-bottom: 14px; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -4px 12px; }
        .card {
            width: 33%;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 11px;
        }
        .card-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 6px;
        }
        .card strong { font-size: 11px; color: #0f172a; }
        .card .line { margin: 2px 0; color: #334155; }
        .muted { color: #64748b; }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.lines th {
            background: #1e3a5f;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 7px 8px;
            border: none;
            text-align: left;
        }
        table.lines td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.lines tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mono { font-family: DejaVu Sans Mono, DejaVu Sans, sans-serif; font-size: 10px; }
        .footer-grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .footer-grid > tbody > tr > td { vertical-align: top; border: none; padding: 0; }
        .notes {
            width: 54%;
            padding-right: 16px;
            color: #64748b;
            font-size: 9.5px;
        }
        .totals-wrap { width: 46%; }
        .totals {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .totals td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .totals tr:last-child td { border-bottom: none; }
        .totals .label { color: #475569; }
        .totals .grand td {
            background: #1e3a5f;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            padding: 8px 10px;
        }
        .totals .balance td {
            background: #fef2f2;
            color: #991b1b;
            font-weight: bold;
        }
        .foot {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
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
                <div class="status {{ $isPaid ? 'status-paid' : 'status-open' }}">{{ $invoice->status }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card">
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
        <td class="card">
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
        <td class="card">
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
                <td colspan="7" class="muted center" style="padding:16px">No line items on the linked sales order.</td>
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
