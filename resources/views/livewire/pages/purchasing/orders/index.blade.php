<?php

use App\Livewire\Concerns\InteractsWithDeskQuery;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\InventoryReceiving;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Purchase Orders')] class extends Component
{
    use WithPagination;
    use InteractsWithDeskQuery;
    use SortsDeskList;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    /** '' | pending | received */
    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $supplierId = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = PurchaseOrder::query()
            ->with(['supplier', 'buyer'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('po_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)->orWhere('supplier_id', 'like', $term))
                        ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', $term));
                });
            })
            ->when($this->favorite === 'pending', fn ($q) => $q->whereIn('status', ['New', 'Partially Received']))
            ->when($this->favorite === 'received', fn ($q) => $q->where('status', 'Received'))
            ->when($this->favorite === 'month', fn ($q) => $q->where('requisition_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('requisition_date', today()))
            ->when($this->statusFilter === 'pending', fn ($q) => $q->whereIn('status', ['New', 'Partially Received']))
            ->when($this->statusFilter === 'received', fn ($q) => $q->where('status', 'Received'))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('requisition_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('requisition_date', '<=', $this->dateTo))
            ->when($this->supplierId !== '' && ctype_digit((string) $this->supplierId), fn ($q) => $q->where('supplier_id', (int) $this->supplierId))
            ->when($this->queryCriteria !== [], fn ($q) => $this->applyQueryCriteria($q));

        $query = $this->applyDeskSort($query, 'requisition_date', 'desc');

        $listTitle = match (true) {
            $this->statusFilter === 'pending', $this->favorite === 'pending' => 'Purchase Orders List (Pending)',
            $this->statusFilter === 'received', $this->favorite === 'received' => 'Purchase Orders List (Received)',
            $this->favorite === 'month' => 'Purchase Orders List (This Month)',
            $this->favorite === 'today' => 'Purchase Orders List (Today)',
            default => 'Purchase Orders List',
        };

        if ($this->queryCriteria !== []) {
            $listTitle = $this->queryLoadedName !== ''
                ? 'Query: '.$this->queryLoadedName
                : 'Query Results ('.count($this->queryCriteria).' criteria)';
        }

        return [
            'orders' => $query->paginate(50),
            'favorites' => [
                'all' => 'All POs',
                'pending' => 'Pending POs',
                'received' => 'Received',
                'month' => 'This Month',
                'today' => 'Today',
            ],
            'listTitle' => $listTitle,
            'queryFields' => $this->deskQueryFieldOptions(),
            'queryFieldTypes' => $this->deskQueryFieldTypes(),
            'queryOperators' => $this->deskQueryOperatorOptions(),
            'savedDeskQueries' => $this->loadSavedDeskQueries(),
            'deskQueryTitle' => 'Purchase Order Query',
            'filterSuppliers' => Supplier::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'supplier_id', 'name']),
        ];
    }

    /** @return array<string, array{label: string, column: string, has?: string}> */
    protected function deskQueryFields(): array
    {
        return [
            'po_number' => ['label' => 'PO Number', 'column' => 'po_number'],
            'status' => ['label' => 'Status', 'column' => 'status'],
            'order_type' => ['label' => 'Order Type', 'column' => 'order_type'],
            'reference_no' => ['label' => 'Reference #', 'column' => 'reference_no'],
            'requisition_date' => ['label' => 'Requisition Date', 'column' => 'requisition_date', 'type' => 'date'],
            'required_date' => ['label' => 'Required Date', 'column' => 'required_date', 'type' => 'date'],
            'total' => ['label' => 'PO Total', 'column' => 'total', 'type' => 'number'],
            'supplier_code' => ['label' => 'Supplier ID', 'has' => 'supplier', 'column' => 'supplier_id'],
            'supplier_name' => ['label' => 'Supplier Name', 'has' => 'supplier', 'column' => 'name'],
            'buyer_name' => ['label' => 'Buyer', 'has' => 'buyer', 'column' => 'name'],
            'supplier_contact' => ['label' => 'Supplier Contact', 'has' => 'supplier', 'column' => 'contact_name'],
            'supplier_city' => ['label' => 'Supplier City', 'has' => 'supplier', 'column' => 'city'],
            'supplier_state' => ['label' => 'Supplier State', 'has' => 'supplier', 'column' => 'state'],
            'supplier_phone' => ['label' => 'Supplier Phone', 'has' => 'supplier', 'column' => 'phone1'],
            'ship_from' => ['label' => 'Ship From', 'column' => 'ship_from'],
            'comments' => ['label' => 'Comments', 'column' => 'comments'],
            'item_code' => ['label' => 'Item Code', 'has' => 'lines', 'column' => 'item_code'],
            'item_description' => ['label' => 'Item Description', 'has' => 'lines', 'column' => 'description'],
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'po_number' => 'po_number',
            'requisition_date' => 'requisition_date',
            'status' => 'status',
            'required_date' => 'required_date',
            'reference_no' => 'reference_no',
            'supplier_code' => ['relation' => 'supplier', 'column' => 'supplier_id'],
            'supplier_name' => ['relation' => 'supplier', 'column' => 'name'],
            'buyer' => ['relation' => 'buyer', 'column' => 'name'],
            'subtotal' => 'subtotal',
            'trade_discount' => 'trade_discount',
            'freight' => 'freight',
            'total' => 'total',
        ];
    }

    protected function deskQuerySessionKey(): string
    {
        return 'purchase_orders_query_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->statusFilter = match ($this->favorite) {
            'pending' => 'pending',
            'received' => 'received',
            default => $this->statusFilter,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        if ($this->statusFilter === 'pending') {
            $this->favorite = 'pending';
        } elseif ($this->statusFilter === 'received') {
            $this->favorite = 'received';
        }
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
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
        $this->favorite = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->supplierId = '';
        $this->selectedId = null;
        $this->clearQueryCriteria();
        $this->resetPage();
    }

    public function openDeskQuery(): void
    {
        $this->showQueryModal = true;
        $this->queryStatus = '';
        $fields = $this->deskQueryFields();
        if ($this->queryField === '' || ! isset($fields[$this->queryField])) {
            $this->queryField = (string) (array_key_first($fields) ?? '');
            if ($this->queryField !== '') {
                $this->syncQueryOperatorForField($this->queryField);
            }
        }
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
            session()->flash('status', 'Select a purchase order first.');

            return null;
        }

        $order = PurchaseOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Purchase order not found.');

            return null;
        }

        return $this->redirect(route('purchasing.orders.show', $order), navigate: true);
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a purchase order first.');

            return null;
        }

        $order = PurchaseOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Purchase order not found.');

            return null;
        }

        return $this->redirect(route('purchasing.orders.edit', $order), navigate: true);
    }

    public function openOrder(int $id): mixed
    {
        $order = PurchaseOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $order) {
            session()->flash('status', 'Purchase order not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('purchasing.orders.show', $order), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! auth()->user()?->canAccessFeature('purchasing.orders', 'delete')) {
            session()->flash('status', 'Your role cannot delete purchase orders.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', 'Select a purchase order first.');

            return;
        }

        $order = PurchaseOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Purchase order not found.');

            return;
        }

        if ($order->status === 'Received' || $order->status === 'Partially Received') {
            session()->flash('status', 'Received purchase orders cannot be deleted.');

            return;
        }

        if (class_exists(InventoryReceiving::class)
            && InventoryReceiving::query()->where('purchase_order_id', $order->id)->exists()) {
            session()->flash('status', 'Purchase order has receivings and cannot be deleted.');

            return;
        }

        $order->lines()->delete();
        $order->delete();
        $this->selectedId = null;
        session()->flash('status', 'Purchase order deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a purchase order first.');

            return;
        }

        $order = PurchaseOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Purchase order not found.');

            return;
        }

        $this->dispatch('open-purchase-order-pdf', url: route('purchasing.orders.print', $order));
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
                    <label class="desk-toolbar-label" for="po-search">Search Purchase Orders:</label>
                    <input
                        id="po-search" data-pos-search
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="PO #, supplier, reference…"
                        class="desk-search orders-search-input"
                        aria-label="Search Purchase Orders"
                    />
                    <button type="button" wire:click="openDeskQuery" class="desk-btn items-query-btn" title="Query by field">Query</button>
                    @if ($queryCriteria !== [])
                        <button type="button" wire:click="clearQueryCriteria" class="desk-btn desk-btn-sm" title="Clear query">Clear Query</button>
                    @endif

                    <div class="orders-toolbar-right">
                        <label class="desk-toolbar-label" for="po-date-from">From</label>
                        <input id="po-date-from" type="date" wire:model.live="dateFrom" class="desk-input" aria-label="Requisition date from" />
                        <label class="desk-toolbar-label" for="po-date-to">To</label>
                        <input id="po-date-to" type="date" wire:model.live="dateTo" class="desk-input" aria-label="Requisition date to" />
                        <select wire:model.live="supplierId" class="desk-select orders-party-select" aria-label="Supplier">
                            <option value="">All suppliers</option>
                            @foreach ($filterSuppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->supplier_id }} — {{ $sup->name }}</option>
                            @endforeach
                        </select>
                        <select
                            id="po-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Status filter"
                            title="Pending / Received"
                        >
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="received">Received</option>
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
                                <x-desk-sort-th field="po_number" label="Order Number" />
                                <x-desk-sort-th field="requisition_date" label="Requisition Date" />
                                <x-desk-sort-th field="status" label="Status" align="center" />
                                <x-desk-sort-th field="required_date" label="Required Date" />
                                <x-desk-sort-th field="reference_no" label="Reference No." />
                                <x-desk-sort-th field="supplier_code" label="Supplier ID" />
                                <x-desk-sort-th field="supplier_name" label="Supplier" />
                                <x-desk-sort-th field="buyer" label="Buyer / Requester" />
                                <x-desk-sort-th field="subtotal" label="Order Subtotal" align="money" />
                                <x-desk-sort-th field="trade_discount" label="Discount" align="money" />
                                <x-desk-sort-th field="freight" label="Freight" align="money" />
                                <x-desk-sort-th field="total" label="Order Total" align="money" />
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
                                            name="po_select"
                                            value="{{ $order->id }}"
                                            @checked($selectedId === $order->id)
                                            wire:click="selectRow({{ $order->id }})"
                                            aria-label="Select PO {{ $order->po_number }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('purchasing.orders.show', $order) }}" wire:navigate wire:click.stop>{{ $order->po_number }}</a>
                                    </td>
                                    <td>{{ optional($order->requisition_date)?->format('n/j/Y') }}</td>
                                    <td class="text-center">
                                        <span @class([
                                            'desk-pill',
                                            'desk-pill-new' => in_array($order->status, ['New', 'Partially Received'], true),
                                            'desk-pill-invoiced' => $order->status === 'Received',
                                            'desk-pill-muted' => ! in_array($order->status, ['New', 'Partially Received', 'Received'], true),
                                        ])>{{ $order->status }}</span>
                                    </td>
                                    <td>{{ optional($order->required_date)?->format('n/j/Y') ?: '—' }}</td>
                                    <td>{{ $order->reference_no ?: '' }}</td>
                                    <td class="desk-num">{{ $order->supplier?->supplier_id ?: '—' }}</td>
                                    <td>{{ $order->supplier?->name ?: '—' }}</td>
                                    <td>{{ $order->buyer?->name ?: '—' }}</td>
                                    <td class="desk-money">${{ number_format($order->subtotal, 2) }}</td>
                                    <td class="desk-money">${{ number_format($order->trade_discount, 2) }}</td>
                                    <td class="desk-money">${{ number_format($order->freight, 2) }}</td>
                                    <td class="desk-money">${{ number_format($order->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="13">No purchase orders found. Use the <strong>+</strong> button to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$orders->total()">
                    <a href="{{ route('purchasing.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Purchase Order</a>
                    {{ $orders->links() }}
                </x-record-count>
            </div>

            {{-- Right rail: grid, view, edit, delete, print, refresh, + --}}
            <aside class="desk-rail" aria-label="Purchase order actions">
                <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="openDeskQuery" class="desk-rail-btn" title="Query purchase orders by field" aria-label="Query purchase orders">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <circle cx="7" cy="7" r="4.5"/>
                        <path d="M10.5 10.5L14 14"/>
                        <path d="M5.2 7h3.6M7 5.2v3.6" stroke-width="1.3"/>
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
                    wire:confirm="Delete the selected purchase order? This cannot be undone."
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
                <a href="{{ route('purchasing.orders.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Purchase Order" aria-label="New Purchase Order">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>
    @include('livewire.partials.desk-query-modal')
</div>
@script
<script>
    $wire.on('open-purchase-order-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank', 'noopener');
    });
</script>
@endscript
