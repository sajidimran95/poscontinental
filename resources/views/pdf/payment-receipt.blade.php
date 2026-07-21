<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <h1>Payment Receipt</h1>
    <p class="muted">{{ $company?->name ?? 'Continental Wholesale' }}</p>
    <p>
        Invoice #: <strong>{{ $invoice->invoice_number }}</strong><br>
        Invoice Date: {{ optional($invoice->invoice_date)?->format('n/j/Y') }}<br>
        Order #: {{ $invoice->salesOrder?->order_number }}<br>
        Customer: {{ $invoice->customer?->company_name ?: $invoice->customer?->contact }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Payment Date</th>
                <th>Method</th>
                <th class="right">Amount</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ optional($payment->payment_date)?->format('n/j/Y') }}</td>
                <td>{{ $payment->payment_method }}</td>
                <td class="right">${{ number_format($payment->amount, 2) }}</td>
                <td>{{ $payment->comments }}</td>
            </tr>
        </tbody>
    </table>
    <p style="margin-top:16px">
        Invoice Total: ${{ number_format($invoice->invoice_total, 2) }}<br>
        Total Payments: ${{ number_format($invoice->total_payments, 2) }}<br>
        Total Credits: ${{ number_format($invoice->total_credits, 2) }}<br>
        <strong>Balance Due: ${{ number_format($invoice->invoice_balance, 2) }}</strong>
    </p>
</body>
</html>
