<?php

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrderLine;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Item Velocity')] class extends Component
{
    #[Url]
    public string $itemCode = '';

    public ?int $itemId = null;

    #[Url]
    public ?int $customerId = null;

    public string $datePreset = '30';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $lookupError = '';

    public bool $showItemBrowse = false;

    public string $itemBrowseSearch = '';

    public function mount(): void
    {
        $this->applyPreset();
        if (trim($this->itemCode) !== '') {
            $this->lookupItem();
        }
    }

    public function updatedDatePreset(): void
    {
        $this->applyPreset();
    }

    protected function applyPreset(): void
    {
        $this->dateTo = now()->toDateString();
        $this->dateFrom = match ($this->datePreset) {
            '7' => now()->subDays(7)->toDateString(),
            '90' => now()->subDays(90)->toDateString(),
            '365' => now()->subYear()->toDateString(),
            'ytd' => now()->startOfYear()->toDateString(),
            default => now()->subDays(30)->toDateString(),
        };
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
        $this->reset(['itemCode', 'itemId', 'customerId', 'lookupError', 'showItemBrowse', 'itemBrowseSearch']);
        $this->datePreset = '30';
        $this->applyPreset();
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
        $rows = collect();

        if ($this->itemId) {
            $rows = SalesOrderLine::query()
                ->select('sales_order_lines.*')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_orders.company_id', $companyId)
                ->where('sales_order_lines.item_id', $this->itemId)
                ->when($this->customerId, fn ($q) => $q->where('sales_orders.customer_id', $this->customerId))
                ->when($this->dateFrom, fn ($q) => $q->whereDate('sales_orders.order_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($q) => $q->whereDate('sales_orders.order_date', '<=', $this->dateTo))
                ->with(['salesOrder.customer'])
                ->orderByDesc('sales_orders.order_date')
                ->limit(500)
                ->get();
        }

        $totalQty = $rows->sum(fn ($r) => (float) $r->qty_ordered);
        $totalSales = $rows->sum(fn ($r) => (float) $r->line_total);
        $orderCount = $rows->pluck('sales_order_id')->unique()->count();
        $days = max(1, (int) \Illuminate\Support\Carbon::parse($this->dateFrom)->diffInDays(\Illuminate\Support\Carbon::parse($this->dateTo)) + 1);

        return [
            'item' => $this->itemId
                ? Item::query()
                    ->with(['department', 'category'])
                    ->where('company_id', $companyId)
                    ->find($this->itemId)
                : null,
            'customers' => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->get(['id', 'customer_id', 'company_name']),
            'rows' => $rows,
            'totalQty' => $totalQty,
            'totalSales' => $totalSales,
            'orderCount' => $orderCount,
            'avgDailyQty' => $totalQty / $days,
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
        <x-action-bar title="Item Velocity" />

        <div class="desk-toolbar rpt-toolbar">
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="iv-code">Item Code / UPC</label>
                <div class="inq-lookup-row">
                    <input
                        id="iv-code"
                        type="search"
                        wire:model="itemCode"
                        wire:keydown.enter.prevent="lookupItem"
                        class="desk-search font-mono"
                        placeholder="Item code / UPC…"
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
            <div class="rpt-actions" style="margin-left:0">
                <button type="button" wire:click="lookupItem" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="lookupItem">Lookup</span>
                    <span wire:loading wire:target="lookupItem">Looking up…</span>
                </button>
            </div>
            <div class="rpt-field rpt-field-search">
                <label class="desk-toolbar-label" for="iv-cust">Customer</label>
                <select id="iv-cust" wire:model.live="customerId" class="desk-select">
                    <option value="">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="iv-preset">Preset</label>
                <select id="iv-preset" wire:model.live="datePreset" class="desk-select">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last year</option>
                    <option value="ytd">Year to date</option>
                </select>
            </div>
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="iv-from">From</label>
                <input id="iv-from" type="date" wire:model.live="dateFrom" class="desk-select" />
            </div>
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="iv-to">To</label>
                <input id="iv-to" type="date" wire:model.live="dateTo" class="desk-select" />
            </div>
            <div class="rpt-actions">
                <button type="button" wire:click="clearLookup" class="desk-btn">Clear</button>
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
                        Item Velocity
                    @endif
                </h2>
                <span class="desk-title-meta">
                    @if ($item)
                        {{ $item->description }}
                        · {{ $dateFrom }} → {{ $dateTo }}
                    @else
                        Lookup an item to view sales velocity · click ⋯
                    @endif
                </span>
            </div>
            @if ($item)
                <div class="rpt-stats">
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Orders</span>
                        <span class="rpt-stat-val">{{ number_format($orderCount) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Qty Sold</span>
                        <span class="rpt-stat-val">{{ number_format($totalQty, 2) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Avg / Day</span>
                        <span class="rpt-stat-val">{{ number_format($avgDailyQty, 2) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Sales $</span>
                        <span class="rpt-stat-val">${{ number_format($totalSales, 2) }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Order Date</th>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th class="text-right">Qty Ordered</th>
                        <th class="text-right">Qty Shipped</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ optional($row->salesOrder?->order_date)?->format('n/j/Y') }}</td>
                            <td class="desk-num">
                                @if ($row->salesOrder && Route::has('sales.orders.edit'))
                                    <a href="{{ route('sales.orders.edit', $row->salesOrder) }}" wire:navigate>{{ $row->salesOrder->order_number }}</a>
                                @else
                                    {{ $row->salesOrder?->order_number }}
                                @endif
                            </td>
                            <td>{{ $row->salesOrder?->customer?->company_name ?: '—' }}</td>
                            <td class="desk-money">{{ number_format((float) $row->qty_ordered, 2) }}</td>
                            <td class="desk-money">{{ number_format((float) $row->qty_shipped, 2) }}</td>
                            <td class="desk-money">${{ number_format((float) $row->price, 2) }}</td>
                            <td class="desk-money">${{ number_format((float) $row->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="7">
                                {{ $item ? 'No sales lines in this date range.' : 'Lookup an item to view velocity.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="desk-footer">
            <span>
                @if ($item)
                    {{ number_format($rows->count()) }} line(s) · Qty {{ number_format($totalQty, 2) }} · Sales ${{ number_format($totalSales, 2) }}
                @else
                    Ready for inquiry · click ⋯ to pick an existing code / UPC
                @endif
            </span>
            @if ($item && Route::has('inventory.items.edit'))
                <div class="desk-footer-actions">
                    <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="desk-btn desk-btn-sm">Open Item</a>
                </div>
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
                        <label class="desk-toolbar-label" for="iv-item-browse">Search</label>
                        <input
                            id="iv-item-browse"
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
                    <p class="item-hint" style="padding:0.65rem 0 0">Click a row or <strong>Select</strong> to load that item’s velocity.</p>
                </div>
            </div>
        </div>
    @endif
</div>
