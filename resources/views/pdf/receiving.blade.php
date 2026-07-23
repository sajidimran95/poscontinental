<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receiving {{ $receiving->receipt_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $supplier = $receiving->supplier;
    $po = $receiving->purchaseOrder;
    $isProcessed = $receiving->status === 'Processed';
    $lines = $receiving->relationLoaded('lines') ? $receiving->lines : $receiving->lines()->get();
    $lineTotal = $lines->sum(fn ($l) => (float) $l->qty_received * (float) $l->unit_cost);
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Receiving Memo</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">GOODS RECEIPT</div>
                <div class="doc-meta">
                    #{{ $receiving->receipt_number }}
                    &nbsp;·&nbsp;
                    {{ optional($receiving->receipt_date)?->format('M j, Y') }}
                </div>
                <div class="status {{ $isProcessed ? 'status-paid' : 'status-open' }}">{{ $receiving->status }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card" style="width:34%">
            <div class="card-title">Vendor / Supplier</div>
            <strong>{{ $supplier?->name ?: '—' }}</strong>
            @if ($supplier?->supplier_id)
                <div class="line muted mono">ID: {{ $supplier->supplier_id }}</div>
            @endif
            @if ($supplier?->contact_name)
                <div class="line">{{ $supplier->contact_name }}</div>
            @endif
            <div class="line">{{ $supplier?->address }}</div>
            @php
                $supplierCity = collect([
                    $supplier?->city,
                    $supplier?->state,
                    $supplier?->zip_code,
                ])->filter()->implode(', ');
            @endphp
            @if ($supplierCity !== '')
                <div class="line">{{ $supplierCity }}</div>
            @endif
            @if ($supplier?->phone1)
                <div class="line">{{ $supplier->phone1 }}</div>
            @endif
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">Received At</div>
            <strong>{{ $receiving->site?->name ?: $companyName }}</strong>
            @if ($receiving->site?->code)
                <div class="line muted mono">Site: {{ $receiving->site->code }}</div>
            @endif
            <div class="line"><span class="muted">Received By:</span> {{ $receiving->received_by ?: '—' }}</div>
            <div class="line"><span class="muted">Carrier:</span> {{ $receiving->shipping_carrier ?: '—' }}</div>
            @if ($receiving->processed_at)
                <div class="line"><span class="muted">Processed:</span> {{ $receiving->processed_at->format('n/j/Y g:i A') }}</div>
            @endif
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">Receipt Details</div>
            <div class="line"><span class="muted">Receipt No:</span> <strong class="mono">{{ $receiving->receipt_number }}</strong></div>
            <div class="line"><span class="muted">PO No:</span> <strong class="mono">{{ $po?->po_number ?: '—' }}</strong></div>
            <div class="line"><span class="muted">Receipt Date:</span> {{ optional($receiving->receipt_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Buyer:</span> {{ $receiving->buyer?->name ?: '—' }}</div>
            @if (filled($receiving->reference_no))
                <div class="line"><span class="muted">Reference:</span> {{ $receiving->reference_no }}</div>
            @endif
            @if ($po?->requisition_date)
                <div class="line"><span class="muted">PO Req. Date:</span> {{ $po->requisition_date->format('n/j/Y') }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:28px">#</th>
            <th style="width:90px">Item</th>
            <th>Description</th>
            <th style="width:50px">UOM</th>
            <th class="right" style="width:60px">Qty Ord</th>
            <th class="right" style="width:60px">Qty Rec</th>
            <th class="right" style="width:70px">Cost</th>
            <th class="right" style="width:80px">Extended</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $line)
            <tr>
                <td class="muted">{{ $line->line_no ?: $loop->iteration }}</td>
                <td class="mono">{{ $line->item_code }}</td>
                <td>{{ $line->description }}</td>
                <td>{{ $line->uom ?: '—' }}</td>
                <td class="right">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                <td class="right">{{ number_format((float) $line->qty_received, 2) }}</td>
                <td class="right">${{ number_format((float) $line->unit_cost, 2) }}</td>
                <td class="right"><strong>${{ number_format((float) $line->qty_received * (float) $line->unit_cost, 2) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="empty">No line items on this receiving.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            @if (filled($receiving->comments))
                <div>{{ $receiving->comments }}</div>
            @else
                <div>Goods received against purchase order {{ $po?->po_number ?: '—' }}.</div>
            @endif
            <div style="margin-top:8px" class="muted">
                Ordered qty: {{ number_format((float) $lines->sum('qty_ordered'), 2) }}
                &nbsp;·&nbsp;
                Received qty: {{ number_format((float) $lines->sum('qty_received'), 2) }}
            </div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr class="grand">
                    <td>Receipt Total</td>
                    <td class="right">${{ number_format((float) $lineTotal, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Receiving {{ $receiving->receipt_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
