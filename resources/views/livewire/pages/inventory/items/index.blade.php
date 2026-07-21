<?php

use App\Models\Department;
use App\Models\Item;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Items')] class extends Component
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

        $query = Item::query()
            ->with('department')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term)
                        ->orWhereHas('upcs', fn ($upc) => $upc->where('upc', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->newItems())
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->favorite === 'low_stock', fn ($q) => $q->lowStock())
            ->when(str_starts_with($this->favorite, 'dept:'), function ($q) {
                $deptId = (int) substr($this->favorite, 5);
                $q->where('department_id', $deptId);
            })
            ->orderBy('item_code');

        $favorites = [
            'all' => 'All Items',
            'new' => 'New Items',
            'active' => 'Active Items',
            'inactive' => 'Inactive Items',
            'low_stock' => 'Low Stock',
        ];

        $departments = Department::query()->where('company_id', $companyId)->orderBy('name')->get();
        foreach ($departments as $dept) {
            $favorites['dept:'.$dept->id] = $dept->name;
        }

        $listTitle = 'Items List';
        if ($this->favorite === 'new') {
            $listTitle = 'New Items';
        } elseif ($this->favorite === 'active') {
            $listTitle = 'Active Items';
        } elseif ($this->favorite === 'inactive') {
            $listTitle = 'Inactive Items';
        } elseif ($this->favorite === 'low_stock') {
            $listTitle = 'Low Stock Items';
        } elseif (str_starts_with($this->favorite, 'dept:')) {
            $deptId = (int) substr($this->favorite, 5);
            $listTitle = $departments->firstWhere('id', $deptId)?->name ?? 'Items List';
        }

        return [
            'items' => $query->paginate(50),
            'favorites' => $favorites,
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

    public function toggleInactive(int $id): void
    {
        $item = Item::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $item->update(['is_inactive' => ! $item->is_inactive]);
        $this->selectedId = $id;
    }

    public function toggleCanSell(int $id): void
    {
        $item = Item::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $item->update(['can_sell' => ! $item->can_sell]);
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        <x-list-chrome label="Search Items:" model="search" placeholder="Code, description, UPC, manufacturer…">
            <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Item</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($items->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Department</th>
                        <th>UOM</th>
                        <th class="desk-money">List Price</th>
                        <th class="desk-money">Std Cost</th>
                        <th class="desk-money">In Stock</th>
                        <th class="desk-money">Available</th>
                        <th class="text-center">Can Sell</th>
                        <th class="text-center">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr
                            wire:click="selectRow({{ $item->id }})"
                            @class(['is-selected' => $selectedId === $item->id, 'cursor-pointer'])
                        >
                            <td class="desk-num">
                                <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate wire:click.stop>{{ $item->item_code }}</a>
                            </td>
                            <td title="{{ $item->description }}">{{ \Illuminate\Support\Str::limit($item->description, 48) }}</td>
                            <td>{{ $item->department?->name ?: '—' }}</td>
                            <td>{{ $item->unit_of_measure }}</td>
                            <td class="desk-money">${{ number_format($item->list_price, 2) }}</td>
                            <td class="desk-money">${{ number_format($item->standard_cost, 2) }}</td>
                            <td class="desk-money">{{ number_format($item->quantity_in_stock, 2) }}</td>
                            <td class="desk-money">{{ number_format($item->available_quantity, 2) }}</td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleCanSell({{ $item->id }})"
                                    @class([
                                        'desk-pill',
                                        'desk-pill-invoiced' => $item->can_sell,
                                        'desk-pill-muted' => ! $item->can_sell,
                                    ])
                                    title="{{ $item->can_sell ? 'Can sell — click to disable' : 'Cannot sell — click to enable' }}"
                                    aria-label="Toggle can sell"
                                >{{ $item->can_sell ? 'Yes' : 'No' }}</button>
                            </td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleInactive({{ $item->id }})"
                                    @class([
                                        'desk-pill',
                                        'desk-pill-muted' => $item->is_inactive,
                                        'desk-pill-invoiced' => ! $item->is_inactive,
                                    ])
                                    title="{{ $item->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                    aria-label="Toggle inactive"
                                >{{ $item->is_inactive ? 'Inactive' : 'Active' }}</button>
                            </td>
                            <td wire:click.stop>
                                <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="desk-btn desk-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="11">No items found. Click <strong>New Item</strong> to create one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$items->total()">
            <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Item</a>
            {{ $items->links() }}
        </x-record-count>
    </div>
</div>
