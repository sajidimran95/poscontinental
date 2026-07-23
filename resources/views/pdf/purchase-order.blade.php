<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $order->po_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $supplier = $order->supplier;
    $isReceived = $order->status === 'Received';
    $lines = $order->relationLoaded('lines') ? $order->lines : $order->lines()->get();
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Purchase Memo</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">PURCHASE ORDER</div>
                <div class="doc-meta">
                    #{{ $order->po_number }}
                    &nbsp;·&nbsp;
                    {{ optional($order->requisition_date)?->format('M j, Y') }}
                </div>
                <div class="status {{ $isReceived ? 'status-paid' : 'status-open' }}">{{ $order->status }}</div>
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
            @if ($supplier?->email)
                <div class="line">{{ $supplier->email }}</div>
            @endif
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">Ship To</div>
            <strong>{{ $order->shipToSite?->name ?: ($companyName) }}</strong>
            @if ($order->shipToSite?->code)
                <div class="line muted mono">Site: {{ $order->shipToSite->code }}</div>
            @endif
            @if (filled($order->ship_from))
                <div class="line"><span class="muted">Ship From:</span> {{ $order->ship_from }}</div>
            @endif
            <div class="line"><span class="muted">Ship Via:</span> {{ $order->shipVia?->name ?: '—' }}</div>
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">PO Details</div>
            <div class="line"><span class="muted">PO No:</span> <strong class="mono">{{ $order->po_number }}</strong></div>
            <div class="line"><span class="muted">Type:</span> {{ $order->order_type ?: '—' }}</div>
            <div class="line"><span class="muted">Req. Date:</span> {{ optional($order->requisition_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Required:</span> {{ optional($order->required_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Buyer:</span> {{ $order->buyer?->name ?: '—' }}</div>
            <div class="line"><span class="muted">Terms:</span> {{ $order->paymentTerm?->name ?: '—' }}</div>
            @if (filled($order->reference_no))
                <div class="line"><span class="muted">Reference:</span> {{ $order->reference_no }}</div>
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
                <td class="right"><strong>${{ number_format((float) ($line->extended_cost ?: ((float) $line->qty_ordered * (float) $line->unit_cost)), 2) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="empty">No line items on this purchase order.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            @if (filled($order->comments))
                <div>{{ $order->comments }}</div>
            @else
                <div>Please confirm pricing, availability, and ship date.</div>
            @endif
            <div style="margin-top:8px" class="muted">
                Ordered qty: {{ number_format((float) $order->total_items_ordered, 2) }}
                &nbsp;·&nbsp;
                Received qty: {{ number_format((float) $order->total_items_received, 2) }}
            </div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="right">${{ number_format((float) $order->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Trade Discount</td>
                    <td class="right">${{ number_format((float) $order->trade_discount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Freight</td>
                    <td class="right">${{ number_format((float) $order->freight, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Miscellaneous</td>
                    <td class="right">${{ number_format((float) $order->miscellaneous, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Tax</td>
                    <td class="right">${{ number_format((float) $order->tax, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td>PO Total</td>
                    <td class="right">${{ number_format((float) $order->total, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Purchase Order {{ $order->po_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
