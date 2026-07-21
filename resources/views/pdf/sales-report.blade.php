<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .muted { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Sales Report — {{ ucfirst($mode) }}</h1>
    <p class="muted">
        {{ $company?->name ?? 'Continental Wholesale' }}<br>
        Period: {{ $dateFrom }} to {{ $dateTo }}
    </p>
    <table>
        <thead>
            @if ($mode === 'invoices')
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Order No</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th class="right">Total</th>
                </tr>
            @else
                <tr>
                    <th>Order No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th class="right">Total</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @if ($mode === 'invoices')
                @foreach ($rows as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ optional($inv->invoice_date)?->format('Y-m-d') }}</td>
                        <td>{{ $inv->salesOrder?->order_number }}</td>
                        <td>{{ $inv->customer?->company_name }}</td>
                        <td>{{ $inv->status }}</td>
                        <td class="right">${{ number_format((float) $inv->invoice_total, 2) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach ($rows as $ord)
                    <tr>
                        <td>{{ $ord->order_number }}</td>
                        <td>{{ optional($ord->order_date)?->format('Y-m-d') }}</td>
                        <td>{{ $ord->customer?->company_name }}</td>
                        <td>{{ $ord->status }}</td>
                        <td class="right">${{ number_format((float) $ord->total, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    <p style="margin-top:12px"><strong>Grand Total: ${{ number_format((float) $grandTotal, 2) }}</strong></p>
</body>
</html>
