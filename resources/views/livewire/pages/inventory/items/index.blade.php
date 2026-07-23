<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\Subcategory;
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

    /** '' | active | inactive */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

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
            ->when(str_starts_with($this->favorite, 'cat:'), function ($q) {
                $catId = (int) substr($this->favorite, 4);
                $q->where('category_id', $catId);
            })
            ->when(str_starts_with($this->favorite, 'sub:'), function ($q) {
                $subId = (int) substr($this->favorite, 4);
                $q->where('subcategory_id', $subId);
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->orderByDesc('id');

        $favorites = [
            'all' => 'All Items',
            'new' => 'New Items',
            'active' => 'Active Items',
            'inactive' => 'Inactive Items',
            'low_stock' => 'Low Stock',
        ];

        $nodes = [
            ['type' => 'item', 'key' => 'all', 'label' => 'All Items', 'level' => 0],
            ['type' => 'item', 'key' => 'new', 'label' => 'New Items', 'level' => 0],
            ['type' => 'item', 'key' => 'active', 'label' => 'Active Items', 'level' => 0],
            ['type' => 'item', 'key' => 'inactive', 'label' => 'Inactive Items', 'level' => 0],
            ['type' => 'item', 'key' => 'low_stock', 'label' => 'Low Stock', 'level' => 0],
        ];

        $departments = Department::query()
            ->with(['categories' => fn ($q) => $q->orderBy('name')->with(['subcategories' => fn ($sq) => $sq->orderBy('name')])])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        if ($departments->isNotEmpty()) {
            $nodes[] = ['type' => 'heading', 'label' => 'By Department'];
        }

        foreach ($departments as $dept) {
            $favorites['dept:'.$dept->id] = $dept->name;
            $nodes[] = [
                'type' => 'item',
                'key' => 'dept:'.$dept->id,
                'label' => $dept->name,
                'level' => 0,
                'kind' => 'Dept',
            ];
            foreach ($dept->categories as $cat) {
                $favorites['cat:'.$cat->id] = $cat->name;
                $nodes[] = [
                    'type' => 'item',
                    'key' => 'cat:'.$cat->id,
                    'label' => $cat->name,
                    'level' => 1,
                    'kind' => 'Category',
                ];
                foreach ($cat->subcategories as $sub) {
                    $favorites['sub:'.$sub->id] = $sub->name;
                    $nodes[] = [
                        'type' => 'item',
                        'key' => 'sub:'.$sub->id,
                        'label' => $sub->name,
                        'level' => 2,
                        'kind' => 'Subcat',
                    ];
                }
            }
        }

        $listTitle = 'Items List';
        if ($this->statusFilter === 'active') {
            $listTitle = 'Items List (Active)';
        } elseif ($this->statusFilter === 'inactive') {
            $listTitle = 'Items List (Inactive)';
        } elseif ($this->favorite === 'new') {
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
        } elseif (str_starts_with($this->favorite, 'cat:')) {
            $catId = (int) substr($this->favorite, 4);
            $cat = Category::query()->with('department')->find($catId);
            $listTitle = $cat
                ? trim(($cat->department?->name ? $cat->department->name.' › ' : '').$cat->name)
                : 'Items List';
        } elseif (str_starts_with($this->favorite, 'sub:')) {
            $subId = (int) substr($this->favorite, 4);
            $sub = Subcategory::query()->with('category.department')->find($subId);
            if ($sub) {
                $parts = array_filter([
                    $sub->category?->department?->name,
                    $sub->category?->name,
                    $sub->name,
                ]);
                $listTitle = implode(' › ', $parts) ?: 'Items List';
            }
        }

        return [
            'items' => $query->paginate(50),
            'favorites' => $favorites,
            'nodes' => $nodes,
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
        $this->statusFilter = match ($this->favorite) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => $this->statusFilter,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        if ($this->statusFilter === 'active') {
            $this->favorite = 'active';
        } elseif ($this->statusFilter === 'inactive') {
            $this->favorite = 'inactive';
        }
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an item first.');

            return null;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return null;
        }

        return $this->redirect(route('inventory.items.edit', $item), navigate: true);
    }

    public function openItem(int $id): mixed
    {
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('inventory.items.show', $item), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an item first.');

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return;
        }

        if (
            SalesOrderLine::query()->where('item_id', $item->id)->exists()
            || PurchaseOrderLine::query()->where('item_id', $item->id)->exists()
        ) {
            session()->flash('status', 'Item is used on orders and cannot be deleted. Mark it Inactive instead.');

            return;
        }

        $item->delete();
        $this->selectedId = null;
        session()->flash('status', 'Item deleted.');
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
    <x-favorite-list :nodes="$nodes" :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action" />

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar">
                    <label class="desk-toolbar-label" for="items-search">Search Items:</label>
                    <input
                        id="items-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Code, description, UPC, manufacturer…"
                        class="desk-search orders-search-input"
                        aria-label="Search Items"
                    />

                    <div class="orders-toolbar-right">
                        <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                            <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                            </svg>
                            New Search
                        </button>
                        <select
                            id="items-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Active filter"
                            title="Active / Inactive"
                        >
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button
                            type="button"
                            wire:click="clearSearch"
                            class="so-icon-btn"
                            title="Clear search"
                            aria-label="Clear search"
                        >
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M4 4l8 8M12 4l-8 8"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($items->total()) }} records</span>
                </div>

                <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr
                                    wire:click="selectRow({{ $item->id }})"
                                    wire:dblclick="openItem({{ $item->id }})"
                                    @class(['is-selected' => $selectedId === $item->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="item_select"
                                            value="{{ $item->id }}"
                                            @checked($selectedId === $item->id)
                                            wire:click="selectRow({{ $item->id }})"
                                            aria-label="Select item {{ $item->item_code }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('inventory.items.show', $item) }}" wire:navigate wire:click.stop>{{ $item->item_code }}</a>
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
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="11">No items found. Use the <strong>+</strong> button to create one.</td>
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

            {{-- Right icon rail — items list: grid, cross-pen, pen, delete, print, refresh, + --}}
            <aside class="desk-rail" aria-label="Item actions">
                <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                        <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button type="button" wire:click="openItem({{ $selectedId ?: 0 }})" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected item? This cannot be undone."
                    class="desk-rail-btn desk-rail-btn-danger"
                    title="Delete selected"
                    aria-label="Delete selected"
                    @disabled(! $selectedId)
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                        <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
                <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Item" aria-label="New Item">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>
</div>
