@if ($colKey === 'invoice_number')
    <td class="desk-num">
        <a href="{{ route('sales.invoices.pdf', $inv) }}" target="_blank" rel="noopener" wire:click.stop>{{ $inv->invoice_number }}</a>
    </td>
@elseif ($colKey === 'invoice_date')
    <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'order_number')
    <td class="desk-num">{{ $inv->salesOrder?->order_number }}</td>
@elseif ($colKey === 'customer_code')
    <td class="desk-num">{{ $inv->customer?->customer_id }}</td>
@elseif ($colKey === 'bill_to')
    <td title="{{ $inv->customer?->company_name ?: $inv->salesOrder?->bill_to_name }}">{{ $inv->customer?->company_name ?: $inv->salesOrder?->bill_to_name }}</td>
@elseif ($colKey === 'subtotal')
    <td class="desk-money">${{ number_format($inv->subtotal, 2) }}</td>
@elseif ($colKey === 'total_discount')
    <td class="desk-money">${{ number_format($inv->total_discount, 2) }}</td>
@elseif ($colKey === 'trade_discount')
    <td class="desk-money">${{ number_format($inv->trade_discount, 2) }}</td>
@elseif ($colKey === 'freight')
    <td class="desk-money">${{ number_format($inv->freight, 2) }}</td>
@elseif ($colKey === 'miscellaneous')
    <td class="desk-money">${{ number_format($inv->miscellaneous, 2) }}</td>
@elseif ($colKey === 'invoice_total')
    <td class="desk-money">${{ number_format($inv->invoice_total, 2) }}</td>
@elseif ($colKey === 'payments')
    <td class="desk-money">${{ number_format($inv->total_payments, 2) }}</td>
@elseif ($colKey === 'credits')
    <td class="desk-money">${{ number_format($inv->total_credits, 2) }}</td>
@elseif ($colKey === 'balance')
    <td class="desk-money">${{ number_format($inv->invoice_balance, 2) }}</td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => $inv->status === 'NOT PAID',
            'desk-pill-invoiced' => $inv->status === 'PAID',
            'desk-pill-muted' => ! in_array($inv->status, ['NOT PAID', 'PAID'], true),
        ])>{{ $inv->status }}</span>
        @if ($inv->salesOrder?->delivery_status === 'delivered')
            <div style="margin-top:0.2rem"><span class="dlv-pill is-delivered">Delivered</span></div>
        @elseif (in_array($inv->salesOrder?->delivery_status, ['failed', 'en_route', 'arrived', 'assigned'], true))
            <div class="dlv-muted" style="margin-top:0.2rem">{{ ucfirst(str_replace('_', ' ', (string) $inv->salesOrder->delivery_status)) }}</div>
        @endif
    </td>
@endif
