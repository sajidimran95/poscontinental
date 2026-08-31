<?php

use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Customers')] class extends Component
{
    use WithPagination;
    use SortsDeskList;
    use PaginatesDeskLists;

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
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_inactive', true));

        $query = $this->applyDeskSort($query);

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

        $scroll = $this->scrollDeskList($query);
        $customers = $scroll['rows'];

        $openCreditsByCustomer = [];
        $pageIds = $customers->pluck('id')->all();
        if ($pageIds !== []) {
            $memos = CreditMemo::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $pageIds)
                ->where('status', 'Open')
                ->withSum('applications as applied_total', 'amount')
                ->get(['id', 'customer_id', 'amount']);

            foreach ($memos as $memo) {
                $remaining = max(0, (float) $memo->amount - (float) ($memo->applied_total ?? 0));
                if ($remaining <= 0.0001) {
                    continue;
                }
                $cid = (int) $memo->customer_id;
                $openCreditsByCustomer[$cid] = round(($openCreditsByCustomer[$cid] ?? 0) + $remaining, 2);
            }
        }

        return [
            'customers' => $customers,
            'listHasMore' => $scroll['hasMore'],
            'listShown' => $scroll['shown'],
            'openCreditsByCustomer' => $openCreditsByCustomer,
            'salesReps' => User::assignableSalesRepsQuery($companyId)->get(['id', 'name', 'is_active', 'role_id']),
            'canEditCustomers' => auth()->user()?->canAccessFeature('sales.customers', 'edit') ?? false,
            'favorites' => [
                'all' => 'All Customers',
                'active' => 'Active Customers',
                'inactive' => 'Inactive Customers',
            ],
            'listTitle' => $listTitle,
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'customer_id' => 'customer_id',
            'contact' => 'contact',
            'company_name' => 'company_name',
            'address' => 'address',
            'telephone' => 'telephone',
            'email' => 'email',
            'sales_rep' => ['relation' => 'salesRep', 'column' => 'name'],
            'balance' => 'balance',
            'opt_out_telemarketing' => 'opt_out_telemarketing',
            'opt_out_email' => 'opt_out_email',
            'comments' => 'comments',
            'is_inactive' => 'is_inactive',
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

    public function viewSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a customer first.');

            return null;
        }

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $customer) {
            session()->flash('status', 'Customer not found.');

            return null;
        }

        return $this->redirect(route('sales.customers.show', $customer), navigate: true);
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a customer first.');

            return null;
        }

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $customer) {
            session()->flash('status', 'Customer not found.');

            return null;
        }

        return $this->redirect(route('sales.customers.edit', $customer), navigate: true);
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
        if (! auth()->user()?->canAccessFeature('sales.customers', 'delete')) {
            session()->flash('status', 'Your role cannot delete customers.');

            return;
        }

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
        // Selected customer → full detail sheet; otherwise print all filtered customers.
        $params = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'favorite' => $this->favorite !== 'all' ? $this->favorite : null,
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'customer_id' => $this->selectedId ?: null,
            'title' => $this->selectedId ? 'Customer Detail' : $this->listTitleForPrint(),
        ]);

        $this->dispatch('open-customers-print', url: route('sales.customers.print', $params));
    }

    public function printList(): void
    {
        $this->dispatch('open-customers-print', url: route('sales.customers.print', array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'favorite' => $this->favorite !== 'all' ? $this->favorite : null,
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'title' => $this->listTitleForPrint(),
        ])));
    }

    protected function listTitleForPrint(): string
    {
        if ($this->favorite === 'active' || $this->statusFilter === 'active') {
            return 'Customers List (Active)';
        }
        if ($this->favorite === 'inactive' || $this->statusFilter === 'inactive') {
            return 'Customers List (Inactive)';
        }

        return 'Customers List';
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

    /**
     * Assign / change Sales Rep from the list page (click dropdown).
     */
    public function updateSalesRep(int $customerId, ?string $salesRepId = null): void
    {
        if (! auth()->user()?->canAccessFeature('sales.customers', 'edit')) {
            session()->flash('status', 'Your role cannot assign sales reps.');

            return;
        }

        $companyId = auth()->user()->company_id;
        $customer = Customer::query()->where('company_id', $companyId)->findOrFail($customerId);

        $repId = filled($salesRepId) ? (int) $salesRepId : null;

        if ($repId !== null) {
            $valid = User::query()
                ->where('company_id', $companyId)
                ->whereKey($repId)
                ->where(function ($q) use ($repId) {
                    $q->where('is_active', true)->orWhere('id', $repId);
                })
                ->exists();

            if (! $valid) {
                session()->flash('status', 'Select a valid user.');

                return;
            }
        }

        $customer->update(['sales_rep_id' => $repId]);
        $this->selectedId = $customerId;
        session()->flash('status', $repId
            ? 'Sales rep assigned for '.$customer->customer_id.'.'
            : 'Sales rep cleared for '.$customer->customer_id.'.');
    }

    public function createNewCustomer(): mixed
    {
        return $this->redirect(route('sales.customers.create'), navigate: true);
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action">
            <x-slot:menu>
                <x-action-item label="Add New Customer" kbd="Ctrl+N" wire:click="createNewCustomer" />
                <x-action-item label="View/Edit Selected Customer" kbd="Ctrl+E" sep wire:click="editSelected" />
                <x-action-item label="Delete Selected Customer" sep wire:click="deleteSelected" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar">
                    <label class="desk-toolbar-label" for="customers-search">Search Customers:</label>
                    <input
                        id="customers-search" data-pos-search
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
                    <span class="desk-title-meta">{{ number_format($listShown) }}{{ $listHasMore ? '+' : '' }} records</span>
                </div>

                <x-desk-scroll-grid :has-more="$listHasMore" class="{{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <x-desk-sort-th field="customer_id" label="Customer ID" />
                                <x-desk-sort-th field="contact" label="Name" />
                                <x-desk-sort-th field="company_name" label="Company" />
                                <x-desk-sort-th field="address" label="Address" />
                                <x-desk-sort-th field="telephone" label="Telephone" />
                                <x-desk-sort-th field="email" label="Email" />
                                <x-desk-sort-th field="sales_rep" label="Sales Rep" />
                                <x-desk-sort-th field="balance" label="Balance Owed" align="right" />
                                <th class="text-right" title="Unapplied open credit memos (e.g. overpayments)">Open Credit</th>
                                <x-desk-sort-th field="opt_out_telemarketing" label="Don't Call" align="center" />
                                <x-desk-sort-th field="opt_out_email" label="Don't Email" align="center" />
                                <x-desk-sort-th field="comments" label="Comments" />
                                <x-desk-sort-th field="is_inactive" label="Status" align="center" />
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
                                        <a href="{{ route('sales.customers.show', $customer) }}" wire:navigate wire:click.stop>{{ $customer->customer_id }}</a>
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
                                    <td wire:click.stop style="min-width:9rem">
                                        @if ($canEditCustomers)
                                            <select
                                                class="so-input so-input-sm"
                                                style="min-width:8.5rem;max-width:12rem;padding:0.2rem 0.35rem;font-size:0.8rem"
                                                wire:change="updateSalesRep({{ $customer->id }}, $event.target.value)"
                                                aria-label="Assign sales rep for {{ $customer->customer_id }}"
                                                title="Change sales rep"
                                            >
                                                <option value="">— None —</option>
                                                @foreach ($salesReps as $rep)
                                                    <option value="{{ $rep->id }}" @selected((int) $customer->sales_rep_id === (int) $rep->id)>
                                                        {{ $rep->name }}@if(! $rep->is_active) (inactive)@endif
                                                    </option>
                                                @endforeach
                                                {{-- Keep current assignment visible if user is no longer sales_rep role --}}
                                                @if ($customer->sales_rep_id && ! $salesReps->contains('id', $customer->sales_rep_id) && $customer->salesRep)
                                                    <option value="{{ $customer->salesRep->id }}" selected>
                                                        {{ $customer->salesRep->name }} (other role)
                                                    </option>
                                                @endif
                                            </select>
                                        @else
                                            {{ $customer->salesRep?->name ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="desk-money">${{ number_format((float) $customer->balance, 2) }}</td>
                                    <td class="desk-money" @if (($openCreditsByCustomer[$customer->id] ?? 0) > 0.0001) style="color:#0a7a32;font-weight:600" @endif>
                                        @php $oc = (float) ($openCreditsByCustomer[$customer->id] ?? 0); @endphp
                                        @if ($oc > 0.0001)
                                            ${{ number_format($oc, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
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
                                    <td colspan="14">No customers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-desk-scroll-grid>

                <x-record-count :count="$listShown">
                    <a href="{{ route('sales.customers.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Customer</a>
                    <x-desk-load-more :has-more="$listHasMore" />
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
                <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
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
                <button type="button" wire:click="printList" class="desk-rail-btn" title="Print all filtered customers" aria-label="Print customers list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="{{ $selectedId ? 'Print selected customer detail' : 'Select a customer to print detail' }}" aria-label="Print selected customer" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.35" aria-hidden="true">
                        <path d="M3 2.5h10v11H3z"/>
                        <path d="M5 5h6M5 7.5h6M5 10h4"/>
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
    $wire.on('open-customers-print', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
@endscript
