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

    public ?int $selectedId = null;

    public ?int $createFromPo = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = InventoryReceiving::query()
            ->with(['supplier', 'purchaseOrder', 'site'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('receipt_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('purchaseOrder', fn ($p) => $p->where('po_number', 'like', $term))
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'processed', fn ($q) => $q->where('status', 'Processed'))
            ->orderByDesc('id');

        $listTitle = match ($this->favorite) {
            'new' => 'New Receivings',
            'processed' => 'Processed Receivings',
            default => 'Inventory Receivings',
        };

        return [
            'receivings' => $query->paginate(50),
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
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
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
        app(InventoryService::class)->processReceiving($receiving);
        session()->flash('status', 'Receiving '.$receiving->receipt_number.' processed.');
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <x-list-chrome label="Search Receivings:" model="search" placeholder="Receipt #, PO #, supplier…">
            <label class="desk-toolbar-label" for="createFromPo">From PO</label>
            <select id="createFromPo" wire:model="createFromPo" class="desk-select" style="min-width:14rem">
                <option value="">— Select open PO —</option>
                @foreach ($openPos as $po)
                    <option value="{{ $po->id }}">{{ $po->po_number }} — {{ $po->status }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="createReceiving" class="desk-btn desk-btn-primary">New Receiving</button>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($receivings->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Receipt Date</th>
                        <th>PO #</th>
                        <th class="text-center">Status</th>
                        <th>Supplier</th>
                        <th>Site</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receivings as $rec)
                        <tr
                            wire:click="selectRow({{ $rec->id }})"
                            @class(['is-selected' => $selectedId === $rec->id, 'cursor-pointer'])
                        >
                            <td class="desk-num">
                                <a href="{{ route('purchasing.receivings.edit', $rec) }}" wire:navigate wire:click.stop>{{ $rec->receipt_number }}</a>
                            </td>
                            <td>{{ optional($rec->receipt_date)?->format('n/j/Y') }}</td>
                            <td class="desk-num">{{ $rec->purchaseOrder?->po_number }}</td>
                            <td class="text-center">
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-new' => $rec->status === 'New',
                                    'desk-pill-invoiced' => $rec->status === 'Processed',
                                    'desk-pill-muted' => ! in_array($rec->status, ['New', 'Processed'], true),
                                ])>{{ $rec->status }}</span>
                            </td>
                            <td>{{ $rec->supplier?->name }}</td>
                            <td class="desk-num">{{ $rec->site?->code ?: '—' }}</td>
                            <td wire:click.stop>
                                <div class="flex gap-2">
                                    <a href="{{ route('purchasing.receivings.edit', $rec) }}" wire:navigate class="desk-btn desk-btn-sm">
                                        {{ $rec->status === 'Processed' ? 'View' : 'Edit' }}
                                    </a>
                                    @if ($rec->status !== 'Processed')
                                        <button
                                            type="button"
                                            wire:click="process({{ $rec->id }})"
                                            class="desk-btn desk-btn-sm desk-btn-primary"
                                            wire:confirm="Process this receiving and update stock?"
                                        >Process</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="7">No receivings found. Select an open PO above and click <strong>New Receiving</strong>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$receivings->total()">
            {{ $receivings->links() }}
        </x-record-count>
    </div>
</div>
