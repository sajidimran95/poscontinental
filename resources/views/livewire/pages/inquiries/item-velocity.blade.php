<?php

use App\Livewire\Concerns\BrowsesItemsForInquiry;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrderLine;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Item Velocity')] class extends Component
{
    use BrowsesItemsForInquiry;

    #[Url]
    public string $itemCode = '';

    public ?int $itemId = null;

    #[Url]
    public ?int $customerId = null;

    public string $datePreset = '30';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $lookupError = '';

    public function mount(): void
    {
        $this->applyPreset();
        if (trim($this->itemCode) !== '') {
            $this->lookupItem(playSound: false);
        }
    }

    public function updatedDatePreset(): void
    {
        $this->applyPreset();
    }

    protected function applyPreset(): void
    {
        if ($this->datePreset === 'all') {
            $this->dateFrom = '';
            $this->dateTo = '';

            return;
        }

        $this->dateTo = now()->toDateString();
        $this->dateFrom = match ($this->datePreset) {
            '7' => now()->subDays(7)->toDateString(),
            '90' => now()->subDays(90)->toDateString(),
            '365' => now()->subYear()->toDateString(),
            'ytd' => now()->startOfYear()->toDateString(),
            default => now()->subDays(30)->toDateString(),
        };
    }

    public function showAllDates(): void
    {
        $this->datePreset = 'all';
        $this->applyPreset();
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
                const el = document.getElementById('iv-code');
                if (!el) return;
                el.focus();
                const v = (el.value || '').trim();
                if (v !== '') {
                    $wire.lookupItem(v);
                }
            });
        JS);
    }

    public function clearLookup(): void
    {
        $this->reset(['itemCode', 'itemId', 'customerId', 'lookupError']);
        $this->closeBrowse();
        $this->datePreset = '30';
        $this->applyPreset();
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
        $rows = collect();
        $outsideRangeCount = 0;
        $itemCode = null;

        if ($this->itemId) {
            $item = Item::query()->where('company_id', $companyId)->find($this->itemId);
            $itemCode = $item?->item_code;

            $base = SalesOrderLine::query()
                ->select('sales_order_lines.*')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_orders.company_id', $companyId)
                ->where(function ($q) use ($itemCode) {
                    $q->where('sales_order_lines.item_id', $this->itemId);
                    if (filled($itemCode)) {
                        $q->orWhere('sales_order_lines.item_code', $itemCode);
                    }
                })
                ->when($this->customerId, fn ($q) => $q->where('sales_orders.customer_id', $this->customerId));

            $outsideRangeCount = (clone $base)->count();

            $rows = (clone $base)
                ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('sales_orders.order_date', '>=', $this->dateFrom))
                ->when($this->dateTo !== '', fn ($q) => $q->whereDate('sales_orders.order_date', '<=', $this->dateTo))
                ->with(['salesOrder.customer'])
                ->orderByDesc('sales_orders.order_date')
                ->limit(500)
                ->get();

            $outsideRangeCount = max(0, $outsideRangeCount - $rows->count());
            if ($this->dateFrom === '' && $this->dateTo === '') {
                $outsideRangeCount = 0;
            }
        }

        $totalQty = $rows->sum(fn ($r) => (float) $r->qty_ordered);
        $totalSales = $rows->sum(fn ($r) => (float) $r->line_total);
        $orderCount = $rows->pluck('sales_order_id')->unique()->count();

        if ($this->dateFrom !== '' && $this->dateTo !== '') {
            $days = max(1, (int) \Illuminate\Support\Carbon::parse($this->dateFrom)->diffInDays(\Illuminate\Support\Carbon::parse($this->dateTo)) + 1);
        } elseif ($rows->isNotEmpty()) {
            $dates = $rows->map(fn ($r) => optional($r->salesOrder?->order_date)->toDateString())->filter();
            $days = max(1, (int) \Illuminate\Support\Carbon::parse($dates->min())->diffInDays(\Illuminate\Support\Carbon::parse($dates->max())) + 1);
        } else {
            $days = 1;
        }

        return array_merge($this->inquiryBrowseViewData(), [
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
            'outsideRangeCount' => $outsideRangeCount,
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
        <x-action-bar title="Item Velocity">
            <x-slot:menu>
                <x-action-item label="View/Edit Item" kbd="Ctrl+E" wire:click="editLookedUpItem" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-toolbar rpt-toolbar">
            <div class="rpt-field rpt-field-search">
                <label class="desk-toolbar-label" for="iv-code">Item Code / UPC</label>
                <div class="so-scan-bar" style="max-width:28rem;min-width:16rem;height:2.15rem">
                    <button type="button" wire:click="focusItemScan" class="so-scan-btn" title="Scan barcode">
                        <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                            <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                        </svg>
                        <span>Scan</span>
                    </button>
                    <input
                        id="iv-code"
                        type="search"
                        wire:model="itemCode"
                        wire:keydown.enter.prevent="lookupItem($event.target.value)"
                        wire:keydown.f2.prevent="openItemBrowse"
                        class="so-input font-mono"
                        placeholder="Scan or type item code / UPC…"
                        autofocus
                        autocomplete="off"
                    />
                    <button
                        type="button"
                        wire:click="openItemBrowse"
                        class="so-icon-btn"
                        title="Browse items (F2)"
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
            <div class="rpt-actions" style="margin-left:0">
                <button type="button" wire:click="openItemBrowse" class="so-browse-btn" title="Item list (F2)">Browse (F2)</button>
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
                    <option value="all">All dates</option>
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
                        · {{ $dateFrom !== '' || $dateTo !== '' ? (($dateFrom ?: '…').' → '.($dateTo ?: '…')) : 'All dates' }}
                    @else
                        Lookup an item to view sales velocity · Browse (F2)
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
                                @if (! $item)
                                    Lookup an item to view velocity.
                                @elseif ($outsideRangeCount > 0)
                                    No sales lines in this date range.
                                    This item has <strong>{{ number_format($outsideRangeCount) }}</strong> sales line(s) outside the selected dates
                                    (e.g. older order dates).
                                    <button type="button" wire:click="showAllDates" class="desk-btn desk-btn-sm desk-btn-primary" style="margin-left:0.5rem">Show all dates</button>
                                @else
                                    No sales lines found for this item.
                                @endif
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
                    Ready for inquiry · Browse (F2) to pick an existing code / UPC
                @endif
            </span>
            @if ($item && Route::has('inventory.items.edit'))
                <div class="desk-footer-actions">
                    <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="desk-btn desk-btn-sm">Open Item</a>
                </div>
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
