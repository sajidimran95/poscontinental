<?php

use App\Livewire\Concerns\BrowsesItemsForInquiry;
use App\Livewire\Concerns\SearchesItemEntryHits;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrderLine;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Item Velocity')] class extends Component
{
    use BrowsesItemsForInquiry;
    use SearchesItemEntryHits;

    #[Url(history: false, keep: true)]
    public string $itemCode = '';

    public ?int $itemId = null;

    #[Url(as: 'customer', history: false, keep: true)]
    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $datePreset = '30';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $lookupError = '';

    public function mount(): void
    {
        $this->restoreVelocityFilters();
        $this->hydrateCustomerSearchLabel();
        if ($this->dateFrom === '' && $this->dateTo === '' && $this->datePreset !== 'all') {
            $this->applyPreset();
        }
        if (trim($this->itemCode) !== '') {
            $this->lookupItem(playSound: false);
        }
    }

    protected function velocitySessionKey(): string
    {
        return 'item_velocity.filters.'.(int) auth()->id().'.'.(int) auth()->user()->company_id;
    }

    protected function persistVelocityFilters(): void
    {
        Session::put($this->velocitySessionKey(), [
            'customerId' => $this->customerId,
            'customerSearch' => $this->customerSearch,
            'itemCode' => $this->itemCode,
            'itemId' => $this->itemId,
            'datePreset' => $this->datePreset,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    protected function restoreVelocityFilters(): void
    {
        $saved = Session::get($this->velocitySessionKey(), []);
        if (! is_array($saved) || $saved === []) {
            return;
        }

        if ($this->customerId === null && ! empty($saved['customerId'])) {
            $this->customerId = (int) $saved['customerId'];
        }
        if (trim($this->customerSearch) === '' && filled($saved['customerSearch'] ?? null)) {
            $this->customerSearch = (string) $saved['customerSearch'];
        }
        if (trim($this->itemCode) === '' && filled($saved['itemCode'] ?? null)) {
            $this->itemCode = (string) $saved['itemCode'];
        }
        if ($this->itemId === null && ! empty($saved['itemId'])) {
            $this->itemId = (int) $saved['itemId'];
        }
        if (isset($saved['datePreset']) && is_string($saved['datePreset']) && $saved['datePreset'] !== '') {
            $this->datePreset = $saved['datePreset'];
        }
        if (isset($saved['dateFrom']) && is_string($saved['dateFrom'])) {
            $this->dateFrom = $saved['dateFrom'];
        }
        if (isset($saved['dateTo']) && is_string($saved['dateTo'])) {
            $this->dateTo = $saved['dateTo'];
        }
    }

    protected function hydrateCustomerSearchLabel(): void
    {
        if (! $this->customerId) {
            return;
        }

        if (trim($this->customerSearch) !== '') {
            return;
        }

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->customerId);

        if (! $customer) {
            $this->customerId = null;

            return;
        }

        $this->customerSearch = trim(($customer->customer_id ? $customer->customer_id.' — ' : '').($customer->company_name ?: $customer->contact ?: ''));
    }

    public function updatedCustomerSearch(): void
    {
        if (! $this->customerId) {
            return;
        }

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->customerId);
        $label = $customer
            ? trim(($customer->customer_id ? $customer->customer_id.' — ' : '').($customer->company_name ?: $customer->contact ?: ''))
            : '';

        // Keep the pick when we only re-hydrate the same label (mount / session restore).
        if (trim($this->customerSearch) === $label) {
            return;
        }

        $this->customerId = null;
        $this->persistVelocityFilters();
    }

    public function pickCustomer(int $id): void
    {
        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $customer) {
            return;
        }

        // Set label first — updatedCustomerSearch clears any prior id — then lock the pick.
        $this->customerSearch = trim(($customer->customer_id ? $customer->customer_id.' — ' : '').($customer->company_name ?: $customer->contact ?: ''));
        $this->customerId = (int) $customer->id;
        $this->persistVelocityFilters();
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerSearch = '';
        $this->persistVelocityFilters();
    }

    public function updatedCustomerId(): void
    {
        $this->persistVelocityFilters();
    }

    public function updatedItemId(): void
    {
        $this->persistVelocityFilters();
    }

    public function updatedDateFrom(): void
    {
        $this->persistVelocityFilters();
    }

    public function updatedDateTo(): void
    {
        $this->persistVelocityFilters();
    }

    public function updatedDatePreset(): void
    {
        $this->applyPreset();
        $this->persistVelocityFilters();
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
        $this->entryHits = [];
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
            $this->persistVelocityFilters();
            if ($playSound) {
                $this->playPosSound('success');
            }
            $code = json_encode($item->item_code);
            // Box was emptied when the scan was sent; if it has text again the next scan is being typed.
            $this->js('requestAnimationFrame(() => { const el = document.getElementById("iv-code"); if (el && (el.value || "").trim() === "") { el.value = '.$code.'; if (document.activeElement === el) el.select(); } });');
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

    public function rendered(): void
    {
        if ($this->showBrowse) {
            return;
        }

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('iv-code');
                if (!el) return;
                const a = document.activeElement;
                if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT') && a !== el) return;
                el.focus();
            });
        JS);
    }

    public function clearLookup(): void
    {
        // Clear item only — keep the selected customer fixed for the next lookup.
        $this->reset(['itemCode', 'itemId', 'lookupError', 'entryHits']);
        $this->closeBrowse();
        $this->datePreset = '30';
        $this->applyPreset();
        $this->persistVelocityFilters();
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("iv-code"); if (el) { el.value = ""; el.focus(); } });');
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
        $this->entryHits = [];
        $this->closeBrowse();
        $this->persistVelocityFilters();
        $this->playPosSound('success');
        $code = json_encode($item->item_code);
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("iv-code"); if (el) el.value = '.$code.'; });');
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
            'customerSuggestions' => $this->customerSuggestions($companyId),
            'rows' => $rows,
            'totalQty' => $totalQty,
            'totalSales' => $totalSales,
            'orderCount' => $orderCount,
            'avgDailyQty' => $totalQty / $days,
            'outsideRangeCount' => $outsideRangeCount,
        ]);
    }

    protected function customerSuggestions(int $companyId)
    {
        if ($this->customerId || trim($this->customerSearch) === '') {
            return collect();
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->customerSearch)).'%';

        return Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                    ->orWhere('contact', 'like', $term)
                    ->orWhere('customer_id', 'like', $term)
                    ->orWhere('telephone', 'like', $term)
                    ->orWhere('mobile', 'like', $term);
            })
            ->orderBy('company_name')
            ->limit(40)
            ->get(['id', 'customer_id', 'company_name', 'contact', 'city', 'telephone', 'mobile']);
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
    <style>
        .iv-table { table-layout: fixed; width: 100%; }
        .iv-table th.iv-l, .iv-table td.iv-l { text-align: left !important; }
        .iv-table th.iv-r, .iv-table td.iv-r { text-align: right !important; }
        .iv-table th, .iv-table td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .iv-table tr.is-empty td { white-space: normal; text-align: left; }
    </style>
    <div class="desk-main">
        <x-action-bar title="Item Velocity">
            <x-slot:menu>
                <x-action-item label="View/Edit Item" kbd="Ctrl+E" wire:click="editLookedUpItem" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-toolbar rpt-toolbar">
            <div class="rpt-field rpt-field-search" style="flex:1 1 22rem;min-width:18rem">
                <label class="desk-toolbar-label" for="iv-code">Item code / barcode (F2) · Browse (F3)</label>
                @include('livewire.partials.item-entry-search-bar', [
                    'entryInputId' => 'iv-code',
                    'entryCommit' => 'lookupItem',
                    'entryPlaceholder' => 'Scan to look up instantly · type name or code to search',
                ])
            </div>
            <div class="rpt-actions" style="margin-left:0">
                <button type="button" wire:click="openItemBrowse" class="so-browse-btn" data-pos-browse title="Item list (F3)">Browse (F3)</button>
                <button type="button" x-on:click.prevent="$wire.lookupItem(document.getElementById('iv-code')?.value || '')" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="lookupItem">Lookup</span>
                    <span wire:loading wire:target="lookupItem">Looking up…</span>
                </button>
            </div>
            <div class="rpt-field rpt-field-search" style="position:relative;flex:1 1 16rem;min-width:14rem">
                <label class="desk-toolbar-label" for="iv-cust">Customer</label>
                <div class="so-lookup-row">
                    <input
                        id="iv-cust"
                        type="search"
                        class="so-input desk-select"
                        style="width:100%;min-width:12rem"
                        placeholder="Search customer ID or name…"
                        wire:model.live.debounce.200ms="customerSearch"
                        autocomplete="off"
                        aria-label="Search customer"
                        aria-autocomplete="list"
                    />
                    @if ($customerId)
                        <button type="button" wire:click="clearCustomer" class="so-icon-btn" title="Clear customer (show all)" aria-label="Clear customer">×</button>
                    @endif
                </div>
                @if (! $customerId && trim($customerSearch) !== '')
                    <div class="so-lookup-panel" role="listbox" aria-label="Customer suggestions" style="position:absolute;left:0;right:0;z-index:40;max-height:16rem;margin-top:0.2rem;overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:4px;box-shadow:0 8px 20px rgba(15,23,42,.12)">
                        @forelse ($customerSuggestions as $bc)
                            <button
                                type="button"
                                wire:key="iv-cust-{{ $bc->id }}"
                                wire:click="pickCustomer({{ $bc->id }})"
                                class="so-lookup-row-pick"
                                role="option"
                                style="display:block;width:100%;text-align:left;border:0;background:transparent;padding:0.45rem 0.6rem;cursor:pointer"
                            >
                                <div style="font-weight:700;font-size:13px">{{ $bc->customer_id }} — {{ $bc->company_name ?: $bc->contact }}</div>
                                <div style="font-size:11px;color:#64748b">{{ collect([$bc->contact, $bc->mobile ?: $bc->telephone, $bc->city])->filter()->implode(' · ') }}</div>
                            </button>
                        @empty
                            <div style="padding:0.5rem 0.6rem;font-size:12px;color:#64748b">No customers found.</div>
                        @endforelse
                    </div>
                @endif
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
                        Lookup an item to view sales velocity · Browse (F3)
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
            <table class="desk-table iv-table">
                <colgroup>
                    <col style="width:8rem" />
                    <col style="width:8rem" />
                    <col />
                    <col style="width:8rem" />
                    <col style="width:8rem" />
                    <col style="width:8rem" />
                    <col style="width:9rem" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="iv-l">Order Date</th>
                        <th class="iv-l">Order No</th>
                        <th class="iv-l">Customer</th>
                        <th class="iv-r">Qty Ordered</th>
                        <th class="iv-r">Qty Shipped</th>
                        <th class="iv-r">Price</th>
                        <th class="iv-r">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="iv-l">{{ optional($row->salesOrder?->order_date)?->format('n/j/Y') }}</td>
                            <td class="desk-num iv-l">
                                @if ($row->salesOrder && Route::has('sales.orders.edit'))
                                    <a href="{{ route('sales.orders.edit', $row->salesOrder) }}" wire:navigate>{{ $row->salesOrder->order_number }}</a>
                                @else
                                    {{ $row->salesOrder?->order_number }}
                                @endif
                            </td>
                            <td class="iv-l">{{ $row->salesOrder?->customer?->company_name ?: '—' }}</td>
                            <td class="desk-money iv-r">{{ number_format((float) $row->qty_ordered, 2) }}</td>
                            <td class="desk-money iv-r">{{ number_format((float) $row->qty_shipped, 2) }}</td>
                            <td class="desk-money iv-r">${{ number_format((float) $row->price, 2) }}</td>
                            <td class="desk-money iv-r">${{ number_format((float) $row->line_total, 2) }}</td>
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
                    Ready for inquiry · Browse (F3) to pick an existing code / UPC
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
        const kind = window.posAlertFromEvent ? window.posAlertFromEvent(e) : ((e && e.kind) || (Array.isArray(e) && e[0] && e[0].kind) || 'error');
        if (! kind) return;
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
