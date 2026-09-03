@if ($colKey === 'po_number')
    <td class="desk-num">
        <a href="{{ route('purchasing.orders.edit', $order) }}" wire:navigate wire:click.stop>{{ $order->po_number }}</a>
    </td>
@elseif ($colKey === 'requisition_date')
    <td>{{ optional($order->requisition_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => in_array($order->status, ['New', 'Partially Received'], true),
            'desk-pill-invoiced' => $order->status === 'Received',
            'desk-pill-muted' => ! in_array($order->status, ['New', 'Partially Received', 'Received'], true),
        ])>{{ $order->status }}</span>
    </td>
@elseif ($colKey === 'required_date')
    <td>{{ optional($order->required_date)?->format('n/j/Y') ?: '—' }}</td>
@elseif ($colKey === 'reference_no')
    <td>{{ $order->reference_no ?: '' }}</td>
@elseif ($colKey === 'supplier_code')
    <td class="desk-num">{{ $order->supplier?->supplier_id ?: '—' }}</td>
@elseif ($colKey === 'supplier_name')
    <td>{{ $order->supplier?->name ?: '—' }}</td>
@elseif ($colKey === 'buyer')
    <td>{{ $order->buyer?->name ?: '—' }}</td>
@elseif ($colKey === 'subtotal')
    <td class="desk-money">${{ number_format($order->subtotal, 2) }}</td>
@elseif ($colKey === 'trade_discount')
    <td class="desk-money">${{ number_format($order->trade_discount, 2) }}</td>
@elseif ($colKey === 'freight')
    <td class="desk-money">${{ number_format($order->freight, 2) }}</td>
@elseif ($colKey === 'total')
    <td class="desk-money">${{ number_format($order->total, 2) }}</td>
@endif
