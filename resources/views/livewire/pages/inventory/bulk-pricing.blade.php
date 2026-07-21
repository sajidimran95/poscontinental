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

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $items = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('item_code')
            ->limit(300)
            ->get();

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'items' => $items,
        ];
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
    }

    public function apply(): void
    {
        $this->validate([
            'adjustment_type' => 'required|in:percent,amount,set',
            'adjustment_value' => 'required|numeric',
            'target' => 'required|in:list_price,msrp,standard_cost,current_cost',
        ]);

        $companyId = auth()->user()->company_id;
        $value = (float) $this->adjustment_value;
        $field = $this->target;
        $affected = 0;

        $query = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)->orWhere('description', 'like', $term);
                });
            });

        DB::transaction(function () use ($query, $field, $value, &$affected, $companyId) {
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
                    ]),
                    'adjustment_type' => $this->adjustment_type,
                    'adjustment_value' => $value,
                    'targets' => json_encode([$field]),
                    'items_affected' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $query->orderBy('id')->chunkById(100, function ($items) use ($field, $value, &$affected, $logId) {
                foreach ($items as $item) {
                    $before = (float) $item->{$field};
                    $after = match ($this->adjustment_type) {
                        'percent' => round($before * (1 + ($value / 100)), 4),
                        'amount' => round($before + $value, 4),
                        default => round($value, 4),
                    };
                    if ($after < 0) {
                        $after = 0;
                    }
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

        $this->status = "Updated {$affected} item(s).";
    }
}; ?>

<div class="flex gap-2 h-full">
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Bulk Pricing Update" />
        <div class="flex flex-wrap items-end gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <div>
                <label class="block text-xs text-slate-600">Department</label>
                <select wire:model.live="department_id" class="chief-input w-44">
                    <option value="">All</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Category</label>
                <select wire:model.live="category_id" class="chief-input w-44">
                    <option value="">All</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Search</label>
                <input wire:model.live.debounce.300ms="search" class="chief-input w-44" />
            </div>
            <div>
                <label class="block text-xs text-slate-600">Field</label>
                <select wire:model="target" class="chief-input w-40">
                    <option value="list_price">List Price</option>
                    <option value="msrp">MSRP</option>
                    <option value="standard_cost">Standard Cost</option>
                    <option value="current_cost">Current Cost</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Adjustment</label>
                <select wire:model="adjustment_type" class="chief-input w-36">
                    <option value="percent">% Change</option>
                    <option value="amount">+/- Amount</option>
                    <option value="set">Set To</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Value</label>
                <input wire:model="adjustment_value" class="chief-input w-28 text-right" />
            </div>
            <button type="button" wire:click="apply" wire:confirm="Apply price change to matching items?" class="chief-btn-primary">Apply</button>
        </div>

        @if ($status)
            <div class="px-3 py-2 text-sm bg-emerald-50 border-b border-emerald-200 text-emerald-900">{{ $status }}</div>
        @endif

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Matching Items (preview up to 300)</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th class="text-right">List Price</th>
                        <th class="text-right">MSRP</th>
                        <th class="text-right">Std Cost</th>
                        <th class="text-right">Current Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="font-mono">{{ $item->item_code }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-right">${{ number_format($item->list_price, 2) }}</td>
                            <td class="text-right">${{ number_format($item->msrp, 2) }}</td>
                            <td class="text-right">${{ number_format($item->standard_cost, 2) }}</td>
                            <td class="text-right">${{ number_format($item->current_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-2 py-6 text-slate-500">No matching items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$items->count()" />
    </div>
</div>
