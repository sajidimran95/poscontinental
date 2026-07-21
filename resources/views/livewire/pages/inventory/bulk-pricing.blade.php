<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Bulk Pricing')] class extends Component
{
    public ?int $department_id = null;

    public ?int $category_id = null;

    public string $search = '';

    public string $target = 'list_price';

    public string $adjustment_type = 'percent';

    public string $adjustment_value = '0';

    public string $status = '';

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $items = Item::query()
            ->with(['department', 'category'])
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
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
            ->limit(300)
            ->get();

        $value = is_numeric($this->adjustment_value) ? (float) $this->adjustment_value : 0.0;
        $field = $this->target;
        $type = $this->adjustment_type;
        $visibleIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedSet = collect($this->selectedIds)->map(fn ($id) => (int) $id)->unique()->all();
        $selectedVisibleCount = count(array_intersect($selectedSet, $visibleIds));
        $allVisibleSelected = $items->isNotEmpty() && $selectedVisibleCount === $items->count();

        $preview = $items->map(function (Item $item) use ($field, $type, $value, $selectedSet) {
            $before = (float) $item->{$field};
            $after = $this->computeAfter($before, $type, $value);

            return [
                'item' => $item,
                'before' => $before,
                'after' => $after,
                'delta' => $after - $before,
                'selected' => in_array((int) $item->id, $selectedSet, true),
            ];
        });

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'items' => $items,
            'preview' => $preview,
            'matchCount' => $items->count(),
            'selectedCount' => count($selectedSet),
            'selectedVisibleCount' => $selectedVisibleCount,
            'allVisibleSelected' => $allVisibleSelected,
            'visibleIds' => $visibleIds,
            'fieldLabel' => match ($this->target) {
                'msrp' => 'MSRP',
                'standard_cost' => 'Standard Cost',
                'current_cost' => 'Current Cost',
                default => 'List Price',
            },
            'adjustLabel' => match ($this->adjustment_type) {
                'amount' => 'Add/Subtract Amount',
                'set' => 'Set Exact Value',
                default => 'Percent Change',
            },
        ];
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
        $this->selectedIds = [];
        $this->status = '';
    }

    public function updatedCategoryId(): void
    {
        $this->selectedIds = [];
        $this->status = '';
    }

    public function updatedSearch(): void
    {
        $this->selectedIds = [];
        $this->status = '';
    }

    public function clearFilters(): void
    {
        $this->reset(['department_id', 'category_id', 'search', 'selectedIds']);
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

        $this->status = '';
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->status = '';
    }

    public function apply(): void
    {
        $this->validate([
            'adjustment_type' => 'required|in:percent,amount,set',
            'adjustment_value' => 'required|numeric',
            'target' => 'required|in:list_price,msrp,standard_cost,current_cost',
            'selectedIds' => 'required|array|min:1',
        ], [
            'selectedIds.required' => 'Select at least one item before applying.',
            'selectedIds.min' => 'Select at least one item before applying.',
        ]);

        $companyId = auth()->user()->company_id;
        $value = (float) $this->adjustment_value;
        $field = $this->target;
        $type = $this->adjustment_type;
        $ids = collect($this->selectedIds)->map(fn ($id) => (int) $id)->unique()->filter()->values()->all();
        $affected = 0;

        $query = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->whereIn('id', $ids);

        DB::transaction(function () use ($query, $field, $type, $value, &$affected, $companyId, $ids) {
            $logId = null;
            if (Schema::hasTable('bulk_price_change_logs')) {
                $logId = DB::table('bulk_price_change_logs')->insertGetId([
                    'company_id' => $companyId,
                    'user_id' => auth()->id(),
                    'filter_criteria' => json_encode([
                        'department_id' => $this->department_id,
                        'category_id' => $this->category_id,
                        'search' => $this->search,
                        'target' => $field,
                        'selected_ids' => $ids,
                    ]),
                    'adjustment_type' => $type,
                    'adjustment_value' => $value,
                    'targets' => json_encode([$field]),
                    'items_affected' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $query->orderBy('id')->chunkById(100, function ($chunk) use ($field, $type, $value, &$affected, $logId) {
                foreach ($chunk as $item) {
                    $before = (float) $item->{$field};
                    $after = $this->computeAfter($before, $type, $value);
                    $item->update([$field => $after]);
                    $affected++;

                    if ($logId && Schema::hasTable('bulk_price_change_items')) {
                        $row = [
                            'bulk_price_change_log_id' => $logId,
                            'item_id' => $item->id,
                            'list_price_before' => null,
                            'list_price_after' => null,
                            'standard_cost_before' => null,
                            'standard_cost_after' => null,
                            'current_cost_before' => null,
                            'current_cost_after' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if ($field === 'list_price') {
                            $row['list_price_before'] = $before;
                            $row['list_price_after'] = $after;
                        } elseif ($field === 'standard_cost') {
                            $row['standard_cost_before'] = $before;
                            $row['standard_cost_after'] = $after;
                        } elseif ($field === 'current_cost') {
                            $row['current_cost_before'] = $before;
                            $row['current_cost_after'] = $after;
                        }
                        DB::table('bulk_price_change_items')->insert($row);
                    }
                }
            });

            if ($logId) {
                DB::table('bulk_price_change_logs')->where('id', $logId)->update(['items_affected' => $affected]);
            }
        });

        $this->selectedIds = [];
        $this->status = "Applied successfully — {$affected} selected item(s) updated.";
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
        <x-action-bar title="Bulk Pricing" />

        <div class="bp-howto">
            <div class="bp-howto-title">How to apply</div>
            <ol class="bp-howto-steps">
                <li><strong>Filter</strong> items by department, category, or search (only active items).</li>
                <li><strong>Select</strong> the items to update (checkbox), or use Select all.</li>
                <li>Choose the <strong>price field</strong> and <strong>adjustment</strong> (% / amount / set).</li>
                <li>Review the <strong>New</strong> column, then click <strong>Apply</strong> (selected rows only).</li>
            </ol>
            <div class="bp-howto-examples">
                <span><strong>% Change</strong> — <code>10</code> = +10%, <code>-5</code> = −5%</span>
                <span><strong>+/- Amount</strong> — <code>0.50</code> adds 50¢, <code>-1</code> subtracts $1</span>
                <span><strong>Set To</strong> — <code>9.99</code> sets selected items to $9.99</span>
            </div>
        </div>

        <div class="desk-toolbar rpt-toolbar bp-toolbar">
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
            <div class="rpt-field rpt-field-search">
                <label class="desk-toolbar-label" for="bp-search">Search</label>
                <input
                    id="bp-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    class="desk-search"
                    placeholder="Code, description, UPC…"
                />
            </div>
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="bp-target">Field</label>
                <select id="bp-target" wire:model.live="target" class="desk-select">
                    <option value="list_price">List Price</option>
                    <option value="msrp">MSRP</option>
                    <option value="standard_cost">Standard Cost</option>
                    <option value="current_cost">Current Cost</option>
                </select>
            </div>
            <div class="rpt-field">
                <label class="desk-toolbar-label" for="bp-type">Adjustment</label>
                <select id="bp-type" wire:model.live="adjustment_type" class="desk-select">
                    <option value="percent">% Change</option>
                    <option value="amount">+/- Amount</option>
                    <option value="set">Set To</option>
                </select>
            </div>
            <div class="rpt-field bp-value-field">
                <label class="desk-toolbar-label" for="bp-value">Value</label>
                <input
                    id="bp-value"
                    wire:model.live="adjustment_value"
                    class="desk-search text-right"
                    inputmode="decimal"
                />
            </div>
            <div class="rpt-actions">
                <button type="button" wire:click="clearFilters" class="desk-btn">Clear filters</button>
                <button type="button" wire:click="clearSelection" class="desk-btn" @disabled($selectedCount === 0)>Clear selection</button>
                <button
                    type="button"
                    wire:click="apply"
                    wire:confirm="Apply {{ $adjustLabel }} ({{ $adjustment_value }}) to {{ $fieldLabel }} on {{ $selectedCount }} selected item(s)?"
                    class="desk-btn desk-btn-primary"
                    wire:loading.attr="disabled"
                    @disabled($selectedCount === 0)
                >
                    <span wire:loading.remove wire:target="apply">Apply to {{ number_format($selectedCount) }} selected</span>
                    <span wire:loading wire:target="apply">Applying…</span>
                </button>
            </div>
        </div>

        @if ($status)
            <div class="desk-flash bp-flash">{{ $status }}</div>
        @endif

        @error('adjustment_value')
            <div class="desk-flash bp-flash-error">{{ $message }}</div>
        @enderror
        @error('selectedIds')
            <div class="desk-flash bp-flash-error">{{ $message }}</div>
        @enderror

        <div class="desk-titlebar">
            <div>
                <h2 class="desk-title">Preview — {{ $fieldLabel }}</h2>
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
                            <input
                                type="checkbox"
                                wire:click="toggleSelectAll(@js($visibleIds))"
                                @checked($allVisibleSelected)
                                @disabled($matchCount === 0)
                                aria-label="Select all visible items"
                            />
                        </th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Department</th>
                        <th class="text-right">List</th>
                        <th class="text-right">MSRP</th>
                        <th class="text-right">Std Cost</th>
                        <th class="text-right">Current</th>
                        <th class="text-right">Current {{ $fieldLabel }}</th>
                        <th class="text-right">New {{ $fieldLabel }}</th>
                        <th class="text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview as $row)
                        @php $item = $row['item']; @endphp
                        <tr @class(['is-selected' => $row['selected']])>
                            <td class="bp-check-col">
                                <input
                                    type="checkbox"
                                    value="{{ $item->id }}"
                                    wire:model.live="selectedIds"
                                    aria-label="Select {{ $item->item_code }}"
                                />
                            </td>
                            <td class="desk-num">{{ $item->item_code }}</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->department?->name ?: '—' }}</td>
                            <td class="desk-money">${{ number_format((float) $item->list_price, 2) }}</td>
                            <td class="desk-money">${{ number_format((float) $item->msrp, 2) }}</td>
                            <td class="desk-money">${{ number_format((float) $item->standard_cost, 2) }}</td>
                            <td class="desk-money">${{ number_format((float) $item->current_cost, 2) }}</td>
                            <td class="desk-money">${{ number_format($row['before'], 2) }}</td>
                            <td class="desk-money bp-new">${{ number_format($row['after'], 2) }}</td>
                            <td @class([
                                'desk-money',
                                'bp-up' => $row['delta'] > 0,
                                'bp-down' => $row['delta'] < 0,
                                'bp-same' => $row['delta'] == 0,
                            ])>
                                @if ($row['delta'] > 0)
                                    +${{ number_format($row['delta'], 2) }}
                                @elseif ($row['delta'] < 0)
                                    -${{ number_format(abs($row['delta']), 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="11">No matching active items. Adjust filters to continue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="desk-footer">
            <span>
                {{ number_format($selectedVisibleCount) }} of {{ number_format($matchCount) }} visible selected
                · {{ number_format($selectedCount) }} total selected
            </span>
            <div class="desk-footer-actions">
                <button type="button" wire:click="toggleSelectAll(@js($visibleIds))" class="desk-btn desk-btn-sm" @disabled($matchCount === 0)>
                    {{ $allVisibleSelected ? 'Deselect all' : 'Select all visible' }}
                </button>
                <button
                    type="button"
                    wire:click="apply"
                    wire:confirm="Apply this price change to {{ $selectedCount }} selected item(s)?"
                    class="desk-btn desk-btn-sm desk-btn-primary"
                    @disabled($selectedCount === 0)
                >Apply to selected</button>
            </div>
        </div>
    </div>
</div>
