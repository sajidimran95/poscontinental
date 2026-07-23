<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles')
    <style>
        .cust-card {
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            margin: 0 0 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .cust-card-head {
            background: #1e3a5f;
            color: #fff;
            padding: 9px 12px;
        }
        .cust-card-head table { width: 100%; border-collapse: collapse; }
        .cust-card-head td { border: none; padding: 0; color: #fff; vertical-align: middle; }
        .cust-id { font-family: DejaVu Sans Mono, DejaVu Sans, sans-serif; font-size: 11px; font-weight: bold; }
        .cust-name { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .cust-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: #bbf7d0;
            color: #14532d;
        }
        .cust-badge-off { background: #fecaca; color: #7f1d1d; }
        .cust-body { padding: 10px 12px; background: #fff; }
        .cust-grid { width: 100%; border-collapse: collapse; }
        .cust-grid td {
            width: 33.33%;
            vertical-align: top;
            border: none;
            padding: 0 10px 0 0;
        }
        .cust-grid td:last-child { padding-right: 0; }
        .cust-sec-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }
        .cust-line { margin: 2px 0; font-size: 10px; color: #1e293b; }
        .cust-line .k { color: #64748b; }
        .cust-metrics {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 8px;
        }
        .cust-metrics td {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
            padding: 7px 6px;
            width: 20%;
        }
        .cust-metrics .m-lbl {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 2px;
        }
        .cust-metrics .m-val {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        .cust-alert {
            margin-top: 8px;
            padding: 7px 9px;
            background: #fef3c7;
            border-left: 4px solid #d97706;
            color: #78350f;
            font-size: 9.5px;
        }
    </style>
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $count = is_countable($customers) ? count($customers) : $customers->count();
@endphp

<div class="brand-bar">
    <table>
        <tr>
            <td>
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-sub">Sales · Customer Directory</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">CUSTOMERS</div>
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
            <div class="lbl">Customers</div>
            <div class="val">{{ number_format($count) }}</div>
        </td>
        <td>
            <div class="lbl">Printed</div>
            <div class="val" style="font-size:11px">{{ now()->format('n/j/Y') }}</div>
        </td>
        <td>
            <div class="lbl">Detail</div>
            <div class="val" style="font-size:11px">Full profile</div>
        </td>
    </tr>
</table>

@forelse ($customers as $customer)
    @php
        $address = collect([
            $customer->address,
            trim(collect([$customer->city, $customer->state, $customer->zip_code])->filter()->implode(', ')),
            $customer->country,
        ])->filter()->implode("\n");
    @endphp
    <div class="cust-card">
        <div class="cust-card-head">
            <table>
                <tr>
                    <td>
                        <div class="cust-id">{{ $customer->customer_id }}</div>
                        <div class="cust-name">{{ $customer->company_name ?: $customer->contact }}</div>
                    </td>
                    <td style="text-align:right">
                        <span class="cust-badge {{ $customer->is_inactive ? 'cust-badge-off' : '' }}">
                            {{ $customer->is_inactive ? 'Inactive' : 'Active' }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="cust-body">
            <table class="cust-grid">
                <tr>
                    <td>
                        <div class="cust-sec-title">Contact</div>
                        <div class="cust-line"><span class="k">Contact:</span> {{ $customer->contact ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Phone:</span> {{ $customer->telephone ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Mobile:</span> {{ $customer->mobile ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Email:</span> {{ $customer->email ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Fax:</span> {{ $customer->fax ?: '—' }}</div>
                    </td>
                    <td>
                        <div class="cust-sec-title">Address</div>
                        @foreach (preg_split("/\n+/", $address ?: '—') as $line)
                            <div class="cust-line">{{ $line }}</div>
                        @endforeach
                    </td>
                    <td>
                        <div class="cust-sec-title">Account</div>
                        <div class="cust-line"><span class="k">Type:</span> {{ $customer->account_type ?: '—' }}</div>
                        <div class="cust-line"><span class="k">FEIN:</span> {{ $customer->fein_no ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Price Level:</span> {{ $customer->priceLevel?->name ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Terms:</span> {{ $customer->paymentTerm?->name ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Sales Rep:</span> {{ $customer->salesRep?->name ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Route:</span> {{ $customer->deliveryRoute?->name ?: '—' }}</div>
                    </td>
                </tr>
            </table>

            <table class="cust-metrics">
                <tr>
                    <td>
                        <div class="m-lbl">Credit Limit</div>
                        <div class="m-val">${{ number_format((float) $customer->credit_limit, 2) }}</div>
                    </td>
                    <td>
                        <div class="m-lbl">Balance</div>
                        <div class="m-val">${{ number_format((float) $customer->balance, 2) }}</div>
                    </td>
                    <td>
                        <div class="m-lbl">Available</div>
                        <div class="m-val">${{ number_format((float) $customer->available_credit, 2) }}</div>
                    </td>
                    <td>
                        <div class="m-lbl">Orders</div>
                        <div class="m-val">{{ number_format((int) $customer->number_of_orders) }}</div>
                    </td>
                    <td>
                        <div class="m-lbl">Total Sales</div>
                        <div class="m-val">${{ number_format((float) $customer->total_sales, 2) }}</div>
                    </td>
                </tr>
            </table>

            <table class="cust-grid" style="margin-top:8px">
                <tr>
                    <td>
                        <div class="cust-sec-title">Dates</div>
                        <div class="cust-line"><span class="k">Customer Since:</span> {{ optional($customer->customer_since)?->format('M j, Y') ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Last Order:</span> {{ optional($customer->last_order_on)?->format('M j, Y') ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Order Day:</span> {{ $customer->order_day ?: '—' }}</div>
                    </td>
                    <td>
                        <div class="cust-sec-title">Tax</div>
                        <div class="cust-line"><span class="k">Exempt:</span> {{ $customer->is_tax_exempt ? 'Yes' : 'No' }}</div>
                        <div class="cust-line"><span class="k">Certificate:</span> {{ $customer->tax_certificate_no ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Expires:</span> {{ optional($customer->tax_certificate_exp)?->format('M j, Y') ?: '—' }}</div>
                    </td>
                    <td>
                        <div class="cust-sec-title">Category</div>
                        <div class="cust-line"><span class="k">Category:</span> {{ $customer->customer_category ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Lead Source:</span> {{ $customer->lead_source ?: '—' }}</div>
                        <div class="cust-line"><span class="k">Location #:</span> {{ $customer->location_no ?: '—' }}</div>
                    </td>
                </tr>
            </table>

            @if (filled($customer->messages_alerts))
                <div class="cust-alert"><strong>Alert:</strong> {{ $customer->messages_alerts }}</div>
            @endif
            @if (filled($customer->comments))
                <div class="cust-line" style="margin-top:6px"><span class="k">Comments:</span> {{ $customer->comments }}</div>
            @endif
        </div>
    </div>
@empty
    <div class="empty">No customers match the current filters.</div>
@endforelse

<div class="foot">
    {{ $companyName }} · {{ $title }} · {{ $count }} customer(s) · Printed {{ now()->format('M j, Y g:i A') }}
</div>
</body>
</html>
