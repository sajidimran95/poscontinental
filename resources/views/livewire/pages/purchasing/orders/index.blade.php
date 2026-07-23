<?php

use App\Models\InventoryReceiving;
use App\Models\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Purchase Orders')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    /** '' | pending | received */
    public string $statusFilter = '';

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
            ->orderByDesc('id');

        $listTitle = match (true) {
            $this->statusFilter === 'pending', $this->favorite === 'pending' => 'Purchase Orders List (Pending)',
            $this->statusFilter === 'received', $this->favorite === 'received' => 'Purchase Orders List (Received)',
            $this->favorite === 'month' => 'Purchase Orders List (This Month)',
            $this->favorite === 'today' => 'Purchase Orders List (Today)',
            default => 'Purchase Orders List',
        };

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
                        id="po-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="PO #, supplier, reference…"
                        class="desk-search orders-search-input"
                        aria-label="Search Purchase Orders"
                    />

                    <div class="orders-toolbar-right">
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
                                <th>Order Number</th>
                                <th>Requisition Date</th>
                                <th class="text-center">Status</th>
                                <th>Required Date</th>
                                <th>Reference No.</th>
                                <th>Supplier ID</th>
                                <th>Supplier</th>
                                <th>Buyer / Requester</th>
                                <th class="desk-money">Order Subtotal</th>
                                <th class="desk-money">Discount</th>
                                <th class="desk-money">Freight</th>
                                <th class="desk-money">Order Total</th>
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
