<?php

use App\Models\BulkPriceChangeLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Bulk Pricing')] class extends Component
{
    public string $activeTab = 'update';

    public string $brand = '';

    public string $item_type = '';

    public ?int $department_id = null;

    public ?int $category_id = null;

    public ?int $subcategory_id = null;

    public string $search = '';

    public bool $apply_list_price = true;

    public bool $apply_standard_cost = false;

    public bool $apply_current_cost = false;

    public string $adjustment_type = 'percent';

    public string $adjustment_value = '0';

    public bool $confirming = false;

    public string $status = '';

    public ?int $historyDetailId = null;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;
        $items = $this->filteredQuery($companyId)->limit(400)->get();

        $value = is_numeric($this->adjustment_value) ? (float) $this->adjustment_value : 0.0;
        $targets = $this->activeTargets();
        $selectedSet = collect($this->selectedIds)->map(fn ($id) => (int) $id)->unique()->all();
        $visibleIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedVisibleCount = count(array_intersect($selectedSet, $visibleIds));
        $allVisibleSelected = $items->isNotEmpty() && $selectedVisibleCount === $items->count();

        $preview = $items->map(function (Item $item) use ($targets, $value, $selectedSet) {
            $fields = [];
            foreach ($targets as $field) {
                $before = (float) $item->{$field};
                $after = $this->computeAfter($before, $this->adjustment_type, $value);
                $fields[$field] = [
                    'before' => $before,
                    'after' => $after,
                    'delta' => $after - $before,
                ];
            }

            return [
                'item' => $item,
                'fields' => $fields,
                'selected' => in_array((int) $item->id, $selectedSet, true),
            ];
        });

        $confirmSample = [];
        if ($this->confirming && $selectedSet !== []) {
            $confirmSample = $preview
                ->filter(fn ($row) => $row['selected'])
                ->take(12)
                ->values()
                ->all();
        }

        $history = BulkPriceChangeLog::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $historyDetail = null;
        if ($this->historyDetailId) {
            $historyDetail = BulkPriceChangeLog::query()
                ->with(['user', 'items' => fn ($q) => $q->orderBy('item_code')->limit(200)])
                ->where('company_id', $companyId)
                ->find($this->historyDetailId);
        }

        $brands = Item::query()
            ->where('company_id', $companyId)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->limit(300)
            ->pluck('manufacturer');

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'subcategories' => Subcategory::query()
                ->where('company_id', $companyId)
                ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
                ->orderBy('name')
                ->get(),
            'itemTypes' => ItemType::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'brands' => $brands,
            'items' => $items,
            'preview' => $preview,
            'matchCount' => $items->count(),
            'selectedCount' => count($selectedSet),
            'selectedVisibleCount' => $selectedVisibleCount,
            'allVisibleSelected' => $allVisibleSelected,
            'visibleIds' => $visibleIds,
            'activeTargets' => $targets,
            'confirmSample' => $confirmSample,
            'history' => $history,
            'historyDetail' => $historyDetail,
            'adjustLabel' => match ($this->adjustment_type) {
                'amount' => 'Flat amount (+/-)',
                'set' => 'Set exact value',
                default => 'Percent change (+/-)',
            },
            'targetLabels' => [
                'list_price' => 'List Price',
                'standard_cost' => 'Standard Cost',
                'current_cost' => 'Current Cost',
            ],
        ];
    }

    protected function filteredQuery(int $companyId): Builder
    {
        return Item::query()
            ->with(['department', 'category', 'subcategory'])
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->when($this->brand !== '', fn ($q) => $q->where('manufacturer', $this->brand))
            ->when($this->item_type !== '', fn ($q) => $q->where('item_type', $this->item_type))
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->subcategory_id, fn ($q) => $q->where('subcategory_id', $this->subcategory_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term);
                });
            })
            ->orderBy('item_code');
    }

    /** @return list<string> */
    protected function activeTargets(): array
    {
        $targets = [];
        if ($this->apply_list_price) {
            $targets[] = 'list_price';
        }
        if ($this->apply_standard_cost) {
            $targets[] = 'standard_cost';
        }
        if ($this->apply_current_cost) {
            $targets[] = 'current_cost';
        }

        return $targets;
    }

    public function updatedBrand(): void
    {
        $this->resetSelectionState();
    }

    public function updatedItemType(): void
    {
        $this->resetSelectionState();
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
        $this->subcategory_id = null;
        $this->resetSelectionState();
    }

    public function updatedCategoryId(): void
    {
        $this->subcategory_id = null;
        $this->resetSelectionState();
    }

    public function updatedSubcategoryId(): void
    {
        $this->resetSelectionState();
    }

    public function updatedSearch(): void
    {
        $this->resetSelectionState();
    }

    public function clearFilters(): void
    {
        $this->reset(['brand', 'item_type', 'department_id', 'category_id', 'subcategory_id', 'search', 'selectedIds']);
        $this->confirming = false;
        $this->status = '';
    }

    public function toggleSelectAll(array $visibleIds = []): void
    {
        $visibleIds = collect($visibleIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        if ($visibleIds === []) {
            return;
        }

        $selected = collect($this->selectedIds)->map(fn ($id) => (int) $id)->unique();
        $allSelected = collect($visibleIds)->every(fn ($id) => $selected->contains($id));

        if ($allSelected) {
            $this->selectedIds = $selected->reject(fn ($id) => in_array($id, $visibleIds, true))->values()->all();
        } else {
            $this->selectedIds = $selected->merge($visibleIds)->unique()->values()->all();
        }

        $this->confirming = false;
        $this->status = '';
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->confirming = false;
        $this->status = '';
    }

    public function openConfirm(): void
    {
        $this->validate([
            'adjustment_type' => 'required|in:percent,amount,set',
            'adjustment_value' => 'required|numeric',
            'selectedIds' => 'required|array|min:1',
        ], [
            'selectedIds.required' => 'Select at least one item before continuing.',
            'selectedIds.min' => 'Select at least one item before continuing.',
        ]);

        if ($this->activeTargets() === []) {
            $this->addError('targets', 'Select at least one adjustment target (List Price, Standard Cost, and/or Current Cost).');

            return;
        }

        $this->confirming = true;
        $this->status = '';
    }

    public function cancelConfirm(): void
    {
        $this->confirming = false;
    }

    public function apply(): void
    {
        if (! $this->confirming) {
            $this->openConfirm();

            return;
        }

        $this->validate([
            'adjustment_type' => 'required|in:percent,amount,set',
            'adjustment_value' => 'required|numeric',
            'selectedIds' => 'required|array|min:1',
        ]);

        $targets = $this->activeTargets();
        if ($targets === []) {
            $this->addError('targets', 'Select at least one adjustment target.');

            return;
        }

        $companyId = (int) auth()->user()->company_id;
        $value = (float) $this->adjustment_value;
        $type = $this->adjustment_type;
        $ids = collect($this->selectedIds)->map(fn ($id) => (int) $id)->unique()->filter()->values()->all();
        $affected = 0;

        DB::transaction(function () use ($companyId, $ids, $targets, $type, $value, &$affected) {
            $log = BulkPriceChangeLog::query()->create([
                'company_id' => $companyId,
                'user_id' => auth()->id(),
                'filter_criteria' => [
                    'brand' => $this->brand ?: null,
                    'item_type' => $this->item_type ?: null,
                    'department_id' => $this->department_id,
                    'category_id' => $this->category_id,
                    'subcategory_id' => $this->subcategory_id,
                    'search' => $this->search ?: null,
                    'selected_ids' => $ids,
                ],
                'adjustment_type' => $type,
                'adjustment_value' => $value,
                'targets' => $targets,
                'items_affected' => 0,
            ]);

            Item::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use ($targets, $type, $value, $log, &$affected) {
                    foreach ($chunk as $item) {
                        $updates = [];
                        $audit = [
                            'bulk_price_change_log_id' => $log->id,
                            'item_id' => $item->id,
                            'item_code' => $item->item_code,
                            'list_price_before' => null,
                            'list_price_after' => null,
                            'standard_cost_before' => null,
                            'standard_cost_after' => null,
                            'current_cost_before' => null,
                            'current_cost_after' => null,
                        ];

                        foreach ($targets as $field) {
                            $before = (float) $item->{$field};
                            $after = $this->computeAfter($before, $type, $value);
                            $updates[$field] = $after;
                            $audit[$field.'_before'] = $before;
                            $audit[$field.'_after'] = $after;
                        }

                        $item->update($updates);
                        $log->items()->create($audit);
                        $affected++;
                    }
                });

            $log->update(['items_affected' => $affected]);
        });

        $this->selectedIds = [];
        $this->confirming = false;
        $this->status = "Applied successfully — {$affected} item(s) updated. See Change History for the audit trail.";
        $this->activeTab = 'history';
    }

    public function viewHistory(int $id): void
    {
        $this->historyDetailId = $id;
        $this->activeTab = 'history';
    }

    public function closeHistoryDetail(): void
    {
        $this->historyDetailId = null;
    }

    private function resetSelectionState(): void
    {
        $this->selectedIds = [];
        $this->confirming = false;
        $this->status = '';
    }

    private function computeAfter(float $before, string $type, float $value): float
    {
        $after = match ($type) {
            'percent' => round($before * (1 + ($value / 100)), 4),
            'amount' => round($before + $value, 4),
            default => round($value, 4),
        };

        return max(0, $after);
    }
}; ?>

<div class="desk-page">
    <div class="desk-main">
        <x-action-bar title="Bulk Pricing Update" />

        <div class="entity-tabs bp-tabs" role="tablist">
            <button type="button" wire:click="$set('activeTab', 'update')" @class(['entity-tab', 'is-active' => $activeTab === 'update']) role="tab">Update</button>
            <button type="button" wire:click="$set('activeTab', 'history')" @class(['entity-tab', 'is-active' => $activeTab === 'history']) role="tab">Change History</button>
        </div>

        @if ($status)
            <div class="desk-flash bp-flash">{{ $status }}</div>
        @endif

        @error('adjustment_value') <div class="desk-flash bp-flash-error">{{ $message }}</div> @enderror
        @error('selectedIds') <div class="desk-flash bp-flash-error">{{ $message }}</div> @enderror
        @error('targets') <div class="desk-flash bp-flash-error">{{ $message }}</div> @enderror

        @if ($activeTab === 'update')
            <div class="bp-howto">
                <div class="bp-howto-title">Bulk Pricing Update (Cost & Sales)</div>
                <ol class="bp-howto-steps">
                    <li><strong>Filter</strong> by Brand, Item Type, Department / Category / Sub Category (filters combine with AND).</li>
                    <li><strong>Select</strong> which items to change, then choose <strong>targets</strong> (List Price / Standard Cost / Current Cost).</li>
                    <li>Enter a <strong>% or flat amount</strong>, review the preview, then <strong>Review &amp; Confirm</strong> before committing.</li>
                </ol>
                <div class="bp-howto-examples">
                    <span><strong>% Change</strong> — <code>10</code> = +10%, <code>-5</code> = −5%</span>
                    <span><strong>Flat amount</strong> — <code>0.50</code> adds 50¢, <code>-1</code> subtracts $1</span>
                    <span><strong>Set To</strong> — <code>9.99</code> sets selected targets to $9.99</span>
                </div>
            </div>

            <div class="desk-toolbar rpt-toolbar bp-toolbar">
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-brand">Brand</label>
                    <select id="bp-brand" wire:model.live="brand" class="desk-select">
                        <option value="">All brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-type-filter">Item Type</label>
                    <select id="bp-type-filter" wire:model.live="item_type" class="desk-select">
                        <option value="">All types</option>
                        @foreach ($itemTypes as $t)
                            <option value="{{ $t->name }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-dept">Department</label>
                    <select id="bp-dept" wire:model.live="department_id" class="desk-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-cat">Category</label>
                    <select id="bp-cat" wire:model.live="category_id" class="desk-select">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-sub">Sub Category</label>
                    <select id="bp-sub" wire:model.live="subcategory_id" class="desk-select">
                        <option value="">All sub categories</option>
                        @foreach ($subcategories as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-field rpt-field-search">
                    <label class="desk-toolbar-label" for="bp-search">Search</label>
                    <input id="bp-search" type="search" wire:model.live.debounce.300ms="search" class="desk-search" placeholder="Code, description, UPC…" />
                </div>
            </div>

            <div class="desk-toolbar rpt-toolbar bp-toolbar bp-toolbar-adjust">
                <div class="rpt-field bp-targets">
                    <span class="desk-toolbar-label">Apply to</span>
                    <div class="bp-target-checks">
                        <label class="entity-check"><input type="checkbox" wire:model.live="apply_list_price" /> List Price</label>
                        <label class="entity-check"><input type="checkbox" wire:model.live="apply_standard_cost" /> Standard Cost</label>
                        <label class="entity-check"><input type="checkbox" wire:model.live="apply_current_cost" /> Current Cost</label>
                    </div>
                </div>
                <div class="rpt-field">
                    <label class="desk-toolbar-label" for="bp-adj">Adjustment</label>
                    <select id="bp-adj" wire:model.live="adjustment_type" class="desk-select">
                        <option value="percent">% Change</option>
                        <option value="amount">Flat amount (+/-)</option>
                        <option value="set">Set To</option>
                    </select>
                </div>
                <div class="rpt-field bp-value-field">
                    <label class="desk-toolbar-label" for="bp-value">Value</label>
                    <input id="bp-value" wire:model.live="adjustment_value" class="desk-search text-right @error('adjustment_value') is-invalid @enderror" inputmode="decimal" />
                </div>
                <div class="rpt-actions">
                    <button type="button" wire:click="clearFilters" class="desk-btn">Clear filters</button>
                    <button type="button" wire:click="clearSelection" class="desk-btn" @disabled($selectedCount === 0)>Clear selection</button>
                    <button type="button" wire:click="openConfirm" class="desk-btn desk-btn-primary" @disabled($selectedCount === 0 || count($activeTargets) === 0)>
                        Review &amp; Confirm ({{ number_format($selectedCount) }})
                    </button>
                </div>
            </div>

            <div class="desk-titlebar">
                <div>
                    <h2 class="desk-title">Preview</h2>
                    <span class="desk-title-meta">{{ $adjustLabel }} · value {{ $adjustment_value }} · only checked rows are updated</span>
                </div>
                <div class="rpt-stats">
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Matching</span>
                        <span class="rpt-stat-val">{{ number_format($matchCount) }}</span>
                    </div>
                    <div class="rpt-stat">
                        <span class="rpt-stat-lbl">Selected</span>
                        <span class="rpt-stat-val">{{ number_format($selectedCount) }}</span>
                    </div>
                </div>
            </div>

            <div class="desk-grid">
                <table class="desk-table">
                    <thead>
                        <tr>
                            <th class="bp-check-col">
                                <input type="checkbox" wire:click="toggleSelectAll(@js($visibleIds))" @checked($allVisibleSelected) @disabled($matchCount === 0) aria-label="Select all visible" />
                            </th>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Brand</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th class="text-right">List</th>
                            <th class="text-right">Std Cost</th>
                            <th class="text-right">Current</th>
                            @foreach ($activeTargets as $field)
                                <th class="text-right">New {{ $targetLabels[$field] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($preview as $row)
                            @php $item = $row['item']; @endphp
                            <tr @class(['is-selected' => $row['selected']])>
                                <td class="bp-check-col">
                                    <input type="checkbox" value="{{ $item->id }}" wire:model.live="selectedIds" aria-label="Select {{ $item->item_code }}" />
                                </td>
                                <td class="desk-num">{{ $item->item_code }}</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->manufacturer ?: '—' }}</td>
                                <td>{{ $item->item_type ?: '—' }}</td>
                                <td>{{ $item->department?->name ?: '—' }}</td>
                                <td class="desk-money">${{ number_format((float) $item->list_price, 2) }}</td>
                                <td class="desk-money">${{ number_format((float) $item->standard_cost, 2) }}</td>
                                <td class="desk-money">${{ number_format((float) $item->current_cost, 2) }}</td>
                                @foreach ($activeTargets as $field)
                                    @php $f = $row['fields'][$field] ?? null; @endphp
                                    <td class="desk-money bp-new">
                                        @if ($f)
                                            ${{ number_format($f['after'], 2) }}
                                            <span @class(['bp-delta', 'bp-up' => $f['delta'] > 0, 'bp-down' => $f['delta'] < 0])>
                                                @if ($f['delta'] > 0)+{{ number_format($f['delta'], 2) }}
                                                @elseif ($f['delta'] < 0){{ number_format($f['delta'], 2) }}
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr class="is-empty">
                                <td colspan="{{ 9 + count($activeTargets) }}">No matching active items. Adjust filters to continue.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="desk-footer">
                <span>{{ number_format($selectedVisibleCount) }} of {{ number_format($matchCount) }} visible selected · {{ number_format($selectedCount) }} total</span>
                <div class="desk-footer-actions">
                    <button type="button" wire:click="toggleSelectAll(@js($visibleIds))" class="desk-btn desk-btn-sm" @disabled($matchCount === 0)>
                        {{ $allVisibleSelected ? 'Deselect all' : 'Select all visible' }}
                    </button>
                    <button type="button" wire:click="openConfirm" class="desk-btn desk-btn-sm desk-btn-primary" @disabled($selectedCount === 0)>Review &amp; Confirm</button>
                </div>
            </div>

            @if ($confirming)
                <div class="bp-confirm-overlay" wire:click.self="cancelConfirm">
                    <div class="bp-confirm-card" role="dialog" aria-modal="true" aria-labelledby="bp-confirm-title">
                        <h3 id="bp-confirm-title" class="bp-confirm-title">Confirm bulk price change</h3>
                        <p class="bp-confirm-lead">
                            You are about to update <strong>{{ number_format($selectedCount) }}</strong> item(s)
                            using <strong>{{ $adjustLabel }}</strong> of <strong>{{ $adjustment_value }}</strong>
                            on: <strong>{{ collect($activeTargets)->map(fn ($t) => $targetLabels[$t])->implode(', ') }}</strong>.
                        </p>
                        <p class="bp-confirm-note">Sample before → after (first {{ count($confirmSample) }} selected):</p>
                        <div class="bp-confirm-grid">
                            <table class="desk-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        @foreach ($activeTargets as $field)
                                            <th class="text-right">{{ $targetLabels[$field] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($confirmSample as $row)
                                        <tr>
                                            <td>
                                                <span class="desk-num">{{ $row['item']->item_code }}</span>
                                                <span class="bp-confirm-desc">{{ \Illuminate\Support\Str::limit($row['item']->description, 40) }}</span>
                                            </td>
                                            @foreach ($activeTargets as $field)
                                                @php $f = $row['fields'][$field]; @endphp
                                                <td class="desk-money">
                                                    ${{ number_format($f['before'], 2) }}
                                                    →
                                                    <strong class="bp-new">${{ number_format($f['after'], 2) }}</strong>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="bp-confirm-actions">
                            <button type="button" wire:click="cancelConfirm" class="desk-btn">Cancel</button>
                            <button type="button" wire:click="apply" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="apply">Commit changes</span>
                                <span wire:loading wire:target="apply">Applying…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

        @else
            <div class="desk-titlebar">
                <div>
                    <h2 class="desk-title">Change History</h2>
                    <span class="desk-title-meta">Who ran each update, filters used, and items affected</span>
                </div>
            </div>

            <div class="desk-grid">
                <table class="desk-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Adjustment</th>
                            <th>Targets</th>
                            <th>Filters</th>
                            <th class="text-right">Items</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $log)
                            @php
                                $filters = $log->filter_criteria ?? [];
                                $filterBits = collect([
                                    filled($filters['brand'] ?? null) ? 'Brand: '.$filters['brand'] : null,
                                    filled($filters['item_type'] ?? null) ? 'Type: '.$filters['item_type'] : null,
                                    filled($filters['search'] ?? null) ? 'Search: '.$filters['search'] : null,
                                    filled($filters['department_id'] ?? null) ? 'Dept #'.$filters['department_id'] : null,
                                    filled($filters['category_id'] ?? null) ? 'Cat #'.$filters['category_id'] : null,
                                    filled($filters['subcategory_id'] ?? null) ? 'Sub #'.$filters['subcategory_id'] : null,
                                ])->filter()->implode(' · ') ?: 'Selected items only';
                            @endphp
                            <tr>
                                <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $log->user?->name ?: '—' }}</td>
                                <td>
                                    {{ $log->adjustment_type }}
                                    {{ $log->adjustment_type === 'percent' ? $log->adjustment_value.'%' : '$'.number_format((float) $log->adjustment_value, 2) }}
                                </td>
                                <td>{{ collect($log->targets ?? [])->map(fn ($t) => $targetLabels[$t] ?? $t)->implode(', ') }}</td>
                                <td>{{ $filterBits }}</td>
                                <td class="text-right">{{ number_format($log->items_affected) }}</td>
                                <td>
                                    <button type="button" wire:click="viewHistory({{ $log->id }})" class="desk-btn desk-btn-sm">Details</button>
                                </td>
                            </tr>
                        @empty
                            <tr class="is-empty"><td colspan="7">No bulk price changes logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($historyDetail)
                <div class="bp-history-detail">
                    <div class="desk-titlebar">
                        <div>
                            <h2 class="desk-title">Run detail — {{ $historyDetail->created_at?->format('Y-m-d H:i') }}</h2>
                            <span class="desk-title-meta">{{ $historyDetail->user?->name }} · {{ number_format($historyDetail->items_affected) }} items</span>
                        </div>
                        <button type="button" wire:click="closeHistoryDetail" class="desk-btn desk-btn-sm">Close</button>
                    </div>
                    <div class="desk-grid">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th class="text-right">List before</th>
                                    <th class="text-right">List after</th>
                                    <th class="text-right">Std before</th>
                                    <th class="text-right">Std after</th>
                                    <th class="text-right">Curr before</th>
                                    <th class="text-right">Curr after</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($historyDetail->items as $line)
                                    <tr>
                                        <td class="desk-num">{{ $line->item_code }}</td>
                                        <td class="desk-money">{{ $line->list_price_before !== null ? '$'.number_format((float) $line->list_price_before, 2) : '—' }}</td>
                                        <td class="desk-money bp-new">{{ $line->list_price_after !== null ? '$'.number_format((float) $line->list_price_after, 2) : '—' }}</td>
                                        <td class="desk-money">{{ $line->standard_cost_before !== null ? '$'.number_format((float) $line->standard_cost_before, 2) : '—' }}</td>
                                        <td class="desk-money bp-new">{{ $line->standard_cost_after !== null ? '$'.number_format((float) $line->standard_cost_after, 2) : '—' }}</td>
                                        <td class="desk-money">{{ $line->current_cost_before !== null ? '$'.number_format((float) $line->current_cost_before, 2) : '—' }}</td>
                                        <td class="desk-money bp-new">{{ $line->current_cost_after !== null ? '$'.number_format((float) $line->current_cost_after, 2) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty"><td colspan="7">No item rows stored for this run.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
