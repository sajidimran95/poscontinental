<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: letter; margin: 2.22in 0.40in 0.40in 0.40in; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }
        .page-head { position: fixed; top: -1.88in; left: 0; right: 0; }
        .head { width: 100%; margin-bottom: 0; }
        .head td { vertical-align: top; border: none; padding: 0; }
        .head td.brand { width: 70%; text-align: left; }
        .head td.meta-right { width: 30%; text-align: right; }
        .co-name {
            font-size: 19px;
            font-weight: bold;
            margin: 0;
            text-align: left;
            white-space: nowrap;
        }
        .co-line { font-size: 10px; color: #222; margin: 0; line-height: 1.45; text-align: left; }
        .inv-barcode { width: 115px; margin: 0 0 2px auto; text-align: center; }
        .inv-barcode img { display: block; width: 115px; height: 36px; margin: 0 auto; }
        .invoice-info {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }
        .invoice-info th {
            background: #292929;
            color: #fff;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-align: center;
            padding: 5px 6px;
            border: 1px solid #000;
        }
        .invoice-info td {
            text-align: center;
            border: 1px solid #000;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 5px 6px;
            width: 33.33%;
            vertical-align: top;
        }
        .invoice-info small {
            display: block;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.08em;
            margin-bottom: 2px;
        }
        .pagenum:after { content: counter(page) " of {{ (int) ($pageLabel ?? 1) }}"; }
        .addr-wrap { width: 100%; margin: 4px 0 3px; border-collapse: collapse; }
        .addr-wrap > tr > td,
        .addr-wrap > tbody > tr > td { vertical-align: top; padding: 0; border: none; }
        .addr-wrap td.addr-cell { width: 66%; padding-right: 6px; }
        .addr-wrap td.inv-cell { width: 34%; }
        .addr-box { width: 100%; border-collapse: collapse; margin: 0; }
        .addr-box th {
            width: 50%;
            background: none;
            color: #000;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.06em;
            padding: 3px 7px;
            text-align: left;
            border: 1px solid #000;
        }
        .addr-box td {
            width: 50%;
            vertical-align: top;
            text-align: left;
            padding: 5px 7px 6px;
            font-size: 11px;
            border: 1px solid #000;
        }
        .addr-name { font-weight: bold; font-size: 12px; line-height: 1.25; }
        .addr-line { margin: 1px 0 0; font-size: 11px; line-height: 1.25; }
        .label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }
        .meta {
            padding: 3px 0 4px;
            border-bottom: 1px solid #aaa;
            margin-bottom: 0;
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
            padding: 4px 4px;
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
        /* End of category group: just a touch stronger than normal row lines */
        table.items tbody tr.is-cat-end td {
            border-bottom: 1px solid #666;
            padding-bottom: 4px;
        }
        table.items tbody tr.is-cat-end + tr td {
            padding-top: 4px;
        }
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

    // Category-wise grouping + A–Z within category (no serial column).
    $lineSortKey = static fn ($line) => [
        mb_strtoupper(trim((string) ($line->item?->category?->name ?? 'ZZZZ'))),
        (int) ($line->item?->category_id ?? PHP_INT_MAX),
        (float) ($line->qty_ordered ?? 0) < 0 ? 0 : 1,
        mb_strtoupper(trim((string) ($line->description ?? $line->item?->description ?? ''))),
        mb_strtoupper(trim((string) ($line->item_code ?? $line->item?->item_code ?? ''))),
        (int) ($line->line_no ?? 0),
        (int) ($line->id ?? 0),
    ];
    $lines = ($order?->lines ?? collect())
        ->sort(function ($a, $b) use ($lineSortKey) {
            [$ka, $kb] = [$lineSortKey($a), $lineSortKey($b)];
            foreach ($ka as $i => $va) {
                $cmp = is_int($va) || is_float($va) ? $va <=> $kb[$i] : strnatcasecmp((string) $va, (string) $kb[$i]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        })
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
    $previousBalance = \App\Models\Invoice::previousOpenInvoices(
        (int) ($order?->company_id ?? $invoice->company_id),
        $order?->customer_id ? (int) $order->customer_id : ($invoice->customer_id ? (int) $invoice->customer_id : null),
        $invoice->id
    )['total'];
    // Credits stay on the credit memo / account — never print or deduct them here
    // (staff and customers were subtracting the same credit a second time).
    $accountBalance = round($previousBalance + $invoiceTotal - $payTotal, 2);
@endphp

<div class="page-head">
<table class="head">
    <tr>
        <td class="brand">
            <div class="co-name">{{ $companyName }}</div>
            <div class="co-line">{{ $addr }}@if ($tel !== '') | Tel: {{ $tel }}@endif</div>
            @if ($email !== '')
                <div class="co-line">{{ $email }}</div>
            @endif
        </td>
        <td class="meta-right">
            {!! Code128Barcode::invoiceHeaderHtml($invoiceNo) !!}
        </td>
    </tr>
</table>

<table class="addr-wrap">
    <tr>
        <td class="addr-cell">
            <table class="addr-box">
                <tr>
                    <th>SOLD TO</th>
                    <th>SHIP TO</th>
                </tr>
                <tr>
                    <td>
                        <div class="addr-name">{{ $billName }}</div>
                        @if ($order?->bill_to_address)
                            <div class="addr-line">{{ $order->bill_to_address }}</div>
                        @endif
                        @if ($billCity !== '')
                            <div class="addr-line">{{ $billCity }}</div>
                        @endif
                        @if ($order?->bill_to_phone)
                            <div class="addr-line">Tel: {{ $order->bill_to_phone }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="addr-name">{{ $shipName }}</div>
                        @if ($order?->ship_to_address ?: $order?->bill_to_address)
                            <div class="addr-line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
                        @endif
                        @if ($shipCity !== '')
                            <div class="addr-line">{{ $shipCity }}</div>
                        @endif
                        @if ($order?->ship_to_phone ?: $order?->bill_to_phone)
                            <div class="addr-line">Tel: {{ $order->ship_to_phone ?: $order->bill_to_phone }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="inv-cell">
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

<div class="meta">
    Account: <b>{{ $accountNo !== '' ? $accountNo : '-' }}</b>
    <span class="gap">Order: <b>{{ $order?->order_number ?: '-' }}</b></span>
    <span class="gap">Sales rep: <b>{{ $salesRepLabel !== '' ? $salesRepLabel : '-' }}</b></span>
    <span class="gap">Terms: <b>{{ $paymentLabel !== '' ? $paymentLabel : '-' }}</b></span>
    <span class="gap">Route: {{ $routeLabel !== '' ? $routeLabel : '-' }}</span>
</div>
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
        @forelse ($lines as $i => $line)
            @php
                $qty = (float) $line->qty_ordered;
                $itemCode = (string) $line->item_code;
                $itemBarcode = $showItemBarcodes ? SalesOrderLinePresentation::itemBarcode($line) : '';
                $groupKey = static fn ($l) => (int) ($l->item?->category_id ?? 0);
                $next = $lines->get($i + 1);
                $isCatEnd = $next === null || $groupKey($next) !== $groupKey($line);
            @endphp
            <tr @class(['is-cat-end' => $isCatEnd && $next !== null])>
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
