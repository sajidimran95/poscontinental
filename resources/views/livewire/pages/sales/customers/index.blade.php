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
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->orderBy('customer_id');

        return [
            'customers' => $query->paginate(25),
            'favorites' => [
                'all' => 'All Customers',
                'active' => 'Active Customers',
                'inactive' => 'Inactive Customers',
            ],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function toggleInactive(int $id): void
    {
        $customer = Customer::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $customer->update(['is_inactive' => ! $customer->is_inactive]);
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        <x-list-chrome label="Search Customers:" model="search" placeholder="ID, company, contact, phone…">
            <a href="{{ route('sales.customers.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Customer</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">Customers List</h2>
            <span class="desk-title-meta">{{ number_format($customers->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Contact</th>
                        <th>Company</th>
                        <th>Address</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th class="text-right">Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr
                            wire:click="selectRow({{ $customer->id }})"
                            @class(['is-selected' => $selectedId === $customer->id, 'cursor-pointer'])
                        >
                            <td class="desk-num">
                                <a href="{{ route('sales.customers.edit', $customer) }}" wire:navigate wire:click.stop>{{ $customer->customer_id }}</a>
                            </td>
                            <td>{{ $customer->contact }}</td>
                            <td>{{ $customer->company_name }}</td>
                            <td class="max-w-[12rem] truncate" title="{{ $customer->address }}">{{ $customer->address }}</td>
                            <td>{{ $customer->telephone }}</td>
                            <td>{{ $customer->email }}</td>
                            <td class="desk-money">${{ number_format($customer->balance, 2) }}</td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleInactive({{ $customer->id }})"
                                    class="desk-pill {{ $customer->is_inactive ? 'desk-pill-muted' : 'desk-pill-invoiced' }}"
                                    title="{{ $customer->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                    aria-label="Toggle active status"
                                >{{ $customer->is_inactive ? 'Inactive' : 'Active' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="8">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$customers->total()">
            <a href="{{ route('sales.customers.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Customer</a>
            {{ $customers->links() }}
        </x-record-count>
    </div>
</div>
