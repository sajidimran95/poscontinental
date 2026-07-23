<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $count = is_countable($items) ? count($items) : $items->count();
    $levels = $priceLevels ?? collect();
    $multi = $levels->isNotEmpty();
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Product Pricing</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">PRICE LIST</div>
                <div class="doc-meta">{{ now()->format('M j, Y · g:i A') }}</div>
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
            <div class="lbl">Price Levels</div>
            <div class="val" style="font-size:11px">
                @if ($multi)
                    {{ $levels->pluck('name')->implode(', ') }}
                @else
                    List Price
                @endif
            </div>
        </td>
        <td>
            <div class="lbl">Generated</div>
            <div class="val" style="font-size:11px">{{ now()->format('n/j/Y') }}</div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:28px">#</th>
            <th style="width:95px">Item</th>
            <th>Description</th>
            <th style="width:100px">UPC</th>
            <th style="width:45px">UOM</th>
            @if ($multi)
                @foreach ($levels as $level)
                    <th class="right" style="width:72px">{{ $level->name }}</th>
                @endforeach
            @else
                <th class="right" style="width:75px">Price</th>
            @endif
            <th class="right" style="width:70px">MSRP</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $i => $item)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td class="mono">{{ $item->item_code }}</td>
                <td>{{ $item->description }}</td>
                <td class="mono muted">{{ $item->primary_upc ?: '—' }}</td>
                <td>{{ $item->unit_of_measure ?: '—' }}</td>
                @if ($multi)
                    @foreach ($levels as $level)
                        <td class="right"><strong>${{ number_format((float) (($item->level_prices[$level->id] ?? null) ?? $item->list_price), 2) }}</strong></td>
                    @endforeach
                @else
                    <td class="right"><strong>${{ number_format((float) ($item->display_price ?? $item->list_price), 2) }}</strong></td>
                @endif
                <td class="right">${{ number_format((float) $item->msrp, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $multi ? (6 + $levels->count()) : 7 }}" class="empty">No items match the selected filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="foot">
    {{ $companyName }} · {{ $title }} · {{ $count }} item(s) · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
