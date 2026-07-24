<?php

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Orders')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    /** '' | not_invoiced | Invoiced */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public function updatedSearch(): void
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
            session()->flash('status', 'Select an order first.');

            return null;
        }

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return null;
        }

        return $this->redirect(route('sales.orders.show', $order), navigate: true);
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return null;
        }

        $order = SalesOrder::query()
            ->with('invoice')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return null;
        }

        if ($order->status === 'Invoiced' || $order->invoice) {
            session()->flash('status', 'Invoiced orders are locked and cannot be edited. Use View instead.');

            return null;
        }

        return $this->redirect(route('sales.orders.edit', $order), navigate: true);
    }

    public function openOrder(int $id): mixed
    {
        $order = SalesOrder::query()
            ->with('invoice')
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return null;
        }

        $this->selectedId = $id;

        // Double-click / open → view page
        return $this->redirect(route('sales.orders.show', $order), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $order = SalesOrder::query()
            ->with('invoice')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return;
        }

        if ($order->status === 'Invoiced' || $order->invoice) {
            session()->flash('status', 'Invoiced orders cannot be deleted.');

            return;
        }

        $order->loadMissing('lines');
        $itemIds = $order->lines->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $order->lines()->delete();
        $order->delete();

        if ($itemIds !== []) {
            app(\App\Services\InventoryService::class)->syncAllocatedQty($itemIds);
        }

        $this->selectedId = null;
        session()->flash('status', 'Order deleted. Allocated qty released.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return;
        }

        $this->dispatch('open-order-invoice-pdf', url: route('sales.orders.print', $order));
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = SalesOrder::query()
            ->with(['customer', 'createdBy'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'not_invoiced', fn ($q) => $q->where('status', '!=', 'Invoiced'))
            ->when($this->favorite === 'invoiced', fn ($q) => $q->where('status', 'Invoiced'))
            ->when($this->favorite === 'month', fn ($q) => $q->where('order_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('order_date', '>=', now()->subDay()))
            ->when($this->statusFilter === 'not_invoiced', fn ($q) => $q->where('status', '!=', 'Invoiced'))
            ->when($this->statusFilter === 'Invoiced', fn ($q) => $q->where('status', 'Invoiced'))
            ->orderByDesc('id');

        $listTitle = match ($this->favorite) {
            'new' => 'Orders List (New)',
            'not_invoiced' => 'Orders List (Not Invoiced)',
            'invoiced' => 'Orders List (Invoiced)',
            'month' => 'Orders List (This Month)',
            'today' => 'Orders List (Today & Yesterday)',
            default => 'Orders List',
        };

        if ($this->statusFilter === 'not_invoiced') {
            $listTitle = 'Orders List (Not Invoiced)';
        } elseif ($this->statusFilter === 'Invoiced') {
            $listTitle = 'Orders List (Invoiced)';
        }

        return [
            'orders' => $query->paginate(50),
            'favorites' => [
                'all' => 'All Orders',
                'new' => 'New Orders',
                'not_invoiced' => 'Not Invoiced',
                'invoiced' => 'Invoiced',
                'month' => 'This Month',
                'today' => 'Today & Yesterday',
            ],
            'listTitle' => $listTitle,
            'selectedOrder' => $this->selectedId
                ? SalesOrder::query()->where('company_id', $companyId)->find($this->selectedId)
                : null,
        ];
    }

    public function invoiceOrder(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $order = SalesOrder::query()->with(['lines', 'customer', 'invoice'])->lockForUpdate()->findOrFail($id);
                abort_unless($order->company_id === auth()->user()->company_id, 403);
                if ($order->status === 'Invoiced' || $order->invoice) {
                    return;
                }

                $lineDiscount = (float) $order->lines->sum('discount');
                $invoice = Invoice::query()->create([
                    'company_id' => $order->company_id,
                    'invoice_number' => Invoice::nextNumber($order->company_id),
                    'invoice_date' => now()->toDateString(),
                    'sales_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'status' => 'NOT PAID',
                    'subtotal' => $order->subtotal,
                    'total_discount' => $lineDiscount,
                    'trade_discount' => $order->trade_discount,
                    'freight' => $order->freight,
                    'miscellaneous' => $order->miscellaneous,
                    'tax' => $order->tax,
                    'invoice_total' => $order->total,
                    'driver' => null,
                ]);

                app(InventoryService::class)->applyInvoiceStock($order, $invoice);

                $order->update(['status' => 'Invoiced']);

                if ($order->customer) {
                    $customer = $order->customer;
                    $updates = [
                        'last_order_on' => $order->order_date ?? now()->toDateString(),
                        'number_of_orders' => (int) $customer->number_of_orders + 1,
                        'total_sales' => (float) $customer->total_sales + (float) $order->total,
                        'balance' => (float) $customer->balance + (float) $order->total,
                    ];
                    if (blank($customer->customer_since)) {
                        $updates['customer_since'] = $order->order_date ?? now()->toDateString();
                    }
                    $customer->update($updates);
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('status', collect($e->errors())->flatten()->first() ?: 'Unable to invoice order.');

            return;
        }

        session()->flash('status', 'Invoice created. Stock quantities updated.');
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
                    <label class="desk-toolbar-label" for="orders-search">Search Orders:</label>
                    <input
                        id="orders-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Order #, customer, phone…"
                        class="desk-search orders-search-input"
                        aria-label="Search Orders"
                    />

                    <div class="orders-toolbar-right">
                        <select
                            id="orders-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Invoiced filter"
                            title="Invoiced / Not Invoiced"
                        >
                            <option value="">All</option>
                            <option value="not_invoiced">Not Invoiced</option>
                            <option value="Invoiced">Invoiced</option>
                        </select>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($orders->total()) }} records</span>
                </div>

                <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <th>Order #</th>
                                <th>Type</th>
                                <th>Order Date</th>
                                <th>Ship Date</th>
                                <th>Status</th>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Address</th>
                                <th>Telephone</th>
                                <th>User Name</th>
                                <th>Last Updated</th>
                                <th>Req. Delivery</th>
                                <th class="text-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr
                                    wire:click="selectRow({{ $order->id }})"
                                    wire:dblclick="openOrder({{ $order->id }})"
                                    @class(['is-selected' => $selectedId === $order->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="order_select"
                                            value="{{ $order->id }}"
                                            @checked($selectedId === $order->id)
                                            wire:click="selectRow({{ $order->id }})"
                                            aria-label="Select order {{ $order->order_number }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('sales.orders.show', $order) }}" wire:navigate wire:click.stop>{{ $order->order_number }}</a>
                                    </td>
                                    <td>{{ $order->order_type }}</td>
                                    <td>{{ optional($order->order_date)?->format('n/j/Y') }}</td>
                                    <td>{{ optional($order->ship_date)?->format('n/j/Y') }}</td>
                                    <td>
                                        <span @class([
                                            'desk-pill',
                                            'desk-pill-new' => $order->status === 'New',
                                            'desk-pill-invoiced' => $order->status === 'Invoiced',
                                            'desk-pill-muted' => ! in_array($order->status, ['New', 'Invoiced'], true),
                                        ])>{{ $order->status }}</span>
                                    </td>
                                    <td class="desk-num">{{ $order->customer?->customer_id }}</td>
                                    <td>{{ $order->customer?->contact }}</td>
                                    <td>{{ $order->customer?->company_name }}</td>
                                    <td class="max-w-[10rem] truncate" title="{{ $order->customer?->address }}">{{ $order->customer?->address }}</td>
                                    <td>{{ $order->customer?->telephone }}</td>
                                    <td>{{ $order->createdBy?->name }}</td>
                                    <td>{{ optional($order->updated_at)?->format('n/j/Y g:i A') }}</td>
                                    <td>{{ optional($order->required_date)?->format('n/j/Y') }}</td>
                                    <td class="desk-money">${{ number_format($order->total, 2) }}</td>
                                    <td wire:click.stop>
                                        @if ($order->status !== 'Invoiced')
                                            <button type="button" wire:click="invoiceOrder({{ $order->id }})" class="desk-btn desk-btn-sm">Invoice</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="16">No orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$orders->total()">
                    <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Sales Order</a>
                    {{ $orders->links() }}
                </x-record-count>
            </div>

            {{-- Right icon rail: grid, view, edit, delete, print, refresh, + --}}
            <aside class="desk-rail" aria-label="Order actions">
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
                    wire:confirm="Delete the selected order? This cannot be undone."
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
                <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Sales Order" aria-label="New Sales Order">
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
    $wire.on('open-order-invoice-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });
</script>
@endscript
