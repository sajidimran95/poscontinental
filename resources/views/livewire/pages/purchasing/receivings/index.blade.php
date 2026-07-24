<?php

use App\Models\InventoryReceiving;
use App\Models\PurchaseOrder;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Inventory Receivings')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public ?int $createFromPo = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $hasSearch = $this->search !== '';

        $query = InventoryReceiving::query()
            ->with(['supplier', 'purchaseOrder', 'site', 'buyer'])
            ->where('company_id', $companyId)
            ->when($hasSearch, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('receipt_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhere('received_by', 'like', $term)
                        ->orWhere('shipping_carrier', 'like', $term)
                        ->orWhere('comments', 'like', $term)
                        ->orWhereHas('purchaseOrder', fn ($p) => $p->where('po_number', 'like', $term))
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)->orWhere('supplier_id', 'like', $term))
                        ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', $term))
                        ->orWhereHas('site', fn ($s) => $s->where('code', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'processed', fn ($q) => $q->where('status', 'Processed'))
            ->when($this->statusFilter === 'New', fn ($q) => $q->where('status', 'New'))
            ->when($this->statusFilter === 'Processed', fn ($q) => $q->where('status', 'Processed'))
            ->orderByDesc('id');

        if (! $hasSearch && $this->favorite === 'all' && $this->statusFilter === '') {
            $receivings = $query->limit(10)->get();
            $total = $receivings->count();
            $footerNote = '10 most recently updated records with no search criteria.';
            $isPaginated = false;
        } else {
            $receivings = $query->paginate(50);
            $total = $receivings->total();
            $footerNote = null;
            $isPaginated = true;
        }

        $listTitle = match (true) {
            $this->statusFilter === 'New', $this->favorite === 'new' => 'Inventory Receipts List (New)',
            $this->statusFilter === 'Processed', $this->favorite === 'processed' => 'Inventory Receipts List (Processed)',
            default => 'Inventory Receipts List',
        };

        return [
            'receivings' => $receivings,
            'total' => $total,
            'footerNote' => $footerNote,
            'isPaginated' => $isPaginated,
            'openPos' => PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['New', 'Partially Received'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'favorites' => [
                'all' => 'All Receivings',
                'new' => 'New',
                'processed' => 'Processed',
            ],
            'listTitle' => $listTitle,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->statusFilter = match ($this->favorite) {
            'new' => 'New',
            'processed' => 'Processed',
            default => $this->statusFilter,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->favorite = match ($this->statusFilter) {
            'New' => 'new',
            'Processed' => 'processed',
            default => 'all',
        };
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

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a receiving first.');

            return null;
        }

        $rec = InventoryReceiving::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $rec) {
            session()->flash('status', 'Receiving not found.');

            return null;
        }

        return $this->redirect(route('purchasing.receivings.edit', $rec), navigate: true);
    }

    public function viewSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a receiving first.');

            return null;
        }

        return $this->openReceiving($this->selectedId);
    }

    public function openReceiving(int $id): mixed
    {
        $rec = InventoryReceiving::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $rec) {
            session()->flash('status', 'Receiving not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('purchasing.receivings.show', $rec), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a receiving first.');

            return;
        }

        $rec = InventoryReceiving::query()
            ->with('lines')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $rec) {
            session()->flash('status', 'Receiving not found.');

            return;
        }

        try {
            if ($rec->status === 'Processed') {
                app(InventoryService::class)->reverseReceiving($rec);
                $rec->refresh();
            }

            $rec->lines()->delete();
            $rec->delete();
            $this->selectedId = null;
            session()->flash('status', 'Receiving deleted. Stock reversed if it was processed.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('status', collect($e->errors())->flatten()->first() ?: 'Receiving could not be deleted.');
        }
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a receiving first.');

            return;
        }

        $rec = InventoryReceiving::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $rec) {
            session()->flash('status', 'Receiving not found.');

            return;
        }

        $this->dispatch('open-receiving-pdf', url: route('purchasing.receivings.print', $rec));
    }

    public function createReceiving(): void
    {
        $this->validate(['createFromPo' => 'required|integer|exists:purchase_orders,id']);

        $po = PurchaseOrder::query()->with('lines')->findOrFail($this->createFromPo);
        abort_unless($po->company_id === auth()->user()->company_id, 403);

        $receiving = InventoryReceiving::query()->create([
            'company_id' => $po->company_id,
            'receipt_number' => InventoryReceiving::nextNumber($po->company_id),
            'receipt_date' => now()->toDateString(),
            'purchase_order_id' => $po->id,
            'status' => 'New',
            'supplier_id' => $po->supplier_id,
            'buyer_id' => $po->buyer_id,
            'site_id' => $po->ship_to_site_id,
            'received_by' => auth()->user()->name,
        ]);

        foreach ($po->lines as $i => $line) {
            $remaining = max(0, (float) $line->qty_ordered - (float) $line->qty_received);
            if ($remaining <= 0) {
                continue;
            }
            $receiving->lines()->create([
                'purchase_order_line_id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'description' => $line->description,
                'uom' => $line->uom,
                'qty_ordered' => $line->qty_ordered,
                'qty_received' => $remaining,
                'unit_cost' => $line->unit_cost,
                'line_no' => $i + 1,
            ]);
        }

        $this->redirect(route('purchasing.receivings.edit', $receiving), navigate: true);
    }

    public function process(int $id): void
    {
        $receiving = InventoryReceiving::query()->findOrFail($id);
        abort_unless($receiving->company_id === auth()->user()->company_id, 403);
        if (! filled($receiving->received_by)) {
            $receiving->update(['received_by' => auth()->user()->name]);
        }
        app(InventoryService::class)->processReceiving($receiving);
        $this->selectedId = $id;
        session()->flash('status', 'Receiving '.$receiving->receipt_number.' processed.');
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
                    <label class="desk-toolbar-label" for="rcv-search">Search Inventory Receipts:</label>
                    <input
                        id="rcv-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Receipt #, PO #, supplier…"
                        class="desk-search orders-search-input"
                        aria-label="Search Inventory Receipts"
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
                            id="rcv-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Status filter"
                        >
                            <option value="">All</option>
                            <option value="New">New</option>
                            <option value="Processed">Processed</option>
                        </select>
                        <button type="button" class="desk-btn" title="Saved Search" disabled>Saved Search</button>
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

                <div class="desk-toolbar" style="border-top:0;padding-top:0">
                    <label class="desk-toolbar-label" for="createFromPo">From PO</label>
                    <select id="createFromPo" wire:model="createFromPo" class="desk-select" style="min-width:14rem">
                        <option value="">— Select open PO —</option>
                        @foreach ($openPos as $po)
                            <option value="{{ $po->id }}">{{ $po->po_number }} — {{ $po->status }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="createReceiving" class="desk-btn desk-btn-primary">New Receiving</button>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($total) }} records</span>
                </div>

                <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <th>Receipt No.</th>
                                <th>Receipt Date</th>
                                <th>Purchase Ord. #</th>
                                <th>Reference No.</th>
                                <th class="text-center">Status</th>
                                <th>Requisition Date</th>
                                <th>Required Date</th>
                                <th>Supplier</th>
                                <th>Buyer / Requester</th>
                                <th>Site</th>
                                <th>Received By</th>
                                <th>Shipping Carrier</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receivings as $rec)
                                <tr
                                    wire:click="selectRow({{ $rec->id }})"
                                    wire:dblclick="openReceiving({{ $rec->id }})"
                                    @class(['is-selected' => $selectedId === $rec->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="rcv_select"
                                            value="{{ $rec->id }}"
                                            @checked($selectedId === $rec->id)
                                            wire:click="selectRow({{ $rec->id }})"
                                            aria-label="Select receipt {{ $rec->receipt_number }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('purchasing.receivings.show', $rec) }}" wire:navigate wire:click.stop>{{ $rec->receipt_number }}</a>
                                    </td>
                                    <td>{{ optional($rec->receipt_date)?->format('n/j/Y') }}</td>
                                    <td class="desk-num">{{ $rec->purchaseOrder?->po_number ?: '—' }}</td>
                                    <td>{{ $rec->reference_no ?: '' }}</td>
                                    <td class="text-center">
                                        <span @class([
                                            'desk-pill',
                                            'desk-pill-new' => $rec->status === 'New',
                                            'desk-pill-invoiced' => $rec->status === 'Processed',
                                            'desk-pill-muted' => ! in_array($rec->status, ['New', 'Processed'], true),
                                        ])>{{ $rec->status }}</span>
                                    </td>
                                    <td>{{ optional($rec->purchaseOrder?->requisition_date)?->format('n/j/Y') ?: '—' }}</td>
                                    <td>{{ optional($rec->purchaseOrder?->required_date)?->format('n/j/Y') ?: '—' }}</td>
                                    <td>{{ $rec->supplier?->name ?: '—' }}</td>
                                    <td>{{ $rec->buyer?->name ?: '—' }}</td>
                                    <td class="desk-num">{{ $rec->site?->code ?: '—' }}</td>
                                    <td>{{ $rec->received_by ?: '' }}</td>
                                    <td>{{ $rec->shipping_carrier ?: '' }}</td>
                                    <td title="{{ $rec->comments }}">{{ $rec->comments ? \Illuminate\Support\Str::limit($rec->comments, 28) : '' }}</td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="14">No receivings found. Select an open PO above and click <strong>New Receiving</strong>.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$total">
                    @if ($footerNote)
                        <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                    @endif
                    @if ($isPaginated)
                        {{ $receivings->links() }}
                    @endif
                </x-record-count>
            </div>

            <aside class="desk-rail" aria-label="Receiving actions">
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
                    wire:confirm="Delete the selected receiving? If processed, received qty will be removed from stock."
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
            </aside>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('open-receiving-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank', 'noopener');
    });
</script>
@endscript
