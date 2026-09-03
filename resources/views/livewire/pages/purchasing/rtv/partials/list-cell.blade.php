@if ($colKey === 'rtv_number')
    <td class="desk-num">
        <button type="button" wire:click.stop="edit({{ $rec->id }})" class="text-sky-700 font-semibold hover:underline">{{ $rec->rtv_number }}</button>
    </td>
@elseif ($colKey === 'rtv_date')
    <td>{{ optional($rec->rtv_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => $rec->status === 'New',
            'desk-pill-invoiced' => $rec->status === 'Returned',
            'desk-pill-muted' => ! in_array($rec->status, ['New', 'Returned'], true),
        ])>{{ $rec->status }}</span>
    </td>
@elseif ($colKey === 'reference_no')
    <td>{{ $rec->reference_no ?: '' }}</td>
@elseif ($colKey === 'supplier_code')
    <td class="desk-num">{{ $rec->supplier?->supplier_id ?: '—' }}</td>
@elseif ($colKey === 'supplier_name')
    <td>{{ $rec->supplier?->name ?: '—' }}</td>
@elseif ($colKey === 'requested_by')
    <td>{{ $rec->requestedBy?->name ?: '—' }}</td>
@elseif ($colKey === 'subtotal')
    <td class="desk-money">${{ number_format($rec->subtotal, 2) }}</td>
@elseif ($colKey === 'discount')
    <td class="desk-money">${{ number_format($rec->discount, 2) }}</td>
@elseif ($colKey === 'freight')
    <td class="desk-money">${{ number_format($rec->freight, 2) }}</td>
@elseif ($colKey === 'total')
    <td class="desk-money">${{ number_format($rec->total, 2) }}</td>
@endif
