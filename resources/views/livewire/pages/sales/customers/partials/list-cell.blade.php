@if ($colKey === 'customer_id')
    <td class="desk-num" data-excel-value="{{ $customer->customer_id }}">
        <a href="{{ route('sales.customers.show', $customer) }}" wire:navigate wire:click.stop>{{ $customer->customer_id }}</a>
    </td>
@elseif ($colKey === 'contact')
    <td>{{ $customer->contact }}</td>
@elseif ($colKey === 'company_name')
    <td>{{ $customer->company_name }}</td>
@elseif ($colKey === 'address')
    <td class="max-w-[12rem] truncate" title="{{ $customer->address }}">{{ $customer->address }}</td>
@elseif ($colKey === 'telephone')
    <td>{{ $customer->telephone }}</td>
@elseif ($colKey === 'email')
    <td>
        @if ($customer->email)
            <a href="mailto:{{ $customer->email }}" wire:click.stop>{{ $customer->email }}</a>
        @else
            —
        @endif
    </td>
@elseif ($colKey === 'sales_rep')
    <td>{{ $customer->salesRep?->name ?: '—' }}</td>
@elseif ($colKey === 'balance')
    <td class="desk-money">${{ number_format((float) $customer->balance, 2) }}</td>
@elseif ($colKey === 'open_credit')
    <td class="desk-money">
        @php $oc = (float) ($openCreditsByCustomer[$customer->id] ?? 0); @endphp
        {{ $oc > 0.0001 ? '$'.number_format($oc, 2) : '—' }}
    </td>
@elseif ($colKey === 'opt_out_telemarketing')
    <td class="text-center">{{ $customer->opt_out_telemarketing ? 'Yes' : 'No' }}</td>
@elseif ($colKey === 'opt_out_email')
    <td class="text-center">{{ $customer->opt_out_email ? 'Yes' : 'No' }}</td>
@elseif ($colKey === 'comments')
    <td class="max-w-[12rem] truncate" title="{{ $customer->comments }}">{{ $customer->comments ?: '—' }}</td>
@elseif ($colKey === 'is_inactive')
    <td class="text-center">{{ $customer->is_inactive ? 'Inactive' : 'Active' }}</td>
@endif
