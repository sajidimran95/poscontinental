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

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = SalesOrder::query()
            ->with('customer')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_id', 'like', $term)->orWhere('company_name', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'not_invoiced', fn ($q) => $q->where('status', '!=', 'Invoiced'))
            ->when($this->favorite === 'month', fn ($q) => $q->where('order_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('order_date', '>=', now()->subDay()))
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
        ];
    }

    public function invoiceOrder(int $id): void
    {
        $order = SalesOrder::query()->with('lines')->findOrFail($id);
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
        ]);

        $order->update(['status' => 'Invoiced']);
        session()->flash('status', 'Invoice created.');
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Orders:" model="search" />
        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Orders List</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Type</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Company</th>
                        <th>Req. Delivery</th>
                        <th class="text-right">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="font-mono">
                                <a href="{{ route('sales.orders.edit', $order) }}" wire:navigate class="hover:underline">{{ $order->order_number }}</a>
                            </td>
                            <td>{{ $order->order_type }}</td>
                            <td>{{ optional($order->order_date)?->format('n/j/Y') }}</td>
                            <td>{{ $order->status }}</td>
                            <td>{{ $order->customer?->customer_id }}</td>
                            <td>{{ $order->customer?->company_name }}</td>
                            <td>{{ optional($order->required_date)?->format('n/j/Y') }}</td>
                            <td class="text-right">${{ number_format($order->total, 2) }}</td>
                            <td>
                                @if ($order->status !== 'Invoiced')
                                    <button type="button" wire:click="invoiceOrder({{ $order->id }})" class="chief-btn text-xs">Invoice</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-2 py-6 text-slate-500">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$orders->total()">
            <a href="{{ route('sales.orders.create') }}" wire:navigate class="chief-btn-primary">New Sales Order</a>
            {{ $orders->links() }}
        </x-record-count>
    </div>
</div>
