@php
    $orderId = (int) $order->getKey();
    $oc = ($order->relationLoaded('customer') && $order->getRelation('customer') instanceof \App\Models\Customer)
        ? $order->getRelation('customer')
        : null;
    $rowInvoice = ($order->relationLoaded('invoice') && $order->getRelation('invoice') instanceof \App\Models\Invoice)
        ? $order->getRelation('invoice')
        : null;
@endphp
@if ($colKey === 'order_number')
    <td class="desk-num" data-excel-value="{{ $order->order_number }}">
        <a href="{{ route($order->canBeEditedBy(auth()->user()) ? 'sales.orders.edit' : 'sales.orders.show', $orderId) }}" wire:navigate wire:click.stop>{{ $order->order_number }}</a>
    </td>
@elseif ($colKey === 'invoice_number')
    <td class="desk-num">
        @if ($rowInvoice)
            <a href="{{ route('sales.invoices.pdf', $rowInvoice->getKey()) }}" target="_blank" rel="noopener" wire:click.stop title="Open invoice PDF">{{ $rowInvoice->invoice_number }}</a>
        @else
            —
        @endif
    </td>
@elseif ($colKey === 'order_type')
    <td>{{ $order->order_type }}</td>
@elseif ($colKey === 'order_source')
    <td>
        @php $src = (string) ($order->order_source ?? 'pos'); @endphp
        <span @class(['desk-pill', 'desk-pill-muted' => $src === 'pos', 'desk-pill-new' => $src === 'sales', 'desk-pill-invoiced' => $src === 'customer'])>{{ $order->sourceLabel() }}</span>
    </td>
@elseif ($colKey === 'order_date')
    <td>{{ optional($order->order_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'ship_date')
    <td>{{ optional($order->ship_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'status')
    <td>
        <span @class(['desk-pill', 'desk-pill-new' => $order->status === 'New', 'desk-pill-invoiced' => $order->status === 'Invoiced', 'desk-pill-muted' => ! in_array($order->status, ['New', 'Invoiced'], true)])>{{ $order->status }}</span>
    </td>
@elseif ($colKey === 'customer_code')
    <td class="desk-num">{{ $oc?->customer_id }}</td>
@elseif ($colKey === 'customer_contact')
    <td title="{{ $oc?->contact }}">{{ $oc?->contact }}</td>
@elseif ($colKey === 'customer_company')
    <td title="{{ $oc?->company_name }}">{{ $oc?->company_name }}</td>
@elseif ($colKey === 'customer_address')
    <td title="{{ $oc?->address }}">{{ $oc?->address }}</td>
@elseif ($colKey === 'customer_phone')
    <td title="{{ $oc?->telephone }}">{{ $oc?->telephone }}</td>
@elseif ($colKey === 'total')
    <td class="desk-money">${{ number_format($order->total, 2) }}</td>
@elseif ($colKey === 'invoice_action')
    <td wire:click.stop>
        @if ($order->status !== 'Invoiced')
            <button type="button" wire:click="invoiceOrder({{ $orderId }})" class="desk-btn desk-btn-sm">Invoice</button>
        @endif
    </td>
@endif
