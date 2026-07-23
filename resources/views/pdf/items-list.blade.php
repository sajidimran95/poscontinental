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
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Inventory · Item Master</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">ITEMS LIST</div>
                <div class="doc-meta">{{ now()->format('M j, Y · g:i A') }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="summary section">
    <tr>
        <td>
            <div class="lbl">Report</div>
            <div class="val" style="font-size:11px">{{ $title }}</div>
        </td>
        <td>
            <div class="lbl">Items</div>
            <div class="val">{{ number_format($count) }}</div>
        </td>
        <td>
            <div class="lbl">Printed</div>
            <div class="val" style="font-size:11px">{{ now()->format('n/j/Y') }}</div>
        </td>
        <td>
            <div class="lbl">Format</div>
            <div class="val" style="font-size:11px">Landscape</div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:26px">#</th>
            <th style="width:100px">Item Code</th>
            <th>Description</th>
            <th style="width:95px">Department</th>
            <th style="width:45px">UOM</th>
            <th class="right" style="width:70px">List Price</th>
            <th class="right" style="width:70px">Std Cost</th>
            <th class="right" style="width:60px">In Stock</th>
            <th class="right" style="width:60px">Available</th>
            <th style="width:50px">Sell</th>
            <th style="width:55px">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $i => $item)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td class="mono">{{ $item->item_code }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->department?->name ?: '—' }}</td>
                <td>{{ $item->unit_of_measure ?: '—' }}</td>
                <td class="right">${{ number_format((float) $item->list_price, 2) }}</td>
                <td class="right">${{ number_format((float) $item->standard_cost, 2) }}</td>
                <td class="right">{{ number_format((float) $item->quantity_in_stock, 0) }}</td>
                <td class="right">{{ number_format((float) $item->available_quantity, 0) }}</td>
                <td>{{ $item->can_sell ? 'Yes' : 'No' }}</td>
                <td>{{ $item->is_inactive ? 'Inactive' : 'Active' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="empty">No items match the current filters.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="foot">
    {{ $companyName }} · {{ $title }} · {{ $count }} item(s) · Printed {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
