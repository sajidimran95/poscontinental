<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 5px 6px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 260px; margin-left: auto; }
        .totals td { border: none; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>Invoice</h1>
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
                <th>#</th>
                <th>Item</th>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Ext</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($invoice->salesOrder?->lines ?? collect()) as $line)
                <tr>
                    <td>{{ $line->line_no }}</td>
                    <td>{{ $line->item_code }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                    <td class="right">${{ number_format((float) $line->price, 2) }}</td>
                    <td class="right">${{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No line detail on linked order.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">${{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td>Trade Discount</td><td class="right">${{ number_format($invoice->trade_discount, 2) }}</td></tr>
        <tr><td>Freight</td><td class="right">${{ number_format($invoice->freight, 2) }}</td></tr>
        <tr><td>Misc</td><td class="right">${{ number_format($invoice->miscellaneous, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">${{ number_format($invoice->tax, 2) }}</td></tr>
        <tr><td><strong>Invoice Total</strong></td><td class="right"><strong>${{ number_format($invoice->invoice_total, 2) }}</strong></td></tr>
        <tr><td>Payments</td><td class="right">${{ number_format($invoice->total_payments, 2) }}</td></tr>
        <tr><td>Credits</td><td class="right">${{ number_format($invoice->total_credits, 2) }}</td></tr>
        <tr><td><strong>Balance</strong></td><td class="right"><strong>${{ number_format($invoice->invoice_balance, 2) }}</strong></td></tr>
    </table>
</body>
</html>
