<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Suppliers')] class extends Component
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
        $hasSearch = $this->search !== '';

        $query = Supplier::query()
            ->where('company_id', $companyId)
            ->when($hasSearch, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('supplier_id', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone1', 'like', $term)
                        ->orWhere('web_page', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhere('contact_name', 'like', $term);
                });
            })
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->favorite === 'tobacco', fn ($q) => $q->where('is_tobacco_supplier', true))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->orderByDesc('id');

        if (! $hasSearch && $this->favorite === 'all' && $this->statusFilter === '') {
            $suppliers = Supplier::query()
                ->where('company_id', $companyId)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
            $total = $suppliers->count();
            $footerNote = '10 most recently added records with no search criteria.';
            $isPaginated = false;
        } else {
            $suppliers = $query->paginate(50);
            $total = $suppliers->total();
            $footerNote = null;
            $isPaginated = true;
        }

        $listTitle = match (true) {
            $this->statusFilter === 'active', $this->favorite === 'active' => 'Supplier List (Active)',
            $this->statusFilter === 'inactive', $this->favorite === 'inactive' => 'Supplier List (Inactive)',
            $this->favorite === 'tobacco' => 'Tobacco Suppliers',
            default => 'Supplier List',
        };

        return [
            'suppliers' => $suppliers,
            'total' => $total,
            'footerNote' => $footerNote,
            'isPaginated' => $isPaginated,
            'favorites' => [
                'all' => 'All Suppliers',
                'active' => 'Active Suppliers',
                'inactive' => 'Inactive Suppliers',
                'tobacco' => 'Tobacco Suppliers',
            ],
            'listTitle' => $listTitle,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedId = null;
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
        $this->favorite = match ($this->statusFilter) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => 'all',
        };
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
            session()->flash('status', 'Select a supplier first.');

            return null;
        }

        return $this->openSupplier($this->selectedId);
    }

    public function openSupplier(int $id): mixed
    {
        $supplier = Supplier::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $supplier) {
            session()->flash('status', 'Supplier not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('purchasing.suppliers.edit', $supplier), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a supplier first.');

            return;
        }

        $supplier = Supplier::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $supplier) {
            session()->flash('status', 'Supplier not found.');

            return;
        }

        if (PurchaseOrder::query()->where('supplier_id', $supplier->id)->exists()) {
            session()->flash('status', 'Supplier has purchase orders and cannot be deleted. Mark Inactive instead.');

            return;
        }

        $supplier->contacts()->delete();
        $supplier->delete();
        $this->selectedId = null;
        session()->flash('status', 'Supplier deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a supplier first.');

            return;
        }

        $this->dispatch('print-supplier', id: $this->selectedId);
    }

    public function toggleInactive(int $id): void
    {
        $supplier = Supplier::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $supplier->update(['is_inactive' => ! $supplier->is_inactive]);
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action" />

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar">
                    <label class="desk-toolbar-label" for="suppliers-search">Search Suppliers:</label>
                    <input
                        id="suppliers-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="ID, company, phone, email…"
                        class="desk-search orders-search-input"
                        aria-label="Search Suppliers"
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
                            id="suppliers-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Active filter"
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
                    <span class="desk-title-meta">{{ number_format($total) }} records</span>
                </div>

                <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <th>Supplier ID</th>
                                <th>Company Name</th>
                                <th>Address</th>
                                <th>Telephone</th>
                                <th>Email Address</th>
                                <th>Web Site</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                                @php
                                    $fullAddress = collect([
                                        $supplier->address,
                                        collect([$supplier->city, $supplier->state, $supplier->zip_code])->filter()->implode(', '),
                                    ])->filter()->implode(' ');
                                @endphp
                                <tr
                                    wire:click="selectRow({{ $supplier->id }})"
                                    wire:dblclick="openSupplier({{ $supplier->id }})"
                                    @class(['is-selected' => $selectedId === $supplier->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="supplier_select"
                                            value="{{ $supplier->id }}"
                                            @checked($selectedId === $supplier->id)
                                            wire:click="selectRow({{ $supplier->id }})"
                                            aria-label="Select supplier {{ $supplier->supplier_id }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate wire:click.stop>{{ $supplier->supplier_id }}</a>
                                    </td>
                                    <td>{{ $supplier->name }}</td>
                                    <td title="{{ $fullAddress }}">{{ \Illuminate\Support\Str::limit($fullAddress, 40) }}</td>
                                    <td>{{ $supplier->phone1 ?: '' }}</td>
                                    <td>
                                        @if ($supplier->email)
                                            <a href="mailto:{{ $supplier->email }}" wire:click.stop>{{ $supplier->email }}</a>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($supplier->web_page)
                                            <a href="{{ str_starts_with($supplier->web_page, 'http') ? $supplier->web_page : 'https://'.$supplier->web_page }}" target="_blank" rel="noopener" wire:click.stop>{{ $supplier->web_page }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="7">No suppliers found. Use the <strong>+</strong> button to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$total">
                    @if ($footerNote)
                        <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                    @endif
                    <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Supplier</a>
                    @if ($isPaginated)
                        {{ $suppliers->links() }}
                    @endif
                </x-record-count>
            </div>

            <aside class="desk-rail" aria-label="Supplier actions">
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
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected supplier? This cannot be undone."
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
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
                <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Supplier" aria-label="New Supplier">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('print-supplier', (payload) => {
        const id = payload?.id ?? payload?.[0]?.id;
        if (!id) return;
        const url = @js(url('/purchasing/suppliers')) + '/' + id + '/edit';
        const w = window.open(url, '_blank');
        if (w) {
            w.addEventListener('load', () => {
                try { w.print(); } catch (e) {}
            });
        }
    });
</script>
@endscript
