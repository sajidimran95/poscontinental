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
            ->when($this->search !== '', fn ($q) => $q->looseSearch($this->search))
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
            'bootIds' => $selected,
            'bootKey' => md5(implode('|', [
                $this->search,
                (string) $this->department_id,
                (string) $this->category_id,
                (string) $this->price_level_id,
                $this->includeInactive ? '1' : '0',
                implode(',', $selected),
            ])),
        ];
    }

    /**
     * Enter: exact barcode → filter to that item code. Else keep partial list filter.
     */
    public function scanSearch(?string $code = null): void
    {
        if ($code !== null) {
            $this->search = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '');
        }

        $resolved = trim($this->search);
        if ($resolved === '') {
            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');
        if ($item) {
            $this->search = (string) $item->item_code;
            $this->selectedId = (int) $item->id;
            if (! in_array((int) $item->id, $this->normalizedSelectedIds(), true)) {
                $this->selectedIds[] = $item->id;
            }
        }
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

    /**
     * Save the boxes the screen already checked. Skip the item-list reload so the click stays instant.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncSelection(array $ids): void
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1 || isset($clean[$id])) {
                continue;
            }
            $clean[$id] = $id;
            if (count($clean) >= 500) {
                break;
            }
        }

        $clean = array_values($clean);
        $this->selectedIds = array_map('strval', $clean);
        $this->selectedId = $clean[0] ?? null;
        $this->skipRender();
    }

    public function openSelectedItem(?int $id = null): mixed
    {
        if ($id) {
            $this->selectedId = $id;
        }

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

        return $this->redirect(route('inventory.items.edit', $item), navigate: true);
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
        $selected = $this->normalizedSelectedIds();

        return array_filter([
            'department_ids' => $this->department_id ? [$this->department_id] : null,
            'category_ids' => $this->category_id ? [$this->category_id] : null,
            'price_level_ids' => $this->price_level_id ? [$this->price_level_id] : null,
            'search' => $this->search !== '' ? $this->search : null,
            'include_inactive' => $this->includeInactive ? 1 : null,
            'title' => $this->priceListTitle(),
            'item_ids' => $selected !== [] ? $selected : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    protected function priceListItems(DocumentPdfService $pdfs)
    {
        return $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            $this->department_id,
            $this->category_id,
            $this->search,
            $this->includeInactive,
            $this->department_id ? [$this->department_id] : [],
            $this->category_id ? [$this->category_id] : [],
            $this->normalizedSelectedIds(),
        );
    }

    public function printView(): void
    {
        $this->dispatch('open-price-list-print', url: route('reports.price-list.print', $this->printQuery()));
    }

    public function downloadPdf(DocumentPdfService $pdfs): StreamedResponse
    {
        $items = $this->priceListItems($pdfs);

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
        $selectedIds = $this->normalizedSelectedIds();

        return response()->streamDownload(function () use ($companyId, $departmentId, $categoryId, $search, $includeInactive, $priceLevelId, $selectedIds) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Category', 'Item Number', 'Description', 'List Price']);

            Item::query()
                ->with(['department', 'category', 'prices'])
                ->where('company_id', $companyId)
                ->when(! $includeInactive, fn ($q) => $q->where('is_inactive', false))
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when($search !== '', fn ($q) => $q->looseSearch($search))
                ->when($selectedIds !== [], fn ($q) => $q->whereIn('id', $selectedIds))
                ->orderBy(
                    \App\Models\Category::query()
                        ->select('name')
                        ->whereColumn('categories.id', 'items.category_id')
                )
                ->orderBy('item_code')
                ->chunk(200, function ($rows) use ($out, $priceLevelId) {
                    foreach ($rows as $item) {
                        $list = round((float) $item->list_price, 2);
                        $price = $priceLevelId
                            ? ItemPricing::resolve($item, $priceLevelId, $item->unit_of_measure ?: null)
                            : ($list > 0 ? $list : ItemPricing::resolve($item, null, $item->unit_of_measure ?: null));
                        fputcsv($out, [
                            $item->category?->name ?: 'No Category',
                            $item->item_code,
                            $item->description,
                            number_format($price, 2, '.', ''),
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

        $items = $this->priceListItems($pdfs);
        if ($this->normalizedSelectedIds() !== [] && $items->isEmpty()) {
            session()->flash('status', 'None of the selected items can be emailed.');

            return;
        }

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
        $count = $items->count();
        session()->flash('status', 'Price list emailed to '.$this->emailTo.' ('.$count.' item'.($count === 1 ? '' : 's').').');
    }
}; ?>

@php
    $listAvg = $items->count() ? $items->avg(fn ($i) => (float) ($i->display_price ?? $i->list_price)) : 0;
    $msrpAvg = $items->count() ? $items->avg(fn ($i) => (float) $i->msrp) : 0;
@endphp

<div
    class="desk-page relative"
    x-data='{
        ids: @json($bootIds),
        pageIds: @json($pageIds),
        on: {},
        init() { this.setIds(this.ids); this.$nextTick(() => this.apply()); },
        boot(detail) {
            this.pageIds = (detail.pageIds || []).map(Number);
            this.setIds(detail.ids || []);
            this.$nextTick(() => this.apply());
        },
        setIds(list) {
            const on = {};
            const ids = [];
            (list || []).forEach((raw) => {
                const id = Number(raw);
                if (!id || on[id]) return;
                on[id] = true;
                ids.push(id);
            });
            this.on = on;
            this.ids = ids;
        },
        boxes() { return this.$root.querySelectorAll("tbody .pl-check-col input[type=checkbox]"); },
        recount() {
            const ids = [];
            const on = {};
            this.boxes().forEach((box) => {
                const id = Number(box.dataset.id);
                const row = box.closest("tr");
                if (box.checked && id) {
                    ids.push(id);
                    on[id] = true;
                    row && row.classList.add("is-selected");
                } else if (row) {
                    row.classList.remove("is-selected");
                }
            });
            this.on = on;
            this.ids = ids;
            const head = this.$root.querySelector("[data-pl-all]");
            if (head) {
                const total = this.boxes().length;
                head.checked = total > 0 && ids.length === total;
                head.indeterminate = ids.length > 0 && ids.length < total;
            }
        },
        apply() {
            this.boxes().forEach((box) => { box.checked = !!this.on[Number(box.dataset.id)]; });
            this.recount();
        },
        toggleRow(id, e) {
            if (e.detail > 1) {
                this.$wire.openSelectedItem(Number(id));
                return;
            }
            const box = e.currentTarget.querySelector("input[type=checkbox]");
            if (!box) return;
            box.checked = !box.checked;
            this.recount();
        },
        toggleAll(want) {
            this.boxes().forEach((box) => { box.checked = !!want; });
            this.recount();
        },
        flush() {
            return this.$wire.syncSelection(this.ids.slice());
        }
    }'
    @pl-boot.window="boot($event.detail)"
>
    <style>
        .desk-grid > .desk-table thead th.pl-check-col,
        .desk-grid > .desk-table tbody td.pl-check-col {
            width: 2.5rem;
            min-width: 2.5rem;
            max-width: 2.5rem;
            overflow: visible;
            text-align: center;
            padding: 0.35rem 0.15rem;
        }
        .pl-check-col input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            margin: 0;
            cursor: pointer;
            accent-color: #1e3a5f;
            vertical-align: middle;
        }
        [x-cloak] { display: none !important; }
    </style>
    <span class="hidden" wire:key="pl-boot-{{ $bootKey }}" x-init="$dispatch('pl-boot', { ids: {{ \Illuminate\Support\Js::from($bootIds) }}, pageIds: {{ \Illuminate\Support\Js::from($pageIds) }} })"></span>
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
                        <div class="so-scan-bar" style="min-width:14rem;height:2.05rem">
                            <button
                                type="button"
                                class="so-scan-btn"
                                title="Focus search / Enter for exact code"
                                wire:click="$js('const el=document.getElementById(\'pl-search\'); if(el){ el.focus(); el.select(); }')"
                            >
                                <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <input
                                id="pl-search"
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                wire:keydown.enter.prevent="scanSearch($event.target.value)"
                                class="so-input desk-search"
                                placeholder="Code, UPC, or words in the description"
                                autocomplete="off"
                            />
                        </div>
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
                                <th class="pl-check-col">
                                    <input
                                        type="checkbox"
                                        data-pl-all
                                        @change.stop="toggleAll($event.target.checked)"
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
                                <tr class="cursor-pointer" @click="toggleRow({{ (int) $item->id }}, $event)">
                                    <td class="pl-check-col" @click.stop>
                                        <input
                                            type="checkbox"
                                            data-id="{{ (int) $item->id }}"
                                            @change.stop="recount()"
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
                        <span x-show="ids.length > 0" x-cloak> · <strong x-text="ids.length.toLocaleString() + ' selected'"></strong></span>
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
                <button type="button" class="desk-rail-btn" title="View selected item" aria-label="View selected item" :disabled="ids.length === 0" @click="ids[0] && $wire.openSelectedItem(ids[0])">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" class="desk-rail-btn" title="Print price list" aria-label="Print price list" @click="flush().then(() => $wire.printView())">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" class="desk-rail-btn" title="Email price list" aria-label="Email price list" @click="flush().then(() => $wire.openEmailModal())">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="3.5" width="12" height="9" rx="1"/>
                        <path d="M2.5 4.5L8 9l5.5-4.5"/>
                    </svg>
                </button>
                <button type="button" class="desk-rail-btn" title="Download CSV" aria-label="Download CSV" wire:target="downloadCsv" wire:loading.attr="disabled" @click="flush().then(() => $wire.downloadCsv())">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M8 2.5v8M5 8l3 3 3-3"/>
                        <path d="M3 13h10"/>
                    </svg>
                </button>
                <button type="button" class="desk-rail-btn desk-rail-btn-primary" title="Download PDF" aria-label="Download PDF" wire:target="downloadPdf" wire:loading.attr="disabled" @click="flush().then(() => $wire.downloadPdf())">
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
                    <p class="text-sm" style="margin:0" x-show="ids.length > 0" x-cloak>
                        This email includes only the <strong x-text="ids.length"></strong> selected item(s).
                    </p>
                    <p class="text-sm" style="margin:0" x-show="ids.length === 0">
                        No items are selected, so this email includes the full filtered list.
                    </p>
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
