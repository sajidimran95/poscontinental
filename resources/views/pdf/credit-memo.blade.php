<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Memo {{ $memo->memo_number }}</title>
    <style>
        /* Top = repeating company/bill-to. Totals/comments in normal flow → last page only, no overlay. */
        @page { size: letter; margin: 2.45in 0.42in 0.32in; }
        .page-head { position: fixed; top: -2.28in; left: 0; right: 0; }
        .doc-foot {
            width: 100%;
            margin-top: 6px;
            page-break-inside: avoid;
        }
        .pagenum:after { content: counter(page) " of {{ max(1, (int) ($pageLabel ?? 1)) }}"; }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.35;
            margin: 0;
        }
        table { border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; }

        .mh { width: 100%; }
        .mh td { border: none; vertical-align: middle; }
        .brand {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: -0.02em;
            color: #0f172a;
            line-height: 1.15;
        }
        .brand-sub { font-size: 8.5px; color: #64748b; margin-top: 3px; }
        .title-chip {
            background: #0f172a;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.18em;
            text-align: center;
            padding: 10px 14px 8px;
        }
        .chip-sub {
            font-size: 7.5px;
            letter-spacing: 0.1em;
            color: #ffffff;
            font-weight: normal;
            margin-top: 3px;
        }

        .facts {
            width: 100%;
            margin-top: 8px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 5px 0;
        }
        .facts td {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
            padding: 6px 6px 7px;
        }
        .facts .lbl {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #64748b;
            text-transform: uppercase;
        }
        .facts .val {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }

        .split {
            width: 100%;
            margin-top: 6px;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .split td.gap {
            width: 6px;
            border: none;
            padding: 0;
            background: transparent;
        }
        /* Same fixed height on both panels so bottoms align in DomPDF */
        .split td.panel {
            vertical-align: top;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 0;
            height: 88px;
        }
        .card-hd {
            background: #0f172a;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.12em;
            padding: 5px 10px;
        }
        .card-bd {
            padding: 7px 10px 8px;
        }
        .cust-name { font-size: 11.5px; font-weight: bold; color: #0f172a; }
        .cust-line { font-size: 9.5px; color: #334155; margin-top: 1px; }
        .kv { width: 100%; }
        .kv td { border: none; padding: 2px 0; font-size: 9.5px; }
        .kv .k {
            width: 42%;
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .kv .v { width: 58%; text-align: right; font-weight: bold; color: #0f172a; }

        /* DomPDF only honors field widths via CSS % on col+td classes (not HTML width / pt / px). */
        table.items {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        table.items col.col-qty,
        table.items td.col-qty { width: 7%; }
        table.items col.col-item,
        table.items td.col-item { width: 8%; }
        table.items col.col-desc,
        table.items td.col-desc { width: 52%; }
        table.items col.col-uom,
        table.items td.col-uom { width: 9%; }
        table.items col.col-price,
        table.items td.col-price { width: 13%; }
        table.items col.col-amt,
        table.items td.col-amt { width: 11%; }
        table.items tfoot { display: table-footer-group; }
        table.items tfoot td {
            padding: 0;
            height: 1px;
            font-size: 1px;
            line-height: 1px;
            border: none;
            border-top: 1px solid #94a3b8;
        }
        table.items td {
            height: 20px;
            font-size: 9.5px;
            line-height: 1.15;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            overflow: hidden;
            padding: 3px 2px;
            white-space: nowrap;
        }
        table.items tr.head td {
            background: #0f172a;
            color: #fff;
            font-size: 7.5px;
            font-weight: bold;
            height: 24px;
            padding: 5px 2px;
            border-bottom: 1px solid #0f172a;
        }
        table.items tr.empty td {
            height: 20px;
            color: transparent;
            border-bottom: 1px solid #f1f5f9;
        }
        table.items td.col-qty { text-align: left; padding-left: 8px !important; }
        table.items td.col-item { text-align: left; font-size: 8.5px; }
        table.items td.col-desc { text-align: left; }
        table.items td.col-uom { text-align: center; }
        table.items td.col-price { text-align: right; padding-right: 16px !important; }
        table.items td.col-amt { text-align: right; padding-left: 10px !important; padding-right: 10px !important; }

        .foot-grid { width: 100%; table-layout: fixed; }
        .foot-grid > tbody > tr > td { border: none; vertical-align: bottom; padding: 0; }
        .notes {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .notes-hd {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #64748b;
            padding: 6px 10px 0;
            text-transform: uppercase;
        }
        .notes-bd {
            padding: 5px 10px 9px;
            font-size: 10px;
            color: #334155;
            min-height: 38px;
        }
        .totals {
            width: 250px;
            margin-left: auto;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        .totals td {
            padding: 7px 12px;
            font-size: 10.5px;
            font-weight: bold;
            border-bottom: 1px solid #e2e8f0;
        }
        .totals tr:last-child td { border-bottom: none; }
        .totals .lbl {
            width: 56%;
            background: #f8fafc;
            color: #475569;
            letter-spacing: 0.04em;
            border-right: 1px solid #e2e8f0;
        }
        .totals .amt { width: 44%; text-align: right; color: #0f172a; background: #fff; }
        .totals tr.grand td {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 9px 12px;
            font-size: 12px;
        }
        .totals tr.grand .lbl { background: #0f172a; border-right: 1px solid #334155; color: #fff; }
        .totals tr.grand .amt { background: #0f172a; color: #fff; }
        .thanks {
            margin-top: 9px;
            text-align: center;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.14em;
            color: #64748b;
        }
        .thanks-line {
            border-top: 1px solid #cbd5e1;
            margin: 0 12% 6px;
            font-size: 1px;
            line-height: 1px;
        }
        .thanks-line-b {
            border-top: 1px solid #cbd5e1;
            margin: 6px 12% 0;
            font-size: 1px;
            line-height: 1px;
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

    $accountNo = filled($customer?->customer_id) ? $customer->customer_id : '—';
    $orderNo = filled($order?->order_number) ? $order->order_number : '—';
    $salesRepName = filled($order?->salesRep?->name) ? $order->salesRep->name : '—';
    $driverLabel = filled($order?->invoice?->driver) ? $order->invoice->driver : '—';

    $memoLines = $memo->relationLoaded('lines') ? $memo->lines : $memo->lines()->with('item')->get();
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
            'item' => null,
        ]);
    }
    if ($memoLines->isEmpty() && (float) ($memo->amount ?? 0) > 0.004) {
        $memoLines = collect([(object) [
            'qty' => 1.0,
            'item_code' => '000',
            'description' => 'CREDIT',
            'uom' => 'EA',
            'price' => (float) $memo->amount,
            'line_total' => (float) $memo->amount,
            'item' => null,
        ]]);
    }

    $lines = $memoLines->values();
    $lineCount = $lines->count();
    $subtotal = (float) $lines->sum(fn ($l) => (float) $l->line_total);
    $tax = (float) ($memo->tax ?? 0);
    $creditTotal = (float) ($memo->amount ?? $subtotal);
    $commentsText = filled($memo->comments) ? (string) $memo->comments : 'Credit Memo';

    // Empty rows fill after last real line so comments/totals sit near page bottom.
    // 19 fits letter under fixed header + footer; 22 spilled footer onto an extra page.
    $rowsPerFullPage = 30;    // continued pages (items only)
    $rowsOnLastWithFoot = 19;

    if ($lineCount <= $rowsOnLastWithFoot) {
        $fillerCount = max(0, $rowsOnLastWithFoot - $lineCount);
    } else {
        $onLast = $lineCount % $rowsPerFullPage;
        if ($onLast === 0) {
            $fillerCount = $rowsOnLastWithFoot;
        } elseif ($onLast < $rowsOnLastWithFoot) {
            $fillerCount = $rowsOnLastWithFoot - $onLast;
        } else {
            $fillerCount = 0;
        }
    }

    $formatPhone = static function (?string $raw): string {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6);
        }

        return preg_replace('/^(Tel|Fax):\s*/i', '', (string) $raw);
    };

    $telDisplay = $formatPhone($companyTel);
    $faxDisplay = $companyFax ? $formatPhone($companyFax) : '';
    $billPhoneDisplay = $billPhone !== '' ? $formatPhone($billPhone) : '';
@endphp

<div class="page-head">
    <table class="mh">
        <tr>
            <td style="width:58%">
                <div class="brand">{{ $companyName }}</div>
                <div class="brand-sub">{{ trim($companyAddress.($companyCityLine !== '' ? ', '.$companyCityLine : '')) }}</div>
                <div class="brand-sub">
                    Tel: {{ $telDisplay }}
                    @if ($faxDisplay !== '')
                        &nbsp;|&nbsp; Fax: {{ $faxDisplay }}
                    @endif
                </div>
            </td>
            <td style="width:42%">
                <div class="title-chip">
                    CREDIT MEMO
                    <div class="chip-sub">CUSTOMER CREDIT</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="facts">
        <tr>
            <td>
                <div class="lbl">Memo No.</div>
                <div class="val">{{ $memo->memo_number }}</div>
            </td>
            <td>
                <div class="lbl">Date</div>
                <div class="val">{{ optional($memo->memo_date)?->format('m/d/Y') }}</div>
            </td>
            <td>
                <div class="lbl">Page</div>
                <div class="val"><span class="pagenum"></span></div>
            </td>
        </tr>
    </table>

    <table class="split">
        <tr>
            <td class="panel" style="width:53%;height:88px;">
                <div class="card-hd">BILL TO</div>
                <div class="card-bd">
                    <div class="cust-name">{{ $billName !== '' ? $billName : '—' }}</div>
                    @if ($billAddress !== '')
                        <div class="cust-line">{{ $billAddress }}</div>
                    @endif
                    @if ($billCity !== '')
                        <div class="cust-line">{{ $billCity }}</div>
                    @endif
                    @if ($billPhoneDisplay !== '')
                        <div class="cust-line">Tel: {{ $billPhoneDisplay }}</div>
                    @endif
                </div>
            </td>
            <td class="gap">&nbsp;</td>
            <td class="panel" style="width:46%;height:88px;">
                <div class="card-hd">REFERENCE</div>
                <div class="card-bd">
                    <table class="kv">
                        <tr><td class="k">Account</td><td class="v">{{ $accountNo }}</td></tr>
                        <tr><td class="k">Order No.</td><td class="v">{{ $orderNo }}</td></tr>
                        <tr><td class="k">Sale Rep.</td><td class="v">{{ $salesRepName }}</td></tr>
                        <tr><td class="k">Driver</td><td class="v">{{ $driverLabel }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- Field widths: QTY 7% | ITEM 8% | DESC 52% | UOM 9% | PRICE 13% | AMOUNT 11% --}}
<table class="items" width="100%">
    <colgroup>
        <col class="col-qty">
        <col class="col-item">
        <col class="col-desc">
        <col class="col-uom">
        <col class="col-price">
        <col class="col-amt">
    </colgroup>
    <tfoot>
        <tr>
            <td class="col-qty"></td>
            <td class="col-item"></td>
            <td class="col-desc"></td>
            <td class="col-uom"></td>
            <td class="col-price"></td>
            <td class="col-amt"></td>
        </tr>
    </tfoot>
    <tbody>
        <tr class="head">
            <td class="col-qty" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">QTY</td>
            <td class="col-item" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">ITEM</td>
            <td class="col-desc" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">DESCRIPTION</td>
            <td class="col-uom" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">UOM</td>
            <td class="col-price" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">PRICE</td>
            <td class="col-amt" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">AMOUNT</td>
        </tr>
        @foreach ($lines as $i => $line)
            @php
                $uom = filled($line->uom ?? null)
                    ? $line->uom
                    : (filled($line->item?->uom ?? null) ? $line->item->uom : '—');
                $bg = ($i % 2 === 1) ? '#f8fafc' : '#ffffff';
                $desc = preg_replace('/\s+/u', ' ', trim((string) ($line->description ?? '')));
            @endphp
            <tr>
                <td class="col-qty" style="background:{{ $bg }};">{{ number_format((float) $line->qty, 2) }}</td>
                <td class="col-item" style="background:{{ $bg }};">{{ $line->item_code }}</td>
                <td class="col-desc" style="background:{{ $bg }};">{{ $desc }}</td>
                <td class="col-uom" style="background:{{ $bg }};">{{ $uom }}</td>
                <td class="col-price" style="background:{{ $bg }};">${{ number_format((float) $line->price, 2) }}</td>
                <td class="col-amt" style="background:{{ $bg }};">${{ number_format((float) $line->line_total, 2) }}</td>
            </tr>
        @endforeach

        @for ($f = 0; $f < $fillerCount; $f++)
            @php
                $idx = $lineCount + $f;
                $bg = ($idx % 2 === 1) ? '#fafbfc' : '#ffffff';
            @endphp
            <tr class="empty">
                <td class="col-qty" style="background:{{ $bg }};">&nbsp;</td>
                <td class="col-item" style="background:{{ $bg }};">&nbsp;</td>
                <td class="col-desc" style="background:{{ $bg }};">&nbsp;</td>
                <td class="col-uom" style="background:{{ $bg }};">&nbsp;</td>
                <td class="col-price" style="background:{{ $bg }};">&nbsp;</td>
                <td class="col-amt" style="background:{{ $bg }};">&nbsp;</td>
            </tr>
        @endfor
    </tbody>
</table>

{{-- Comments + totals + thank-you: only once (after all lines = last page), no overlay --}}
<div class="doc-foot">
    <table class="foot-grid">
        <tr>
            <td style="width:54%; padding-right:10px;">
                <table class="notes">
                    <tr><td class="notes-hd">Comments</td></tr>
                    <tr><td class="notes-bd">{{ $commentsText }}</td></tr>
                </table>
            </td>
            <td style="width:46%">
                <table class="totals">
                    <tr>
                        <td class="lbl">SUBTOTAL</td>
                        <td class="amt">${{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">TAX</td>
                        <td class="amt">${{ number_format($tax, 2) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="lbl" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">TOTAL CREDIT</td>
                        <td class="amt" bgcolor="#0f172a" style="background:#0f172a;color:#fff;">${{ number_format($creditTotal, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="thanks">
        <div class="thanks-line">&nbsp;</div>
        THANK YOU FOR YOUR BUSINESS
        <div class="thanks-line-b">&nbsp;</div>
    </div>
</div>
</body>
</html>
