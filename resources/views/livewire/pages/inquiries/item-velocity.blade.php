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

    public function mount(): void
    {
        $this->applyPreset();
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

        return [
            'item' => $this->itemId
                ? Item::query()->where('company_id', $companyId)->find($this->itemId)
                : null,
            'customers' => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->get(['id', 'customer_id', 'company_name']),
            'rows' => $rows,
            'totalQty' => $rows->sum(fn ($r) => (float) $r->qty_ordered),
            'totalSales' => $rows->sum(fn ($r) => (float) $r->line_total),
        ];
    }
}; ?>

<div class="flex gap-2 h-full">
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Item Velocity" />
        <div class="flex flex-wrap items-end gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <div>
                <label class="block text-xs text-slate-600">Item Code / UPC</label>
                <input wire:model="itemCode" wire:keydown.enter.prevent="lookupItem" class="chief-input w-44 font-mono" />
            </div>
            <button type="button" wire:click="lookupItem" class="chief-btn">Lookup</button>
            <div>
                <label class="block text-xs text-slate-600">Customer</label>
                <select wire:model.live="customerId" class="chief-input w-56">
                    <option value="">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Preset</label>
                <select wire:model.live="datePreset" class="chief-input w-36">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last year</option>
                    <option value="ytd">Year to date</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">From</label>
                <input type="date" wire:model.live="dateFrom" class="chief-input" />
            </div>
            <div>
                <label class="block text-xs text-slate-600">To</label>
                <input type="date" wire:model.live="dateTo" class="chief-input" />
            </div>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white flex justify-between">
            <span>
                @if ($item)
                    {{ $item->item_code }} — {{ $item->description }}
                @else
                    Item Velocity
                @endif
            </span>
            @if ($item)
                <span class="text-sm font-normal">
                    Qty: <strong>{{ number_format($totalQty, 2) }}</strong>
                    &nbsp;|&nbsp; Sales: <strong>${{ number_format($totalSales, 2) }}</strong>
                </span>
            @endif
        </div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
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
                            <td class="font-mono">{{ $row->salesOrder?->order_number }}</td>
                            <td>{{ $row->salesOrder?->customer?->company_name }}</td>
                            <td class="text-right">{{ number_format((float) $row->qty_ordered, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->qty_shipped, 2) }}</td>
                            <td class="text-right">${{ number_format((float) $row->price, 2) }}</td>
                            <td class="text-right">${{ number_format((float) $row->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-6 text-slate-500">
                                {{ $item ? 'No sales lines in this date range.' : 'Lookup an item to view velocity.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$rows->count()" />
    </div>
</div>
