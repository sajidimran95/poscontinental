<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles')
    <style>
        table.lines th,
        table.lines td {
            vertical-align: middle;
            padding: 6px 8px;
        }
        table.lines th.num,
        table.lines td.num {
            text-align: right;
            white-space: nowrap;
            width: 82px;
            padding-right: 10px;
        }
        table.lines th.ctr,
        table.lines td.ctr {
            text-align: center;
            white-space: nowrap;
        }
        table.lines td.desc {
            word-wrap: break-word;
        }
        table.lines tr.cat td {
            background: #e8eef7;
            color: #1e3a5f;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 8px;
        }
    </style>
</head>
<body>
@php
    $count = is_countable($items) ? count($items) : $items->count();
    $levels = $priceLevels ?? collect();
    $multi = $levels->isNotEmpty();
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $company?->letterheadAddress() ?? config('company.address', '3802 TRADE CENTER DR');
    $companyCityLine = $company?->letterheadCityLine() ?? config('company.city_line', 'ANN ARBOR, MI 48108');
    $companyTel = $company?->letterheadTel() ?? config('company.tel', 'Tel:7346773510');
    $companyFax = $company?->letterheadFax() ?? config('company.fax', 'Fax:7346773567');
    $priceCols = $multi ? $levels->count() : 1;
    $grouped = collect($items)
        ->sortBy(fn ($item) => sprintf('%s|%s', $item->category?->name ?: 'ZZZ', $item->item_code))
        ->groupBy(fn ($item) => $item->category?->name ?: 'No Category');
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">{{ $companyAddress }}</div>
                <div class="brand-sub">{{ $companyCityLine }}</div>
                <div class="brand-sub">{{ $companyTel }}@if ($companyFax) &nbsp;·&nbsp; {{ $companyFax }}@endif</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">PRICE LIST</div>
                <div class="doc-meta">{{ $multi ? $levels->pluck('name')->implode(', ') : 'List Price' }}</div>
                <div class="doc-meta">{{ now()->format('F j, Y') }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="summary section">
    <tr>
        <td>
            <div class="lbl">Document</div>
            <div class="val" style="font-size:11px">{{ $title }}</div>
        </td>
        <td>
            <div class="lbl">Items</div>
            <div class="val">{{ number_format($count) }}</div>
        </td>
        <td>
            <div class="lbl">Prices</div>
            <div class="val" style="font-size:11px">{{ $multi ? $levels->pluck('name')->implode(', ') : 'List Price' }}</div>
        </td>
        <td>
            <div class="lbl">Date</div>
            <div class="val" style="font-size:11px">{{ now()->format('n/j/Y') }}</div>
        </td>
    </tr>
</table>

<table class="lines">
    <colgroup>
        <col style="width:90px">
        <col>
        @for ($c = 0; $c < $priceCols; $c++)
            <col style="width:82px">
        @endfor
    </colgroup>
    <thead>
        <tr>
            <th>Item Number</th>
            <th>Description</th>
            @if ($multi)
                @foreach ($levels as $level)
                    <th class="num">{{ $level->name }}</th>
                @endforeach
            @else
                <th class="num">List Price</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($grouped as $categoryName => $categoryItems)
            <tr class="cat">
                <td colspan="{{ 2 + $priceCols }}">{{ $categoryName }}</td>
            </tr>
            @foreach ($categoryItems as $item)
                <tr>
                    <td class="mono">{{ $item->item_code }}</td>
                    <td class="desc">{{ $item->description }}</td>
                    @if ($multi)
                        @foreach ($levels as $level)
                            <td class="num"><strong>${{ number_format((float) (($item->level_prices[$level->id] ?? null) ?? $item->list_price), 2) }}</strong></td>
                        @endforeach
                    @else
                        <td class="num"><strong>${{ number_format((float) ($item->display_price ?? $item->list_price), 2) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="{{ 2 + $priceCols }}" class="empty">No items match the selected filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="foot">
    {{ $companyName }} · {{ $title }} · {{ $count }} item(s) · {{ now()->format('M j, Y') }}
</div>
</body>
</html>
