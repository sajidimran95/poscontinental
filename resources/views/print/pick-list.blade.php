<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pick List {{ $order->order_number }}</title>
    <style>
        /* Chief pick-list (letter) — match warehouse screenshots */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            background: #c8c8c8;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            font-weight: normal;
            line-height: 1.25;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 14px;
            background: #333;
        }
        .toolbar button {
            border: 0;
            border-radius: 3px;
            padding: 7px 14px;
            font: bold 12px Arial, Helvetica, sans-serif;
            cursor: pointer;
            background: #eee;
        }
        .toolbar .go { background: #2f7fd1; color: #fff; }

        /* Screen preview: stacked letter pages */
        #pages {
            padding: 14px 0 28px;
        }
        .page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto 16px;
            padding: 0.42in 0.55in 0.48in;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.28);
            position: relative;
        }
        .page-body {
            /* filled by paginator */
        }

        .banner {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .banner td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .banner .o {
            width: 33%;
            text-align: left;
            font-size: 11pt;
            font-weight: bold;
        }
        .banner .t {
            width: 34%;
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
        }
        .banner .p {
            width: 33%;
            text-align: right;
            font-size: 10.5pt;
            font-weight: bold;
            white-space: nowrap;
        }

        .rule {
            border: 0;
            border-top: 1px solid #000;
            margin: 3px 0 8px;
        }
        /* Double rule under meta (Chief) */
        .rule-dbl {
            border: 0;
            height: 0;
            margin: 10px 0 11px;
            border-top: 1px solid #000;
            box-shadow: 0 2px 0 #000;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
        }
        .meta td {
            border: none;
            vertical-align: top;
            padding: 0;
            font-size: 10.5pt;
        }
        .meta .a { width: 48%; padding-right: 10px; }
        .meta .b { width: 52%; }
        .kv { margin: 0 0 2px; }
        .nm { font-weight: bold; text-transform: uppercase; }
        .ad {
            margin: 0;
            text-transform: uppercase;
            padding-left: 4.5em;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        col.c-q { width: 0.62in; }
        col.c-i { width: 0.95in; }
        col.c-u { width: 0.40in; }
        col.c-d { width: auto; }

        /* Category: small name box + full-width rules (Chief) */
        tr.cat td {
            border: none;
            padding: 0;
            background: transparent;
        }
        .cat-wrap {
            border-top: none;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 0;
            text-align: left;
        }
        .cat-box {
            display: inline-block;
            width: max-content;
            max-width: max-content;
            border: 1px solid #000;
            padding: 2px 9px 1px;
            font-size: 11pt;
            font-weight: bold;
            background: #fff;
            line-height: 1.15;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: middle;
        }

        /* Clear space between category sections (match Chief photo) */
        tr.gap td {
            height: 32px;
            border: none !important;
            padding: 0 !important;
            font-size: 0;
            line-height: 0;
            background: transparent !important;
        }
        tr.gap td::before {
            content: '';
            display: block;
            height: 32px;
        }

        tr.ln td {
            padding: 2px 3px 2px 0;
            border: none;
            border-bottom: 1px solid #9a9a9a;
            vertical-align: top;
            font-size: 10.5pt;
            font-weight: normal;
        }
        tr.ln.z td { background: #efefef; }

        td.q { font-weight: bold; white-space: nowrap; }
        td.i { white-space: nowrap; }
        td.u { white-space: nowrap; }
        td.d {
            text-transform: uppercase;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        .msg {
            margin-top: 1px;
            font-size: 9pt;
            text-transform: none;
            font-weight: normal;
        }

        /* Source rows measured off-screen */
        #measure {
            position: absolute;
            left: -9999px;
            top: 0;
            width: 7.4in; /* content width inside page padding */
            visibility: hidden;
            pointer-events: none;
        }

        @media print {
            html, body { background: #fff; }
            .toolbar, #measure, #source { display: none !important; }
            #pages { padding: 0; }
            .page {
                width: auto;
                min-height: 0;
                height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            @page {
                size: letter portrait;
                margin: 0.42in 0.55in 0.48in 0.55in;
            }
        }
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

    $formatQty = static fn ($qty): string => number_format((float) $qty, 1, '.', '');

    $groups = $order->lines
        ->map(function ($line) {
            $sub = trim((string) ($line->item?->subcategory?->name ?? ''));
            $cat = trim((string) ($line->item?->category?->name ?? ''));
            $label = $sub !== '' ? $sub : ($cat !== '' ? $cat : 'Other');
            $line->_grp_cat = strtoupper($cat !== '' ? $cat : $label);
            $line->_grp_label = $label;

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

<div class="toolbar">
    <button type="button" class="go" onclick="window.print()">Print</button>
    <button type="button" onclick="window.close()">Close</button>
</div>

{{-- Hidden source used only for measurement / cloning --}}
<div id="source" hidden>
    <div class="hdr-tpl">
        <table class="banner">
            <tr>
                <td class="o">Order No. {{ $order->order_number }}</td>
                <td class="t">Pick List</td>
                <td class="p">Page <span class="pg-cur">1</span> of <span class="pg-tot">1</span></td>
            </tr>
        </table>
        <hr class="rule">
        <table class="meta">
            <tr>
                <td class="a">
                    <div class="kv">Order Date: {{ optional($order->order_date)?->format('m/d/Y') }}</div>
                    <div class="kv">Sales Rep.: {{ $order->salesRep?->name ?: '' }}</div>
                    <div class="kv">Driver: {{ $driverLabel }}</div>
                    <div class="kv">Route: {{ $routeLabel }}</div>
                </td>
                <td class="b">
                    <div class="kv">Account No.: {{ $accountNo }}</div>
                    <div class="kv">Ship to: <span class="nm">{{ $shipName }}</span></div>
                    @if (filled($shipAddress))
                        <p class="ad">{{ $shipAddress }}</p>
                    @endif
                    @if ($shipCityLine !== '')
                        <p class="ad">{{ $shipCityLine }}</p>
                    @endif
                    @if (filled($shipPhone))
                        <p class="ad">Tel:{{ preg_replace('/^Tel:\s*/i', '', (string) $shipPhone) }}</p>
                    @endif
                </td>
            </tr>
        </table>
        <hr class="rule-dbl">
    </div>

    <table class="lines" id="src-lines">
        <colgroup>
            <col class="c-q">
            <col class="c-i">
            <col class="c-u">
            <col class="c-d">
        </colgroup>
        <tbody>
            @forelse ($groups as $grpLabel => $lines)
                <tr class="cat" data-kind="cat">
                    <td colspan="4">
                        <div class="cat-wrap">
                            <span class="cat-box">{{ strtoupper($grpLabel) }}</span>
                        </div>
                    </td>
                </tr>
                @foreach ($lines as $line)
                    <tr class="ln {{ $loop->even ? 'z' : '' }}" data-kind="ln">
                        <td class="q">{{ $formatQty($line->qty_ordered) }}</td>
                        <td class="i">{{ $line->item_code }}</td>
                        <td class="u">{{ $line->uom ?: '' }}</td>
                        <td class="d">
                            {{ $line->description }}
                            @if (filled($line->instructions))
                                <div class="msg">{{ $line->instructions }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @unless ($loop->last)
                    <tr class="gap" data-kind="gap"><td colspan="4"></td></tr>
                @endunless
            @empty
                <tr class="ln" data-kind="ln">
                    <td colspan="4" style="padding:12px 0;">No line items on this sales order.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="measure"></div>
<div id="pages"></div>

<script>
(function () {
    var PAGE_BODY_MAX = 9.35 * 96; // px of printable body under header (~letter minus margins/header)

    function buildPages() {
        var src = document.getElementById('source');
        var hdrTpl = src.querySelector('.hdr-tpl');
        var rows = Array.prototype.slice.call(src.querySelectorAll('#src-lines tbody tr'));
        var pagesEl = document.getElementById('pages');
        var measure = document.getElementById('measure');
        pagesEl.innerHTML = '';

        // Measure header once
        measure.innerHTML = '';
        measure.appendChild(hdrTpl.cloneNode(true));
        var headerH = measure.offsetHeight;
        var bodyMax = Math.max(280, PAGE_BODY_MAX - headerH);

        // Measure each row
        var probe = document.createElement('table');
        probe.className = 'lines';
        probe.innerHTML = '<colgroup><col class="c-q"><col class="c-i"><col class="c-u"><col class="c-d"></colgroup><tbody></tbody>';
        measure.innerHTML = '';
        measure.appendChild(probe);
        var tb = probe.querySelector('tbody');
        var heights = rows.map(function (row) {
            var clone = row.cloneNode(true);
            tb.innerHTML = '';
            tb.appendChild(clone);
            return Math.max(16, clone.offsetHeight);
        });

        // Pack rows into pages
        var pages = [];
        var cur = [];
        var used = 0;
        rows.forEach(function (row, i) {
            var h = heights[i];
            if (cur.length && used + h > bodyMax) {
                pages.push(cur);
                cur = [];
                used = 0;
            }
            // Keep category with at least one following line when possible
            if (row.getAttribute('data-kind') === 'cat' && cur.length && used + h + 22 > bodyMax) {
                pages.push(cur);
                cur = [];
                used = 0;
            }
            cur.push(row);
            used += h;
        });
        if (cur.length) pages.push(cur);
        if (!pages.length) pages.push([]);

        var total = pages.length;
        pages.forEach(function (pageRows, idx) {
            var page = document.createElement('div');
            page.className = 'page';

            var hdr = hdrTpl.cloneNode(true);
            hdr.querySelector('.pg-cur').textContent = String(idx + 1);
            hdr.querySelector('.pg-tot').textContent = String(total);
            page.appendChild(hdr);

            var table = document.createElement('table');
            table.className = 'lines';
            table.innerHTML = '<colgroup><col class="c-q"><col class="c-i"><col class="c-u"><col class="c-d"></colgroup>';
            var tbody = document.createElement('tbody');
            // Re-stripe within page for cleaner look
            var lnI = 0;
            pageRows.forEach(function (row) {
                var clone = row.cloneNode(true);
                if (clone.getAttribute('data-kind') === 'ln') {
                    clone.classList.toggle('z', (lnI % 2) === 1);
                    lnI++;
                }
                tbody.appendChild(clone);
            });
            table.appendChild(tbody);
            page.appendChild(table);
            pagesEl.appendChild(page);
        });

        measure.innerHTML = '';
    }

    function ready() {
        buildPages();
        if (new URLSearchParams(location.search).get('autoprint') === '0') return;
        setTimeout(function () { window.print(); }, 250);
    }

    if (document.readyState === 'complete') ready();
    else window.addEventListener('load', ready);
})();
</script>
</body>
</html>
