<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pick List {{ $order->order_number }}</title>
    @include('pdf._styles')
    <style>
        .pick-note {
            margin-top: 3px;
            padding: 4px 6px;
            background: #f1f5f9;
            border-left: 3px solid #64748b;
            font-size: 9.5px;
            color: #1e293b;
        }
        .pick-note-lbl {
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.04em;
            margin-right: 4px;
        }
        .chk {
            width: 14px;
            height: 14px;
            border: 1px solid #64748b;
            display: inline-block;
        }
    </style>
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Warehouse Pick List</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">PICK LIST</div>
                <div class="doc-meta">
                    SO #{{ $order->order_number }}
                    &nbsp;·&nbsp;
                    {{ optional($order->order_date)?->format('M j, Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card" style="width:50%">
            <div class="card-title">Ship To</div>
            <strong>{{ $order->ship_to_name ?: ($order->bill_to_name ?: '—') }}</strong>
            <div class="line">{{ $order->ship_to_address ?: $order->bill_to_address }}</div>
            @php
                $shipCity = collect([
                    $order->ship_to_city ?: $order->bill_to_city,
                    $order->ship_to_state ?: $order->bill_to_state,
                    $order->ship_to_zip ?: $order->bill_to_zip,
                ])->filter()->implode(', ');
            @endphp
            @if ($shipCity !== '')
                <div class="line">{{ $shipCity }}</div>
            @endif
            @if ($order->ship_to_phone)
                <div class="line">{{ $order->ship_to_phone }}</div>
            @endif
        </td>
        <td class="card" style="width:50%">
            <div class="card-title">Order Details</div>
            <div class="line"><span class="muted">Customer:</span> <strong>{{ $order->customer?->company_name ?: $order->bill_to_name ?: '—' }}</strong></div>
            <div class="line"><span class="muted">Customer PO:</span> {{ $order->customer_po_no ?: '—' }}</div>
            <div class="line"><span class="muted">Required:</span> {{ optional($order->required_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Sales Rep:</span> {{ $order->salesRep?->name ?: '—' }}</div>
            <div class="line"><span class="muted">Priority:</span> {{ $order->priority ?: '—' }}</div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:28px">✓</th>
            <th style="width:28px">#</th>
            <th style="width:95px">Item</th>
            <th>Description / Instructions</th>
            <th class="right" style="width:55px">UOM</th>
            <th class="right" style="width:70px">Qty</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($order->lines as $line)
            <tr>
                <td><span class="chk"></span></td>
                <td class="muted">{{ $line->line_no }}</td>
                <td class="mono">{{ $line->item_code }}</td>
                <td>
                    {{ $line->description }}
                    @if (filled($line->instructions))
                        <div class="pick-note">
                            <span class="pick-note-lbl">Instructions</span>
                            {{ $line->instructions }}
                        </div>
                    @endif
                </td>
                <td class="right">{{ $line->uom ?: '—' }}</td>
                <td class="right"><strong>{{ number_format((float) $line->qty_ordered, 2) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="empty">No line items on this sales order.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if (filled($order->comments))
    <div class="section" style="margin-top:14px">
        <div class="card-title">Order Comments</div>
        <div class="pick-note">{{ $order->comments }}</div>
    </div>
@endif

<div class="muted" style="margin-top:18px;font-size:9px">
    Line messages are internal and are not shown on this pick list.
</div>
</body>
</html>
