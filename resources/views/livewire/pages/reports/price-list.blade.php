<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Item;
use App\Models\PriceLevel;
use App\Services\DocumentPdfService;
use App\Support\ItemPricing;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('Price List')] class extends Component
{
    #[Url]
    public ?int $department_id = null;

    #[Url]
    public ?int $category_id = null;

    #[Url]
    public ?int $price_level_id = null;

    #[Url]
    public string $search = '';

    public bool $includeInactive = false;

    public bool $showEmailModal = false;

    public ?int $emailCustomerId = null;

    public string $emailTo = '';

    public string $emailSubject = 'Price List';

    public ?int $selectedId = null;

    /** @var array<int, int|string> */
    public array $selectedIds = [];

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $items = Item::query()
            ->with(['prices', 'department', 'category'])
            ->where('company_id', $companyId)
            ->when(! $this->includeInactive, fn ($q) => $q->where('is_inactive', false))
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term);
                });
            })
            ->orderBy('item_code')
            ->limit(500)
            ->get()
            ->map(function (Item $item) {
                $item->setAttribute(
                    'display_price',
                    ItemPricing::resolve($item, $this->price_level_id, $item->unit_of_measure ?: null)
                );

                return $item;
            });

        $pageIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = $this->normalizedSelectedIds();
        $allSelected = $pageIds !== [] && count(array_intersect($selected, $pageIds)) === count($pageIds);
        $selectedSet = array_fill_keys($selected, true);

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'customers' => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->get(['id', 'customer_id', 'company_name', 'email', 'price_level_id']),
            'items' => $items,
            'pageIds' => $pageIds,
            'allSelected' => $allSelected,
            'selectedCount' => count($selected),
            'selectedSet' => $selectedSet,
        ];
    }

    /** @return array<int, int> */
    public function normalizedSelectedIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->selectedIds))));
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
        $this->clearSelection();
    }

    public function updatedCategoryId(): void
    {
        $this->clearSelection();
    }

    public function updatedPriceLevelId(): void
    {
        $this->clearSelection();
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
    }

    public function selectRow(int $id): void
    {
        $ids = $this->normalizedSelectedIds();
        if (in_array($id, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($x) => $x !== $id));
        } else {
            $ids[] = $id;
        }
        $this->selectedIds = array_map('strval', $ids);
        $this->selectedId = $ids[0] ?? null;
    }

    public function toggleSelectAll(): void
    {
        $companyId = auth()->user()->company_id;
        $pageIds = Item::query()
            ->where('company_id', $companyId)
            ->when(! $this->includeInactive, fn ($q) => $q->where('is_inactive', false))
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term);
                });
            })
            ->orderBy('item_code')
            ->limit(500)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selected = $this->normalizedSelectedIds();
        $allSelected = $pageIds !== [] && count(array_intersect($selected, $pageIds)) === count($pageIds);

        if ($allSelected) {
            $this->clearSelection();

            return;
        }

        $this->selectedIds = array_map('strval', $pageIds);
        $this->selectedId = $pageIds[0] ?? null;
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->selectedIds = [];
    }

    public function updatedEmailCustomerId($value): void
    {
        $customer = Customer::query()->find($value);
        if ($customer) {
            $this->emailTo = $customer->email ?? '';
            if ($customer->price_level_id && ! $this->price_level_id) {
                $this->price_level_id = $customer->price_level_id;
            }
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['department_id', 'category_id', 'price_level_id', 'search', 'includeInactive', 'selectedId', 'selectedIds']);
    }

    public function openSelectedItem(): mixed
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

        return $this->redirect(route('inventory.items.show', $item), navigate: true);
    }

    public function refreshList(): void
    {
        $this->clearSelection();
    }

    public function openEmailModal(): void
    {
        $this->showEmailModal = true;
        $this->emailSubject = $this->priceListTitle();
    }

    public function closeEmailModal(): void
    {
        $this->showEmailModal = false;
    }

    protected function priceListTitle(): string
    {
        $title = 'Price List';
        if ($this->price_level_id) {
            $level = PriceLevel::query()->find($this->price_level_id);
            $title .= $level ? ' — '.$level->name : '';
        } else {
            $title .= ' — List Price';
        }

        return $title;
    }

    protected function printQuery(): array
    {
        return array_filter([
            'department_ids' => $this->department_id ? [$this->department_id] : null,
            'category_ids' => $this->category_id ? [$this->category_id] : null,
            'price_level_ids' => $this->price_level_id ? [$this->price_level_id] : null,
            'search' => $this->search !== '' ? $this->search : null,
            'include_inactive' => $this->includeInactive ? 1 : null,
            'title' => $this->priceListTitle(),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function printView(): void
    {
        $this->dispatch('open-price-list-print', url: route('reports.price-list.print', $this->printQuery()));
    }

    public function downloadPdf(DocumentPdfService $pdfs): StreamedResponse
    {
        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            $this->department_id,
            $this->category_id,
            $this->search,
            $this->includeInactive,
            $this->department_id ? [$this->department_id] : [],
            $this->category_id ? [$this->category_id] : [],
        );

        return $pdfs->streamDownload(
            $pdfs->priceListPdf(
                $items,
                auth()->user(),
                $this->priceListTitle(),
                $this->price_level_id,
                $this->price_level_id ? [$this->price_level_id] : []
            ),
            'price-list-'.now()->format('Ymd-His').'.pdf'
        );
    }

    public function downloadCsv(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $departmentId = $this->department_id;
        $categoryId = $this->category_id;
        $search = $this->search;
        $includeInactive = $this->includeInactive;
        $priceLevelId = $this->price_level_id;

        return response()->streamDownload(function () use ($companyId, $departmentId, $categoryId, $search, $includeInactive, $priceLevelId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item Code', 'Description', 'UPC', 'Department', 'Category', 'UOM', 'Price', 'MSRP', 'Std Cost']);

            Item::query()
                ->with(['department', 'category', 'prices'])
                ->where('company_id', $companyId)
                ->when(! $includeInactive, fn ($q) => $q->where('is_inactive', false))
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when($search !== '', function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('item_code', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('primary_upc', 'like', $term);
                    });
                })
                ->orderBy('item_code')
                ->chunk(200, function ($rows) use ($out, $priceLevelId) {
                    foreach ($rows as $item) {
                        $price = ItemPricing::resolve($item, $priceLevelId, $item->unit_of_measure ?: null);
                        fputcsv($out, [
                            $item->item_code,
                            $item->description,
                            $item->primary_upc,
                            $item->department?->name,
                            $item->category?->name,
                            $item->unit_of_measure,
                            number_format($price, 2, '.', ''),
                            number_format((float) $item->msrp, 2, '.', ''),
                            number_format((float) $item->standard_cost, 2, '.', ''),
                        ]);
                    }
                });

            fclose($out);
        }, 'price-list-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function emailPriceList(DocumentPdfService $pdfs): void
    {
        $this->validate([
            'emailTo' => 'required|email',
            'emailSubject' => 'nullable|string|max:255',
        ]);

        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            $this->department_id,
            $this->category_id,
            $this->search,
            $this->includeInactive,
            $this->department_id ? [$this->department_id] : [],
            $this->category_id ? [$this->category_id] : [],
        );

        $pdfs->emailPriceList(
            $items,
            $this->emailTo,
            auth()->user(),
            $this->emailSubject ?: $this->priceListTitle(),
            $this->priceListTitle(),
            $this->price_level_id,
            $this->price_level_id ? [$this->price_level_id] : []
        );

        $this->showEmailModal = false;
        session()->flash('status', 'Price list emailed to '.$this->emailTo);
    }
}; ?>

@php
    $listAvg = $items->count() ? $items->avg(fn ($i) => (float) ($i->display_price ?? $i->list_price)) : 0;
    $msrpAvg = $items->count() ? $items->avg(fn ($i) => (float) $i->msrp) : 0;
@endphp

<div class="desk-page relative">
    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Price List" />

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar rpt-toolbar">
                    <div class="rpt-field">
                        <label class="desk-toolbar-label" for="pl-dept">Department</label>
                        <select id="pl-dept" wire:model.live="department_id" class="desk-select">
                            <option value="">All departments</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rpt-field">
                        <label class="desk-toolbar-label" for="pl-cat">Category</label>
                        <select id="pl-cat" wire:model.live="category_id" class="desk-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rpt-field">
                        <label class="desk-toolbar-label" for="pl-level">Price Level</label>
                        <select id="pl-level" wire:model.live="price_level_id" class="desk-select">
                            <option value="">List Price</option>
                            @foreach ($priceLevels as $pl)
                                <option value="{{ $pl->id }}">{{ $pl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rpt-field rpt-field-search">
                        <label class="desk-toolbar-label" for="pl-search">Search</label>
                        <input
                            id="pl-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            class="desk-search"
                            placeholder="Code, description, UPC…"
                        />
                    </div>
                    <label class="entity-check rpt-check">
                        <input type="checkbox" wire:model.live="includeInactive" />
                        Include inactive
                    </label>
                </div>

                <div class="desk-titlebar">
                    <div>
                        <h2 class="desk-title">Price List</h2>
                        <span class="desk-title-meta">Preview up to 500 matching items · Print/PDF includes up to 2000</span>
                    </div>
                    <div class="rpt-stats">
                        <div class="rpt-stat">
                            <span class="rpt-stat-lbl">Items</span>
                            <span class="rpt-stat-val">{{ number_format($items->count()) }}</span>
                        </div>
                        <div class="rpt-stat">
                            <span class="rpt-stat-lbl">Avg Price</span>
                            <span class="rpt-stat-val">${{ number_format($listAvg, 2) }}</span>
                        </div>
                        <div class="rpt-stat">
                            <span class="rpt-stat-lbl">Avg MSRP</span>
                            <span class="rpt-stat-val">${{ number_format($msrpAvg, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="desk-grid">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2.25rem">
                                    <input
                                        type="checkbox"
                                        wire:click.prevent="toggleSelectAll"
                                        @checked($allSelected)
                                        title="Select all"
                                        aria-label="Select all items"
                                    />
                                </th>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>UPC</th>
                                <th>Department</th>
                                <th>Category</th>
                                <th>UOM</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">MSRP</th>
                                <th class="text-right">Std Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                @php $isSelected = isset($selectedSet[(int) $item->id]); @endphp
                                <tr
                                    wire:click="selectRow({{ $item->id }})"
                                    wire:dblclick="openSelectedItem"
                                    @class(['is-selected' => $isSelected, 'cursor-pointer'])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="checkbox"
                                            value="{{ $item->id }}"
                                            @checked($isSelected)
                                            wire:click="selectRow({{ $item->id }})"
                                            aria-label="Select {{ $item->item_code }}"
                                        />
                                    </td>
                                    <td class="desk-num">{{ $item->item_code }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="desk-num">{{ $item->primary_upc ?: '—' }}</td>
                                    <td>{{ $item->department?->name ?: '—' }}</td>
                                    <td>{{ $item->category?->name ?: '—' }}</td>
                                    <td>{{ $item->unit_of_measure ?: '—' }}</td>
                                    <td class="desk-money">${{ number_format((float) ($item->display_price ?? $item->list_price), 2) }}</td>
                                    <td class="desk-money">${{ number_format((float) $item->msrp, 2) }}</td>
                                    <td class="desk-money">${{ number_format((float) $item->standard_cost, 2) }}</td>
                                    <td>
                                        @if ($item->is_inactive)
                                            <span class="desk-pill desk-pill-muted">Inactive</span>
                                        @else
                                            <span class="desk-pill desk-pill-invoiced">Active</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="11">No items match the filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="desk-footer">
                    <span>
                        {{ number_format($items->count()) }} item(s) shown
                        @if ($selectedCount > 0)
                            · <strong>{{ number_format($selectedCount) }} selected</strong>
                        @endif
                    </span>
                </div>
            </div>

            <aside class="desk-rail" aria-label="Price list actions">
                <button type="button" wire:click="clearFilters" class="desk-rail-btn" title="Clear filters / new search" aria-label="Clear filters">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                        <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button type="button" wire:click="openSelectedItem" class="desk-rail-btn" title="View selected item" aria-label="View selected item" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="printView" class="desk-rail-btn" title="Print price list" aria-label="Print price list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="openEmailModal" class="desk-rail-btn" title="Email price list" aria-label="Email price list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="3.5" width="12" height="9" rx="1"/>
                        <path d="M2.5 4.5L8 9l5.5-4.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="downloadCsv" class="desk-rail-btn" title="Download CSV" aria-label="Download CSV" wire:loading.attr="disabled">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M8 2.5v8M5 8l3 3 3-3"/>
                        <path d="M3 13h10"/>
                    </svg>
                </button>
                <button type="button" wire:click="downloadPdf" class="desk-rail-btn desk-rail-btn-primary" title="Download PDF" aria-label="Download PDF" wire:loading.attr="disabled">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 2.5h5l3 3V13.5H4V2.5z"/>
                        <path d="M9 2.5V6h3"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
            </aside>
        </div>
    </div>

    @if ($showEmailModal)
        <div class="desk-modal-backdrop" wire:click.self="closeEmailModal" role="dialog" aria-modal="true" aria-label="Email price list">
            <div class="desk-modal" style="max-width:28rem">
                <div class="desk-modal-head">
                    <span>Email Price List</span>
                    <button type="button" wire:click="closeEmailModal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form wire:submit="emailPriceList" class="desk-modal-body" style="display:grid;gap:0.75rem">
                    <div>
                        <label class="desk-toolbar-label" for="pl-email-cust">Customer</label>
                        <select id="pl-email-cust" wire:model.live="emailCustomerId" class="desk-select" style="width:100%">
                            <option value="">— Select customer —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="desk-toolbar-label" for="pl-email-to">Email</label>
                        <input id="pl-email-to" type="email" wire:model="emailTo" class="desk-search" style="width:100%" required />
                        @error('emailTo') <p class="text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="desk-toolbar-label" for="pl-email-subj">Subject</label>
                        <input id="pl-email-subj" type="text" wire:model="emailSubject" class="desk-search" style="width:100%" />
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" wire:click="closeEmailModal" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Send PDF</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('open-price-list-print', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
@endscript
