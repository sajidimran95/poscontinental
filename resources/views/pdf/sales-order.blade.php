<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Order {{ $order->order_number }}</title>
    <style>
        @page { margin: 36px 40px 48px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
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
        }
        table.items th {
            border-top: 1px solid #222;
            border-bottom: 1px solid #222;
            font-size: 9.5px;
            font-weight: bold;
            text-align: left;
            padding: 5px 4px;
            vertical-align: bottom;
        }
        table.items td {
            padding: 3px 4px;
            font-size: 10px;
            vertical-align: top;
            border: none;
            text-align: left;
        }
        table.items th.col-qty,
        table.items td.col-qty {
            width: 70px;
            text-align: right;
            padding-right: 14px;
        }
        table.items th.col-item,
        table.items td.col-item {
            width: 90px;
            text-align: left;
            padding-left: 10px;
        }
        table.items th.col-desc,
        table.items td.col-desc {
            text-align: left;
        }
        table.items th.col-uom,
        table.items td.col-uom {
            width: 45px;
            text-align: center;
        }
        table.items th.col-price,
        table.items td.col-price {
            width: 70px;
            text-align: right;
        }
        table.items th.col-total,
        table.items td.col-total {
            width: 80px;
            text-align: right;
        }
        table.items tr.uom-end td {
            border-bottom: 1px solid #222;
            padding-bottom: 5px;
        }
        table.items tr.uom-start td {
            padding-top: 5px;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals {
            width: 280px;
            margin-top: 10px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 3px 6px;
            font-size: 10px;
        }
        .totals .grand td {
            border-top: 1px solid #222;
            font-weight: bold;
            padding-top: 6px;
        }
        .logo-img { max-height: 52px; max-width: 180px; margin-bottom: 4px; }
    </style>
</head>
<body>
@php
    use App\Support\Code128Barcode;

    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $companyAddress ?? '3802 TRADE CENTER DR';
    $companyCityLine = $companyCityLine ?? 'ANN ARBOR, MI 48108';
    $companyTel = $companyTel ?? 'Tel:7346773510';
    $companyFax = $companyFax ?? 'Fax:7346773567';
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
    $statusLabel = $order->status ?: '';
    $barcodeValue = (string) $order->order_number;

    $grouped = $order->lines
        ->sortBy(fn ($line) => [strtoupper((string) ($line->uom ?: '')), (int) $line->line_no])
        ->values();

    $uomGroups = $grouped->groupBy(fn ($line) => strtoupper((string) ($line->uom ?: '')));
@endphp

{{-- Header: company + Bill/Ship left; Sales Order + barcode + meta right --}}
<table class="hdr">
    <tr>
        <td style="width:72%">
            @if ($logoPath && is_file($logoPath))
                <img class="logo-img" src="{{ $logoPath }}" alt="Logo">
            @endif
            <div class="co-name">{{ $companyName }}</div>
            <div class="co-line">{{ $companyAddress }}</div>
            <div class="co-line">{{ $companyCityLine }}</div>
            <div class="co-line">{{ $companyTel }} &nbsp; {{ $companyFax }}</div>

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
            <div class="doc-title">Sales Order</div>
            <div class="barcode-wrap">
                {!! Code128Barcode::html($barcodeValue, 2, 44) !!}
            </div>
            <div class="page-under-barcode">Page {{ $pageLabel ?? '1 of 1' }}</div>
            <div class="meta">
                <div><span class="lbl">Order No:</span> {{ $order->order_number }}</div>
                <div><span class="lbl">Order Date:</span> {{ optional($order->order_date)?->format('m/d/Y') }}</div>
                <div><span class="lbl">Order Status:</span> {{ $statusLabel }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- Lines grouped by U/M with border between groups --}}
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
    <tbody>
        @forelse ($uomGroups as $uom => $lines)
            @foreach ($lines as $idx => $line)
                @php
                    $isFirst = $idx === 0;
                    $isLast = $idx === $lines->count() - 1;
                    $rowClass = trim(($isFirst ? 'uom-start' : '').' '.($isLast ? 'uom-end' : ''));
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="col-qty">{{ fmod((float) $line->qty_ordered, 1.0) == 0.0 ? number_format((float) $line->qty_ordered, 0) : rtrim(rtrim(number_format((float) $line->qty_ordered, 2, '.', ''), '0'), '.') }}</td>
                    <td class="col-item">{{ $line->item_code }}</td>
                    <td class="col-desc">{{ $line->description }}</td>
                    <td class="col-uom">{{ $line->uom }}</td>
                    <td class="col-price">{{ number_format((float) $line->price, 2) }}</td>
                    <td class="col-total">{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="6" style="padding:12px;text-align:center;color:#666">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="right">Subtotal</td>
        <td class="right" style="width:90px">{{ number_format((float) $order->subtotal, 2) }}</td>
    </tr>
    @if ((float) $order->trade_discount != 0)
        <tr>
            <td class="right">Trade Discount</td>
            <td class="right">{{ number_format((float) $order->trade_discount, 2) }}</td>
        </tr>
    @endif
    @if ((float) $order->freight != 0)
        <tr>
            <td class="right">Freight</td>
            <td class="right">{{ number_format((float) $order->freight, 2) }}</td>
        </tr>
    @endif
    @if ((float) $order->miscellaneous != 0)
        <tr>
            <td class="right">Miscellaneous</td>
            <td class="right">{{ number_format((float) $order->miscellaneous, 2) }}</td>
        </tr>
    @endif
    @if ((float) $order->tax != 0)
        <tr>
            <td class="right">Tax</td>
            <td class="right">{{ number_format((float) $order->tax, 2) }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td class="right">Order Total</td>
        <td class="right">{{ number_format((float) $order->total, 2) }}</td>
    </tr>
</table>
</body>
</html>
