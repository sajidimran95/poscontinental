<?php

use App\Models\Category;
use App\Models\CigaretteTaxClass;
use App\Models\Department;
use App\Models\DiscountSchedule;
use App\Models\ItemType;
use App\Models\PaymentTerm;
use App\Models\PriceLevel;
use App\Models\PricingMethod;
use App\Models\PurchaseLimitSchedule;
use App\Models\RouteLookup;
use App\Models\ShipVia;
use App\Models\Subcategory;
use App\Models\TaxSchedule;
use App\Models\UomSchedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Lookups')] class extends Component
{
    #[Url]
    public string $activeLookup = 'departments';

    public string $code = '';

    public string $name = '';

    public string $base_uom = '';

    public ?int $parent_id = null;

    /** @return array<string, class-string> */
    protected function tables(): array
    {
        return [
            'departments' => Department::class,
            'categories' => Category::class,
            'subcategories' => Subcategory::class,
            'item_types' => ItemType::class,
            'uom_schedules' => UomSchedule::class,
            'delivery_routes' => RouteLookup::class,
            'tax_schedules' => TaxSchedule::class,
            'pricing_methods' => PricingMethod::class,
            'payment_terms' => PaymentTerm::class,
            'ship_vias' => ShipVia::class,
            'price_levels' => PriceLevel::class,
            'discount_schedules' => DiscountSchedule::class,
            'cigarette_tax_classes' => CigaretteTaxClass::class,
            'purchase_limit_schedules' => PurchaseLimitSchedule::class,
        ];
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $tables = $this->tables();

        if (! isset($tables[$this->activeLookup])) {
            $this->activeLookup = 'departments';
        }

        $model = $tables[$this->activeLookup];

        $labels = [
            'departments' => 'Departments',
            'categories' => 'Categories',
            'subcategories' => 'Sub Categories',
            'item_types' => 'Item Types',
            'uom_schedules' => 'UOM Schedules',
            'delivery_routes' => 'Delivery Routes',
            'tax_schedules' => 'Tax Schedules',
            'pricing_methods' => 'Pricing Methods',
            'payment_terms' => 'Payment Terms',
            'ship_vias' => 'Ship Via',
            'price_levels' => 'Price Levels',
            'discount_schedules' => 'Discount Schedules',
            'cigarette_tax_classes' => 'Cigarette Tax Classes',
            'purchase_limit_schedules' => 'Purchase Limit Schedules',
        ];

        $rows = $model::query()
            ->where('company_id', $companyId)
            ->when($this->activeLookup === 'categories', fn ($q) => $q->with('department'))
            ->when($this->activeLookup === 'subcategories', fn ($q) => $q->with('category.department'))
            ->orderBy('code')
            ->limit(300)
            ->get();

        return [
            'lookupKeys' => $labels,
            'rows' => $rows,
            'listTitle' => $labels[$this->activeLookup] ?? 'Lookups',
            'departments' => Department::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'categories' => Category::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'helpText' => match ($this->activeLookup) {
                'departments' => 'Top-level item group (example: TOB — Tobacco). Create this first.',
                'categories' => 'Belongs to a Department (example: CIG — Cigarettes under Tobacco).',
                'subcategories' => 'Belongs to a Category (optional finer group under Cigarettes).',
                'item_types' => 'Appears in the Item Type dropdown on New Item (example: STD — Standard Item). Code is short; Name is what users see.',
                'uom_schedules' => 'Unit of Measure schedules for items (example: EA-BX — Each/Box). Set Base U of M (EA, BX, CS…). Then pick this schedule on New Item → Inventory.',
                default => 'Shared setup values used across sales and inventory screens.',
            },
        ];
    }

    public function selectLookup(string $key): void
    {
        if (! isset($this->tables()[$key])) {
            return;
        }
        $this->activeLookup = $key;
        $this->reset('code', 'name', 'base_uom', 'parent_id');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $rules = [
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'parent_id' => in_array($this->activeLookup, ['categories', 'subcategories'], true)
                ? 'required|integer'
                : 'nullable',
        ];
        if ($this->activeLookup === 'uom_schedules') {
            $rules['base_uom'] = 'required|string|max:16';
        }

        $this->validate($rules, [
            'base_uom.required' => 'Base U of M is required (example: EA, BX, CS).',
        ]);

        $map = $this->tables();

        if (! isset($map[$this->activeLookup])) {
            $this->addError('code', 'Unknown lookup table.');

            return;
        }

        $companyId = auth()->user()->company_id;
        $code = strtoupper(trim($this->code));

        $exists = $map[$this->activeLookup]::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            $this->addError('code', 'This code already exists.');

            return;
        }

        $payload = [
            'company_id' => $companyId,
            'code' => $code,
            'name' => trim($this->name),
            'is_active' => true,
        ];

        if ($this->activeLookup === 'categories' && $this->parent_id) {
            $payload['department_id'] = $this->parent_id;
        }
        if ($this->activeLookup === 'subcategories' && $this->parent_id) {
            $payload['category_id'] = $this->parent_id;
        }
        if ($this->activeLookup === 'uom_schedules') {
            $payload['base_uom'] = strtoupper(trim($this->base_uom));
        }

        $map[$this->activeLookup]::query()->create($payload);

        $this->reset('code', 'name', 'base_uom', 'parent_id');
        session()->flash('status', 'Saved successfully. It will appear on the Item form dropdowns.');
    }
}; ?>

<div class="desk-page">
    <aside class="desk-sidebar" aria-label="Lookup tables">
        <div class="desk-sidebar-head" id="lookup-tables-heading">Lookup Tables</div>
        <ul class="desk-sidebar-list" role="list" aria-labelledby="lookup-tables-heading">
            @foreach ($lookupKeys as $key => $label)
                <li>
                    <button
                        type="button"
                        wire:click="selectLookup('{{ $key }}')"
                        aria-current="{{ $activeLookup === $key ? 'true' : 'false' }}"
                        @class(['desk-sidebar-item', 'is-active' => $activeLookup === $key])
                    >{{ $label }}</button>
                </li>
            @endforeach
        </ul>
    </aside>

    <div class="desk-main">
        <x-action-bar title="Lookups" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $listTitle }}</h2>
            <span class="desk-title-meta">{{ number_format($rows->count()) }} records</span>
        </div>

        <div class="cm-help" style="margin:0.65rem 0.85rem 0">{{ $helpText }}</div>

        <form wire:submit="save" class="desk-toolbar" style="align-items:flex-end">
            @if ($activeLookup === 'categories')
                <div>
                    <label class="desk-toolbar-label" for="parent_id">Department</label>
                    <select id="parent_id" wire:model="parent_id" class="desk-select" style="min-width:12rem">
                        <option value="">— Select —</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($activeLookup === 'subcategories')
                <div>
                    <label class="desk-toolbar-label" for="parent_id">Category</label>
                    <select id="parent_id" wire:model="parent_id" class="desk-select" style="min-width:12rem">
                        <option value="">— Select —</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="desk-toolbar-label" for="code">Code</label>
                <input id="code" wire:model="code" class="desk-search font-mono" style="width:7rem" placeholder="TOB" />
                @error('code') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
            </div>

            <div style="flex:1;min-width:12rem">
                <label class="desk-toolbar-label" for="name">Name</label>
                <input id="name" wire:model="name" class="desk-search" style="width:100%" placeholder="Tobacco" />
                @error('name') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
            </div>

            @if ($activeLookup === 'uom_schedules')
                <div>
                    <label class="desk-toolbar-label" for="base_uom">Base U of M</label>
                    <select id="base_uom" wire:model="base_uom" class="desk-select" style="min-width:6.5rem">
                        <option value="">—</option>
                        @foreach (['EA','BX','CS','CTN','PK','DZ','LB','KG','OZ','GAL','PLT','BAG','BOT','CAN'] as $uom)
                            <option value="{{ $uom }}">{{ $uom }}</option>
                        @endforeach
                    </select>
                    @error('base_uom') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            <button type="submit" class="desk-btn desk-btn-primary">Add {{ $listTitle === 'Sub Categories' ? 'Sub Category' : rtrim($listTitle, 's') }}</button>
        </form>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        @if ($activeLookup === 'uom_schedules')
                            <th>Base U of M</th>
                        @endif
                        @if ($activeLookup === 'categories')
                            <th>Department</th>
                        @endif
                        @if ($activeLookup === 'subcategories')
                            <th>Category</th>
                        @endif
                        <th class="text-center">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="desk-num">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            @if ($activeLookup === 'uom_schedules')
                                <td class="desk-num">{{ $row->base_uom ?: '—' }}</td>
                            @endif
                            @if ($activeLookup === 'categories')
                                <td>{{ $row->department ? $row->department->code.' — '.$row->department->name : '—' }}</td>
                            @endif
                            @if ($activeLookup === 'subcategories')
                                <td>{{ $row->category ? $row->category->code.' — '.$row->category->name : '—' }}</td>
                            @endif
                            <td class="text-center">
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-invoiced' => $row->is_active,
                                    'desk-pill-muted' => ! $row->is_active,
                                ])>{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty"><td colspan="5">No records yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$rows->count()" note="Showing up to 300 records" />
    </div>
</div>
