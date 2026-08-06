<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Report by Customer</title>
    <style>
        @page { margin: 0.55in 0.5in; }
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11px;
            color: #000;
            margin: 0;
        }
        .meta {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #333;
            margin-bottom: 12px;
            border-bottom: 1px solid #999;
            padding-bottom: 6px;
        }
        .customer {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .cust-head {
            margin-bottom: 4px;
        }
        .cust-title {
            font-weight: bold;
            font-size: 12px;
            overflow: hidden;
        }
        .cust-name {
            float: left;
            text-transform: uppercase;
        }
        .cust-id {
            float: right;
        }
        .cust-line {
            clear: both;
            font-size: 11px;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 2px;
        }
        table.lines th {
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 2px 3px 4px;
            font-size: 10px;
        }
        table.lines td {
            padding: 1px 3px;
            vertical-align: top;
            font-size: 10.5px;
        }
        .num { text-align: right; white-space: nowrap; }
        th.num { text-align: right; }
        .w-date { width: 9%; }
        .w-inv { width: 10%; }
        .w-ord { width: 10%; }
        .w-num { width: 11.8%; }
        tr.totals td {
            border-top: 1px solid #000;
            font-weight: bold;
            padding-top: 4px;
        }
        tr.grand td {
            border-top: 2px solid #000;
            font-weight: bold;
            padding-top: 5px;
        }
        .empty {
            text-align: center;
            color: #666;
            padding: 24px;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
@php
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $fromLabel = \Carbon\Carbon::parse($dateFrom)->format('n/j/Y');
    $toLabel = \Carbon\Carbon::parse($dateTo)->format('n/j/Y');
    $period = $dateFrom === $dateTo ? $fromLabel : $fromLabel.' – '.$toLabel;
@endphp

<div class="meta">
    <strong>{{ $companyName }}</strong>
    &nbsp;·&nbsp; Sales Report by Customer
    &nbsp;·&nbsp; {{ $period }}
    &nbsp;·&nbsp; Generated {{ now()->format('M j, Y g:i A') }}
</div>

@forelse ($groups as $group)
    @php
        $cust = $group['customer'];
        $name = $cust?->company_name ?: 'Unknown Customer';
        $code = $cust?->customer_id ?: '—';
        $addr = trim((string) ($cust?->address ?? ''));
        $cityLine = '';
        if ($cust?->city && $cust?->state) {
            $cityLine = $cust->city.', '.$cust->state.($cust->zip_code ? ' '.$cust->zip_code : '');
        } elseif ($cust?->city) {
            $cityLine = $cust->city.($cust->zip_code ? ' '.$cust->zip_code : '');
        }
        $phone = $cust?->telephone ?: ($cust?->mobile ?: '');
    @endphp
    <div class="customer">
        <div class="cust-head">
            <div class="cust-title">
                <span class="cust-name">{{ $name }}</span>
                <span class="cust-id">{{ $code }}</span>
            </div>
            @if ($addr !== '')
                <div class="cust-line">{{ $addr }}</div>
            @endif
            @if ($cityLine !== '')
                <div class="cust-line">{{ $cityLine }}</div>
            @endif
            @if ($phone !== '')
                <div class="cust-line">{{ $phone }}</div>
            @endif
        </div>
        <table class="lines">
            <thead>
                <tr>
                    <th class="w-date">Date</th>
                    <th class="w-inv">Inv. No.</th>
                    <th class="w-ord">Order No.</th>
                    <th class="w-num num">Subtotal</th>
                    <th class="w-num num">Miscellaneous</th>
                    <th class="w-num num">Freight</th>
                    <th class="w-num num">Trade Discount</th>
                    <th class="w-num num">Item Discounts</th>
                    <th class="w-num num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $inv)
                    <tr>
                        <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->salesOrder?->order_number }}</td>
                        <td class="num">{{ $money($inv->subtotal) }}</td>
                        <td class="num">{{ $money($inv->miscellaneous) }}</td>
                        <td class="num">{{ $money($inv->freight) }}</td>
                        <td class="num">{{ $money($inv->trade_discount) }}</td>
                        <td class="num">{{ $money($inv->total_discount) }}</td>
                        <td class="num">{{ $money($inv->invoice_total) }}</td>
                    </tr>
                @endforeach
                <tr class="totals">
                    <td colspan="3">Totals for {{ $name }}</td>
                    <td class="num">{{ $money($group['totals']['subtotal']) }}</td>
                    <td class="num">{{ $money($group['totals']['miscellaneous']) }}</td>
                    <td class="num">{{ $money($group['totals']['freight']) }}</td>
                    <td class="num">{{ $money($group['totals']['trade_discount']) }}</td>
                    <td class="num">{{ $money($group['totals']['item_discounts']) }}</td>
                    <td class="num">{{ $money($group['totals']['total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@empty
    <div class="empty">No invoices found for the selected criteria.</div>
@endforelse

@if (isset($grand) && ($grand['count'] ?? 0) > 0)
    <table class="lines">
        <tr class="grand">
            <td colspan="3" style="width:29%">Report totals ({{ number_format($grand['count']) }} invoices)</td>
            <td class="num" style="width:11.8%">{{ $money($grand['subtotal']) }}</td>
            <td class="num" style="width:11.8%">{{ $money($grand['miscellaneous']) }}</td>
            <td class="num" style="width:11.8%">{{ $money($grand['freight']) }}</td>
            <td class="num" style="width:11.8%">{{ $money($grand['trade_discount']) }}</td>
            <td class="num" style="width:11.8%">{{ $money($grand['item_discounts']) }}</td>
            <td class="num" style="width:11.8%">{{ $money($grand['total']) }}</td>
        </tr>
    </table>
@endif
</body>
</html>
