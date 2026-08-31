<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pick List {{ $order->order_number }}</title>
    <style>
        @page { margin: 36px 40px 42px 40px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #000;
        }
        table { border-collapse: collapse; }
        .hdr { width: 100%; margin-bottom: 4px; }
        .hdr td { vertical-align: middle; }
        .hdr-left { width: 34%; text-align: left; font-size: 11pt; font-weight: bold; }
        .hdr-center { width: 32%; text-align: center; font-size: 16pt; font-weight: bold; }
        .hdr-right { width: 34%; text-align: right; font-size: 9pt; font-weight: bold; height: 16px; }
        .rule { border-top: 1px solid #000; margin: 4px 0 8px; height: 0; }
        .rule2 { border-top: 2px solid #000; margin: 8px 0 10px; height: 0; }
        .meta { width: 100%; margin-bottom: 2px; }
        .meta td { vertical-align: top; font-size: 9pt; }
        .meta-l { width: 48%; }
        .meta-r { width: 52%; }
        .kv { margin: 0 0 2px; }
        .ship { font-weight: bold; text-transform: uppercase; }
        .ship-line { text-transform: uppercase; padding-left: 4.5em; margin: 0; }

        /* Flat item table — no outer wrapper cell (that caused blank pages) */
        table.lines {
            width: 100%;
            table-layout: fixed;
        }
        table.lines thead { display: table-header-group; }
        table.lines tbody { display: table-row-group; }
        tr.cat td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9.5pt;
            font-weight: bold;
            background: #fff;
        }
        tr.gap td {
            height: 12px;
            border: none;
            padding: 0;
            font-size: 1px;
            line-height: 12px;
        }
        tr.item td {
            padding: 3px 2px;
            border-bottom: 1px solid #999;
            vertical-align: top;
        }
        td.q { width: 48px; font-size: 8pt; font-weight: bold; white-space: nowrap; }
        td.c { width: 72px; font-size: 8pt; white-space: nowrap; }
        td.u { width: 36px; font-size: 8pt; white-space: nowrap; }
        td.d {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            word-wrap: break-word;
        }
        .instr { margin-top: 1px; font-size: 8pt; text-transform: none; font-weight: normal; }
    </style>
</head>
<body>
@php
    $accountNo = $order->customer?->customer_id ?: '';
    $driverLabel = $order->invoice?->driver ?: '';
    $routeLabel = $order->route?->name ?: ($order->route?->code ?: '');

    $shipName = $order->ship_to_name ?: ($order->bill_to_name ?: ($order->customer?->company_name ?: ''));
    $shipAddress = $order->ship_to_address ?: $order->bill_to_address;
    $shipCityLine = collect([
        $order->ship_to_city ?: $order->bill_to_city,
        $order->ship_to_state ?: $order->bill_to_state,
        $order->ship_to_zip ?: $order->bill_to_zip,
    ])->filter()->implode(' ');
    $shipPhone = $order->ship_to_phone ?: ($order->bill_to_phone ?: ($order->customer?->telephone ?: ''));

    $formatQty = static function ($qty): string {
        return number_format((float) $qty, 1, '.', '');
    };

    $groupLabelFor = static function ($line): array {
        $sub = trim((string) ($line->item?->subcategory?->name ?? ''));
        $cat = trim((string) ($line->item?->category?->name ?? ''));
        $label = $sub !== '' ? $sub : ($cat !== '' ? $cat : 'Other');

        return [
            'cat' => strtoupper($cat !== '' ? $cat : $label),
            'label' => $label,
        ];
    };

    $groups = $order->lines
        ->map(function ($line) use ($groupLabelFor) {
            $meta = $groupLabelFor($line);
            $line->_grp_cat = $meta['cat'];
            $line->_grp_label = $meta['label'];

            return $line;
        })
        ->sortBy(fn ($line) => [
            $line->_grp_cat,
            strtoupper((string) $line->_grp_label),
            strtoupper((string) $line->item_code),
            (int) $line->line_no,
        ])
        ->groupBy(fn ($line) => $line->_grp_label);
@endphp

<table class="lines">
    <colgroup>
        <col style="width:48px">
        <col style="width:72px">
        <col style="width:36px">
        <col>
    </colgroup>
    <thead>
        <tr>
            <td colspan="4" style="padding:0 0 6px 0; border:none;">
                <table class="hdr">
                    <tr>
                        <td class="hdr-left">Order No. {{ $order->order_number }}</td>
                        <td class="hdr-center">Pick List</td>
                        <td class="hdr-right">&nbsp;</td>
                    </tr>
                </table>
                <div class="rule"></div>
                <table class="meta">
                    <tr>
                        <td class="meta-l">
                            <div class="kv">Order Date: {{ optional($order->order_date)?->format('m/d/Y') }}</div>
                            <div class="kv">Sales Rep.: {{ $order->salesRep?->name ?: '' }}</div>
                            <div class="kv">Driver: {{ $driverLabel }}</div>
                            <div class="kv">Route: {{ $routeLabel }}</div>
                        </td>
                        <td class="meta-r">
                            <div class="kv">Account No.: {{ $accountNo }}</div>
                            <div class="kv">Ship to: <span class="ship">{{ $shipName }}</span></div>
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
                <div class="rule2"></div>
            </td>
        </tr>
        {{-- Invisible width lock row (DomPDF needs this when thead has colspan) --}}
        <tr>
            <td class="q" style="height:0;padding:0;border:none;font-size:0;line-height:0;overflow:hidden;">&nbsp;</td>
            <td class="c" style="height:0;padding:0;border:none;font-size:0;line-height:0;overflow:hidden;">&nbsp;</td>
            <td class="u" style="height:0;padding:0;border:none;font-size:0;line-height:0;overflow:hidden;">&nbsp;</td>
            <td class="d" style="height:0;padding:0;border:none;font-size:0;line-height:0;overflow:hidden;">&nbsp;</td>
        </tr>
    </thead>
    <tbody>
        @forelse ($groups as $grpLabel => $lines)
            <tr class="cat">
                <td colspan="4">{{ $grpLabel }}</td>
            </tr>
            @foreach ($lines as $line)
                <tr class="item">
                    <td class="q">{{ $formatQty($line->qty_ordered) }}</td>
                    <td class="c">{{ $line->item_code }}</td>
                    <td class="u">{{ $line->uom ?: '' }}</td>
                    <td class="d">
                        {{ $line->description }}
                        @if (filled($line->instructions))
                            <div class="instr">{{ $line->instructions }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
            @unless ($loop->last)
                <tr class="gap"><td colspan="4">&nbsp;</td></tr>
            @endunless
        @empty
            <tr>
                <td colspan="4" style="padding:12px 0;">No line items on this sales order.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
