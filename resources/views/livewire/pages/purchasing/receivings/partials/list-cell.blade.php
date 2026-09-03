@if ($colKey === 'receipt_number')
    <td class="desk-num">
        <a href="{{ route($rec->status === 'Processed' ? 'purchasing.receivings.show' : 'purchasing.receivings.edit', $rec) }}" wire:navigate wire:click.stop>{{ $rec->receipt_number }}</a>
    </td>
@elseif ($colKey === 'receipt_date')
    <td>{{ optional($rec->receipt_date)?->format('n/j/Y') }}</td>
@elseif ($colKey === 'po_number')
    <td class="desk-num">{{ $rec->purchaseOrder?->po_number ?: '—' }}</td>
@elseif ($colKey === 'reference_no')
    <td>{{ $rec->reference_no ?: '' }}</td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => $rec->status === 'New',
            'desk-pill-invoiced' => $rec->status === 'Processed',
            'desk-pill-muted' => ! in_array($rec->status, ['New', 'Processed'], true),
        ])>{{ $rec->status }}</span>
    </td>
@elseif ($colKey === 'requisition_date')
    <td>{{ optional($rec->purchaseOrder?->requisition_date)?->format('n/j/Y') ?: '—' }}</td>
@elseif ($colKey === 'required_date')
    <td>{{ optional($rec->purchaseOrder?->required_date)?->format('n/j/Y') ?: '—' }}</td>
@elseif ($colKey === 'supplier_name')
    <td>{{ $rec->supplier?->name ?: '—' }}</td>
@elseif ($colKey === 'buyer')
    <td>{{ $rec->buyer?->name ?: '—' }}</td>
@elseif ($colKey === 'site')
    <td class="desk-num">{{ $rec->site?->code ?: '—' }}</td>
@elseif ($colKey === 'received_by')
    <td>{{ $rec->received_by ?: '' }}</td>
@elseif ($colKey === 'shipping_carrier')
    <td>{{ $rec->shipping_carrier ?: '' }}</td>
@elseif ($colKey === 'comments')
    <td title="{{ $rec->comments }}">{{ $rec->comments ? \Illuminate\Support\Str::limit($rec->comments, 28) : '' }}</td>
@endif
