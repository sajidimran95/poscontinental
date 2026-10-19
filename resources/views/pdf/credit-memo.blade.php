<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Memo {{ $memo->memo_number }}</title>
    <style>
        @page { size: letter; margin: 0.5in 0.5in 0.55in; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.25;
            margin: 0;
        }
        table { border-collapse: collapse; }
        .hdr { width: 100%; margin-bottom: 10px; }
        .hdr td { vertical-align: top; border: none; padding: 0; }
        .co-name { font-size: 17px; font-weight: bold; }
        .co-line { font-size: 9.5px; margin-top: 2px; }
        .doc-box {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .doc-box .title {
            background: #5a5a5a;
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-align: center;
            padding: 5px 6px;
            border-bottom: 1px solid #000;
        }
        .doc-box th {
            background: #d0d0d0;
            color: #000;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            padding: 3px 4px;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .doc-box th:last-child { border-right: none; }
        .doc-box td {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 4px;
            border-right: 1px solid #000;
        }
        .doc-box td:last-child { border-right: none; }
        .pagenum:after { content: counter(page); }
        .bill-box {
            width: 48%;
            border: 1px solid #000;
            margin: 6px 0 8px;
            border-collapse: collapse;
        }
        .bill-box th {
            background: #d0d0d0;
            color: #000;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-align: left;
            padding: 2px 6px;
            border-bottom: 1px solid #000;
        }
        .bill-box td {
            vertical-align: top;
            text-align: left;
            padding: 3px 6px 5px;
            font-size: 11px;
            line-height: 1.25;
        }
        .bill-name { font-weight: bold; font-size: 11px; }
        .bill-line { margin-top: 0; }
        .meta-bar {
            width: 100%;
            border: 1px solid #000;
            margin: 0 0 10px;
            table-layout: fixed;
        }
        .meta-bar th {
            background: #d0d0d0;
            color: #000;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-align: center;
            padding: 3px 4px;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .meta-bar td {
            text-align: center;
            padding: 5px 4px;
            font-size: 11px;
            font-weight: bold;
            border-right: 1px solid #000;
            height: 18px;
        }
        .meta-bar th:last-child,
        .meta-bar td:last-child { border-right: none; }
        table.items {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.items thead { display: table-header-group; }
        table.items th {
            background: #d0d0d0;
            color: #000;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            padding: 4px 3px;
            border: 1px solid #000;
            white-space: nowrap;
        }
        table.items td {
            padding: 2px 4px;
            font-size: 10.5px;
            vertical-align: top;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: none;
            border-top: none;
        }
        table.items tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }
        .col-qty { width: 10%; text-align: right; white-space: nowrap; }
        .col-item { width: 12%; text-align: left; white-space: nowrap; }
        .col-desc { width: 40%; text-align: left; }
        .col-uom { width: 14%; text-align: center; white-space: nowrap; }
        .col-unit { width: 12%; text-align: right; white-space: nowrap; }
        .col-total { width: 12%; text-align: right; white-space: nowrap; }
        .totals-wrap {
            width: 100%;
            margin-top: 0;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .totals-wrap > tbody > tr > td { border: none; vertical-align: bottom; padding: 0; }
        .totals {
            width: 210px;
            margin-left: auto;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .totals td {
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
            border-bottom: 1px solid #000;
        }
        .totals tr:last-child td { border-bottom: none; }
        .totals .lbl {
            text-align: left;
            width: 58%;
            background: #d0d0d0;
            border-right: 1px solid #000;
        }
        .totals .amt { text-align: right; width: 42%; }
        .thanks {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.05em;
            margin-top: 22px;
        }
        .notes {
            margin-top: 6px;
            font-size: 9px;
            color: #333;
        }
    </style>
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $companyAddress ?? ($company?->letterheadAddress() ?? config('company.address', '3802 TRADE CENTER DR'));
    $companyCityLine = $companyCityLine ?? ($company?->letterheadCityLine() ?? config('company.city_line', 'ANN ARBOR, MI 48108'));
    $companyTel = $companyTel ?? ($company?->letterheadTel() ?? config('company.tel', 'Tel:7346773510'));
    $companyFax = $companyFax ?? ($company?->letterheadFax() ?? config('company.fax', 'Fax:7346773567'));

    $customer = $memo->customer;
    $order = $memo->salesOrder;

    $billName = $order?->bill_to_name ?: ($customer?->company_name ?: $customer?->contact ?: '');
    $billAddress = $order?->bill_to_address ?: ($customer?->address ?: '');
    $billCity = $order
        ? collect([$order->bill_to_city, $order->bill_to_state, $order->bill_to_zip])->filter()->implode(' ')
        : collect([$customer?->city, $customer?->state, $customer?->zip_code])->filter()->implode(' ');
    $billPhone = $order?->bill_to_phone ?: ($customer?->telephone ?: '');

    $accountNo = $customer?->customer_id ?: '';
    $orderNo = $order?->order_number ?: '';
    $salesRepName = $order?->salesRep?->name ?: '';
    $driverLabel = $order?->invoice?->driver ?: '';

    // Prefer credit_memo_lines; imported Chief credits often only have lines on the linked SO.
    $memoLines = $memo->relationLoaded('lines') ? $memo->lines : $memo->lines()->get();
    if ($memoLines->isEmpty() && $order) {
        $orderLines = $order->relationLoaded('lines')
            ? $order->lines
            : $order->lines()->orderBy('line_no')->orderBy('id')->get();
        $memoLines = $orderLines->map(fn ($l) => (object) [
            'qty' => (float) ($l->qty_ordered ?? $l->qty ?? 0),
            'item_code' => $l->item_code,
            'description' => $l->description,
            'uom' => $l->uom,
            'price' => (float) $l->price,
            'line_total' => (float) $l->line_total,
        ]);
    }
    // Amount-only credit (no item lines): show a single CREDIT row so the print isn't blank.
    if ($memoLines->isEmpty() && (float) ($memo->amount ?? 0) > 0.004) {
        $memoLines = collect([(object) [
            'qty' => 1.0,
            'item_code' => '',
            'description' => 'CREDIT',
            'uom' => 'EA',
            'price' => (float) $memo->amount,
            'line_total' => (float) $memo->amount,
        ]]);
    }
    $lines = $memoLines;
    $lineCount = max($lines->count(), 1);
    $subtotal = (float) $lines->sum(fn ($l) => (float) $l->line_total);
    $tax = (float) ($memo->tax ?? 0);
    $creditTotal = (float) ($memo->amount ?? $subtotal);
    // Chief sample: large empty gap after items, totals near page bottom.
    $gapPx = max(60, 480 - ($lineCount * 16));
@endphp

<table class="hdr">
    <tr>
        <td style="width:58%">
            <div class="co-name">{{ $companyName }}</div>
            <div class="co-line">{{ $companyAddress }} {{ $companyCityLine }}</div>
            <div class="co-line">{{ $companyTel }}@if ($companyFax) &nbsp; {{ $companyFax }}@endif</div>
        </td>
        <td style="width:42%">
            <table class="doc-box">
                <tr>
                    <td class="title" colspan="3">CREDIT MEMO</td>
                </tr>
                <tr>
                    <th style="width:34%">NUMBER</th>
                    <th style="width:33%">DATE</th>
                    <th style="width:33%">PAGE</th>
                </tr>
                <tr>
                    <td>{{ $memo->memo_number }}</td>
                    <td>{{ optional($memo->memo_date)?->format('n/j/Y') }}</td>
                    <td><span class="pagenum"></span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="bill-box">
    <tr>
        <th>BILL TO</th>
    </tr>
    <tr>
        <td>
            <div class="bill-name">{{ $billName }}</div>
            @if ($billAddress !== '')
                <div class="bill-line">{{ $billAddress }}</div>
            @endif
            @if ($billCity !== '')
                <div class="bill-line">{{ $billCity }}</div>
            @endif
            @if ($billPhone !== '')
                <div class="bill-line">Tel:{{ preg_replace('/^Tel:\s*/i', '', (string) $billPhone) }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="meta-bar">
    <tr>
        <th style="width:25%">ACCOUNT NO.</th>
        <th style="width:25%">ORDER NO.</th>
        <th style="width:25%">SALE REP.</th>
        <th style="width:25%">DRIVER</th>
    </tr>
    <tr>
        <td>{{ $accountNo }}</td>
        <td>{{ $orderNo }}</td>
        <td>{{ $salesRepName }}</td>
        <td>{{ $driverLabel }}</td>
    </tr>
</table>

<table class="items">
    <colgroup>
        <col class="col-qty">
        <col class="col-item">
        <col class="col-desc">
        <col class="col-uom">
        <col class="col-unit">
        <col class="col-total">
    </colgroup>
    <thead>
        <tr>
            <th>QUANTITY</th>
            <th>ITEM</th>
            <th>DESCRIPTION</th>
            <th>UNIT MEASURE</th>
            <th>UNIT</th>
            <th>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $line)
            <tr>
                <td class="col-qty">{{ number_format((float) $line->qty, 2) }}</td>
                <td class="col-item">{{ $line->item_code }}</td>
                <td class="col-desc">{{ $line->description }}</td>
                <td class="col-uom">{{ $line->uom }}</td>
                <td class="col-unit">${{ number_format((float) $line->price, 2) }}</td>
                <td class="col-total">${{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding:12px;text-align:center;color:#666;border:1px solid #000">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Empty gap (no column lines) then totals near the bottom, matching the Chief sample --}}
<div style="height: {{ $gapPx }}px"></div>

<table class="totals-wrap">
    <tr>
        <td style="width:58%">&nbsp;</td>
        <td style="width:42%">
            <table class="totals">
                <tr>
                    <td class="lbl">SUBTOTAL</td>
                    <td class="amt">${{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">TAX</td>
                    <td class="amt">${{ number_format($tax, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">TOTAL CREDIT</td>
                    <td class="amt">${{ number_format($creditTotal, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if (filled($memo->comments))
    <div class="notes"><strong>Comments:</strong> {{ $memo->comments }}</div>
@endif

<div class="thanks">THANK YOU FOR YOUR BUSINESS</div>
</body>
</html>
