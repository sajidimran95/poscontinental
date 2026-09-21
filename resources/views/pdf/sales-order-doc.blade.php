<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Order {{ $order->order_number }}</title>
    <style>
        @page { size: letter; margin: 0.4in 0.48in 0.45in; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.25;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .hdr td { vertical-align: top; padding: 0; border: none; }
        .co-name {
            font-family: Times-Bold, Times, "Times New Roman", serif;
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 3px;
        }
        .co-line { font-size: 9.5px; margin-top: 1px; }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            text-align: right;
            margin: 0 0 2px;
            letter-spacing: 0.01em;
        }
        .barcode-wrap { text-align: right; margin: 2px 0 4px; }
        .barcode-wrap img { max-width: 200px; height: 40px; }
        .parties-wrap { width: 100%; margin: 10px 0 10px; table-layout: fixed; border-collapse: collapse; }
        .parties-wrap > tbody > tr > td { vertical-align: top; padding: 0; border: none; }
        .parties-wrap td.parties-main { width: 68%; }
        .parties-wrap td.parties-side { width: 32%; padding-left: 8px; text-align: right; }
        .parties { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .parties td.bill,
        .parties td.ship {
            width: 50%;
            padding: 5px 7px 6px;
            vertical-align: top;
            border: 1px solid #000;
        }
        .parties td.ship { border-left: none; }
        .party-lbl { font-weight: bold; font-size: 10px; margin-bottom: 3px; }
        .party-name { font-weight: bold; font-size: 11px; margin-bottom: 1px; }
        .party-line { font-size: 9.5px; margin-top: 1px; }
        .party-meta {
            margin-top: 10px;
            padding-top: 4px;
            border-top: 1px solid #999;
            font-size: 9.5px;
            line-height: 1.55;
        }
        .party-meta .k { font-weight: bold; }
        .meta-right {
            text-align: right;
            font-size: 10px;
            line-height: 1.55;
        }
        .meta-right .k { font-weight: bold; }
        table.items {
            width: 100%;
            margin-top: 2px;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.items th.col-qty, table.items td.col-qty { width: 8%; }
        table.items th.col-item, table.items td.col-item { width: 11%; }
        table.items th.col-desc, table.items td.col-desc { width: 50%; }
        table.items th.col-uom, table.items td.col-uom { width: 7%; }
        table.items th.col-price, table.items td.col-price { width: 12%; }
        table.items th.col-total, table.items td.col-total { width: 12%; }
        table.items th {
            background: #333;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            padding: 5px 4px;
            vertical-align: middle;
            white-space: nowrap;
            border: none;
            border-top: 1px solid #222;
            border-bottom: 1px solid #222;
            text-align: left;
        }
        table.items thead { display: table-header-group; }
        table.items tfoot { display: table-footer-group; }
        table.items tfoot td {
            padding: 0;
            height: 1px;
            font-size: 1px;
            line-height: 1px;
            border: none;
            border-top: 1px solid #222;
            background: transparent;
        }
        table.items td {
            padding: 3px 4px;
            font-size: 9.5px;
            vertical-align: top;
            border: none;
            word-wrap: break-word;
        }
        table.items tbody tr:last-child td {
            border-bottom: 1px solid #222;
        }
        /* Same category group: dotted bottom line under last item in category */
        table.items tbody tr.is-cat-end td {
            border-bottom: 1px dotted #333;
            padding-bottom: 5px;
        }
        table.items tbody tr.is-cat-end + tr td {
            padding-top: 5px;
        }
        table.items th.col-qty, table.items td.col-qty,
        table.items th.col-price, table.items td.col-price,
        table.items th.col-total, table.items td.col-total {
            text-align: right;
            white-space: nowrap;
        }
        table.items th.col-qty, table.items td.col-qty {
            text-align: center;
            padding-left: 2px;
            padding-right: 3px;
        }
        table.items th.col-uom, table.items td.col-uom { padding-left: 2px; padding-right: 2px; text-align: center; }
        table.items th.col-item, table.items td.col-item { text-align: left; }
        table.items th.col-desc, table.items td.col-desc { text-align: left; }
        table.items td.col-desc { font-size: 10.5px; }
        .line-msg { margin-top: 1px; font-size: 8px; }
        .line-msg-lbl { font-weight: bold; margin-right: 3px; }
        .foot { margin-top: 14px; table-layout: fixed; border-collapse: collapse; }
        .foot td.sum-box {
            border: 1px solid #000;
            padding: 5px 4px 6px;
            text-align: center;
            vertical-align: middle;
            width: 15.5%;
        }
        .foot td.gap {
            width: 2.5%;
            border: none !important;
            padding: 0;
        }
        .foot td.tot-wrap {
            width: 35.5%;
            border: 1px solid #000;
            padding: 0;
            vertical-align: top;
        }
        .sum-lbl {
            display: block;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .sum-val {
            display: block;
            font-size: 12px;
            font-weight: bold;
        }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td {
            border: none;
            border-bottom: 1px solid #000;
            padding: 5px 7px;
            font-size: 10px;
            font-weight: bold;
        }
        .totals tr:last-child td { border-bottom: none; }
        .totals td.lbl { text-align: left; width: 55%; border-right: 1px solid #000; }
        .totals td.amt { text-align: right; width: 45%; }
        .totals tr.grand td { font-size: 12px; }
        .logo-img { max-height: 44px; max-width: 150px; margin-bottom: 2px; }
        .closing {
            width: 100%;
            margin-top: 36px;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .closing td {
            border: none;
            vertical-align: bottom;
            padding: 0;
            font-size: 11px;
            font-weight: bold;
        }
        .closing td.recv { width: 48%; text-align: left; }
        .closing td.thanks { width: 52%; text-align: center; letter-spacing: 0.02em; }
        .closing .uline {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 72%;
            height: 12px;
            margin-left: 4px;
            vertical-align: bottom;
        }
    </style>
</head>
<body>
@php
    use App\Support\Code128Barcode;
    use App\Support\DocumentMerchandiseTotals;
    use App\Support\SalesOrderLinePresentation;

    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $companyAddress ?? ($company?->letterheadAddress() ?? config('company.address', '3802 TRADE CENTER DR'));
    $companyCityLine = $companyCityLine ?? ($company?->letterheadCityLine() ?? config('company.city_line', 'ANN ARBOR, MI 48108'));
    $companyTel = $companyTel ?? ($company?->letterheadTel() ?? config('company.tel', 'Tel:7346773510'));
    $companyFax = $companyFax ?? ($company?->letterheadFax() ?? config('company.fax', 'Fax:7346773567'));
    $logoPath = $logoPath ?? null;

    $billCity = collect([$order->bill_to_city, $order->bill_to_state, $order->bill_to_zip])->filter()->implode(' ');
    $shipCity = collect([
        $order->ship_to_city ?: $order->bill_to_city,
        $order->ship_to_state ?: $order->bill_to_state,
        $order->ship_to_zip ?: $order->bill_to_zip,
    ])->filter()->implode(' ');

    $paymentLabel = $order->paymentTerm?->name ?: $order->paymentTerm?->code ?: '';
    $driverLabel = $order->invoice?->driver ?: '';
    $routeLabel = $order->route?->name ?: $order->route?->code ?: '';
    $accountNo = $order->customer?->customer_id ?: '';
    $salesRepLabel = $order->salesRep?->name ?: '';
    $barcodeValue = (string) ($barcodeValue ?? $order->order_number);
    $orderDate = optional($order->order_date)?->format('m/d/Y') ?: '';
    $pageOf = 'Page 1 of '.($pageLabel ?? '1');

    $lines = $order->lines
        ->sortBy([
            fn ($line) => mb_strtoupper(trim((string) ($line->item?->category?->name ?? 'ZZZZ'))),
            fn ($line) => mb_strtoupper(trim((string) ($line->item_code ?? $line->item?->item_code ?? ''))),
            fn ($line) => mb_strtoupper(trim((string) ($line->description ?? $line->item?->description ?? ''))),
            fn ($line) => (int) ($line->line_no ?? 0),
            fn ($line) => (int) $line->id,
        ])
        ->values();
    $buckets = DocumentMerchandiseTotals::fromLines($lines);

    $docSubtotal = (float) ($order->subtotal ?? 0);
    $docDiscount = (float) ($order->trade_discount ?? 0);
    $docFreight = (float) ($order->freight ?? 0);
    $docMisc = (float) ($order->miscellaneous ?? 0);
    $docTax = (float) ($order->tax ?? 0);
    $docTotal = (float) ($order->total ?? 0);
    $tobaccoCount = (int) $buckets['tobacco_count'] + (int) $buckets['cigarette_count'];
    $tobaccoTotal = (float) $buckets['tobacco_total'] + (float) $buckets['cigarette_total'];

    $addrLine = trim($companyAddress.' '.$companyCityLine);
@endphp

<table class="hdr">
    <tr>
        <td style="width:54%">
            @if ($logoPath && is_file($logoPath))
                <img class="logo-img" src="{{ $logoPath }}" alt="">
            @endif
            <div class="co-name">{{ $companyName }}</div>
            <div class="co-line">{{ $addrLine }}</div>
            <div class="co-line">{{ $companyTel }}@if ($companyFax) &nbsp; {{ $companyFax }}@endif</div>
        </td>
        <td style="width:46%">
            <div class="doc-title">Sales Order</div>
            <div class="barcode-wrap">
                {!! Code128Barcode::html($barcodeValue, 2, 40) !!}
            </div>
        </td>
    </tr>
</table>

<table class="parties-wrap">
    <tr>
        <td class="parties-main">
            <table class="parties">
                <tr>
                    <td class="bill">
                        <div class="party-lbl">Bill to:</div>
                        <div class="party-name">{{ $order->bill_to_name ?: ($order->customer?->company_name ?: '') }}</div>
                        @if ($order->bill_to_address)
                            <div class="party-line">{{ $order->bill_to_address }}</div>
                        @endif
                        @if ($billCity !== '')
                            <div class="party-line">{{ $billCity }}</div>
                        @endif
                        @if ($order->bill_to_phone)
                            <div class="party-line">{{ $order->bill_to_phone }}</div>
                        @endif
                        <div class="party-meta">
                            <div><span class="k">Account No.:</span> {{ $accountNo }}</div>
                            <div><span class="k">Payment Terms:</span> {{ $paymentLabel }}</div>
                        </div>
                    </td>
                    <td class="ship">
                        <div class="party-lbl">Ship to:</div>
                        <div class="party-name">{{ $order->ship_to_name ?: ($order->bill_to_name ?: ($order->customer?->company_name ?: '')) }}</div>
                        @if ($order->ship_to_address ?: $order->bill_to_address)
                            <div class="party-line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
                        @endif
                        @if ($shipCity !== '')
                            <div class="party-line">{{ $shipCity }}</div>
                        @endif
                        @if ($order->ship_to_phone ?: $order->bill_to_phone)
                            <div class="party-line">{{ $order->ship_to_phone ?: $order->bill_to_phone }}</div>
                        @endif
                        <div class="party-meta">
                            <div><span class="k">Driver:</span> {{ $driverLabel }}</div>
                            <div><span class="k">Route:</span> {{ $routeLabel }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="parties-side">
            <div class="meta-right">
                <div>{{ $pageOf }}</div>
                <div><span class="k">Order No.:</span> {{ $order->order_number }}</div>
                <div><span class="k">Order Date:</span> {{ $orderDate }}</div>
                <div><span class="k">Sales Rep.:</span> {{ $salesRepLabel }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="col-qty">Quantity</th>
            <th class="col-item">Item</th>
            <th class="col-desc">Description</th>
            <th class="col-uom">U/M</th>
            <th class="col-price">Price</th>
            <th class="col-total">Total</th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
    </tfoot>
    <tbody>
        @forelse ($lines as $i => $line)
            @php
                $uomLabel = SalesOrderLinePresentation::uom($line);
                $catId = (int) ($line->item?->category_id ?? 0);
                $next = $lines->get($i + 1);
                $nextCatId = $next ? (int) ($next->item?->category_id ?? 0) : null;
                $isCatEnd = $next === null || $nextCatId !== $catId;
            @endphp
            <tr @class(['is-cat-end' => $isCatEnd && $next !== null])>
                <td class="col-qty">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                <td class="col-item">{{ $line->item_code }}</td>
                <td class="col-desc">
                    <div>{{ $line->description }}</div>
                </td>
                <td class="col-uom">{{ $uomLabel }}</td>
                <td class="col-price">${{ number_format((float) $line->price, 2) }}</td>
                <td class="col-total">${{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding:12px;text-align:center;color:#666">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="foot">
    <tr>
        <td class="sum-box">
            <span class="sum-lbl">Tobacco Items</span>
            <span class="sum-val">{{ $tobaccoCount }}</span>
        </td>
        <td class="sum-box">
            <span class="sum-lbl">Total Tobacco</span>
            <span class="sum-val">${{ number_format($tobaccoTotal, 2) }}</span>
        </td>
        <td class="sum-box">
            <span class="sum-lbl">Total Others</span>
            <span class="sum-val">${{ number_format((float) $buckets['other_total'], 2) }}</span>
        </td>
        <td class="sum-box">
            <span class="sum-lbl">Total All Items</span>
            <span class="sum-val">{{ (int) $buckets['all_count'] }}</span>
        </td>
        <td class="gap">&nbsp;</td>
        <td class="tot-wrap">
            <table class="totals">
                <tr>
                    <td class="lbl">Sub Total</td>
                    <td class="amt">${{ number_format($docSubtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Discount</td>
                    <td class="amt">${{ number_format($docDiscount, 2) }}</td>
                </tr>
                @if ($docFreight != 0.0)
                    <tr>
                        <td class="lbl">Freight</td>
                        <td class="amt">${{ number_format($docFreight, 2) }}</td>
                    </tr>
                @endif
                @if ($docMisc != 0.0)
                    <tr>
                        <td class="lbl">Miscellaneous</td>
                        <td class="amt">${{ number_format($docMisc, 2) }}</td>
                    </tr>
                @endif
                @if ($docTax != 0.0)
                    <tr>
                        <td class="lbl">Tax</td>
                        <td class="amt">${{ number_format($docTax, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td class="lbl">Total</td>
                    <td class="amt">${{ number_format($docTotal, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="closing">
    <tr>
        <td class="recv">Received By<span class="uline">&nbsp;</span></td>
        <td class="thanks">THANK YOU FOR YOUR BUSINESS</td>
    </tr>
</table>
</body>
</html>
