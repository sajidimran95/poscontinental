<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $docTitle ?? 'Sales Order' }} {{ $barcodeValue ?? $order->order_number }}</title>
    <style>
        @page { margin: 36px 40px 48px; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.3;
            margin: 0;
        }
        table { border-collapse: collapse; }
        .hdr { width: 100%; margin-bottom: 6px; }
        .hdr td { vertical-align: top; border: none; padding: 0; }
        .co-name { font-size: 18px; font-weight: bold; letter-spacing: 0.01em; }
        .co-line { font-size: 9.5px; margin-top: 2px; }
        .doc-title { font-size: 22px; font-weight: bold; text-align: right; margin: 0 0 4px; }
        .barcode-wrap { text-align: right; margin: 2px 0 0; }
        .page-under-barcode {
            text-align: right;
            font-size: 10px;
            margin: 2px 0 4px;
            line-height: 1.3;
        }
        .meta { text-align: right; font-size: 10px; line-height: 1.45; }
        .meta .lbl { color: #333; }
        .addr-box {
            width: 100%;
            border: 1px solid #222;
            margin: 16px 0 14px;
        }
        .addr-box td {
            width: 50%;
            vertical-align: top;
            text-align: left;
            padding: 4px 4px 4px 3px;
        }
        .addr-box td + td { border-left: 1px solid #222; }
        .addr-box tr.addr-meta td {
            border-top: 1px solid #ccc;
            padding-top: 4px;
            padding-bottom: 4px;
            vertical-align: top;
        }
        .addr-lbl { font-weight: bold; margin: 0 0 2px; text-align: left; }
        .addr-name { font-weight: bold; font-size: 11px; text-align: left; }
        .addr-line { margin: 0; padding: 0; text-align: left; }
        .addr-meta .info-pair { margin: 0 0 1px; text-align: left; font-size: 10px; }
        .addr-meta .lbl { font-weight: bold; }
        table.items {
            width: 100%;
            margin-top: 10px;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.items col.col-qty { width: 12%; }
        table.items col.col-item { width: 14%; }
        table.items col.col-desc { width: 42%; }
        table.items col.col-uom { width: 8%; }
        table.items col.col-price { width: 12%; }
        table.items col.col-total { width: 12%; }
        table.items th {
            border-top: 1px solid #222;
            border-bottom: 1px solid #222;
            font-size: 9.5px;
            font-weight: bold;
            padding: 5px 4px;
            vertical-align: bottom;
            white-space: nowrap;
        }
        table.items td {
            padding: 4px;
            font-size: 10px;
            vertical-align: top;
            border: none;
            word-wrap: break-word;
        }
        table.items th.col-qty,
        table.items td.col-qty {
            text-align: right;
        }
        table.items th.col-item,
        table.items td.col-item {
            text-align: left;
        }
        table.items th.col-desc,
        table.items td.col-desc {
            text-align: left;
        }
        table.items th.col-uom,
        table.items td.col-uom {
            text-align: center;
        }
        table.items th.col-price,
        table.items td.col-price,
        table.items th.col-total,
        table.items td.col-total {
            text-align: right;
            white-space: nowrap;
        }
        table.items tr.uom-head td {
            font-weight: bold;
            font-size: 10px;
            padding-top: 8px;
            padding-bottom: 3px;
            border-bottom: 1px solid #999;
            background: #f3f3f3;
        }
        table.items tr.uom-end td {
            border-bottom: 1px solid #222;
            padding-bottom: 6px;
        }
        table.items tr.uom-start td {
            padding-top: 5px;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer-pay {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .footer-pay td {
            vertical-align: top;
        }
        .pay-box {
            width: 58%;
            padding-right: 12px;
        }
        .pay-box .pay-title {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .pay-lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .pay-lines th {
            text-align: left;
            padding: 2px 4px 3px 0;
            border-bottom: 1px solid #999;
            font-size: 9px;
        }
        .pay-lines th.amt,
        .pay-lines td.amt {
            text-align: right;
            white-space: nowrap;
            padding-right: 0;
        }
        .pay-lines td {
            padding: 3px 4px 2px 0;
            vertical-align: top;
        }
        .pay-empty {
            font-size: 10px;
            color: #666;
        }
        .totals {
            width: 280px;
            margin-top: 0;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 3px 6px;
            font-size: 10px;
            text-align: right;
            white-space: nowrap;
        }
        .totals .grand td {
            border-top: 1px solid #222;
            font-weight: bold;
            padding-top: 6px;
        }
        .logo-img { max-height: 52px; max-width: 180px; margin-bottom: 4px; }
        .line-msg {
            margin-top: 2px;
            font-size: 9px;
            color: #111;
            line-height: 1.35;
        }
        .line-msg-lbl {
            font-weight: bold;
            margin-right: 3px;
        }
    </style>
</head>
<body>
@php
    use App\Support\Code128Barcode;
    use App\Support\SalesOrderLinePresentation;

    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $companyAddress ?? ($company?->letterheadAddress() ?? config('company.address', '3802 TRADE CENTER DR'));
    $companyCityLine = $companyCityLine ?? ($company?->letterheadCityLine() ?? config('company.city_line', 'ANN ARBOR, MI 48108'));
    $companyTel = $companyTel ?? ($company?->letterheadTel() ?? config('company.tel', 'Tel:7346773510'));
    $companyFax = $companyFax ?? ($company?->letterheadFax() ?? config('company.fax', 'Fax:7346773567'));
    $companyEmail = $companyEmail ?? trim((string) ($company?->email ?? ''));
    $companyContact = $companyContact ?? trim((string) ($company?->contact_name ?? ''));
    $logoPath = $logoPath ?? null;

    $billCity = collect([$order->bill_to_city, $order->bill_to_state, $order->bill_to_zip])->filter()->implode(', ');
    $shipCity = collect([
        $order->ship_to_city ?: $order->bill_to_city,
        $order->ship_to_state ?: $order->bill_to_state,
        $order->ship_to_zip ?: $order->bill_to_zip,
    ])->filter()->implode(', ');

    $paymentLabel = $order->paymentTerm?->name
        ?: $order->paymentTerm?->code
        ?: '';
    $driverLabel = $order->invoice?->driver ?: '';
    $routeLabel = $order->route?->name ?: $order->route?->code ?: '';
    $accountNo = $order->customer?->customer_id ?: '';
    $statusLabel = $statusLabel ?? ($order->status ?: '');
    $barcodeValue = (string) ($barcodeValue ?? $order->order_number);
    $docTitle = $docTitle ?? 'Sales Order';
    $showLineMessage = $showLineMessage ?? true;
    $metaLines = $metaLines ?? [
        ['label' => 'Order No:', 'value' => $order->order_number],
        ['label' => 'Order Date:', 'value' => optional($order->order_date)?->format('m/d/Y')],
        ['label' => 'Order Status:', 'value' => $statusLabel],
    ];

    $lines = $order->lines
        ->sortBy(fn ($line) => (int) $line->line_no)
        ->values();
@endphp

{{-- Header: company + Bill/Ship left; document title + barcode + meta right --}}
<table class="hdr">
    <tr>
        <td style="width:72%">
            @if ($logoPath && is_file($logoPath))
                <img class="logo-img" src="{{ $logoPath }}" alt="Logo">
            @endif
            <div class="co-name">{{ $companyName }}</div>
            @if ($companyContact !== '')
                <div class="co-line">{{ $companyContact }}</div>
            @endif
            <div class="co-line">{{ $companyAddress }}</div>
            <div class="co-line">{{ $companyCityLine }}</div>
            <div class="co-line">{{ $companyTel }} &nbsp; {{ $companyFax }}</div>
            @if ($companyEmail !== '')
                <div class="co-line">{{ $companyEmail }}</div>
            @endif

            <table class="addr-box">
                <tr>
                    <td>
                        <div class="addr-lbl">Bill To:</div>
                        <div class="addr-name">{{ $order->bill_to_name ?: ($order->customer?->company_name ?: '') }}</div>
                        @if ($order->bill_to_address)
                            <div class="addr-line">{{ $order->bill_to_address }}</div>
                        @endif
                        @if ($billCity !== '')
                            <div class="addr-line">{{ $billCity }}</div>
                        @endif
                        @if ($order->bill_to_phone)
                            <div class="addr-line">Tel:{{ $order->bill_to_phone }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="addr-lbl">Ship To:</div>
                        <div class="addr-name">{{ $order->ship_to_name ?: ($order->bill_to_name ?: ($order->customer?->company_name ?: '')) }}</div>
                        @if ($order->ship_to_address ?: $order->bill_to_address)
                            <div class="addr-line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
                        @endif
                        @if ($shipCity !== '')
                            <div class="addr-line">{{ $shipCity }}</div>
                        @endif
                        @if ($order->ship_to_phone ?: $order->bill_to_phone)
                            <div class="addr-line">Tel:{{ $order->ship_to_phone ?: $order->bill_to_phone }}</div>
                        @endif
                    </td>
                </tr>
                <tr class="addr-meta">
                    <td>
                        <div class="info-pair"><span class="lbl">Account No.:</span> {{ $accountNo }}</div>
                        <div class="info-pair"><span class="lbl">Payment Terms:</span> {{ $paymentLabel }}</div>
                    </td>
                    <td>
                        <div class="info-pair"><span class="lbl">Driver:</span> {{ $driverLabel }}</div>
                        <div class="info-pair"><span class="lbl">Route:</span> {{ $routeLabel }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width:28%">
            <div class="doc-title">{{ $docTitle }}</div>
            <div class="barcode-wrap">
                {!! Code128Barcode::html($barcodeValue, 2, 44) !!}
            </div>
            <div class="page-under-barcode">Page {{ $pageLabel ?? '1 of 1' }}</div>
            <div class="meta">
                @foreach ($metaLines as $meta)
                    <div><span class="lbl">{{ $meta['label'] }}</span> {{ $meta['value'] }}</div>
                @endforeach
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <colgroup>
        <col class="col-qty">
        <col class="col-item">
        <col class="col-desc">
        <col class="col-uom">
        <col class="col-price">
        <col class="col-total">
    </colgroup>
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
    <tbody>
        @forelse ($lines as $line)
            @php
                $qty = (float) $line->qty_ordered;
                $qtyLabel = fmod($qty, 1.0) == 0.0
                    ? number_format($qty, 0)
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
                <td class="col-uom">{{ $line->uom ?: '—' }}</td>
                <td class="col-price">{{ number_format((float) $line->price, 2) }}</td>
                <td class="col-total">{{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding:12px;text-align:center;color:#666">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@php
    $invoiceDoc = $order->invoice;
    $payRows = $invoiceDoc?->payments ?? collect();
    $creditRows = $invoiceDoc?->credits ?? collect();
    $isInvoiceDoc = ($docTitle ?? 'Sales Order') === 'Invoice';
@endphp

<table class="footer-pay">
    <tr>
        <td class="pay-box">
            @if ($isInvoiceDoc)
                <div class="pay-title">Payment Method</div>
                @if ($payRows->isEmpty() && $creditRows->isEmpty())
                    <div class="pay-empty">No payments recorded yet.</div>
                @else
                    <table class="pay-lines">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Date / Ref</th>
                                <th class="amt">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payRows as $p)
                                <tr>
                                    <td><strong>{{ $p->payment_method ?: 'Payment' }}</strong></td>
                                    <td>
                                        {{ optional($p->payment_date)?->format('m/d/Y') ?: '—' }}
                                        @if (filled($p->check_number))
                                            · Check #{{ $p->check_number }}
                                        @endif
                                    </td>
                                    <td class="amt">{{ number_format((float) $p->amount, 2) }}</td>
                                </tr>
                            @endforeach
                            @foreach ($creditRows as $c)
                                <tr>
                                    <td><strong>Credit Memo</strong></td>
                                    <td>
                                        #{{ $c->creditMemo?->memo_number ?: '—' }}
                                        @if ($c->creditMemo?->memo_date)
                                            · {{ $c->creditMemo->memo_date->format('m/d/Y') }}
                                        @endif
                                    </td>
                                    <td class="amt">{{ number_format((float) $c->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </td>
        <td style="width:42%">
            <table class="totals">
                <tr>
                    <td class="right">Subtotal</td>
                    <td class="right" style="width:90px">{{ number_format((float) ($order->subtotal ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="right">Trade Discount</td>
                    <td class="right">{{ number_format((float) ($order->trade_discount ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="right">Freight</td>
                    <td class="right">{{ number_format((float) ($order->freight ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="right">Miscellaneous</td>
                    <td class="right">{{ number_format((float) ($order->miscellaneous ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="right">Tax</td>
                    <td class="right">{{ number_format((float) ($order->tax ?? 0), 2) }}</td>
                </tr>
                <tr class="grand">
                    <td class="right">{{ $isInvoiceDoc ? 'Invoice Total' : 'Order Total' }}</td>
                    <td class="right">{{ number_format((float) ($order->total ?? 0), 2) }}</td>
                </tr>
                @if ($isInvoiceDoc && $invoiceDoc)
                    <tr>
                        <td class="right">Payments</td>
                        <td class="right">{{ number_format((float) $invoiceDoc->total_payments, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="right">Credits</td>
                        <td class="right">{{ number_format((float) $invoiceDoc->total_credits, 2) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="right">Balance Due</td>
                        <td class="right">{{ number_format((float) $invoiceDoc->invoice_balance, 2) }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
</body>
</html>
