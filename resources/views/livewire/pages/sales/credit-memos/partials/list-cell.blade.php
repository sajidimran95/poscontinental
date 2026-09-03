@php
    $applied = (float) ($m->applied_sum ?? 0);
    $remaining = max(0, (float) $m->amount - $applied);
@endphp
@if ($colKey === 'memo_number')
    <td class="desk-num">
        <a href="{{ route('sales.credit-memos.pdf', $m) }}" target="_blank" rel="noopener" wire:click.stop>{{ $m->memo_number }}</a>
    </td>
@elseif ($colKey === 'memo_date')
    <td>{{ optional($m->memo_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'customer_code')
    <td class="desk-num">{{ $m->customer?->customer_id }}</td>
@elseif ($colKey === 'customer_name')
    <td>{{ $m->customer?->company_name }}</td>
@elseif ($colKey === 'order_number')
    <td class="desk-num">{{ $m->salesOrder?->order_number ?: '—' }}</td>
@elseif ($colKey === 'invoice_number')
    <td class="desk-num">{{ $m->salesOrder?->invoice?->invoice_number ?: '—' }}</td>
@elseif ($colKey === 'reason')
    <td>{{ $m->reason ?: '—' }}</td>
@elseif ($colKey === 'amount')
    <td class="desk-money">${{ number_format($m->amount, 2) }}</td>
@elseif ($colKey === 'remaining')
    <td class="desk-money">${{ number_format($remaining, 2) }}</td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => $m->status === 'Open',
            'desk-pill-invoiced' => $m->status === 'Applied',
            'desk-pill-muted' => ! in_array($m->status, ['Open', 'Applied'], true),
        ])>{{ $m->status }}</span>
    </td>
@endif
