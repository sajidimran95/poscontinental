<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pick List {{ $order->order_number }}</title>
    <style>
        @page { margin: 32px 40px 36px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.25;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: none; vertical-align: top; padding: 0; }

        /* Row 1: barcode | company+title | page */
        .top {
            width: 100%;
            margin-bottom: 8px;
        }
        .top td { vertical-align: middle; }
        .top-left { width: 28%; text-align: left; }
        .top-center { width: 44%; text-align: center; }
        .top-right { width: 28%; text-align: right; vertical-align: top; padding-top: 4px; }

        .barcode-wrap { text-align: left; }
        .co-name {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.01em;
            line-height: 1.15;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
        }
        .page-no {
            font-size: 10px;
            white-space: nowrap;
        }
        .page-no:after {
            content: "Page " counter(page);
        }

        /* Border under header */
        .hdr-rule {
            border: none;
            border-top: 1.5px solid #222;
            margin: 0 0 10px;
            height: 0;
        }

        /* Row 2: order info left | account/ship right */
        .meta {
            width: 100%;
            margin-bottom: 12px;
        }
        .meta td { vertical-align: top; }
        .meta-left { width: 50%; padding-right: 10px; }
        .meta-right { width: 50%; padding-left: 10px; }

        .kv {
            font-size: 10px;
            margin: 0 0 2px;
            line-height: 1.4;
        }
        .kv .lbl { font-weight: bold; }

        .ship-name {
            font-weight: bold;
            font-size: 10.5px;
            margin: 1px 0 0;
        }
        .ship-line {
            margin: 0;
            font-size: 10px;
            line-height: 1.35;
        }

        /* Department blocks: title + table + border */
        .dept-block {
            margin-bottom: 0;
            page-break-inside: avoid;
        }
        .dept-title {
            font-weight: bold;
            font-size: 11px;
            padding: 8px 6px 4px;
            border: 1px solid #222;
            border-bottom: none;
            background: #f5f5f5;
        }
        .dept-rule {
            border: none;
            border-top: 1.5px solid #222;
            margin: 12px 0 4px;
            height: 0;
        }

        table.items {
            width: 100%;
            table-layout: fixed;
            border: 1px solid #222;
            border-top: none;
            margin-bottom: 0;
        }
        table.items col.col-no { width: 5%; }
        table.items col.col-qty { width: 8%; }
        table.items col.col-item { width: 14%; }
        table.items col.col-uom { width: 8%; }
        table.items col.col-desc { width: 65%; }

        table.items thead th {
            font-size: 9px;
            font-weight: bold;
            text-align: left;
            padding: 4px 4px 5px;
            border-bottom: 1px solid #222;
            border-top: 1px solid #222;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            background: #fafafa;
        }
        table.items thead th.col-no,
        table.items thead th.col-qty { text-align: right; }
        table.items thead th.col-uom { text-align: center; }

        table.items td {
            padding: 3px 4px;
            font-size: 10px;
            vertical-align: top;
            border-bottom: 1px solid #ddd;
        }
        table.items tbody tr:last-child td {
            border-bottom: none;
        }
        table.items td.col-no {
            text-align: right;
            color: #444;
            padding-right: 6px;
        }
        table.items td.col-qty {
            text-align: right;
            font-weight: bold;
            padding-right: 8px;
            white-space: nowrap;
        }
        table.items td.col-item {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9.5px;
        }
        table.items td.col-uom { text-align: center; }
        table.items td.col-desc { word-wrap: break-word; }

        .empty-msg {
            padding: 12px 0;
            color: #666;
            font-size: 10px;
        }

        .instr {
            margin-top: 2px;
            font-size: 9px;
            color: #111;
            line-height: 1.35;
        }
        .instr-lbl {
            font-weight: bold;
            margin-right: 3px;
        }
    </style>
</head>
<body>
@php
    use App\Support\Code128Barcode;

    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $barcodeValue = (string) ($barcodeValue ?? $order->order_number);
    $accountNo = $order->customer?->customer_id ?: '';
    $driverLabel = $order->invoice?->driver ?: '';
    $routeLabel = $order->route?->name ?: ($order->route?->code ?: '');
    $cityLabel = $order->ship_to_city ?: ($order->bill_to_city ?: '');
    $statusLabel = $order->status ?: '';

    $shipName = $order->ship_to_name ?: ($order->bill_to_name ?: ($order->customer?->company_name ?: ''));
    $shipAddress = $order->ship_to_address ?: $order->bill_to_address;
    $shipCityLine = collect([
        $order->ship_to_city ?: $order->bill_to_city,
        $order->ship_to_state ?: $order->bill_to_state,
        $order->ship_to_zip ?: $order->bill_to_zip,
    ])->filter()->implode(' ');
    $shipPhone = $order->ship_to_phone ?: ($order->bill_to_phone ?: ($order->customer?->telephone ?: ''));

    $formatQty = static function ($qty): string {
        $n = (float) $qty;
        if (abs($n - round($n)) < 0.00001) {
            return number_format($n, 1, '.', '');
        }

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    };

    // Department name-wise groups (warehouse pick order)
    $deptLabelFor = static function ($line): array {
        $dept = $line->item?->department;
        $code = trim((string) ($dept?->code ?? ''));
        $name = trim((string) ($dept?->name ?? ''));

        if ($code !== '' && $name !== '') {
            $label = $code.'-'.$name;
        } elseif ($name !== '') {
            $label = $name;
        } elseif ($code !== '') {
            $label = $code;
        } else {
            // Fallback to category if no department
            $cat = $line->item?->category;
            $cCode = trim((string) ($cat?->code ?? ''));
            $cName = trim((string) ($cat?->name ?? ''));
            if ($cCode !== '' && $cName !== '') {
                $label = $cCode.'-'.$cName;
            } elseif ($cName !== '') {
                $label = $cName;
            } else {
                $label = 'Other';
            }
            $code = $cCode !== '' ? $cCode : 'ZZZ';
        }

        return [
            'sort' => strtoupper($code !== '' ? $code : $label),
            'label' => $label,
        ];
    };

    $groups = $order->lines
        ->map(function ($line) use ($deptLabelFor) {
            $meta = $deptLabelFor($line);
            $line->_dept_sort = $meta['sort'];
            $line->_dept_label = $meta['label'];

            return $line;
        })
        ->sortBy(fn ($line) => [
            $line->_dept_sort,
            (int) $line->line_no,
            strtoupper((string) $line->item_code),
        ])
        ->groupBy(fn ($line) => $line->_dept_label);
@endphp

{{-- TOP: barcode left | name+title center | page right --}}
<table class="top">
    <tr>
        <td class="top-left">
            <div class="barcode-wrap">
                {!! Code128Barcode::html($barcodeValue, 1, 34, 'left') !!}
            </div>
        </td>
        <td class="top-center">
            <div class="co-name">{{ $companyName }}</div>
            <div class="doc-title">Pick List</div>
        </td>
        <td class="top-right">
            <div class="page-no"></div>
        </td>
    </tr>
</table>

<hr class="hdr-rule">

{{-- META: order status/info left | account + ship to right --}}
<table class="meta">
    <tr>
        <td class="meta-left">
            <div class="kv"><span class="lbl">Order No.:</span> {{ $order->order_number }}</div>
            <div class="kv"><span class="lbl">Order Date:</span> {{ optional($order->order_date)?->format('m/d/Y') }}</div>
            <div class="kv"><span class="lbl">Order Status:</span> {{ $statusLabel }}</div>
            <div class="kv"><span class="lbl">Sales Rep:</span> {{ $order->salesRep?->name ?: '' }}</div>
            <div class="kv"><span class="lbl">Driver:</span> {{ $driverLabel }}</div>
            <div class="kv"><span class="lbl">Route/City:</span> {{ collect([$routeLabel, $cityLabel])->filter()->implode(' / ') }}</div>
        </td>
        <td class="meta-right">
            <div class="kv"><span class="lbl">Account No.:</span> {{ $accountNo }}</div>
            <div class="kv"><span class="lbl">Ship To:</span></div>
            @if ($shipName !== '')
                <div class="ship-name">{{ $shipName }}</div>
            @endif
            @if (filled($shipAddress))
                <div class="ship-line">{{ $shipAddress }}</div>
            @endif
            @if ($shipCityLine !== '')
                <div class="ship-line">{{ $shipCityLine }}</div>
            @endif
            @if (filled($shipPhone))
                <div class="ship-line">Tel:{{ preg_replace('/^Tel:\s*/i', '', (string) $shipPhone) }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Department-wise: name → table (serial #) → border → next department --}}
@if ($groups->isEmpty())
    <p class="empty-msg">No line items on this sales order.</p>
@else
    @foreach ($groups as $deptLabel => $lines)
        <div class="dept-block">
            <div class="dept-title">{{ $deptLabel }}</div>
            <table class="items">
                <colgroup>
                    <col class="col-no">
                    <col class="col-qty">
                    <col class="col-item">
                    <col class="col-uom">
                    <col class="col-desc">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-no">#</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-item">Item Code</th>
                        <th class="col-uom">UOM</th>
                        <th class="col-desc">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr>
                            <td class="col-no">{{ $i + 1 }}</td>
                            <td class="col-qty">{{ $formatQty($line->qty_ordered) }}</td>
                            <td class="col-item">{{ $line->item_code }}</td>
                            <td class="col-uom">{{ $line->uom ?: '' }}</td>
                            <td class="col-desc">
                                <div>{{ $line->description }}</div>
                                @if (filled($line->instructions))
                                    <div class="instr">
                                        <span class="instr-lbl">Instructions:</span>
                                        {{ $line->instructions }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! $loop->last)
            <hr class="dept-rule">
        @endif
    @endforeach
@endif
</body>
</html>
