<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Report — {{ ucfirst($mode) }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $rowCount = is_countable($rows) ? count($rows) : $rows->count();
    $isInvoices = $mode === 'invoices';
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Sales Analytics</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">SALES REPORT</div>
                <div class="doc-meta">
                    {{ ucfirst($mode) }}
                    &nbsp;·&nbsp;
                    {{ $dateFrom }} → {{ $dateTo }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="summary section">
    <tr>
        <td>
            <div class="lbl">View</div>
            <div class="val" style="font-size:11px">{{ $isInvoices ? 'Invoices' : 'Orders' }}</div>
        </td>
        <td>
            <div class="lbl">Period</div>
            <div class="val" style="font-size:11px">{{ $dateFrom }} – {{ $dateTo }}</div>
        </td>
        <td>
            <div class="lbl">Records</div>
            <div class="val">{{ number_format($rowCount) }}</div>
        </td>
        <td>
            <div class="lbl">Grand Total</div>
            <div class="val">${{ number_format((float) $grandTotal, 2) }}</div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        @if ($isInvoices)
            <tr>
                <th style="width:28px">#</th>
                <th style="width:100px">Invoice</th>
                <th style="width:80px">Date</th>
                <th style="width:100px">Order</th>
                <th>Customer</th>
                <th style="width:70px">Status</th>
                <th class="right" style="width:90px">Total</th>
            </tr>
        @else
            <tr>
                <th style="width:28px">#</th>
                <th style="width:100px">Order</th>
                <th style="width:80px">Date</th>
                <th>Customer</th>
                <th style="width:70px">Status</th>
                <th class="right" style="width:90px">Total</th>
            </tr>
        @endif
    </thead>
    <tbody>
        @if ($isInvoices)
            @forelse ($rows as $i => $inv)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="mono">{{ $inv->invoice_number }}</td>
                    <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                    <td class="mono muted">{{ $inv->salesOrder?->order_number ?: '—' }}</td>
                    <td>{{ $inv->customer?->company_name ?: '—' }}</td>
                    <td>
                        <span class="status {{ strtoupper((string) $inv->status) === 'PAID' ? 'status-paid' : 'status-open' }}">
                            {{ $inv->status }}
                        </span>
                    </td>
                    <td class="right"><strong>${{ number_format((float) $inv->invoice_total, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No invoices in this period.</td></tr>
            @endforelse
        @else
            @forelse ($rows as $i => $ord)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="mono">{{ $ord->order_number }}</td>
                    <td>{{ optional($ord->order_date)?->format('n/j/Y') }}</td>
                    <td>{{ $ord->customer?->company_name ?: '—' }}</td>
                    <td><span class="status">{{ $ord->status }}</span></td>
                    <td class="right"><strong>${{ number_format((float) $ord->total, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No orders in this period.</td></tr>
            @endforelse
        @endif
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            <div>Report generated from live sales data for the selected date range.</div>
            <div style="margin-top:6px">{{ $rowCount }} {{ $isInvoices ? 'invoice' : 'order' }} record(s) included.</div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">Records</td>
                    <td class="right">{{ number_format($rowCount) }}</td>
                </tr>
                <tr class="grand">
                    <td>Grand Total</td>
                    <td class="right">${{ number_format((float) $grandTotal, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Sales Report ({{ ucfirst($mode) }}) · {{ $dateFrom }} to {{ $dateTo }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
