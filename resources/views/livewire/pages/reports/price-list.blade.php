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
    /** @var array<int, int|string> */
    public array $department_ids = [];

    /** @var array<int, int|string> */
    public array $category_ids = [];

    /** @var array<int, int|string> */
    public array $price_level_ids = [];

    #[Url]
    public string $search = '';

    public bool $includeInactive = false;

    public bool $showEmailModal = false;

    public ?int $emailCustomerId = null;

    public string $emailTo = '';

    public string $emailSubject = 'Price List';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $deptIds = $this->normalizedIds($this->department_ids);
        $catIds = $this->normalizedIds($this->category_ids);
        $levelIds = $this->normalizedIds($this->price_level_ids);

        $items = Item::query()
            ->with(['prices', 'department', 'category'])
            ->where('company_id', $companyId)
            ->when(! $this->includeInactive, fn ($q) => $q->where('is_inactive', false))
            ->when($deptIds !== [], fn ($q) => $q->whereIn('department_id', $deptIds))
            ->when($catIds !== [], fn ($q) => $q->whereIn('category_id', $catIds))
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
            ->map(function (Item $item) use ($levelIds) {
                if ($levelIds === []) {
                    $item->setAttribute(
                        'display_price',
                        ItemPricing::resolve($item, null, $item->unit_of_measure ?: null)
                    );
                    $item->setAttribute('level_prices', []);
                } else {
                    $levelPrices = [];
                    foreach ($levelIds as $levelId) {
                        $levelPrices[$levelId] = ItemPricing::resolve(
                            $item,
                            $levelId,
                            $item->unit_of_measure ?: null
                        );
                    }
                    $item->setAttribute('level_prices', $levelPrices);
                    $item->setAttribute('display_price', reset($levelPrices) ?: (float) $item->list_price);
                }

                return $item;
            });

        $departments = Department::query()->where('company_id', $companyId)->orderBy('name')->get();
        $priceLevels = PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get();

        return [
            'departments' => $departments,
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($deptIds !== [], fn ($q) => $q->whereIn('department_id', $deptIds))
                ->orderBy('name')
                ->get(),
            'priceLevels' => $priceLevels,
            'selectedLevels' => $priceLevels->whereIn('id', $levelIds)->values(),
            'customers' => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->get(['id', 'customer_id', 'company_name', 'email', 'price_level_id']),
            'items' => $items,
            'deptIds' => $deptIds,
            'catIds' => $catIds,
            'levelIds' => $levelIds,
        ];
    }

    /** @param  array<int, int|string>  $ids */
    protected function normalizedIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function updatedDepartmentIds(): void
    {
        $valid = Category::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->normalizedIds($this->department_ids) !== [], fn ($q) => $q->whereIn(
                'department_id',
                $this->normalizedIds($this->department_ids)
            ))
            ->pluck('id')
            ->all();

        $this->category_ids = array_values(array_intersect(
            $this->normalizedIds($this->category_ids),
            array_map('intval', $valid)
        ));
    }

    public function selectAllDepartments(): void
    {
        $this->department_ids = Department::query()
            ->where('company_id', auth()->user()->company_id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $this->updatedDepartmentIds();
    }

    public function clearDepartments(): void
    {
        $this->department_ids = [];
        $this->category_ids = [];
    }

    public function selectAllCategories(): void
    {
        $deptIds = $this->normalizedIds($this->department_ids);
        $this->category_ids = Category::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($deptIds !== [], fn ($q) => $q->whereIn('department_id', $deptIds))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function clearCategories(): void
    {
        $this->category_ids = [];
    }

    public function selectAllPriceLevels(): void
    {
        $this->price_level_ids = PriceLevel::query()
            ->where('company_id', auth()->user()->company_id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function clearPriceLevels(): void
    {
        $this->price_level_ids = [];
    }

    public function updatedEmailCustomerId($value): void
    {
        $customer = Customer::query()->find($value);
        if ($customer) {
            $this->emailTo = $customer->email ?? '';
            if ($customer->price_level_id && $this->normalizedIds($this->price_level_ids) === []) {
                $this->price_level_ids = [(string) $customer->price_level_id];
            }
        }
    }

    public function clearFilters(): void
    {
        $this->department_ids = [];
        $this->category_ids = [];
        $this->price_level_ids = [];
        $this->search = '';
        $this->includeInactive = false;
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
        $levelIds = $this->normalizedIds($this->price_level_ids);
        if ($levelIds !== []) {
            $names = PriceLevel::query()->whereIn('id', $levelIds)->orderBy('name')->pluck('name')->all();
            if ($names !== []) {
                $title .= ' — '.implode(', ', $names);
            }
        } else {
            $title .= ' — List Price';
        }

        return $title;
    }

    protected function printQuery(): array
    {
        return array_filter([
            'department_ids' => $this->normalizedIds($this->department_ids) ?: null,
            'category_ids' => $this->normalizedIds($this->category_ids) ?: null,
            'price_level_ids' => $this->normalizedIds($this->price_level_ids) ?: null,
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
        $levelIds = $this->normalizedIds($this->price_level_ids);
        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            null,
            null,
            $this->search,
            $this->includeInactive,
            $this->normalizedIds($this->department_ids),
            $this->normalizedIds($this->category_ids),
        );

        return $pdfs->streamDownload(
            $pdfs->priceListPdf($items, auth()->user(), $this->priceListTitle(), null, $levelIds),
            'price-list-'.now()->format('Ymd-His').'.pdf'
        );
    }

    public function downloadCsv(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $departmentIds = $this->normalizedIds($this->department_ids);
        $categoryIds = $this->normalizedIds($this->category_ids);
        $search = $this->search;
        $includeInactive = $this->includeInactive;
        $priceLevelIds = $this->normalizedIds($this->price_level_ids);
        $levels = $priceLevelIds === []
            ? collect()
            : PriceLevel::query()->whereIn('id', $priceLevelIds)->orderBy('name')->get(['id', 'name']);

        return response()->streamDownload(function () use ($companyId, $departmentIds, $categoryIds, $search, $includeInactive, $priceLevelIds, $levels) {
            $out = fopen('php://output', 'w');
            $header = ['Item Code', 'Description', 'UPC', 'Department', 'Category', 'UOM'];
            if ($levels->isEmpty()) {
                $header[] = 'Price';
            } else {
                foreach ($levels as $level) {
                    $header[] = $level->name;
                }
            }
            $header = array_merge($header, ['MSRP', 'Std Cost']);
            fputcsv($out, $header);

            Item::query()
                ->with(['department', 'category', 'prices'])
                ->where('company_id', $companyId)
                ->when(! $includeInactive, fn ($q) => $q->where('is_inactive', false))
                ->when($departmentIds !== [], fn ($q) => $q->whereIn('department_id', $departmentIds))
                ->when($categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->when($search !== '', function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('item_code', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('primary_upc', 'like', $term);
                    });
                })
                ->orderBy('item_code')
                ->chunk(200, function ($rows) use ($out, $priceLevelIds, $levels) {
                    foreach ($rows as $item) {
                        $row = [
                            $item->item_code,
                            $item->description,
                            $item->primary_upc,
                            $item->department?->name,
                            $item->category?->name,
                            $item->unit_of_measure,
                        ];
                        if ($levels->isEmpty()) {
                            $row[] = number_format(
                                ItemPricing::resolve($item, null, $item->unit_of_measure ?: null),
                                2,
                                '.',
                                ''
                            );
                        } else {
                            foreach ($levels as $level) {
                                $row[] = number_format(
                                    ItemPricing::resolve($item, (int) $level->id, $item->unit_of_measure ?: null),
                                    2,
                                    '.',
                                    ''
                                );
                            }
                        }
                        $row[] = number_format((float) $item->msrp, 2, '.', '');
                        $row[] = number_format((float) $item->standard_cost, 2, '.', '');
                        fputcsv($out, $row);
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

        $levelIds = $this->normalizedIds($this->price_level_ids);
        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            null,
            null,
            $this->search,
            $this->includeInactive,
            $this->normalizedIds($this->department_ids),
            $this->normalizedIds($this->category_ids),
        );

        $pdfs->emailPriceList(
            $items,
            $this->emailTo,
            auth()->user(),
            $this->emailSubject ?: $this->priceListTitle(),
            $this->priceListTitle(),
            null,
            $levelIds
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
    <div class="desk-main">
        <x-action-bar title="Price List" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="desk-toolbar rpt-toolbar rpt-toolbar-wrap">
            <div class="rpt-multi">
                <div class="rpt-multi-head">
                    <span class="desk-toolbar-label">Departments</span>
                    <div class="rpt-multi-actions">
                        <button type="button" wire:click="selectAllDepartments" class="desk-btn desk-btn-sm">All</button>
                        <button type="button" wire:click="clearDepartments" class="desk-btn desk-btn-sm">Clear</button>
                    </div>
                </div>
                <div class="rpt-check-list" role="group" aria-label="Departments">
                    @forelse ($departments as $d)
                        <label class="rpt-check-item">
                            <input type="checkbox" wire:model.live="department_ids" value="{{ $d->id }}" />
                            <span>{{ $d->name }}</span>
                        </label>
                    @empty
                        <span class="text-xs text-slate-500">No departments</span>
                    @endforelse
                </div>
                <p class="rpt-multi-hint">{{ $deptIds === [] ? 'All departments' : count($deptIds).' selected' }}</p>
            </div>

            <div class="rpt-multi">
                <div class="rpt-multi-head">
                    <span class="desk-toolbar-label">Categories</span>
                    <div class="rpt-multi-actions">
                        <button type="button" wire:click="selectAllCategories" class="desk-btn desk-btn-sm">All</button>
                        <button type="button" wire:click="clearCategories" class="desk-btn desk-btn-sm">Clear</button>
                    </div>
                </div>
                <div class="rpt-check-list" role="group" aria-label="Categories">
                    @forelse ($categories as $c)
                        <label class="rpt-check-item">
                            <input type="checkbox" wire:model.live="category_ids" value="{{ $c->id }}" />
                            <span>{{ $c->name }}</span>
                        </label>
                    @empty
                        <span class="text-xs text-slate-500">No categories</span>
                    @endforelse
                </div>
                <p class="rpt-multi-hint">{{ $catIds === [] ? 'All categories' : count($catIds).' selected' }}</p>
            </div>

            <div class="rpt-multi">
                <div class="rpt-multi-head">
                    <span class="desk-toolbar-label">Price Levels</span>
                    <div class="rpt-multi-actions">
                        <button type="button" wire:click="selectAllPriceLevels" class="desk-btn desk-btn-sm">All</button>
                        <button type="button" wire:click="clearPriceLevels" class="desk-btn desk-btn-sm">Clear</button>
                    </div>
                </div>
                <div class="rpt-check-list" role="group" aria-label="Price Levels">
                    @forelse ($priceLevels as $pl)
                        <label class="rpt-check-item">
                            <input type="checkbox" wire:model.live="price_level_ids" value="{{ $pl->id }}" />
                            <span>{{ $pl->name }}</span>
                        </label>
                    @empty
                        <span class="text-xs text-slate-500">No price levels — using List Price</span>
                    @endforelse
                </div>
                <p class="rpt-multi-hint">{{ $levelIds === [] ? 'List Price (default)' : count($levelIds).' level(s) selected' }}</p>
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
                <button type="button" wire:click="printView" class="desk-btn" title="Open print view">Print View</button>
                <button type="button" wire:click="downloadCsv" class="desk-btn" wire:loading.attr="disabled">CSV</button>
                <button type="button" wire:click="openEmailModal" class="desk-btn">Email</button>
                <button type="button" wire:click="downloadPdf" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="downloadPdf">Download PDF</span>
                    <span wire:loading wire:target="downloadPdf">Building PDF…</span>
                </button>
            </div>
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
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Department</th>
                        <th>Category</th>
                        <th>UOM</th>
                        @if ($selectedLevels->isEmpty())
                            <th class="text-right">Price</th>
                        @else
                            @foreach ($selectedLevels as $level)
                                <th class="text-right">{{ $level->name }}</th>
                            @endforeach
                        @endif
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
                            @if ($selectedLevels->isEmpty())
                                <td class="desk-money">${{ number_format((float) ($item->display_price ?? $item->list_price), 2) }}</td>
                            @else
                                @foreach ($selectedLevels as $level)
                                    <td class="desk-money">${{ number_format((float) ($item->level_prices[$level->id] ?? $item->list_price), 2) }}</td>
                                @endforeach
                            @endif
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
                            <td colspan="{{ $selectedLevels->isEmpty() ? 10 : (9 + $selectedLevels->count()) }}">No items match the filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="desk-footer">
            <span>{{ number_format($items->count()) }} item(s) shown</span>
            <div class="desk-footer-actions">
                <button type="button" wire:click="printView" class="desk-btn desk-btn-sm">Print View</button>
                <button type="button" wire:click="openEmailModal" class="desk-btn desk-btn-sm">Email to Customer</button>
                <button type="button" wire:click="downloadCsv" class="desk-btn desk-btn-sm">Download CSV</button>
                <button type="button" wire:click="downloadPdf" class="desk-btn desk-btn-sm desk-btn-primary">Download PDF</button>
            </div>
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
