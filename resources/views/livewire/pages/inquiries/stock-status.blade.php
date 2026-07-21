<?php

use App\Models\Item;
use App\Models\Site;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stock Status')] class extends Component
{
    #[Url]
    public string $itemCode = '';

    public ?int $itemId = null;

    public function lookupItem(): void
    {
        $code = trim($this->itemCode);
        if ($code === '') {
            $this->itemId = null;

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)->orWhere('primary_upc', $code);
            })
            ->first();

        $this->itemId = $item?->id;
        if ($item) {
            $this->itemCode = $item->item_code;
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $item = $this->itemId
            ? Item::query()->where('company_id', $companyId)->find($this->itemId)
            : null;

        return [
            'item' => $item,
            'sites' => Site::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
        ];
    }
}; ?>

<div class="flex gap-2 h-full">
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Stock Status" />
        <div class="flex flex-wrap items-end gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <div>
                <label class="block text-xs text-slate-600">Item Code / UPC</label>
                <input wire:model="itemCode" wire:keydown.enter.prevent="lookupItem" class="chief-input w-48 font-mono" />
            </div>
            <button type="button" wire:click="lookupItem" class="chief-btn-primary">Lookup</button>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">
            @if ($item)
                {{ $item->item_code }} — {{ $item->description }}
            @else
                Stock Status Inquiry
            @endif
        </div>

        <div class="flex-1 overflow-auto p-3">
            @if ($item)
                <div class="chief-grid border border-slate-300 overflow-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Site</th>
                                <th class="text-right">In Stock</th>
                                <th class="text-right">Allocated</th>
                                <th class="text-right">On Order</th>
                                <th class="text-right">Back Order</th>
                                <th class="text-right">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sites as $site)
                                <tr>
                                    <td class="font-mono">{{ $site->code }} — {{ $site->name }}</td>
                                    <td class="text-right">{{ number_format((float) $item->quantity_in_stock, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $item->allocated_qty, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $item->on_order_qty, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $item->back_order_qty, 2) }}</td>
                                    <td class="text-right font-semibold">{{ number_format($item->available_quantity, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-slate-500">No sites configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500 mt-2">Quantities are company-level on the item; shown per active site for reference.</p>
            @else
                <p class="text-sm text-slate-600">Enter an item code or UPC and click Lookup.</p>
            @endif
        </div>
    </div>
</div>
