<?php

use App\Livewire\Concerns\BrowsesItemsForInquiry;
use App\Models\Item;
use App\Models\Site;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stock Status')] class extends Component
{
    use BrowsesItemsForInquiry;

    #[Url]
    public string $itemCode = '';

    public ?int $itemId = null;

    public string $lookupError = '';

    public function mount(): void
    {
        if (trim($this->itemCode) !== '') {
            $this->lookupItem(playSound: false);
        }
    }

    public function lookupItem(?string $code = null, bool $playSound = true): void
    {
        $this->lookupError = '';
        if ($code !== null) {
            $this->itemCode = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '');
        }
        $resolved = trim($this->itemCode);

        if ($resolved === '') {
            $this->itemId = null;
            $this->openItemBrowse();

            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');

        $this->itemId = $item?->id;

        if ($item) {
            $this->itemCode = $item->item_code;
            $this->closeBrowse();
            if ($playSound) {
                $this->playPosSound('success');
            }
        } else {
            if ($playSound) {
                $this->playPosSound('error');
            }
            $this->openItemBrowse();
        }
    }

    public function focusItemScan(): void
    {
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('ss-code');
                if (!el) return;
                el.focus();
                const v = (el.value || '').trim();
                if (v !== '') {
                    $wire.lookupItem(v);
                }
            });
        JS);
    }

    public function rendered(): void
    {
        if ($this->showBrowse) {
            return;
        }

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('ss-code');
                if (!el) return;
                const a = document.activeElement;
                if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT') && a !== el) return;
                el.focus();
            });
        JS);
    }

    public function clearLookup(): void
    {
        $this->reset(['itemCode', 'itemId', 'lookupError']);
        $this->closeBrowse();
    }

    public function pickBrowseItem(int $itemId): void
    {
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);

        if (! $item) {
            $this->playPosSound('error');

            return;
        }

        $this->itemId = $item->id;
        $this->itemCode = $item->item_code;
        $this->lookupError = '';
        $this->closeBrowse();
        $this->playPosSound('success');
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

        return array_merge($this->inquiryBrowseViewData(), [
            'item' => $item,
            'sites' => Site::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function editLookedUpItem(): mixed
    {
        if (! $this->itemId) {
            session()->flash('status', 'Look up an item first.');

            return null;
        }

        return $this->redirect(route('inventory.items.edit', $this->itemId), navigate: true);
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page">
    <div class="desk-main">
        <x-action-bar title="Stock Status">
            <x-slot:menu>
                <x-action-item label="View/Edit Item" kbd="Ctrl+E" wire:click="editLookedUpItem" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-toolbar rpt-toolbar">
            <div class="rpt-field rpt-field-search">
                <label class="desk-toolbar-label" for="ss-code">Item Code / UPC</label>
                <div class="so-scan-bar" style="max-width:28rem;min-width:16rem;height:2.15rem">
                    <button type="button" wire:click="focusItemScan" class="so-scan-btn" title="Scan barcode (F2)">
                        <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                            <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                        </svg>
                        <span>Scan</span>
                    </button>
                    <input
                        id="ss-code"
                        data-pos-item-entry
                        type="search"
                        wire:model="itemCode"
                        wire:keydown.enter.prevent="lookupItem($event.target.value)"
                        wire:keydown.f3.prevent="openItemBrowse"
                        class="so-input font-mono"
                        placeholder="Scan or type item code / UPC — Enter"
                        autofocus
                        autocomplete="off"
                    />
                    <button
                        type="button"
                        wire:click="openItemBrowse"
                        class="so-icon-btn"
                        title="Browse items (F3)"
                        aria-label="Browse items"
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
                <button type="button" wire:click="openItemBrowse" class="so-browse-btn" data-pos-browse title="Item list (F3)">Browse (F3)</button>
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
                        Enter an item code / UPC, press Lookup, or Browse (F3)
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
                <span>Ready for inquiry · Browse (F3) to pick an existing code / UPC</span>
            @endif
        </div>
    </div>

    @include('livewire.pages.sales.orders.partials.item-browse-panel')
</div>

@script
<script>
    $wire.on('open-item-record', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });
    $wire.on('pos-alert', (e) => {
        const kind = (e && e.kind) || (Array.isArray(e) && e[0] && e[0].kind) || 'error';
        window.playPosAlert && window.playPosAlert(kind);
    });
    $wire.$watch('showBrowse', (open) => {
        if (!open) return;
        requestAnimationFrame(() => {
            const el = document.getElementById('so-browse-search');
            if (el) {
                el.focus();
                el.select?.();
            }
        });
    });
</script>
@endscript
