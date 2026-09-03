@php
    $fullAddress = collect([
        $supplier->address,
        collect([$supplier->city, $supplier->state, $supplier->zip_code])->filter()->implode(', '),
    ])->filter()->implode(' ');
@endphp
@if ($colKey === 'supplier_id')
    <td class="desk-num">
        <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate wire:click.stop>{{ $supplier->supplier_id }}</a>
    </td>
@elseif ($colKey === 'name')
    <td>{{ $supplier->name }}</td>
@elseif ($colKey === 'address')
    <td title="{{ $fullAddress }}">{{ \Illuminate\Support\Str::limit($fullAddress, 40) }}</td>
@elseif ($colKey === 'phone1')
    <td>{{ $supplier->phone1 ?: '' }}</td>
@elseif ($colKey === 'email')
    <td>
        @if ($supplier->email)
            <a href="mailto:{{ $supplier->email }}" wire:click.stop>{{ $supplier->email }}</a>
        @endif
    </td>
@elseif ($colKey === 'web_page')
    <td>
        @if ($supplier->web_page)
            <a href="{{ str_starts_with($supplier->web_page, 'http') ? $supplier->web_page : 'https://'.$supplier->web_page }}" target="_blank" rel="noopener" wire:click.stop>{{ $supplier->web_page }}</a>
        @endif
    </td>
@endif
