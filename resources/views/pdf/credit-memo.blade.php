<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Memo {{ $memo->memo_number }}</title>
    @include('pdf._styles')
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $lines = $memo->relationLoaded('lines') ? $memo->lines : $memo->lines()->get();
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Customer Credit</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">CREDIT MEMO</div>
                <div class="doc-meta">
                    #{{ $memo->memo_number }}
                    &nbsp;·&nbsp;
                    {{ optional($memo->memo_date)?->format('M j, Y') }}
                </div>
                <div class="status status-credit">{{ $memo->status }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="cards section">
    <tr>
        <td class="card" style="width:50%">
            <div class="card-title">Customer</div>
            <strong>{{ $memo->customer?->company_name ?: ($memo->customer?->contact ?: '—') }}</strong>
            @if ($memo->customer?->customer_id)
                <div class="line muted mono">ID: {{ $memo->customer->customer_id }}</div>
            @endif
            @if ($memo->customer?->address)
                <div class="line">{{ $memo->customer->address }}</div>
            @endif
            @php
                $cityLine = collect([
                    $memo->customer?->city,
                    $memo->customer?->state,
                    $memo->customer?->zip_code,
                ])->filter()->implode(', ');
            @endphp
            @if ($cityLine !== '')
                <div class="line">{{ $cityLine }}</div>
            @endif
        </td>
        <td class="card" style="width:50%">
            <div class="card-title">Memo Details</div>
            <div class="line"><span class="muted">Memo No:</span> <strong class="mono">{{ $memo->memo_number }}</strong></div>
            <div class="line"><span class="muted">Date:</span> {{ optional($memo->memo_date)?->format('n/j/Y') ?: '—' }}</div>
            <div class="line"><span class="muted">Order No:</span> {{ $memo->salesOrder?->order_number ?: '—' }}</div>
            <div class="line"><span class="muted">Status:</span> {{ $memo->status }}</div>
            @if (filled($memo->comments))
                <div class="line" style="margin-top:6px"><span class="muted">Comments:</span> {{ $memo->comments }}</div>
            @endif
        </td>
    </tr>
</table>

@if ($lines->isNotEmpty())
    <table class="lines">
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th style="width:95px">Item</th>
                <th>Description</th>
                <th style="width:50px">UOM</th>
                <th class="right" style="width:70px">Qty</th>
                <th class="right" style="width:75px">Price</th>
                <th class="right" style="width:85px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $line)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="mono">{{ $line->item_code }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->uom ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->qty, 2) }}</td>
                    <td class="right">${{ number_format((float) $line->price, 2) }}</td>
                    <td class="right"><strong>${{ number_format((float) $line->line_total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="footer-grid">
    <tr>
        <td class="notes">
            <div class="card-title">Notes</div>
            <div>This credit may be applied toward open invoices on the customer account.</div>
            <div style="margin-top:6px">Thank you for your business.</div>
        </td>
        <td class="totals-wrap">
            <table class="totals">
                @if ($lines->isNotEmpty())
                    <tr>
                        <td class="label">Line Items</td>
                        <td class="right">{{ $lines->count() }}</td>
                    </tr>
                @endif
                <tr class="credit-grand">
                    <td>Credit Amount</td>
                    <td class="right">${{ number_format((float) $memo->amount, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    {{ $companyName }} · Credit Memo {{ $memo->memo_number }} · Generated {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
