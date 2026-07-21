<?php

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

    public ?int $selectedId = null;

    public function with(): array
    {
        $query = Supplier::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('supplier_id', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone1', 'like', $term)
                        ->orWhere('contact_name', 'like', $term);
                });
            })
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->favorite === 'tobacco', fn ($q) => $q->where('is_tobacco_supplier', true))
            ->orderBy('supplier_id');

        $listTitle = match ($this->favorite) {
            'active' => 'Active Suppliers',
            'inactive' => 'Inactive Suppliers',
            'tobacco' => 'Tobacco Suppliers',
            default => 'Suppliers',
        };

        return [
            'suppliers' => $query->paginate(50),
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
        $supplier = Supplier::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $supplier->update(['is_inactive' => ! $supplier->is_inactive]);
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        <x-list-chrome label="Search Suppliers:" model="search" placeholder="ID, name, phone, email…">
            <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Supplier</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($suppliers->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Supplier ID</th>
                        <th>Company Name</th>
                        <th>Address</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th class="text-center">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr
                            wire:click="selectRow({{ $supplier->id }})"
                            @class(['is-selected' => $selectedId === $supplier->id, 'cursor-pointer'])
                        >
                            <td class="desk-num">
                                <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate wire:click.stop>{{ $supplier->supplier_id }}</a>
                            </td>
                            <td>{{ $supplier->name }}</td>
                            <td title="{{ $supplier->address }}">{{ \Illuminate\Support\Str::limit($supplier->address, 36) }}</td>
                            <td>{{ $supplier->phone1 }}</td>
                            <td>{{ $supplier->email }}</td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleInactive({{ $supplier->id }})"
                                    @class([
                                        'desk-pill',
                                        'desk-pill-muted' => $supplier->is_inactive,
                                        'desk-pill-invoiced' => ! $supplier->is_inactive,
                                    ])
                                    title="{{ $supplier->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                    aria-label="Toggle inactive"
                                >{{ $supplier->is_inactive ? 'Inactive' : 'Active' }}</button>
                            </td>
                            <td wire:click.stop>
                                <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate class="desk-btn desk-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="7">No suppliers found. Click <strong>New Supplier</strong> to create one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$suppliers->total()">
            <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Supplier</a>
            {{ $suppliers->links() }}
        </x-record-count>
    </div>
</div>
