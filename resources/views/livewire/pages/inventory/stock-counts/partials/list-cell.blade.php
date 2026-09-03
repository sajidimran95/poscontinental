@if ($colKey === 'stock_count_no')
    <td class="desk-num">
        <a href="{{ route('inventory.stock-counts.edit', $count) }}" wire:navigate wire:click.stop>{{ $count->stock_count_no }}</a>
    </td>
@elseif ($colKey === 'status')
    <td class="text-center">
        <span @class([
            'desk-pill',
            'desk-pill-new' => $count->status === 'New',
            'desk-pill-invoiced' => $count->status === 'Processed',
            'desk-pill-muted' => ! in_array($count->status, ['New', 'Processed'], true),
        ])>{{ $count->status }}</span>
    </td>
@elseif ($colKey === 'description')
    <td title="{{ $count->description }}">{{ $count->description ? \Illuminate\Support\Str::limit($count->description, 40) : '' }}</td>
@elseif ($colKey === 'date_created')
    <td>{{ user_time($count->date_created) }}</td>
@elseif ($colKey === 'last_count_date')
    <td>{{ user_time($count->last_count_date) }}</td>
@elseif ($colKey === 'date_entered')
    <td>{{ user_time($count->date_entered ?? $count->created_at) }}</td>
@elseif ($colKey === 'date_processed')
    <td>{{ user_time($count->date_processed) }}</td>
@elseif ($colKey === 'site')
    <td class="desk-num">{{ $count->site?->code ?: '—' }}</td>
@elseif ($colKey === 'processed_by')
    <td>{{ $count->processedByUser?->name ?: '—' }}</td>
@endif
