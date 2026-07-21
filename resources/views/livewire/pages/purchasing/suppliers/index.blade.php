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
                        ->orWhere('phone1', 'like', $term);
                });
            })
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->orderByDesc('updated_at');

        return [
            'suppliers' => $query->paginate(25),
            'favorites' => [
                'all' => 'All Suppliers',
                'active' => 'Active Suppliers',
                'inactive' => 'Inactive Suppliers',
            ],
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
        $supplier = Supplier::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $supplier->update(['is_inactive' => ! $supplier->is_inactive]);
        $this->selectedId = $id;
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Suppliers:" model="search" />

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Suppliers List</div>

        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Supplier ID</th>
                        <th>Company Name</th>
                        <th>Address</th>
                        <th>Telephone</th>
                        <th>Email Address</th>
                        <th>Web Site</th>
                        <th class="text-center">Inactive</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr
                            wire:click="selectRow({{ $supplier->id }})"
                            @class(['chief-selected-row' => $selectedId === $supplier->id, 'cursor-pointer'])
                        >
                            <td class="font-mono">
                                <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate class="hover:underline">{{ $supplier->supplier_id }}</a>
                            </td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->address }}</td>
                            <td>{{ $supplier->phone1 }}</td>
                            <td>{{ $supplier->email }}</td>
                            <td>
                                @if ($supplier->web_page)
                                    <a href="{{ Str::startsWith($supplier->web_page, 'http') ? $supplier->web_page : 'https://'.$supplier->web_page }}" target="_blank" class="text-sky-700 underline" wire:click.stop>
                                        {{ $supplier->web_page }}
                                    </a>
                                @endif
                            </td>
                            <td class="text-center" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click="toggleInactive({{ $supplier->id }})"
                                    class="px-1 text-base leading-none hover:scale-110"
                                    title="{{ $supplier->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                    aria-label="Toggle inactive"
                                >{{ $supplier->is_inactive ? '☑' : '☐' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-6 text-slate-500">No suppliers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$suppliers->total()">
            <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="chief-btn-primary">New Supplier</a>
            {{ $suppliers->links() }}
        </x-record-count>
    </div>
</div>
