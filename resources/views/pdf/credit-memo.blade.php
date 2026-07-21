<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit Memo {{ $memo->memo_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Credit Memo</h1>
    <p class="muted">{{ $company?->name ?? 'Continental Wholesale' }}</p>
    <table>
        <tr><th>Memo No.</th><td>{{ $memo->memo_number }}</td></tr>
        <tr><th>Date</th><td>{{ optional($memo->memo_date)?->format('n/j/Y') }}</td></tr>
        <tr><th>Customer</th><td>{{ $memo->customer?->company_name ?: $memo->customer?->contact }}</td></tr>
        <tr><th>Order No.</th><td>{{ $memo->salesOrder?->order_number ?: '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $memo->status }}</td></tr>
        <tr><th>Amount</th><td class="right">${{ number_format($memo->amount, 2) }}</td></tr>
        <tr><th>Comments</th><td>{{ $memo->comments }}</td></tr>
    </table>

    @if ($memo->relationLoaded('lines') ? $memo->lines->isNotEmpty() : $memo->lines()->exists())
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>UOM</th>
                    <th class="right">Qty</th>
                    <th class="right">Price</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($memo->lines as $line)
                    <tr>
                        <td>{{ $line->item_code }}</td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->uom }}</td>
                        <td class="right">{{ number_format((float) $line->qty, 2) }}</td>
                        <td class="right">${{ number_format((float) $line->price, 2) }}</td>
                        <td class="right">${{ number_format((float) $line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
