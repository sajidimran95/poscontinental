<?php

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Customers')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public function with(): array
    {
        $query = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('customer_id', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('contact', 'like', $term)
                        ->orWhere('telephone', 'like', $term);
                });
            })
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->orderBy('customer_id');

        return [
            'customers' => $query->paginate(25),
            'favorites' => [
                'all' => 'All Customers',
                'active' => 'Active Customers',
            ],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Customers:" model="search" />

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Customers List</div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Contact</th>
                        <th>Company</th>
                        <th>Address</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th class="text-right">Balance</th>
                        <th class="text-center">Inactive</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr
                            wire:click="selectRow({{ $customer->id }})"
                            @class(['chief-selected-row' => $selectedId === $customer->id, 'cursor-pointer'])
                        >
                            <td class="font-mono">
                                <a href="{{ route('sales.customers.edit', $customer) }}" wire:navigate class="hover:underline">{{ $customer->customer_id }}</a>
                            </td>
                            <td>{{ $customer->contact }}</td>
                            <td>{{ $customer->company_name }}</td>
                            <td>{{ $customer->address }}</td>
                            <td>{{ $customer->telephone }}</td>
                            <td>{{ $customer->email }}</td>
                            <td class="text-right">${{ number_format($customer->balance, 2) }}</td>
                            <td class="text-center">{{ $customer->is_inactive ? '☑' : '☐' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-2 py-6 text-slate-500">No customers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$customers->total()">
            <a href="{{ route('sales.customers.create') }}" wire:navigate class="chief-btn-primary">New Customer</a>
        </x-record-count>
    </div>
</div>
