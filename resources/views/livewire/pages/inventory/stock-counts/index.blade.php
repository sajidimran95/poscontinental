<?php

use App\Livewire\Concerns\CustomizesDeskListColumns;
use App\Livewire\Concerns\InteractsWithDeskQuery;
use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\PersistsDeskTabSearch;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\StockCount;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithoutUrlPagination;

new #[Layout('layouts.app'), Title('Stock Counts')] class extends Component
{
    use WithoutUrlPagination;
    use InteractsWithDeskQuery;
    use SortsDeskList;
    use PaginatesDeskLists;
    use CustomizesDeskListColumns;
    use PersistsDeskTabSearch;

    public string $search = '';

    #[Url(history: false)]
    public string $favorite = 'all';

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->bootDeskListColumns();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $hasSearch = $this->search !== '';

        $hasQuery = $this->queryCriteria !== [];

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
            ->when($hasQuery, fn ($q) => $this->applyQueryCriteria($q));

        $query = $this->applyDeskSort($query, 'date_created', 'desc');

        $scroll = $this->scrollDeskList($query);
        $counts = $scroll['rows'];
        $total = $scroll['shown'];
        $footerNote = null;
        $listHasMore = $scroll['hasMore'];

        $listTitle = match ($this->favorite) {
            'new' => 'Stock Counts List (New)',
            'processed' => 'Stock Counts List (Processed)',
            default => 'Stock Counts List',
        };

        if ($hasQuery) {
            $listTitle = $this->queryLoadedName !== ''
                ? 'Query: '.$this->queryLoadedName
                : 'Query Results ('.count($this->queryCriteria).' criteria)';
        }

        return [
            'counts' => $counts,
            'total' => $total,
            'footerNote' => $footerNote,
            'listHasMore' => $listHasMore,
            'favorites' => [
                'all' => 'All Stock Counts',
                'new' => 'New',
                'processed' => 'Processed',
            ],
            'listTitle' => $listTitle,
            'queryFields' => $this->deskQueryFieldOptions(),
            'queryFieldTypes' => $this->deskQueryFieldTypes(),
            'queryOperators' => $this->deskQueryOperatorOptions(),
            'savedDeskQueries' => $this->loadSavedDeskQueries(),
            'deskQueryTitle' => 'Stock Count Query',
        ] + $this->deskListColumnViewData(1);
    }

    protected function deskListColumnCatalog(): array
    {
        return [
            'stock_count_no' => ['label' => 'Stock Count #'],
            'status' => ['label' => 'Status', 'type' => 'center'],
            'description' => ['label' => 'Description'],
            'date_created' => ['label' => 'Date Created'],
            'last_count_date' => ['label' => 'Last Count Date'],
            'date_entered' => ['label' => 'Date Entered'],
            'date_processed' => ['label' => 'Date Processed'],
            'site' => ['label' => 'Site'],
            'processed_by' => ['label' => 'Processed By'],
        ];
    }

    protected function defaultVisibleColumns(): array
    {
        return array_keys($this->deskListColumnCatalog());
    }

    protected function visibleColumnsSessionKey(): string
    {
        return 'stock_counts_list_columns_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    /** @return array<string, array{label: string, column: string, has?: string, type?: string}> */
    protected function deskQueryFields(): array
    {
        return [
            'stock_count_no' => ['label' => 'Stock Count #', 'column' => 'stock_count_no'],
            'status' => ['label' => 'Status', 'column' => 'status'],
            'description' => ['label' => 'Description', 'column' => 'description'],
            'date_created' => ['label' => 'Date Created', 'column' => 'date_created', 'type' => 'date'],
            'last_count_date' => ['label' => 'Last Count Date', 'column' => 'last_count_date', 'type' => 'date'],
            'date_entered' => ['label' => 'Date Entered', 'column' => 'date_entered', 'type' => 'date'],
            'date_processed' => ['label' => 'Date Processed', 'column' => 'date_processed', 'type' => 'date'],
            'comments' => ['label' => 'Comments', 'column' => 'comments'],
            'site_code' => ['label' => 'Site Code', 'has' => 'site', 'column' => 'code'],
            'site_name' => ['label' => 'Site Name', 'has' => 'site', 'column' => 'name'],
            'processed_by_name' => ['label' => 'Processed By', 'has' => 'processedByUser', 'column' => 'name'],
            'item_code' => ['label' => 'Item Code', 'has' => 'lines', 'column' => 'item_code'],
            'item_description' => ['label' => 'Item Description', 'has' => 'lines', 'column' => 'description'],
            'in_stock' => ['label' => 'In Stock (line)', 'has' => 'lines', 'column' => 'in_stock', 'type' => 'number'],
            'allocated' => ['label' => 'Allocated (line)', 'has' => 'lines', 'column' => 'allocated', 'type' => 'number'],
            'counted' => ['label' => 'Counted (line)', 'has' => 'lines', 'column' => 'counted', 'type' => 'number'],
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'stock_count_no' => 'stock_count_no',
            'status' => 'status',
            'description' => 'description',
            'date_created' => 'date_created',
            'last_count_date' => 'last_count_date',
            'date_entered' => 'date_entered',
            'date_processed' => 'date_processed',
            'site' => ['relation' => 'site', 'column' => 'code'],
            'processed_by' => ['relation' => 'processedByUser', 'column' => 'name'],
        ];
    }

    protected function deskQuerySessionKey(): string
    {
        return 'stock_counts_query_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    /** Always available in Load saved search (cannot be deleted). */
    protected function builtInDeskQueries(): array
    {
        return [
            'In stock less than zero' => [
                [
                    'field' => 'in_stock',
                    'operator' => 'lt',
                    'value' => '0',
                    'join' => 'and',
                    'label' => '( In Stock (line) | Less than | 0 )',
                ],
            ],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetDeskList();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->clearQueryCriteria();
        $this->resetDeskList();
    }

    public function openDeskQuery(): void
    {
        $this->showQueryModal = true;
        $this->queryStatus = '';
        $fields = $this->deskQueryFields();
        if ($this->queryField === '' || ! isset($fields[$this->queryField])) {
            $this->queryField = (string) (array_key_first($fields) ?? '');
            if ($this->queryField !== '') {
                $this->syncQueryOperatorForField($this->queryField);
            }
        }
    }

    public function refreshList(): void
    {
        $this->resetDeskList();
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
        if (! auth()->user()?->canAccessFeature('inventory.stock_counts', 'delete')) {
            session()->flash('status', 'Your role cannot delete stock counts.');

            return;
        }

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

    public function createNewCount(): mixed
    {
        return $this->redirect(route('inventory.stock-counts.create'), navigate: true);
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
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
        <x-action-bar title="Action">
            <x-slot:menu>
                <x-action-item label="Add New Stock Count" kbd="Ctrl+N" wire:click="createNewCount" />
                <x-action-item label="View/Edit Selected Stock Count" kbd="Ctrl+E" sep wire:click="editSelected" />
                <x-action-item label="Delete Selected Stock Count" sep wire:click="deleteSelected" />
                <x-action-item label="Print" sep wire:click="printSelected" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar" wire:ignore>
                    <label class="desk-toolbar-label" for="stock-counts-search">Search Stock Counts:</label>
                    <input
                        id="stock-counts-search" data-pos-search
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Count #, site, description, processed by…"
                        class="desk-search orders-search-input"
                        aria-label="Search Stock Counts"
                    />
                    <button type="button" wire:click="openDeskQuery" class="desk-btn items-query-btn" title="Query by field">Query</button>
                    @if ($queryCriteria !== [])
                        <button type="button" wire:click="clearQueryCriteria" class="desk-btn desk-btn-sm" title="Clear query">Clear Query</button>
                    @endif

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

                <x-desk-scroll-grid :has-more="$listHasMore">
                    <table class="desk-table desk-table-resizable" data-col-resize="stock-counts-list" data-excel-grid data-excel-copy-all>
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem" data-excel-skip></th>
                                <x-desk-list-col-headers :catalog="$listColumnCatalog" :keys="$visibleColumnKeys" />
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($counts as $count)
                                <tr
                                    wire:click="selectRow({{ $count->id }})"
                                    wire:dblclick="openCount({{ $count->id }})"
                                    @class(['is-selected' => $selectedId === $count->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" data-excel-skip wire:click.stop>
                                        <input
                                            type="radio"
                                            name="stock_count_select"
                                            value="{{ $count->id }}"
                                            @checked($selectedId === $count->id)
                                            wire:click="selectRow({{ $count->id }})"
                                            aria-label="Select stock count {{ $count->stock_count_no }}"
                                        />
                                    </td>
                                    @foreach ($visibleColumnKeys as $colKey)
                                        @include('livewire.pages.inventory.stock-counts.partials.list-cell', ['count' => $count, 'colKey' => $colKey])
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="{{ $columnColspan }}">No stock counts found. Use the <strong>+</strong> button to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-desk-scroll-grid>

                <x-record-count :count="$total">
                    @if ($footerNote)
                        <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                    @endif
                    <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Stock Count</a>
                    <x-desk-load-more :has-more="$listHasMore" />
                </x-record-count>
            </div>

            {{-- Right rail: document, pen, print, delete, refresh, + --}}
            <aside class="desk-rail" aria-label="Stock count actions">
                <x-desk-fields-rail-btn />
                <button type="button" wire:click="openDeskQuery" class="desk-rail-btn" title="Query stock counts by field" aria-label="Query stock counts">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <circle cx="7" cy="7" r="4.5"/>
                        <path d="M10.5 10.5L14 14"/>
                        <path d="M5.2 7h3.6M7 5.2v3.6" stroke-width="1.3"/>
                    </svg>
                </button>
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
    @include('livewire.partials.desk-query-modal')
    <x-desk-column-picker :catalog="$listColumnCatalog" :visible-keys="$visibleColumnKeys" locked="stock_count_no" />
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
