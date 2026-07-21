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

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public ?int $createFromPo = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = InventoryReceiving::query()
            ->with(['supplier', 'purchaseOrder'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('receipt_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term);
                });
            })
            ->orderByDesc('id');

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
            ],
        ];
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
        session()->flash('status', 'Receiving processed.');
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
        <x-list-chrome label="Search Receivings:" model="search" />

        <div class="px-2 py-2 border-b border-slate-300 bg-slate-50 flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs">Create from Purchase Order</label>
                <select wire:model="createFromPo" class="chief-input w-72">
                    <option value="">— Select open PO —</option>
                    @foreach ($openPos as $po)
                        <option value="{{ $po->id }}">{{ $po->po_number }} — {{ $po->status }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="createReceiving" class="chief-btn-primary">New Receiving</button>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Inventory Receivings</div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Receipt Date</th>
                        <th>PO #</th>
                        <th>Status</th>
                        <th>Supplier</th>
                        <th>Site</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receivings as $rec)
                        <tr wire:click="selectRow({{ $rec->id }})" @class(['chief-selected-row' => $selectedId === $rec->id, 'cursor-pointer'])>
                            <td class="font-mono">
                                <a href="{{ route('purchasing.receivings.edit', $rec) }}" wire:navigate class="hover:underline">{{ $rec->receipt_number }}</a>
                            </td>
                            <td>{{ optional($rec->receipt_date)?->format('n/j/Y') }}</td>
                            <td class="font-mono">{{ $rec->purchaseOrder?->po_number }}</td>
                            <td>{{ $rec->status }}</td>
                            <td>{{ $rec->supplier?->name }}</td>
                            <td>{{ $rec->site_id }}</td>
                            <td>
                                @if ($rec->status !== 'Processed')
                                    <button type="button" wire:click="process({{ $rec->id }})" class="chief-btn text-xs" wire:confirm="Process this receiving and update stock?">Process</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-6 text-slate-500">No receivings found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$receivings->total()">
            {{ $receivings->links() }}
        </x-record-count>
    </div>
</div>
