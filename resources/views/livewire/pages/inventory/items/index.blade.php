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

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = Item::query()
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term)
                        ->orWhereHas('upcs', fn ($upc) => $upc->where('upc', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->newItems())
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
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
        ];

        foreach (Department::query()->where('company_id', $companyId)->orderBy('name')->get() as $dept) {
            $favorites['dept:'.$dept->id] = $dept->name;
        }

        return [
            'items' => $query->paginate(50),
            'favorites' => $favorites,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
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

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" variant="green" />
        <x-list-chrome label="Search Items:" model="search" />

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">
            Items List
            @if ($favorite === 'new') (New Items)
            @elseif ($favorite === 'active') (Active Items)
            @elseif ($favorite === 'inactive') (Inactive Items)
            @elseif ($favorite === 'all') (All Items)
            @endif
        </div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Unit of Measure</th>
                        <th class="text-right">List Price</th>
                        <th class="text-right">Standard Cost</th>
                        <th class="text-right">Qty In Stock</th>
                        <th class="text-right">Available</th>
                        <th class="text-center">New</th>
                        <th class="text-center">Can Sell</th>
                        <th class="text-center">Inactive</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr
                            wire:click="selectRow({{ $item->id }})"
                            @class(['chief-selected-row' => $selectedId === $item->id, 'cursor-pointer'])
                        >
                            <td class="font-mono">
                                <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="hover:underline">{{ $item->item_code }}</a>
                            </td>
                            <td class="max-w-xs truncate">{{ $item->description }}</td>
                            <td>{{ $item->unit_of_measure }}</td>
                            <td class="text-right">${{ number_format($item->list_price, 2) }}</td>
                            <td class="text-right">${{ number_format($item->standard_cost, 2) }}</td>
                            <td class="text-right">{{ number_format($item->quantity_in_stock, 2) }}</td>
                            <td class="text-right">{{ number_format($item->available_quantity, 2) }}</td>
                            <td class="text-center">{{ $item->created_at && $item->created_at->gte(now()->subDays(30)) ? 'Yes' : '' }}</td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleCanSell({{ $item->id }})"
                                    class="px-1 text-base leading-none hover:scale-110"
                                    title="{{ $item->can_sell ? 'Can sell — click to disable' : 'Cannot sell — click to enable' }}"
                                    aria-label="Toggle can sell"
                                >{{ $item->can_sell ? '☑' : '☐' }}</button>
                            </td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleInactive({{ $item->id }})"
                                    class="px-1 text-base leading-none hover:scale-110"
                                    title="{{ $item->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                    aria-label="Toggle inactive"
                                >{{ $item->is_inactive ? '☑' : '☐' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-2 py-6 text-slate-500">No items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$items->total()">
            <a href="{{ route('inventory.items.create') }}" wire:navigate class="chief-btn-primary">New Item</a>
            {{ $items->links() }}
        </x-record-count>
    </div>
</div>
