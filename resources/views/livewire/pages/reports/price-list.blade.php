<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\PriceLevel;
use App\Services\DocumentPdfService;
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
            ->get();

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'items' => $items,
        ];
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['department_id', 'category_id', 'price_level_id', 'search', 'includeInactive']);
    }

    public function downloadPdf(DocumentPdfService $pdfs): StreamedResponse
    {
        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            $this->department_id,
            $this->category_id,
            $this->search,
            $this->includeInactive
        );

        $title = 'Price List';
        if ($this->price_level_id) {
            $level = PriceLevel::query()->find($this->price_level_id);
            $title .= $level ? ' — '.$level->name : '';
        }

        return $pdfs->streamDownload(
            $pdfs->priceListPdf($items, auth()->user(), $title),
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

        return response()->streamDownload(function () use ($companyId, $departmentId, $categoryId, $search, $includeInactive) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item Code', 'Description', 'UPC', 'Department', 'Category', 'UOM', 'List Price', 'MSRP', 'Std Cost']);

            Item::query()
                ->with(['department', 'category'])
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
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $item) {
                        fputcsv($out, [
                            $item->item_code,
                            $item->description,
                            $item->primary_upc,
                            $item->department?->name,
                            $item->category?->name,
                            $item->unit_of_measure,
                            number_format((float) $item->list_price, 2, '.', ''),
                            number_format((float) $item->msrp, 2, '.', ''),
                            number_format((float) $item->standard_cost, 2, '.', ''),
                        ]);
                    }
                });

            fclose($out);
        }, 'price-list-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}; ?>

@php
    $listAvg = $items->count() ? $items->avg(fn ($i) => (float) $i->list_price) : 0;
    $msrpAvg = $items->count() ? $items->avg(fn ($i) => (float) $i->msrp) : 0;
@endphp

<div class="desk-page">
    <div class="desk-main">
        <x-action-bar title="Price List" />

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
            <div class="rpt-actions">
                <button type="button" wire:click="clearFilters" class="desk-btn">Clear</button>
                <button type="button" wire:click="downloadCsv" class="desk-btn" wire:loading.attr="disabled">CSV</button>
                <button type="button" wire:click="downloadPdf" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="downloadPdf">Download PDF</span>
                    <span wire:loading wire:target="downloadPdf">Building PDF…</span>
                </button>
            </div>
        </div>

        <div class="desk-titlebar">
            <div>
                <h2 class="desk-title">Price List</h2>
                <span class="desk-title-meta">Preview up to 500 matching items</span>
            </div>
            <div class="rpt-stats">
                <div class="rpt-stat">
                    <span class="rpt-stat-lbl">Items</span>
                    <span class="rpt-stat-val">{{ number_format($items->count()) }}</span>
                </div>
                <div class="rpt-stat">
                    <span class="rpt-stat-lbl">Avg List</span>
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
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Department</th>
                        <th>Category</th>
                        <th>UOM</th>
                        <th class="text-right">List Price</th>
                        <th class="text-right">MSRP</th>
                        <th class="text-right">Std Cost</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="desk-num">{{ $item->item_code }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="desk-num">{{ $item->primary_upc ?: '—' }}</td>
                            <td>{{ $item->department?->name ?: '—' }}</td>
                            <td>{{ $item->category?->name ?: '—' }}</td>
                            <td>{{ $item->unit_of_measure ?: '—' }}</td>
                            <td class="desk-money">${{ number_format((float) $item->list_price, 2) }}</td>
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
                            <td colspan="10">No items match the filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="desk-footer">
            <span>{{ number_format($items->count()) }} item(s) shown</span>
            <div class="desk-footer-actions">
                <button type="button" wire:click="downloadCsv" class="desk-btn desk-btn-sm">Download CSV</button>
                <button type="button" wire:click="downloadPdf" class="desk-btn desk-btn-sm desk-btn-primary">Download PDF</button>
            </div>
        </div>
    </div>
</div>
