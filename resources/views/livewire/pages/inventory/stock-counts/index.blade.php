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

    public ?int $selectedId = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $hasSearch = $this->search !== '';

        $query = StockCount::query()
            ->with(['site', 'processedByUser'])
            ->where('company_id', $companyId)
            ->when($hasSearch, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('stock_count_no', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('site', fn ($s) => $s->where('code', 'like', $term)->orWhere('name', 'like', $term))
                        ->orWhereHas('processedByUser', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'processed', fn ($q) => $q->where('status', 'Processed'))
            ->orderByDesc('id');

        // Chief: with no search criteria, show 10 most recently updated
        if (! $hasSearch && $this->favorite === 'all') {
            $counts = $query->limit(10)->get();
            $total = $counts->count();
            $footerNote = '10 most recently updated records with no search criteria.';
        } else {
            $counts = $query->paginate(50);
            $total = $counts->total();
            $footerNote = null;
        }

        $listTitle = match ($this->favorite) {
            'new' => 'Stock Counts List (New)',
            'processed' => 'Stock Counts List (Processed)',
            default => 'Stock Counts List',
        };

        return [
            'counts' => $counts,
            'total' => $total,
            'footerNote' => $footerNote,
            'isPaginated' => $hasSearch || $this->favorite !== 'all',
            'favorites' => [
                'all' => 'All Stock Counts',
                'new' => 'New',
                'processed' => 'Processed',
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
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function openSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a stock count first.');

            return null;
        }

        return $this->openCount($this->selectedId);
    }

    public function editSelected(): mixed
    {
        return $this->openSelected();
    }

    public function openCount(int $id): mixed
    {
        $count = StockCount::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $count) {
            session()->flash('status', 'Stock count not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('inventory.stock-counts.edit', $count), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a stock count first.');

            return;
        }

        $count = StockCount::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $count) {
            session()->flash('status', 'Stock count not found.');

            return;
        }

        if ($count->status === 'Processed') {
            session()->flash('status', 'Processed stock counts cannot be deleted.');

            return;
        }

        $count->lines()->delete();
        $count->delete();
        $this->selectedId = null;
        session()->flash('status', 'Stock count deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a stock count first.');

            return;
        }

        $this->dispatch('print-stock-count', id: $this->selectedId);
    }

    public function process(int $id): void
    {
        $count = StockCount::query()->findOrFail($id);
        abort_unless($count->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processStockCount($count);
        $this->selectedId = $id;
        session()->flash('status', 'Stock count '.$count->stock_count_no.' processed.');
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
                    <label class="desk-toolbar-label" for="stock-counts-search">Search Stock Counts:</label>
                    <input
                        id="stock-counts-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Count #, site, description, processed by…"
                        class="desk-search orders-search-input"
                        aria-label="Search Stock Counts"
                    />

                    <div class="orders-toolbar-right">
                        <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                            <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                            </svg>
                            New Search
                        </button>
                        <button type="button" class="desk-btn" title="Saved Search" disabled>
                            Saved Search
                        </button>
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

                <div class="desk-grid">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
                                <th>Stock Count #</th>
                                <th class="text-center">Status</th>
                                <th>Description</th>
                                <th>Date Created</th>
                                <th>Last Count Date</th>
                                <th>Date Entered</th>
                                <th>Site</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($counts as $count)
                                <tr
                                    wire:click="selectRow({{ $count->id }})"
                                    wire:dblclick="openCount({{ $count->id }})"
                                    @class(['is-selected' => $selectedId === $count->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="stock_count_select"
                                            value="{{ $count->id }}"
                                            @checked($selectedId === $count->id)
                                            wire:click="selectRow({{ $count->id }})"
                                            aria-label="Select stock count {{ $count->stock_count_no }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a href="{{ route('inventory.stock-counts.edit', $count) }}" wire:navigate wire:click.stop>{{ $count->stock_count_no }}</a>
                                    </td>
                                    <td class="text-center">
                                        <span @class([
                                            'desk-pill',
                                            'desk-pill-new' => $count->status === 'New',
                                            'desk-pill-invoiced' => $count->status === 'Processed',
                                            'desk-pill-muted' => ! in_array($count->status, ['New', 'Processed'], true),
                                        ])>{{ $count->status }}</span>
                                    </td>
                                    <td title="{{ $count->description }}">{{ $count->description ? \Illuminate\Support\Str::limit($count->description, 40) : '' }}</td>
                                    <td>{{ optional($count->date_created)?->format('n/j/Y g:i:s A') ?: '—' }}</td>
                                    <td>{{ optional($count->last_count_date)?->format('n/j/Y g:i:s A') ?: '—' }}</td>
                                    <td>{{ optional($count->date_entered ?? $count->created_at)?->format('n/j/Y g:i:s A') ?: '—' }}</td>
                                    <td class="desk-num">{{ $count->site?->code ?: '—' }}</td>
                                    <td>{{ $count->processedByUser?->name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="9">No stock counts found. Use the <strong>+</strong> button to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$total">
                    @if ($footerNote)
                        <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                    @endif
                    <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Stock Count</a>
                    @if ($isPaginated)
                        {{ $counts->links() }}
                    @endif
                </x-record-count>
            </div>

            {{-- Right rail: document, pen, print, delete, refresh, + --}}
            <aside class="desk-rail" aria-label="Stock count actions">
                <button type="button" wire:click="openSelected" class="desk-rail-btn" title="Open selected" aria-label="Open selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 2.5h5.5L13 6v7.5a1 1 0 01-1 1H4a1 1 0 01-1-1v-10a1 1 0 011-1z"/>
                        <path d="M9.5 2.5V6H13"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected stock count? This cannot be undone."
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
                <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Stock Count" aria-label="New Stock Count">
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
    $wire.on('print-stock-count', (payload) => {
        const id = payload?.id ?? payload?.[0]?.id;
        if (!id) return;
        const url = @js(url('/inventory/stock-counts')) + '/' + id + '/edit';
        const w = window.open(url, '_blank');
        if (w) {
            w.addEventListener('load', () => {
                try { w.print(); } catch (e) {}
            });
        }
    });
</script>
@endscript
