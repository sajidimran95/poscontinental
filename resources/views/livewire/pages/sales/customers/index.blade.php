<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
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

    /** '' | active | inactive */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = Customer::query()
            ->with('salesRep')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('customer_id', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('contact', 'like', $term)
                        ->orWhere('telephone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->orderByDesc('id');

        $listTitle = match ($this->favorite) {
            'active' => 'Customers List (Active)',
            'inactive' => 'Customers List (Inactive)',
            default => 'Customers List',
        };

        if ($this->statusFilter === 'active') {
            $listTitle = 'Customers List (Active)';
        } elseif ($this->statusFilter === 'inactive') {
            $listTitle = 'Customers List (Inactive)';
        }

        return [
            'customers' => $query->paginate(25),
            'favorites' => [
                'all' => 'All Customers',
                'active' => 'Active Customers',
                'inactive' => 'Inactive Customers',
            ],
            'listTitle' => $listTitle,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a customer first.');

            return null;
        }

        return $this->openCustomer($this->selectedId);
    }

    public function openCustomer(int $id): mixed
    {
        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $customer) {
            session()->flash('status', 'Customer not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('sales.customers.edit', $customer), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a customer first.');

            return;
        }

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $customer) {
            session()->flash('status', 'Customer not found.');

            return;
        }

        if (
            SalesOrder::query()->where('customer_id', $customer->id)->exists()
            || Invoice::query()->where('customer_id', $customer->id)->exists()
        ) {
            session()->flash('status', 'Customer has orders or invoices and cannot be deleted.');

            return;
        }

        $customer->delete();
        $this->selectedId = null;
        session()->flash('status', 'Customer deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a customer first.');

            return;
        }

        $this->dispatch('print-customer', id: $this->selectedId);
    }

    public function toggleInactive(int $id): void
    {
        $customer = Customer::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $customer->update(['is_inactive' => ! $customer->is_inactive]);
        $this->selectedId = $id;
    }

    public function toggleOptOut(int $id, string $field): void
    {
        if (! in_array($field, ['opt_out_telemarketing', 'opt_out_email'], true)) {
            return;
        }

        $customer = Customer::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $customer->update([$field => ! $customer->{$field}]);
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action" />

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar">
                    <label class="desk-toolbar-label" for="customers-search">Search Customers:</label>
                    <input
                        id="customers-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="ID, company, contact, phone…"
                        class="desk-search orders-search-input"
                        aria-label="Search Customers"
                    />

                    <div class="orders-toolbar-right">
                        <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                            <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                            </svg>
                            New Search
                        </button>
                        <select
                            id="customers-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Active filter"
                            title="Active / Inactive"
                        >
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button
                            type="button"
                            wire:click="clearSearch"
                            class="so-icon-btn"
                            title="Clear search"
                            aria-label="Clear search"
                        >
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M4 4l8 8M12 4l-8 8"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($customers->total()) }} records</span>
                </div>

                <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Address</th>
                                <th>Telephone</th>
                                <th>Email</th>
                                <th>Sales Rep</th>
                                <th class="text-right">Balance</th>
                                <th class="text-center">Don't Call</th>
                                <th class="text-center">Don't Email</th>
                                <th>Comments</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $customer)
                                <tr
                                    wire:click="selectRow({{ $customer->id }})"
                                    wire:dblclick="openCustomer({{ $customer->id }})"
                                    @class(['is-selected' => $selectedId === $customer->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="customer_select"
                                            value="{{ $customer->id }}"
                                            @checked($selectedId === $customer->id)
                                            wire:click="selectRow({{ $customer->id }})"
                                            aria-label="Select customer {{ $customer->customer_id }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('sales.customers.edit', $customer) }}" wire:navigate wire:click.stop>{{ $customer->customer_id }}</a>
                                    </td>
                                    <td>{{ $customer->contact }}</td>
                                    <td>{{ $customer->company_name }}</td>
                                    <td class="max-w-[12rem] truncate" title="{{ $customer->address }}">{{ $customer->address }}</td>
                                    <td>{{ $customer->telephone }}</td>
                                    <td>
                                        @if ($customer->email)
                                            <a href="mailto:{{ $customer->email }}" wire:click.stop>{{ $customer->email }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $customer->salesRep?->name ?: '—' }}</td>
                                    <td class="desk-money">${{ number_format($customer->balance, 2) }}</td>
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="checkbox"
                                            @checked($customer->opt_out_telemarketing)
                                            wire:click="toggleOptOut({{ $customer->id }}, 'opt_out_telemarketing')"
                                            aria-label="Don't call for {{ $customer->customer_id }}"
                                            title="Don't call"
                                        />
                                    </td>
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="checkbox"
                                            @checked($customer->opt_out_email)
                                            wire:click="toggleOptOut({{ $customer->id }}, 'opt_out_email')"
                                            aria-label="Don't email for {{ $customer->customer_id }}"
                                            title="Don't email"
                                        />
                                    </td>
                                    <td class="max-w-[12rem] truncate" title="{{ $customer->comments }}">{{ $customer->comments ?: '—' }}</td>
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
                                    <td colspan="13">No customers found.</td>
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

            <aside class="desk-rail" aria-label="Customer actions">
                <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                        <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected customer? This cannot be undone."
                    class="desk-rail-btn desk-rail-btn-danger"
                    title="Delete selected"
                    aria-label="Delete selected"
                    @disabled(! $selectedId)
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                        <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                    </svg>
                </button>
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
                <a href="{{ route('sales.customers.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Customer" aria-label="New Customer">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('print-customer', (payload) => {
        const id = payload?.id ?? payload?.[0]?.id;
        if (!id) return;
        const url = @js(url('/sales/customers')) + '/' + id + '/edit';
        const w = window.open(url, '_blank');
        if (w) {
            w.addEventListener('load', () => {
                try { w.print(); } catch (e) {}
            });
        }
    });
</script>
@endscript
