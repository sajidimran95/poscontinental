<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RTV {{ $rtv->rtv_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $supplier = $rtv->supplier;
    $isReturned = $rtv->status === 'Returned';
    $lines = $rtv->relationLoaded('lines') ? $rtv->lines : $rtv->lines()->get();
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Return Memo</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">RETURN TO VENDOR</div>
                <div class="doc-meta">
                    #{{ $rtv->rtv_number }}
                    &nbsp;·&nbsp;
                    {{ optional($rtv->rtv_date)?->format('M j, Y') }}
                </div>
                <div class="status {{ $isReturned ? 'status-paid' : 'status-open' }}">{{ $rtv->status }}</div>
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
            <div class="card-title">Return From</div>
            <strong>{{ $rtv->site?->name ?: $companyName }}</strong>
            @if ($rtv->site?->code)
                <div class="line muted mono">Site: {{ $rtv->site->code }}</div>
            @endif
            <div class="line"><span class="muted">Requested By:</span> {{ $rtv->requestedBy?->name ?: '—' }}</div>
            @if ($rtv->processed_at)
                <div class="line"><span class="muted">Processed:</span> {{ $rtv->processed_at->format('n/j/Y g:i A') }}</div>
            @endif
        </td>
        <td class="card" style="width:33%">
            <div class="card-title">RTV Details</div>
            <div class="line"><span class="muted">RTV No:</span> <strong class="mono">{{ $rtv->rtv_number }}</strong></div>
            <div class="line"><span class="muted">RTV Date:</span> {{ optional($rtv->rtv_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Status:</span> {{ $rtv->status }}</div>
            @if (filled($rtv->reference_no))
                <div class="line"><span class="muted">Reference:</span> {{ $rtv->reference_no }}</div>
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
            <th class="right" style="width:60px">Qty</th>
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
                <td class="right">{{ number_format((float) $line->qty, 2) }}</td>
                <td class="right">${{ number_format((float) $line->unit_cost, 2) }}</td>
                <td class="right"><strong>${{ number_format((float) ($line->extended_cost ?: ((float) $line->qty * (float) $line->unit_cost)), 2) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="empty">No line items on this return.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            @if (filled($rtv->comments))
                <div>{{ $rtv->comments }}</div>
            @else
                <div>Please credit the returned merchandise listed above.</div>
            @endif
            <div style="margin-top:8px" class="muted">
                Return qty: {{ number_format((float) $lines->sum('qty'), 2) }}
            </div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="right">${{ number_format((float) $rtv->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Discount</td>
                    <td class="right">${{ number_format((float) $rtv->discount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Freight</td>
                    <td class="right">${{ number_format((float) $rtv->freight, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td>RTV Total</td>
                    <td class="right">${{ number_format((float) $rtv->total, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Return to Vendor {{ $rtv->rtv_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
