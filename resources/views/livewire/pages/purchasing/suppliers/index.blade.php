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
            ->orderByDesc('updated_at');

        return [
            'suppliers' => $query->paginate(25),
            'favorites' => [
                'all' => 'All Suppliers',
                'active' => 'Active Suppliers',
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr
                            wire:click="selectRow({{ $supplier->id }})"
                            wire:dblclick="$dispatch('navigate', { url: '{{ route('purchasing.suppliers.edit', $supplier) }}' })"
                            @class(['chief-selected-row' => $selectedId === $supplier->id, 'cursor-pointer'])
                        >
                            <td class="font-mono">{{ $supplier->supplier_id }}</td>
                            <td>
                                <a href="{{ route('purchasing.suppliers.edit', $supplier) }}" wire:navigate class="hover:underline @if($selectedId === $supplier->id) text-white @else text-sky-800 @endif">
                                    {{ $supplier->name }}
                                </a>
                            </td>
                            <td>{{ $supplier->address }}</td>
                            <td>{{ $supplier->phone1 }}</td>
                            <td>{{ $supplier->email }}</td>
                            <td>
                                @if ($supplier->web_page)
                                    <a href="{{ Str::startsWith($supplier->web_page, 'http') ? $supplier->web_page : 'https://'.$supplier->web_page }}" target="_blank" class="@if($selectedId === $supplier->id) text-white underline @else text-sky-700 underline @endif">
                                        {{ $supplier->web_page }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-2 py-6 text-slate-500">No suppliers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$suppliers->total()">
            <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="chief-btn-primary">New Supplier</a>
        </x-record-count>
    </div>
</div>
