<?php

use App\Models\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Purchase Orders')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = PurchaseOrder::query()
            ->with('supplier')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('po_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)->orWhere('supplier_id', 'like', $term));
                });
            })
            ->when($this->favorite === 'pending', fn ($q) => $q->whereIn('status', ['New', 'Partially Received']))
            ->when($this->favorite === 'month', fn ($q) => $q->where('requisition_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('requisition_date', today()))
            ->orderByDesc('id');

        return [
            'orders' => $query->paginate(50),
            'favorites' => [
                'all' => 'All POs',
                'pending' => 'Pending POs',
                'month' => 'This Month',
                'today' => 'Today',
            ],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Purchase Orders:" model="search" />

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Purchase Orders List</div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Requisition Date</th>
                        <th>Status</th>
                        <th>Required Date</th>
                        <th>Reference No.</th>
                        <th>Supplier</th>
                        <th class="text-right">Order Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr wire:click="selectRow({{ $order->id }})" @class(['chief-selected-row' => $selectedId === $order->id, 'cursor-pointer'])>
                            <td class="font-mono">
                                <a href="{{ route('purchasing.orders.edit', $order) }}" wire:navigate class="hover:underline">{{ $order->po_number }}</a>
                            </td>
                            <td>{{ optional($order->requisition_date)?->format('n/j/Y') }}</td>
                            <td>{{ $order->status }}</td>
                            <td>{{ optional($order->required_date)?->format('n/j/Y') }}</td>
                            <td>{{ $order->reference_no }}</td>
                            <td>{{ $order->supplier?->name }}</td>
                            <td class="text-right">${{ number_format($order->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-6 text-slate-500">No purchase orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$orders->total()">
            <a href="{{ route('purchasing.orders.create') }}" wire:navigate class="chief-btn-primary">New Purchase Order</a>
            {{ $orders->links() }}
        </x-record-count>
    </div>
</div>
