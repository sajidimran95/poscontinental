<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $docTitle ?? 'Sales Order' }} {{ $barcodeValue ?? $order->order_number }}</title>
    <style>
        @page { size: letter; margin: 0.4in 0.45in 0.45in; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #000;
            line-height: 1.28;
            margin: 0;
        }
        table { border-collapse: collapse; }
        .hdr { width: 100%; margin-bottom: 4px; }
        .hdr td { vertical-align: top; border: none; padding: 0; }
        .co-name { font-size: 17px; font-weight: bold; letter-spacing: 0.01em; }
        .co-line { font-size: 9px; margin-top: 1px; }
        .notice {
            font-size: 7.5px;
            text-align: right;
            line-height: 1.25;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .doc-title-box {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            vertical-align: top;
        }
        .doc-title-box th {
            background: #333;
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #222;
        }
        .doc-title-box td {
            border: 1px solid #222;
            text-align: center;
            padding: 3px 4px;
            font-size: 8px;
            font-weight: bold;
        }
        .doc-title-box .val {
            font-size: 11px;
            font-weight: bold;
            padding-top: 2px;
            min-height: 14px;
        }
        .barcode-wrap { text-align: right; margin: 4px 0 2px; width: 100%; }
        .barcode-wrap img { width: 100%; max-width: 280px; height: 56px; }
        .addr-wrap { width: 100%; margin: 10px 0 8px; border-collapse: collapse; }
        .addr-wrap td.addr-cell,
        .addr-wrap td.inv-cell {
            vertical-align: top;
            padding: 0;
            border: none;
        }
        .addr-wrap td.addr-cell { padding-right: 10px; width: 62%; }
        .addr-wrap td.inv-cell { width: 38%; padding-top: 0; }
        .addr-box {
            width: 100%;
            border: 1px solid #222;
            margin: 0;
        }
        .addr-box th {
            width: 50%;
            background: none;
            color: #000;
            font-size: 8px;
            letter-spacing: 0.06em;
            padding: 2px 6px;
            text-align: left;
            border-bottom: 1px solid #222;
        }
        .addr-box th + th,
        .addr-box td + td { border-left: 1px solid #222; }
        .addr-box td {
            width: 50%;
            vertical-align: top;
            text-align: left;
            padding: 3px 6px 4px;
            font-size: 9.5px;
        }
        .addr-name { font-weight: bold; font-size: 10px; text-align: left; line-height: 1.2; }
        .addr-line { margin: 0; padding: 0; text-align: left; font-size: 9px; line-height: 1.2; }
        .meta-bar {
            width: 100%;
            border: 1px solid #222;
            margin: 0 0 8px;
            table-layout: fixed;
        }
        .meta-bar th {
            background: none;
            color: #000;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            padding: 3px 4px;
            text-align: left;
            border-right: 1px solid #222;
            border-bottom: 1px solid #222;
        }
        .meta-bar td {
            padding: 4px;
            font-size: 9.5px;
            border-right: 1px solid #222;
            height: 18px;
        }
        .meta-bar th:last-child,
        .meta-bar td:last-child { border-right: none; }
        table.items {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.items col.col-qty, table.items th.col-qty, table.items td.col-qty { width: 5%; }
        table.items col.col-item, table.items th.col-item, table.items td.col-item { width: 9%; }
        table.items col.col-desc, table.items th.col-desc, table.items td.col-desc { width: 50%; }
        table.items col.col-uom, table.items th.col-uom, table.items td.col-uom { width: 5%; }
        table.items col.col-price, table.items th.col-price, table.items td.col-price { width: 11%; }
        table.items col.col-disc, table.items th.col-disc, table.items td.col-disc { width: 10%; }
        table.items col.col-total, table.items th.col-total, table.items td.col-total { width: 10%; }
        table.items th {
            background: #333;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            padding: 5px 4px;
            vertical-align: middle;
            white-space: nowrap;
            border: 1px solid #222;
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
            border-left: 1px solid #ccc;
            border-right: 1px solid #ccc;
            border-bottom: none;
            word-wrap: break-word;
        }
        table.items tbody tr:last-child td {
            border-bottom: 1px solid #222;
        }
        table.items th.col-qty, table.items td.col-qty,
        table.items th.col-price, table.items td.col-price,
        table.items th.col-disc, table.items td.col-disc,
        table.items th.col-total, table.items td.col-total {
            text-align: right;
            white-space: nowrap;
        }
        table.items th.col-qty, table.items td.col-qty { padding-left: 2px; padding-right: 3px; }
        table.items th.col-uom, table.items td.col-uom { padding-left: 2px; padding-right: 2px; text-align: center; }
        table.items th.col-item, table.items td.col-item { text-align: left; }
        table.items th.col-desc, table.items td.col-desc { text-align: left; }
        table.items td.col-desc { font-size: 10.5px; }
        .right { text-align: right; }
        .foot-wrap { width: 100%; margin-top: 10px; }
        .foot-wrap > tbody > tr > td { vertical-align: top; }
        .bucket {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .bucket th, .bucket td {
            border: 1px solid #222;
            padding: 4px 6px;
            font-size: 8.5px;
        }
        .bucket th, .bucket td.lbl {
            background: none;
            color: #000;
            font-weight: bold;
            text-align: left;
            width: 25%;
        }
        .bucket td.val { text-align: right; font-weight: bold; font-size: 10px; }
        .prev-inv { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .prev-inv th, .prev-inv td {
            border: 1px solid #222;
            padding: 3px 5px;
            font-size: 8px;
        }
        .prev-inv th {
            background: none;
            color: #000;
            font-weight: bold;
            text-align: left;
        }
        .prev-inv td.num { text-align: center; width: 22px; }
        .prev-inv td.amt, .prev-inv th.amt { text-align: right; white-space: nowrap; }
        .prev-inv tr.tot td { font-weight: bold; background: none; }
        .bal-row { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 6px; }
        .bal-row th, .bal-row td {
            border: 1px solid #222;
            padding: 4px 5px;
            font-size: 8px;
        }
        .bal-row th {
            background: none;
            color: #000;
            font-weight: bold;
            text-align: center;
        }
        .bal-row td { text-align: center; font-weight: bold; font-size: 10px; }
        .totals {
            width: 100%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 3px 6px;
            font-size: 10px;
            text-align: right;
            white-space: nowrap;
            border: 1px solid #222;
        }
        .totals td.lbl {
            text-align: left;
            font-weight: bold;
            background: none;
            width: 58%;
        }
        .totals tr.grand td {
            font-weight: bold;
            font-size: 11px;
            background: none;
            color: #000;
        }
        .sign { margin-top: 14px; font-size: 9px; }
        .sign-line {
            border-bottom: 1px solid #000;
            width: 55%;
            height: 16px;
            display: inline-block;
        }
        .thanks {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin-top: 22px;
            padding-top: 8px;
            letter-spacing: 0.04em;
        }
        .logo-img { max-height: 48px; max-width: 160px; margin-bottom: 3px; }
        .line-msg { margin-top: 1px; font-size: 8px; }
        .line-msg-lbl { font-weight: bold; margin-right: 3px; }
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
    $companyEmail = $companyEmail ?? trim((string) ($company?->email ?? ''));
    $companyContact = $companyContact ?? trim((string) ($company?->contact_name ?? ''));
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
    $statusLabel = $statusLabel ?? ($order->status ?: '');
    $barcodeValue = (string) ($barcodeValue ?? $order->order_number);
    $docTitle = strtoupper((string) ($docTitle ?? 'Sales Order'));
    $showLineMessage = $showLineMessage ?? true;
    $isInvoiceDoc = $docTitle === 'INVOICE';
    $invoiceDoc = $order->invoice;
    $headerNumber = $isInvoiceDoc && $invoiceDoc
        ? $invoiceDoc->invoice_number
        : $order->order_number;
    $headerDate = $isInvoiceDoc && $invoiceDoc
        ? optional($invoiceDoc->invoice_date)?->format('m/d/Y')
        : optional($order->order_date)?->format('m/d/Y');

    $lines = $order->lines
        ->sortBy(fn ($line) => (int) $line->line_no)
        ->values();
    $buckets = DocumentMerchandiseTotals::fromLines($lines);

    $docSubtotal = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->subtotal : (float) ($order->subtotal ?? 0);
    $docDiscount = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->trade_discount : (float) ($order->trade_discount ?? 0);
    $docFreight = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->freight : (float) ($order->freight ?? 0);
    $docMisc = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->miscellaneous : (float) ($order->miscellaneous ?? 0);
    $docTax = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->tax : (float) ($order->tax ?? 0);
    $docTotal = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->invoice_total : (float) ($order->total ?? 0);
    $payTotal = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->total_payments : 0.0;
    $creditTotal = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->total_credits : 0.0;
    $thisOpen = $isInvoiceDoc && $invoiceDoc ? (float) $invoiceDoc->invoice_balance : $docTotal;
    $previousInvoices = \App\Models\Invoice::previousOpenInvoices(
        (int) $order->company_id,
        $order->customer_id ? (int) $order->customer_id : null,
        $invoiceDoc?->id
    );
    $previousBalance = $previousInvoices['total'];
    $todayInvoice = $docTotal;
    $totalDue = round($previousBalance + $thisOpen, 2);
@endphp

<table class="hdr">
    <tr>
        <td style="width:55%">
            @if ($logoPath && is_file($logoPath))
                <img class="logo-img" src="{{ $logoPath }}" alt="Logo">
            @endif
            <div class="co-name">{{ $companyName }}</div>
            @if ($companyContact !== '')
                <div class="co-line">{{ $companyContact }}</div>
            @endif
            <div class="co-line">{{ $companyAddress }}</div>
            <div class="co-line">{{ $companyCityLine }}</div>
            <div class="co-line">{{ $companyTel }}@if ($companyFax) &nbsp; {{ $companyFax }}@endif</div>
            @if ($companyEmail !== '')
                <div class="co-line">{{ $companyEmail }}</div>
            @endif
        </td>
        <td style="width:45%">
            <div class="notice">
                Please refer to the {{ $isInvoiceDoc ? 'invoice' : 'order' }} no. &amp; date below<br>
                in all correspondence regarding this transaction.
            </div>
            <div class="barcode-wrap">
                {!! Code128Barcode::html($barcodeValue, 3, 56) !!}
            </div>
            @if ($isInvoiceDoc)
                <div class="notice" style="margin-top:4px">Please pay invoice in full. A fee will be applied for NSF checks.</div>
            @endif
        </td>
    </tr>
</table>

<table class="addr-wrap">
    <tbody>
    <tr>
        <td class="addr-cell">
            <table class="addr-box">
                <tbody>
                <tr>
                    <th>SOLD TO</th>
                    <th>SHIP TO</th>
                </tr>
                <tr>
                    <td>
                        <div class="addr-name">{{ $order->bill_to_name ?: ($order->customer?->company_name ?: '') }}</div>
                        @if ($order->bill_to_address)
                            <div class="addr-line">{{ $order->bill_to_address }}</div>
                        @endif
                        @if ($billCity !== '')
                            <div class="addr-line">{{ $billCity }}</div>
                        @endif
                        @if ($order->bill_to_phone)
                            <div class="addr-line">Tel: {{ $order->bill_to_phone }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="addr-name">{{ $order->ship_to_name ?: ($order->bill_to_name ?: ($order->customer?->company_name ?: '')) }}</div>
                        @if ($order->ship_to_address ?: $order->bill_to_address)
                            <div class="addr-line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
                        @endif
                        @if ($shipCity !== '')
                            <div class="addr-line">{{ $shipCity }}</div>
                        @endif
                        @if ($order->ship_to_phone ?: $order->bill_to_phone)
                            <div class="addr-line">Tel: {{ $order->ship_to_phone ?: $order->bill_to_phone }}</div>
                        @endif
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
        <td class="inv-cell">
            <table class="doc-title-box">
                <tbody>
                <tr>
                    <td colspan="3" style="background:#333;color:#fff;font-size:11px;font-weight:bold;letter-spacing:0.12em;text-align:center;padding:4px 6px;border:1px solid #222">{{ $docTitle }}</td>
                </tr>
                <tr>
                    <td>NUMBER<div class="val">{{ $headerNumber }}</div></td>
                    <td>DATE<div class="val">{{ $headerDate }}</div></td>
                    <td>PAGE<div class="val">{{ $pageLabel ?? '1' }}</div></td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>

<table class="meta-bar">
    <tr>
        <th>ACCOUNT NO.</th>
        <th>ORDER NO.</th>
        <th>SALE REP.</th>
        <th>DRIVER</th>
        <th>ROUTE</th>
        <th>PAYMENT TERMS</th>
    </tr>
    <tr>
        <td>{{ $accountNo }}</td>
        <td>{{ $order->order_number }}</td>
        <td>{{ $salesRepLabel }}</td>
        <td>{{ $driverLabel }}</td>
        <td>{{ $routeLabel }}</td>
        <td>{{ $paymentLabel }}</td>
    </tr>
</table>

<table class="items">
    <colgroup>
        <col class="col-qty">
        <col class="col-item">
        <col class="col-desc">
        <col class="col-uom">
        <col class="col-price">
        <col class="col-disc">
        <col class="col-total">
    </colgroup>
    <thead>
        <tr>
            <th class="col-qty">QTY</th>
            <th class="col-item">ITEM</th>
            <th class="col-desc">DESCRIPTION</th>
            <th class="col-uom">U/M</th>
            <th class="col-price">UNIT</th>
            <th class="col-disc">DISCOUNT</th>
            <th class="col-total">TOTAL</th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
    </tfoot>
    <tbody>
        @forelse ($lines as $line)
            @php
                $qty = (float) $line->qty_ordered;
                $qtyLabel = fmod($qty, 1.0) == 0.0
                    ? number_format($qty, 2)
                    : number_format($qty, 2);
            @endphp
            <tr>
                <td class="col-qty">{{ $qtyLabel }}</td>
                <td class="col-item">{{ $line->item_code }}</td>
                <td class="col-desc">
                    <div>{{ $line->description }}</div>
                    @if ($showLineMessage && ($lineMsg = SalesOrderLinePresentation::lineMessage($line)))
                        <div class="line-msg">
                            <span class="line-msg-lbl">Line Message:</span>{{ $lineMsg }}
                        </div>
                    @endif
                </td>
                <td class="col-uom">{{ $line->uom ?: '' }}</td>
                <td class="col-price">{{ number_format((float) $line->price, 2) }}</td>
                <td class="col-disc">{{ number_format((float) $line->discount, 2) }}</td>
                <td class="col-total">{{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="padding:12px;text-align:center;color:#666">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="foot-wrap">
    <tbody>
    <tr>
        <td style="width:62%;padding-right:10px;vertical-align:top">
            <table class="bucket">
                <tbody>
                <tr>
                    <td class="lbl">TOBACCO ITEMS</td>
                    <td class="val">{{ $buckets['tobacco_count'] }}</td>
                    <td class="lbl">TOTAL TOBACCO</td>
                    <td class="val">${{ number_format($buckets['tobacco_total'], 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">TOTAL CIGARETTES</td>
                    <td class="val">${{ number_format($buckets['cigarette_total'], 2) }}</td>
                    <td class="lbl">TOTAL OTHERS</td>
                    <td class="val">${{ number_format($buckets['other_total'], 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">TOTAL ALL ITEMS</td>
                    <td class="val">{{ $buckets['all_count'] }}</td>
                    <td class="lbl">ALL ITEMS TOTAL</td>
                    <td class="val">${{ number_format($buckets['all_total'], 2) }}</td>
                </tr>
                </tbody>
            </table>
            <table class="prev-inv">
                <tbody>
                <tr>
                    <td colspan="4" style="font-weight:bold;text-align:left;padding:3px 5px;border:1px solid #222;font-size:8px">PREVIOUS INVOICES DUE</td>
                </tr>
                <tr>
                    <td class="num" style="font-weight:bold;text-align:center">#</td>
                    <td style="font-weight:bold">INVOICE NO</td>
                    <td style="font-weight:bold">DATE</td>
                    <td class="amt" style="font-weight:bold;text-align:right">AMOUNT DUE</td>
                </tr>
                @forelse ($previousInvoices['lines'] as $i => $prev)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $prev['invoice_number'] }}</td>
                        <td>{{ $prev['invoice_date'] ?: '-' }}</td>
                        <td class="amt">${{ number_format($prev['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center">None</td>
                    </tr>
                @endforelse
                <tr class="tot">
                    <td colspan="3">TOTAL PREVIOUS BALANCE</td>
                    <td class="amt">${{ number_format($previousBalance, 2) }}</td>
                </tr>
                </tbody>
            </table>
            <table class="bal-row">
                <tbody>
                <tr>
                    <td style="font-weight:bold;text-align:center">PREVIOUS BALANCE</td>
                    <td style="font-weight:bold;text-align:center">TODAY'S INVOICE</td>
                    <td style="font-weight:bold;text-align:center">TOTAL CREDITS</td>
                    <td style="font-weight:bold;text-align:center">TOTAL PAYMENTS</td>
                </tr>
                <tr>
                    <td>${{ number_format($previousBalance, 2) }}</td>
                    <td>${{ number_format($todayInvoice, 2) }}</td>
                    <td>${{ number_format($creditTotal, 2) }}</td>
                    <td>${{ number_format($payTotal, 2) }}</td>
                </tr>
                </tbody>
            </table>
        </td>
        <td style="width:38%;vertical-align:top">
            <table class="totals">
                <tbody>
                <tr>
                    <td class="lbl">SUB TOTAL</td>
                    <td>${{ number_format($docSubtotal, 2) }}</td>
                </tr>
                @if ($docDiscount != 0.0)
                    <tr>
                        <td class="lbl">TRADE DISCOUNT</td>
                        <td>${{ number_format($docDiscount, 2) }}</td>
                    </tr>
                @endif
                @if ($docFreight != 0.0)
                    <tr>
                        <td class="lbl">FREIGHT</td>
                        <td>${{ number_format($docFreight, 2) }}</td>
                    </tr>
                @endif
                @if ($docMisc != 0.0)
                    <tr>
                        <td class="lbl">MISCELLANEOUS</td>
                        <td>${{ number_format($docMisc, 2) }}</td>
                    </tr>
                @endif
                @if ($docTax != 0.0)
                    <tr>
                        <td class="lbl">TAX</td>
                        <td>${{ number_format($docTax, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td class="lbl">{{ $isInvoiceDoc ? 'INVOICE TOTAL' : 'ORDER TOTAL' }}</td>
                    <td>${{ number_format($docTotal, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td class="lbl">TOTAL DUE</td>
                    <td>${{ number_format($totalDue, 2) }}</td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
<div class="sign">
    <strong>RECEIVED BY</strong>
    <span class="sign-line">&nbsp;</span>
    <div style="margin-top:4px;font-size:8px">SIGNATURE ACKNOWLEDGES RECEIPT OF THE TOTALS SHOWN ABOVE.</div>
</div>
<div class="thanks">THANK YOU FOR YOUR BUSINESS</div>
</body>
</html>
