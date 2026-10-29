<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Memo {{ $memo->memo_number }}</title>
    <style>
        /* Top margin clears the fixed header (company / CREDIT MEMO / Bill To / meta). */
        @page { size: letter; margin: 2.55in 0.4in 0.45in; }
        .page-head { position: fixed; top: -2.35in; left: 0; right: 0; }
        .pagenum:after { content: counter(page) " of {{ max(1, (int) ($pageLabel ?? 1)) }}"; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.3;
            margin: 0;
            width: 100%;
        }
        table { border-collapse: collapse; }
        .hdr { width: 100%; margin-bottom: 8px; }
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
        .bill-box {
            width: 55%;
            border: 1px solid #000;
            margin: 0 0 8px;
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
            margin: 0;
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
        /* DomPDF repeats tfoot on every page → closes the line under the last item of page 1, 2, … */
        table.items tfoot { display: table-footer-group; }
        table.items tfoot td {
            padding: 0;
            height: 1px;
            font-size: 1px;
            line-height: 1px;
            border: none;
            border-top: 1px solid #000;
            background: transparent;
        }
        table.items th {
            background: #d0d0d0;
            color: #000;
            font-size: 8.5px;
            font-weight: bold;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #000;
        }
        table.items td {
            padding: 4px 5px;
            font-size: 10px;
            vertical-align: top;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: none;
            border-top: none;
        }
        table.items tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }
        table.items th.col-qty,
        table.items td.col-qty { width: 7%; text-align: right; white-space: nowrap; }
        table.items th.col-item,
        table.items td.col-item { width: 10%; text-align: left; white-space: nowrap; }
        table.items th.col-desc,
        table.items td.col-desc {
            width: 52%;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.items td.col-desc { font-size: 10.5px; line-height: 1.35; }
        table.items th.col-uom,
        table.items td.col-uom { width: 7%; text-align: center; white-space: nowrap; }
        table.items th.col-unit,
        table.items td.col-unit { width: 12%; text-align: right; white-space: nowrap; }
        table.items th.col-total,
        table.items td.col-total { width: 12%; text-align: right; white-space: nowrap; }
        .notes {
            margin-top: 8px;
            margin-bottom: 8px;
            font-size: 9px;
            color: #333;
        }
        /* Spacer + absolute foot → totals sit at bottom of the last page only. */
        .doc-foot-space { page-break-inside: avoid; }
        .doc-foot {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0.05in;
            width: 100%;
        }
        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .totals-wrap > tbody > tr > td { border: none; vertical-align: bottom; padding: 0; }
        .totals {
            width: 240px;
            margin-left: auto;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px 10px;
            font-size: 10.5px;
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
            margin-top: 14px;
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
    // Push totals toward the bottom of the last page (same approach as sales-order PDF).
    $footGapPx = max(80, 420 - ($lineCount * 16));
@endphp

{{-- Repeats on every page --}}
<div class="page-head">
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
</div>

<table class="items">
    <colgroup>
        <col style="width:7%">
        <col style="width:10%">
        <col style="width:52%">
        <col style="width:7%">
        <col style="width:12%">
        <col style="width:12%">
    </colgroup>
    <thead>
        <tr>
            <th class="col-qty">Qty</th>
            <th class="col-item">Item</th>
            <th class="col-desc">Description</th>
            <th class="col-uom">UOM</th>
            <th class="col-unit">Unit Price</th>
            <th class="col-total">Amount</th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
    </tfoot>
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

@if (filled($memo->comments))
    <div class="notes"><strong>Comments:</strong> {{ $memo->comments }}</div>
@endif

{{-- Totals only after the last line items, pinned to the bottom of the last page --}}
<div class="doc-foot-space" style="height: {{ $footGapPx }}px"></div>
<div class="doc-foot">
    <table class="totals-wrap">
        <tr>
            <td style="width:55%">&nbsp;</td>
            <td style="width:45%">
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
    <div class="thanks">THANK YOU FOR YOUR BUSINESS</div>
</div>
</body>
</html>
