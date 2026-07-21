<?php

use App\Models\StockCount;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Stock Counts')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = StockCount::query()
            ->with('site')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('stock_count_no', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('site', fn ($s) => $s->where('code', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'processed', fn ($q) => $q->where('status', 'Processed'))
            ->orderByDesc('id');

        $listTitle = match ($this->favorite) {
            'new' => 'New Stock Counts',
            'processed' => 'Processed Stock Counts',
            default => 'Stock Counts',
        };

        return [
            'counts' => $query->paginate(50),
            'favorites' => [
                'all' => 'All Counts',
                'new' => 'New',
                'processed' => 'Processed',
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
    }

    public function process(int $id): void
    {
        $count = StockCount::query()->findOrFail($id);
        abort_unless($count->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processStockCount($count);
        session()->flash('status', 'Stock count '.$count->stock_count_no.' processed.');
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <x-list-chrome label="Search Stock Counts:" model="search" placeholder="Count #, site, description…">
            <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="desk-btn desk-btn-primary ms-auto">New Stock Count</a>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($counts->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Stock Count No.</th>
                        <th>Date Created</th>
                        <th class="text-center">Status</th>
                        <th>Site</th>
                        <th>Description</th>
                        <th>Date Processed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        <tr>
                            <td class="desk-num">
                                <a href="{{ route('inventory.stock-counts.edit', $count) }}" wire:navigate>{{ $count->stock_count_no }}</a>
                            </td>
                            <td>{{ optional($count->date_created)?->format('n/j/Y') }}</td>
                            <td class="text-center">
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-new' => $count->status === 'New',
                                    'desk-pill-invoiced' => $count->status === 'Processed',
                                    'desk-pill-muted' => ! in_array($count->status, ['New', 'Processed'], true),
                                ])>{{ $count->status }}</span>
                            </td>
                            <td class="desk-num">{{ $count->site?->code ?: '—' }}</td>
                            <td title="{{ $count->description }}">{{ \Illuminate\Support\Str::limit($count->description, 40) }}</td>
                            <td>{{ optional($count->date_processed)?->format('n/j/Y') ?: '—' }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('inventory.stock-counts.edit', $count) }}" wire:navigate class="desk-btn desk-btn-sm">
                                        {{ $count->status === 'Processed' ? 'View' : 'Edit' }}
                                    </a>
                                    @if ($count->status !== 'Processed')
                                        <button
                                            type="button"
                                            wire:click="process({{ $count->id }})"
                                            wire:confirm="Process count and update In Stock?"
                                            class="desk-btn desk-btn-sm desk-btn-primary"
                                        >Process</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="7">No stock counts found. Click <strong>New Stock Count</strong> to start one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$counts->total()">
            <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Stock Count</a>
            {{ $counts->links() }}
        </x-record-count>
    </div>
</div>
