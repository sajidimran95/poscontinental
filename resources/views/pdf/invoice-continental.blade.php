<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: letter; margin: 0.45in; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }
        .head { width: 100%; margin-bottom: 8px; }
        .head td { vertical-align: top; border: none; padding: 0; }
        .brand-logo { width: 30px; height: 57px; }
        .head td.logo { width: 18%; text-align: left; }
        .head td.brand { width: 49%; text-align: center; vertical-align: middle; }
        .head td.meta-right { width: 33%; text-align: right; }
        .head td.addr { text-align: left; padding-top: 2px; }
        .co-name {
            font-size: 21px;
            font-weight: bold;
            margin: 0;
            text-align: center;
            white-space: nowrap;
        }
        .co-line { font-size: 10px; color: #222; margin: 0; line-height: 1.45; text-align: left; }
        .inv-barcode { text-align: right; margin: 0 0 6px; }
        .inv-barcode img { height: 40px; }
        .inv-barcode .digits {
            font-size: 9px;
            letter-spacing: 0.12em;
            margin-top: 2px;
        }
        .invoice-info { width: 230px; margin-left: auto; border: 1px solid #777; }
        .invoice-info th {
            background: #292929;
            color: #fff;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-align: center;
            padding: 5px 6px;
            border: 1px solid #222;
        }
        .invoice-info td {
            text-align: center;
            border: 1px solid #aaa;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 6px 6px;
            width: 33%;
        }
        .invoice-info small {
            display: block;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.08em;
            margin-bottom: 2px;
        }
        .pagenum:after { content: counter(page) " of {{ (int) ($pageLabel ?? 1) }}"; }
        .customers { width: 100%; margin: 8px 0 6px; }
        .customers td {
            width: 50%;
            border: 1px solid #bbb;
            vertical-align: top;
            padding: 8px 10px;
        }
        .label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }
        .cust-name { font-weight: bold; font-size: 12px; }
        .cust-line { margin-top: 1px; font-size: 11px; }
        .meta {
            padding: 7px 0 8px;
            border-bottom: 1px solid #aaa;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .meta b { font-weight: bold; }
        .meta .gap { padding: 0 10px; }
        table.items { width: 100%; table-layout: fixed; }
        table.items thead { display: table-header-group; }
        table.items th {
            background: #292929;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-align: left;
            padding: 6px 4px;
            border-bottom: 1px solid #222;
            white-space: nowrap;
        }
        table.items td {
            padding: 5px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
            vertical-align: top;
        }
        table.items tr { page-break-inside: avoid; }
        table.items th.num, table.items td.num { text-align: right; white-space: nowrap; }
        table.items th.qty, table.items td.qty { width: 7%; }
        table.items th.item, table.items td.item { width: 11%; }
        table.items th.desc, table.items td.desc { width: 48%; }
        table.items th.uom, table.items td.uom { width: 6%; text-align: center; }
        table.items th.price, table.items td.price { width: 10%; }
        table.items th.disc, table.items td.disc { width: 9%; }
        table.items th.amt, table.items td.amt { width: 9%; }
        table.items.is-barcode th.qty, table.items.is-barcode td.qty { width: 6%; }
        table.items.is-barcode th.bc, table.items.is-barcode td.bc { width: 18%; }
        table.items.is-barcode th.item, table.items.is-barcode td.item { width: 10%; }
        table.items.is-barcode th.desc, table.items.is-barcode td.desc { width: 36%; }
        table.items.is-barcode th.uom, table.items.is-barcode td.uom { width: 5%; }
        table.items.is-barcode th.price, table.items.is-barcode td.price { width: 9%; }
        table.items.is-barcode th.disc, table.items.is-barcode td.disc { width: 8%; }
        table.items.is-barcode th.amt, table.items.is-barcode td.amt { width: 8%; }
        td.bc { text-align: left; }
        td.bc img { height: 34px; max-width: 100%; }
        td.bc .digits { font-size: 7px; letter-spacing: 0.04em; margin-top: 1px; }
        .bottom { width: 100%; margin-top: 16px; page-break-inside: avoid; }
        .bottom td { vertical-align: top; border: none; padding: 0; }
        .bottom .categories { width: 46%; padding-right: 18px; }
        .bottom .summary { width: 54%; }
        .sum-table { width: 100%; }
        .sum-table td { padding: 4px 0; font-size: 11px; border: none; }
        .sum-table td.num { text-align: right; white-space: nowrap; }
        .sum-table tr.grand td {
            font-size: 13px;
            font-weight: bold;
            padding-top: 7px;
            border-top: 2px solid #111;
        }
        .hint { font-size: 9px; color: #555; margin: 6px 0 0; line-height: 1.35; }
        .received { margin-top: 14px; font-size: 11px; }
        .sign-line {
            display: inline-block;
            border-bottom: 1px solid #555;
            width: 55%;
            height: 14px;
            margin-left: 4px;
        }
        .doc-foot {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #aaa;
            font-size: 11px;
        }
        .thanks { font-weight: bold; }
    </style>
</head>
<body>
@php
    use App\Support\Code128Barcode;
    use App\Support\DocumentMerchandiseTotals;
    use App\Support\SalesOrderLinePresentation;

    $order = $invoice->salesOrder;
    $showItemBarcodes = (bool) ($includeSecondaryBarcodeCopy ?? false);
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $street = trim((string) ($company?->address ?? ''));
    $cityLine = collect([
        trim((string) ($company?->city ?? '')),
        strtoupper(trim((string) ($company?->state ?? ''))),
        trim((string) ($company?->zip_code ?? '')),
    ])->filter()->implode(', ');
    $addr = trim(collect([$street, $cityLine])->filter()->implode(', '));
    if ($addr === '') {
        $addr = trim(($companyAddress ?? '').', '.($companyCityLine ?? ''), ' ,');
    }
    $telDigits = preg_replace('/\D+/', '', (string) ($company?->phone ?? ''));
    $tel = strlen($telDigits) === 10
        ? substr($telDigits, 0, 3).'-'.substr($telDigits, 3, 3).'-'.substr($telDigits, 6)
        : preg_replace('/^Tel:\s*/i', '', (string) ($companyTel ?? $company?->phone ?? ''));
    $email = trim((string) ($companyEmail ?? $company?->email ?? ''));
    $logoPath = $logoPath ?? null;
    $invoiceNo = (string) $invoice->invoice_number;
    $invoiceDate = optional($invoice->invoice_date)?->format('m/d/Y') ?: '';

    $billName = $order?->bill_to_name ?: ($invoice->customer?->company_name ?: '');
    $shipName = $order?->ship_to_name ?: $billName;
    $billCity = collect([$order?->bill_to_city, $order?->bill_to_state, $order?->bill_to_zip])->filter()->implode(', ');
    $shipCity = collect([
        $order?->ship_to_city ?: $order?->bill_to_city,
        $order?->ship_to_state ?: $order?->bill_to_state,
        $order?->ship_to_zip ?: $order?->bill_to_zip,
    ])->filter()->implode(', ');

    $accountNo = $order?->customer?->customer_id ?: ($invoice->customer?->customer_id ?: '');
    $salesRepLabel = $order?->salesRep?->name ?: '';
    $paymentLabel = $order?->paymentTerm?->name ?: ($order?->paymentTerm?->code ?: '-');
    $routeLabel = $order?->route?->name ?: ($order?->route?->code ?: '-');

    $lines = ($order?->lines ?? collect())
        ->sortBy(fn ($line) => [(int) ($line->line_no ?? 0), (int) $line->id])
        ->values();
    $buckets = DocumentMerchandiseTotals::fromLines($lines);

    $fmtMoney = static fn (float $v): string => '$'.number_format($v, 2);
    $fmtQty = static function (float $q): string {
        if (abs($q - round($q)) < 0.0005) {
            return (string) (int) round($q);
        }

        return rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.') ?: '0';
    };

    $invoiceTotal = (float) $invoice->invoice_total;
    $freight = (float) $invoice->freight;
    $tax = (float) $invoice->tax;
    $tradeDiscount = (float) $invoice->trade_discount;
    $misc = (float) $invoice->miscellaneous;
    $payTotal = (float) $invoice->total_payments;
    $creditTotal = (float) $invoice->total_credits;
    $previousBalance = \App\Models\Invoice::previousOpenInvoices(
        (int) ($order?->company_id ?? $invoice->company_id),
        $order?->customer_id ? (int) $order->customer_id : ($invoice->customer_id ? (int) $invoice->customer_id : null),
        $invoice->id
    )['total'];
    $accountBalance = round($previousBalance + $invoiceTotal - $payTotal - $creditTotal, 2);
@endphp

<table class="head">
    <tr>
        <td class="logo">
            @if ($logoPath && is_file($logoPath))
                <img class="brand-logo" src="{{ $logoPath }}" alt="">
            @endif
        </td>
        <td class="brand">
            <div class="co-name">{{ $companyName }}</div>
        </td>
        <td class="meta-right">
            <div class="inv-barcode">
                {!! Code128Barcode::html($invoiceNo, 2, 40, 'right') !!}
                <div class="digits">{{ $invoiceNo }}</div>
            </div>
        </td>
    </tr>
    <tr>
        <td class="addr" colspan="2">
            <div class="co-line">{{ $addr }}@if ($tel !== '') | Tel: {{ $tel }}@endif</div>
            @if ($email !== '')
                <div class="co-line">{{ $email }}</div>
            @endif
        </td>
        <td class="meta-right">
            <table class="invoice-info">
                <tr><th colspan="3">INVOICE</th></tr>
                <tr>
                    <td><small>NUMBER</small>{{ $invoiceNo }}</td>
                    <td><small>DATE</small>{{ $invoiceDate }}</td>
                    <td><small>PAGE</small><span class="pagenum"></span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="customers">
    <tr>
        <td>
            <div class="label">SOLD TO</div>
            <div class="cust-name">{{ $billName }}</div>
            @if ($order?->bill_to_address)
                <div class="cust-line">{{ $order->bill_to_address }}</div>
            @endif
            @if ($billCity !== '')
                <div class="cust-line">{{ $billCity }}</div>
            @endif
            @if ($order?->bill_to_phone)
                <div class="cust-line">Tel: {{ $order->bill_to_phone }}</div>
            @endif
        </td>
        <td>
            <div class="label">SHIP TO</div>
            <div class="cust-name">{{ $shipName }}</div>
            @if ($order?->ship_to_address ?: $order?->bill_to_address)
                <div class="cust-line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
            @endif
            @if ($shipCity !== '')
                <div class="cust-line">{{ $shipCity }}</div>
            @endif
            @if ($order?->ship_to_phone ?: $order?->bill_to_phone)
                <div class="cust-line">Tel: {{ $order->ship_to_phone ?: $order->bill_to_phone }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="meta">
    Account: <b>{{ $accountNo !== '' ? $accountNo : '-' }}</b>
    <span class="gap">Order: <b>{{ $order?->order_number ?: '-' }}</b></span>
    <span class="gap">Sales rep: <b>{{ $salesRepLabel !== '' ? $salesRepLabel : '-' }}</b></span>
    <span class="gap">Terms: <b>{{ $paymentLabel !== '' ? $paymentLabel : '-' }}</b></span>
    <span class="gap">Route: {{ $routeLabel !== '' ? $routeLabel : '-' }}</span>
</div>

<table @class(['items', 'is-barcode' => $showItemBarcodes])>
    <thead>
        <tr>
            <th class="qty">QTY</th>
            @if ($showItemBarcodes)
                <th class="bc"></th>
            @endif
            <th class="item">ITEM</th>
            <th class="desc">DESCRIPTION</th>
            <th class="uom">U/M</th>
            <th class="num price">PRICE</th>
            <th class="num disc">{{ $showItemBarcodes ? 'DISC.' : 'DISCOUNT' }}</th>
            <th class="num amt">AMOUNT</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $line)
            @php
                $qty = (float) $line->qty_ordered;
                $itemCode = (string) $line->item_code;
                $itemBarcode = $showItemBarcodes ? SalesOrderLinePresentation::itemBarcode($line) : '';
            @endphp
            <tr>
                <td class="qty">{{ $fmtQty($qty) }}</td>
                @if ($showItemBarcodes)
                    <td class="bc">
                        @if ($itemBarcode !== '')
                            {!! Code128Barcode::html($itemBarcode, 1, 34, 'left') !!}
                            <div class="digits">{{ $itemBarcode }}</div>
                        @endif
                    </td>
                @endif
                <td class="item">{{ $itemCode }}</td>
                <td class="desc">
                    {{ $line->description }}
                    @if ($qty < 0)
                        <div style="font-weight:bold;font-size:9px;margin-top:1px">
                            {{ SalesOrderLinePresentation::lineMessage($line) ?: \App\Services\InvoiceReturnService::RETURN_NOTE }}
                        </div>
                    @endif
                </td>
                <td class="uom">{{ SalesOrderLinePresentation::uom($line) }}</td>
                <td class="num price">{{ number_format((float) $line->price, 2) }}</td>
                <td class="num disc">{{ number_format((float) $line->discount, 2) }}</td>
                <td class="num amt">{{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $showItemBarcodes ? 8 : 7 }}" style="text-align:center;padding:12px;color:#666">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="bottom">
    <tr>
        <td class="categories">
            <div class="label">PRODUCT SUMMARY</div>
            <table class="sum-table">
                <tr>
                    <td>Tobacco (excl. cigarettes)</td>
                    <td class="num">{{ $fmtMoney((float) $buckets['tobacco_total']) }}</td>
                </tr>
                <tr>
                    <td>Cigarettes</td>
                    <td class="num">{{ $fmtMoney((float) $buckets['cigarette_total']) }}</td>
                </tr>
                <tr>
                    <td>Other products</td>
                    <td class="num">{{ $fmtMoney((float) $buckets['other_total']) }}</td>
                </tr>
                <tr>
                    <td>Cigarette qty (selling units)</td>
                    <td class="num">{{ $fmtQty((float) $buckets['cigarette_qty']) }}</td>
                </tr>
                <tr>
                    <td><strong>Total count</strong></td>
                    <td class="num"><strong>{{ $fmtQty((float) $buckets['all_qty']) }}</strong></td>
                </tr>
            </table>
            <div class="received">Received by: <span class="sign-line">&nbsp;</span></div>
            <p class="hint">Signature acknowledges receipt of goods.</p>
        </td>
        <td class="summary">
            <div class="label">ACCOUNT SUMMARY</div>
            <table class="sum-table">
                @if (abs($tax) > 0.004)
                    <tr><td>Tax</td><td class="num">{{ $fmtMoney($tax) }}</td></tr>
                @endif
                @if (abs($tradeDiscount) > 0.004)
                    <tr><td>Trade discount</td><td class="num">{{ $fmtMoney($tradeDiscount) }}</td></tr>
                @endif
                @if (abs($misc) > 0.004)
                    <tr><td>Miscellaneous</td><td class="num">{{ $fmtMoney($misc) }}</td></tr>
                @endif
                <tr>
                    <td>Invoice total</td>
                    <td class="num">{{ $fmtMoney($invoiceTotal) }}</td>
                </tr>
                <tr>
                    <td>Freight</td>
                    <td class="num">{{ $fmtMoney($freight) }}</td>
                </tr>
                <tr>
                    <td>Previous balance</td>
                    <td class="num">{{ $fmtMoney((float) $previousBalance) }}</td>
                </tr>
                @if ($creditTotal > 0.004)
                    <tr>
                        <td>Credit memo</td>
                        <td class="num">-{{ $fmtMoney($creditTotal) }}</td>
                    </tr>
                @endif
                @if ($payTotal > 0.004)
                    <tr>
                        <td>Payments</td>
                        <td class="num">-{{ $fmtMoney($payTotal) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td>Total account balance</td>
                    <td class="num">{{ $fmtMoney($accountBalance) }}</td>
                </tr>
            </table>
            <p class="hint">Account balance includes this invoice and prior outstanding balance. Payment timing follows account terms.</p>
        </td>
    </tr>
</table>

<div class="doc-foot">
    Please reference invoice {{ $invoiceNo }} with payments.
    <span class="thanks">Thank you for your business.</span>
</div>
</body>
</html>
