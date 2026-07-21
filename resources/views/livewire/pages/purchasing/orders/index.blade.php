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

    #[Url]
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
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)->orWhere('supplier_id', 'like', $term));
                });
            })
            ->when($this->favorite === 'pending', fn ($q) => $q->whereIn('status', ['New', 'Partially Received']))
            ->when($this->favorite === 'received', fn ($q) => $q->where('status', 'Received'))
            ->when($this->favorite === 'month', fn ($q) => $q->where('requisition_date', '>=', now()->startOfMonth()))
            ->when($this->favorite === 'today', fn ($q) => $q->whereDate('requisition_date', today()))
            ->orderByDesc('id');

        $listTitle = match ($this->favorite) {
            'pending' => 'Pending Purchase Orders',
            'received' => 'Received Purchase Orders',
            'month' => 'This Month',
            'today' => 'Today',
            default => 'Purchase Orders',
        };

        return [
            'orders' => $query->paginate(50),
            'favorites' => [
                'all' => 'All POs',
                'pending' => 'Pending POs',
                'received' => 'Received',
                'month' => 'This Month',
                'today' => 'Today',
            ],
            'listTitle' => $listTitle,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        <x-list-chrome label="Search Purchase Orders:" model="search" placeholder="PO #, supplier, reference…">
            <a href="{{ route('purchasing.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Purchase Order</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($orders->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Requisition Date</th>
                        <th class="text-center">Status</th>
                        <th>Required Date</th>
                        <th>Reference No.</th>
                        <th>Supplier</th>
                        <th class="desk-money">Order Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr
                            wire:click="selectRow({{ $order->id }})"
                            @class(['is-selected' => $selectedId === $order->id, 'cursor-pointer'])
                        >
                            <td class="desk-num">
                                <a href="{{ route('purchasing.orders.edit', $order) }}" wire:navigate wire:click.stop>{{ $order->po_number }}</a>
                            </td>
                            <td>{{ optional($order->requisition_date)?->format('n/j/Y') }}</td>
                            <td class="text-center">
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-new' => in_array($order->status, ['New', 'Partially Received'], true),
                                    'desk-pill-invoiced' => $order->status === 'Received',
                                    'desk-pill-muted' => ! in_array($order->status, ['New', 'Partially Received', 'Received'], true),
                                ])>{{ $order->status }}</span>
                            </td>
                            <td>{{ optional($order->required_date)?->format('n/j/Y') ?: '—' }}</td>
                            <td>{{ $order->reference_no ?: '—' }}</td>
                            <td>{{ $order->supplier?->name }}</td>
                            <td class="desk-money">${{ number_format($order->total, 2) }}</td>
                            <td wire:click.stop>
                                <a href="{{ route('purchasing.orders.edit', $order) }}" wire:navigate class="desk-btn desk-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="8">No purchase orders found. Click <strong>New Purchase Order</strong> to create one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$orders->total()">
            <a href="{{ route('purchasing.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Purchase Order</a>
            {{ $orders->links() }}
        </x-record-count>
    </div>
</div>
