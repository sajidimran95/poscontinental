<?php

use App\Models\Invoice;
use App\Models\SalesOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Orders')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = SalesOrder::query()
            ->with(['customer', 'createdBy'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'not_invoiced', fn ($q) => $q->where('status', '!=', 'Invoiced'))
            ->when($this->favorite === 'month', fn ($q) => $q->where('order_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('order_date', '>=', now()->subDay()))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id');

        return [
            'orders' => $query->paginate(50),
            'favorites' => [
                'all' => 'All Orders',
                'new' => 'New Orders',
                'not_invoiced' => 'Not Invoiced',
                'month' => 'This Month',
                'today' => 'Today & Yesterday',
            ],
            'statusOptions' => ['New', 'Invoiced'],
        ];
    }

    public function invoiceOrder(int $id): void
    {
        $order = SalesOrder::query()->with(['lines', 'customer'])->findOrFail($id);
        abort_unless($order->company_id === auth()->user()->company_id, 403);
        if ($order->status === 'Invoiced' || $order->invoice) {
            return;
        }

        $lineDiscount = (float) $order->lines->sum('discount');
        Invoice::query()->create([
            'company_id' => $order->company_id,
            'invoice_number' => Invoice::nextNumber($order->company_id),
            'invoice_date' => now()->toDateString(),
            'sales_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'status' => 'NOT PAID',
            'subtotal' => $order->subtotal,
            'total_discount' => $lineDiscount,
            'trade_discount' => $order->trade_discount,
            'freight' => $order->freight,
            'miscellaneous' => $order->miscellaneous,
            'tax' => $order->tax,
            'invoice_total' => $order->total,
            'driver' => null,
        ]);

        $order->update(['status' => 'Invoiced']);

        if ($order->customer && blank($order->customer->customer_since)) {
            $order->customer->update([
                'customer_since' => $order->order_date ?? now()->toDateString(),
            ]);
        }
        if ($order->customer) {
            $order->customer->update([
                'last_order_on' => $order->order_date ?? now()->toDateString(),
                'number_of_orders' => (int) $order->customer->number_of_orders + 1,
                'total_sales' => (float) $order->customer->total_sales + (float) $order->total,
            ]);
        }

        session()->flash('status', 'Invoice created.');
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <x-list-chrome label="Search Orders:" model="search" placeholder="Order #, customer, phone…">
            <label class="desk-toolbar-label" for="orders-status-filter">Status</label>
            <select id="orders-status-filter" wire:model.live="statusFilter" class="desk-select" aria-label="Filter by status">
                <option value="">All Statuses</option>
                @foreach ($statusOptions as $st)
                    <option value="{{ $st }}">{{ $st === 'New' ? 'Not Invoiced (New)' : $st }}</option>
                @endforeach
            </select>
            <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Sales Order</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">Orders List</h2>
            <span class="desk-title-meta">{{ number_format($orders->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Type</th>
                        <th>Order Date</th>
                        <th>Ship Date</th>
                        <th>Status</th>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Address</th>
                        <th>Telephone</th>
                        <th>User Name</th>
                        <th>Last Updated</th>
                        <th>Req. Delivery</th>
                        <th class="text-right">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="desk-num">
                                <a href="{{ route('sales.orders.edit', $order) }}" wire:navigate>{{ $order->order_number }}</a>
                            </td>
                            <td>{{ $order->order_type }}</td>
                            <td>{{ optional($order->order_date)?->format('n/j/Y') }}</td>
                            <td>{{ optional($order->ship_date)?->format('n/j/Y') }}</td>
                            <td>
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-new' => $order->status === 'New',
                                    'desk-pill-invoiced' => $order->status === 'Invoiced',
                                    'desk-pill-muted' => ! in_array($order->status, ['New', 'Invoiced'], true),
                                ])>{{ $order->status }}</span>
                            </td>
                            <td class="desk-num">{{ $order->customer?->customer_id }}</td>
                            <td>{{ $order->customer?->contact }}</td>
                            <td>{{ $order->customer?->company_name }}</td>
                            <td class="max-w-[10rem] truncate" title="{{ $order->customer?->address }}">{{ $order->customer?->address }}</td>
                            <td>{{ $order->customer?->telephone }}</td>
                            <td>{{ $order->createdBy?->name }}</td>
                            <td>{{ optional($order->updated_at)?->format('n/j/Y g:i A') }}</td>
                            <td>{{ optional($order->required_date)?->format('n/j/Y') }}</td>
                            <td class="desk-money">${{ number_format($order->total, 2) }}</td>
                            <td>
                                @if ($order->status !== 'Invoiced')
                                    <button type="button" wire:click="invoiceOrder({{ $order->id }})" class="desk-btn desk-btn-sm">Invoice</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="15">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$orders->total()">
            <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Sales Order</a>
            {{ $orders->links() }}
        </x-record-count>
    </div>
</div>
