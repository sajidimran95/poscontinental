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

    public string $lookupError = '';

    public bool $showItemBrowse = false;

    public string $itemBrowseSearch = '';

    public function mount(): void
    {
        if (trim($this->itemCode) !== '') {
            $this->lookupItem();
        }
    }

    public function lookupItem(): void
    {
        $this->lookupError = '';
        $code = trim($this->itemCode);

        if ($code === '') {
            $this->itemId = null;
            $this->lookupError = 'Enter an item code or UPC.';

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)
                    ->orWhere('primary_upc', $code)
                    ->orWhereHas('upcs', fn ($upc) => $upc->where('upc', $code));
            })
            ->first();

        $this->itemId = $item?->id;

        if ($item) {
            $this->itemCode = $item->item_code;
        } else {
            $this->lookupError = 'No item found for “'.$code.'”.';
        }
    }

    public function clearLookup(): void
    {
        $this->reset(['itemCode', 'itemId', 'lookupError']);
    }

    public function openItemBrowse(): void
    {
        $this->itemBrowseSearch = trim($this->itemCode);
        $this->lookupError = '';
        $this->showItemBrowse = true;
    }

    public function closeItemBrowse(): void
    {
        $this->showItemBrowse = false;
        $this->itemBrowseSearch = '';
    }

    public function pickBrowseItem(int $itemId): void
    {
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);

        if (! $item) {
            return;
        }

        $this->itemId = $item->id;
        $this->itemCode = $item->item_code;
        $this->lookupError = '';
        $this->closeItemBrowse();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $item = $this->itemId
            ? Item::query()
                ->with(['department', 'category'])
                ->where('company_id', $companyId)
                ->find($this->itemId)
            : null;

        return [
            'item' => $item,
            'sites' => Site::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'browseItems' => $this->showItemBrowse
                ? Item::query()
                    ->where('company_id', $companyId)
                    ->where('is_inactive', false)
                    ->when($this->itemBrowseSearch !== '', function ($q) {
                        $term = '%'.$this->itemBrowseSearch.'%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('item_code', 'like', $term)
                                ->orWhere('description', 'like', $term)
                                ->orWhere('primary_upc', 'like', $term)
                                ->orWhereHas('upcs', fn ($upc) => $upc->where('upc', 'like', $term));
                        });
                    })
                    ->orderBy('item_code')
                    ->limit(150)
                    ->get(['id', 'item_code', 'description', 'primary_upc', 'unit_of_measure', 'quantity_in_stock', 'allocated_qty'])
                : collect(),
        ];
    }
}; ?>

<div class="desk-page">
    <div class="desk-main">
        <x-action-bar title="Stock Status" />

        <div class="desk-toolbar rpt-toolbar">
            <div class="rpt-field rpt-field-search">
                <label class="desk-toolbar-label" for="ss-code">Item Code / UPC</label>
                <div class="inq-lookup-row">
                    <input
                        id="ss-code"
                        type="search"
                        wire:model="itemCode"
                        wire:keydown.enter.prevent="lookupItem"
                        class="desk-search font-mono"
                        placeholder="Scan or type item code / UPC…"
                        autofocus
                    />
                    <button
                        type="button"
                        wire:click="openItemBrowse"
                        class="so-icon-btn"
                        title="Show existing codes / UPCs"
                        aria-label="Show existing item codes and UPCs"
                    >
                        <svg viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                            <circle cx="3" cy="6" r="1.15"/>
                            <circle cx="6" cy="6" r="1.15"/>
                            <circle cx="9" cy="6" r="1.15"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="rpt-actions">
                <button type="button" wire:click="clearLookup" class="desk-btn">Clear</button>
                <button type="button" wire:click="lookupItem" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="lookupItem">Lookup</span>
                    <span wire:loading wire:target="lookupItem">Looking up…</span>
                </button>
            </div>
        </div>

        @if ($lookupError !== '')
            <div class="desk-flash bp-flash-error">{{ $lookupError }}</div>
        @endif

        <div class="desk-titlebar">
            <div>
                <h2 class="desk-title">
                    @if ($item)
                        {{ $item->item_code }}
                    @else
                        Stock Status Inquiry
                    @endif
                </h2>
                <span class="desk-title-meta">
                    @if ($item)
                        {{ $item->description }}
                        @if ($item->department?->name)
                            · {{ $item->department->name }}
                        @endif
                        @if ($item->category?->name)
                            · {{ $item->category->name }}
                        @endif
                    @else
                        Enter an item code / UPC, press Lookup, or click ⋯
                    @endif
                </span>
            </div>
            @if ($item)
                <div class="rpt-stats">
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Status</span>
                        <span class="rpt-stat-val" style="font-size:12px">
                            @if ($item->is_inactive)
                                Inactive
                            @else
                                Active
                            @endif
                        </span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Available</span>
                        <span class="rpt-stat-val">{{ number_format((float) $item->available_quantity, 2) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">In Stock</span>
                        <span class="rpt-stat-val">{{ number_format((float) $item->quantity_in_stock, 2) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">List Price</span>
                        <span class="rpt-stat-val">${{ number_format((float) $item->list_price, 2) }}</span>
                    </div>
                </div>
            @endif
        </div>

        @if ($item)
            <div class="inq-detail-strip">
                <div class="inq-detail">
                    <span class="inq-detail-lbl">UPC</span>
                    <span class="inq-detail-val desk-num">{{ $item->primary_upc ?: '—' }}</span>
                </div>
                <div class="inq-detail">
                    <span class="inq-detail-lbl">UOM</span>
                    <span class="inq-detail-val">{{ $item->unit_of_measure ?: '—' }}</span>
                </div>
                <div class="inq-detail">
                    <span class="inq-detail-lbl">Reorder Point</span>
                    <span class="inq-detail-val">{{ number_format((float) $item->reorder_point, 2) }}</span>
                </div>
                <div class="inq-detail">
                    <span class="inq-detail-lbl">Restock Level</span>
                    <span class="inq-detail-val">{{ number_format((float) $item->restock_level, 2) }}</span>
                </div>
                <div class="inq-detail">
                    <span class="inq-detail-lbl">Std Cost</span>
                    <span class="inq-detail-val">${{ number_format((float) $item->standard_cost, 2) }}</span>
                </div>
                <div class="inq-detail">
                    <span class="inq-detail-lbl">Current Cost</span>
                    <span class="inq-detail-val">${{ number_format((float) $item->current_cost, 2) }}</span>
                </div>
            </div>
        @endif

        <div class="desk-grid">
            <table class="desk-table">
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
                    @if ($item)
                        @forelse ($sites as $site)
                            @php
                                $available = (float) $item->available_quantity;
                                $low = (float) $item->reorder_point > 0 && $available <= (float) $item->reorder_point;
                            @endphp
                            <tr>
                                <td>
                                    <span class="desk-num">{{ $site->code }}</span>
                                    <span class="inq-site-name">{{ $site->name }}</span>
                                </td>
                                <td class="desk-money">{{ number_format((float) $item->quantity_in_stock, 2) }}</td>
                                <td class="desk-money">{{ number_format((float) $item->allocated_qty, 2) }}</td>
                                <td class="desk-money">{{ number_format((float) $item->on_order_qty, 2) }}</td>
                                <td class="desk-money">{{ number_format((float) $item->back_order_qty, 2) }}</td>
                                <td class="desk-money">
                                    <strong @class(['inq-low' => $low])>{{ number_format($available, 2) }}</strong>
                                    @if ($low)
                                        <span class="desk-pill desk-pill-muted">Low</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="is-empty">
                                <td colspan="6">No active sites configured.</td>
                            </tr>
                        @endforelse
                    @else
                        <tr class="is-empty">
                            <td colspan="6">Lookup an item to view stock status by site.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="desk-footer">
            @if ($item)
                <span>Quantities are company-level on the item; listed per active site for reference.</span>
                @if (Route::has('inventory.items.edit'))
                    <div class="desk-footer-actions">
                        <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="desk-btn desk-btn-sm">Open Item</a>
                    </div>
                @endif
            @else
                <span>Ready for inquiry · click ⋯ to pick an existing code / UPC</span>
            @endif
        </div>
    </div>

    @if ($showItemBrowse)
        <div class="desk-modal-backdrop" wire:click.self="closeItemBrowse" role="dialog" aria-modal="true" aria-label="Browse items">
            <div class="desk-modal" style="max-width:48rem">
                <div class="desk-modal-head">
                    <span>Existing Item Codes / UPCs</span>
                    <button type="button" wire:click="closeItemBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body">
                    <div class="desk-toolbar" style="padding:0 0 0.75rem;border:0;background:transparent">
                        <label class="desk-toolbar-label" for="ss-item-browse">Search</label>
                        <input
                            id="ss-item-browse"
                            type="search"
                            wire:model.live.debounce.250ms="itemBrowseSearch"
                            class="desk-search"
                            placeholder="Filter by item code, description, UPC…"
                            autofocus
                        />
                    </div>
                    <div class="desk-grid" style="max-height:22rem;border:1px solid #e2e8f0;border-radius:8px">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>UPC</th>
                                    <th>Description</th>
                                    <th class="text-center">UOM</th>
                                    <th class="text-right">Available</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($browseItems as $bi)
                                    @php $avail = (float) $bi->quantity_in_stock - (float) $bi->allocated_qty; @endphp
                                    <tr class="cursor-pointer" wire:click="pickBrowseItem({{ $bi->id }})">
                                        <td class="desk-num">{{ $bi->item_code }}</td>
                                        <td class="desk-num">{{ $bi->primary_upc ?: '—' }}</td>
                                        <td>{{ $bi->description }}</td>
                                        <td class="text-center">{{ $bi->unit_of_measure ?: '—' }}</td>
                                        <td class="desk-money">{{ number_format($avail, 2) }}</td>
                                        <td>
                                            <button type="button" wire:click.stop="pickBrowseItem({{ $bi->id }})" class="desk-btn desk-btn-sm desk-btn-primary">Select</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="6">No matching items. Create items under Inventory → Items first.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint" style="padding:0.65rem 0 0">Click a row or <strong>Select</strong> to load that item’s stock status.</p>
                </div>
            </div>
        </div>
    @endif
</div>
