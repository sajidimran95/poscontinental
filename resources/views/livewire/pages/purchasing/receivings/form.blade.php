<?php

use App\Models\InventoryReceiving;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Receiving')] class extends Component
{
    public InventoryReceiving $receiving;

    public string $receipt_number = '';

    public string $receipt_date = '';

    public string $reference_no = '';

    public string $status = '';

    public string $received_by = '';

    public string $shipping_carrier = '';

    public string $comments = '';

    /** @var array<int, array{id:int,item_code:string,description:string,uom:string,qty_ordered:string,qty_received:string,unit_cost:string}> */
    public array $lines = [];

    public function mount(InventoryReceiving $receiving): void
    {
        abort_unless($receiving->company_id === auth()->user()->company_id, 403);
        $this->receiving = $receiving->load(['lines', 'purchaseOrder', 'supplier']);
        $this->receipt_number = $receiving->receipt_number;
        $this->receipt_date = optional($receiving->receipt_date)?->format('Y-m-d') ?? '';
        $this->reference_no = $receiving->reference_no ?? '';
        $this->status = $receiving->status;
        $this->received_by = $receiving->received_by ?? '';
        $this->shipping_carrier = $receiving->shipping_carrier ?? '';
        $this->comments = $receiving->comments ?? '';
        $this->lines = $receiving->lines->map(fn ($l) => [
            'id' => $l->id,
            'item_code' => $l->item_code ?? '',
            'description' => $l->description ?? '',
            'uom' => $l->uom ?? '',
            'qty_ordered' => (string) $l->qty_ordered,
            'qty_received' => (string) $l->qty_received,
            'unit_cost' => (string) $l->unit_cost,
        ])->all();
    }

    public function save(): void
    {
        if ($this->receiving->status === 'Processed') {
            return;
        }

        $this->receiving->update([
            'receipt_date' => $this->receipt_date ?: null,
            'reference_no' => $this->reference_no,
            'received_by' => $this->received_by,
            'shipping_carrier' => $this->shipping_carrier,
            'comments' => $this->comments,
        ]);

        foreach ($this->lines as $row) {
            $this->receiving->lines()->where('id', $row['id'])->update([
                'qty_received' => $row['qty_received'],
                'unit_cost' => $row['unit_cost'],
            ]);
        }

        session()->flash('status', 'Receiving saved.');
    }

    public function process(): void
    {
        $this->save();
        app(InventoryService::class)->processReceiving($this->receiving->fresh('lines'));
        $this->redirect(route('purchasing.receivings.index'), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="save" class="chief-panel bg-white flex flex-col min-h-[70vh]">
        <x-action-bar title="Inventory Receiving — {{ $receipt_number }}" />

        <div class="flex-1 p-3 space-y-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8">
                <div class="space-y-1">
                    <div class="chief-field"><label>Receipt No.</label><input value="{{ $receipt_number }}" class="chief-input w-44 font-mono bg-slate-50" readonly /></div>
                    <div class="chief-field"><label>Receipt Date</label><input type="date" wire:model="receipt_date" class="chief-input" @disabled($status === 'Processed') /></div>
                    <div class="chief-field"><label>Purchase Ord. #</label><span class="font-mono">{{ $receiving->purchaseOrder?->po_number }}</span></div>
                    <div class="chief-field"><label>Reference No.</label><input wire:model="reference_no" class="chief-input w-44" @disabled($status === 'Processed') /></div>
                    <div class="chief-field"><label>Status</label><span class="font-semibold">{{ $status }}</span></div>
                </div>
                <div class="space-y-1">
                    <div class="chief-field"><label>Supplier</label><span>{{ $receiving->supplier?->name }}</span></div>
                    <div class="chief-field"><label>Received By</label><input wire:model="received_by" class="chief-input w-48" @disabled($status === 'Processed') /></div>
                    <div class="chief-field"><label>Shipping Carrier</label><input wire:model="shipping_carrier" class="chief-input w-48" @disabled($status === 'Processed') /></div>
                    <div class="chief-field chief-field-top"><label>Comments</label><textarea wire:model="comments" rows="3" class="chief-input w-full max-w-md" @disabled($status === 'Processed')></textarea></div>
                </div>
            </div>

            <div class="chief-grid border border-slate-300 overflow-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>U of M</th>
                            <th class="text-right">Qty Ordered</th>
                            <th class="text-right">Qty Received</th>
                            <th class="text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                            <tr>
                                <td class="font-mono">{{ $line['item_code'] }}</td>
                                <td>{{ $line['description'] }}</td>
                                <td>{{ $line['uom'] }}</td>
                                <td class="text-right">{{ number_format((float) $line['qty_ordered'], 2) }}</td>
                                <td>
                                    <input wire:model="lines.{{ $i }}.qty_received" class="chief-input w-24 text-right" @disabled($status === 'Processed') />
                                </td>
                                <td>
                                    <input wire:model="lines.{{ $i }}.unit_cost" class="chief-input w-24 text-right" @disabled($status === 'Processed') />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-3 py-2 border-t border-slate-300 bg-slate-100">
            <a href="{{ route('purchasing.receivings.index') }}" wire:navigate class="chief-btn">Cancel</a>
            @if ($status !== 'Processed')
                <button type="submit" class="chief-btn">Save</button>
                <button type="button" wire:click="process" wire:confirm="Process receiving and update inventory?" class="chief-btn-primary">Process Receiving</button>
            @endif
        </div>
    </form>
</div>
