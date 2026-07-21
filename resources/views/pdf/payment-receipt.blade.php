<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt — {{ $invoice->invoice_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Accounts Receivable</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">PAYMENT RECEIPT</div>
                <div class="doc-meta">
                    Invoice #{{ $invoice->invoice_number }}
                    &nbsp;·&nbsp;
                    {{ optional($payment->payment_date)?->format('M j, Y') }}
                </div>
                <div class="status status-paid">RECEIVED</div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card" style="width:50%">
            <div class="card-title">Received From</div>
            <strong>{{ $invoice->customer?->company_name ?: ($invoice->customer?->contact ?: '—') }}</strong>
            @if ($invoice->customer?->customer_id)
                <div class="line muted mono">ID: {{ $invoice->customer->customer_id }}</div>
            @endif
            @if ($invoice->customer?->address)
                <div class="line">{{ $invoice->customer->address }}</div>
            @endif
        </td>
        <td class="card" style="width:50%">
            <div class="card-title">Invoice Reference</div>
            <div class="line"><span class="muted">Invoice No:</span> <strong class="mono">{{ $invoice->invoice_number }}</strong></div>
            <div class="line"><span class="muted">Invoice Date:</span> {{ optional($invoice->invoice_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Order No:</span> {{ $invoice->salesOrder?->order_number ?: '—' }}</div>
            <div class="line"><span class="muted">Invoice Status:</span> {{ $invoice->status }}</div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:100px">Payment Date</th>
            <th style="width:120px">Method</th>
            <th class="right" style="width:100px">Amount</th>
            <th>Comments</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ optional($payment->payment_date)?->format('n/j/Y') }}</td>
            <td><strong>{{ $payment->payment_method ?: '—' }}</strong></td>
            <td class="right"><strong>${{ number_format((float) $payment->amount, 2) }}</strong></td>
            <td class="muted">{{ $payment->comments ?: '—' }}</td>
        </tr>
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            <div>Thank you for your payment. Please retain this receipt for your records.</div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">This Payment</td>
                    <td class="right">${{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Invoice Total</td>
                    <td class="right">${{ number_format((float) $invoice->invoice_total, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Total Payments</td>
                    <td class="right">${{ number_format((float) $invoice->total_payments, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Total Credits</td>
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
    {{ $companyName }} · Payment Receipt · Invoice {{ $invoice->invoice_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
