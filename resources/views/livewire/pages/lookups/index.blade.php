<?php

use App\Models\Category;
use App\Models\CigaretteTaxClass;
use App\Models\Department;
use App\Models\DiscountSchedule;
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
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Lookups')] class extends Component
{
    public string $activeLookup = 'departments';

    public string $code = '';

    public string $name = '';

    public ?int $parent_id = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $tables = [
            'departments' => Department::class,
            'categories' => Category::class,
            'subcategories' => Subcategory::class,
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

        $model = $tables[$this->activeLookup];

        return [
            'lookupKeys' => array_keys($tables),
            'rows' => $model::query()
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->limit(200)
                ->get(),
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'categories' => Category::query()->where('company_id', $companyId)->orderBy('code')->get(),
        ];
    }

    public function selectLookup(string $key): void
    {
        $this->activeLookup = $key;
        $this->reset('code', 'name', 'parent_id');
    }

    public function save(): void
    {
        $this->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'parent_id' => in_array($this->activeLookup, ['categories', 'subcategories'], true)
                ? 'required|integer'
                : 'nullable',
        ]);

        $map = [
            'departments' => Department::class,
            'categories' => Category::class,
            'subcategories' => Subcategory::class,
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

        if (! isset($map[$this->activeLookup])) {
            $this->addError('code', 'Unknown lookup table.');

            return;
        }

        $payload = [
            'company_id' => auth()->user()->company_id,
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'is_active' => true,
        ];

        if ($this->activeLookup === 'categories' && $this->parent_id) {
            $payload['department_id'] = $this->parent_id;
        }
        if ($this->activeLookup === 'subcategories' && $this->parent_id) {
            $payload['category_id'] = $this->parent_id;
        }

        $map[$this->activeLookup]::query()->create($payload);

        $this->reset('code', 'name', 'parent_id');
        session()->flash('status', 'Lookup saved.');
    }
}; ?>

<div class="flex gap-3">
    <aside class="w-56 shrink-0 border border-slate-400 bg-white" aria-label="Lookup tables">
        <div class="bg-slate-200 px-2 py-1 text-xs font-semibold uppercase text-slate-600" id="lookup-tables-heading">Lookup Tables</div>
        <ul class="text-sm max-h-[70vh] overflow-auto" role="list" aria-labelledby="lookup-tables-heading">
            @foreach ($lookupKeys as $key)
                <li>
                    <button type="button" wire:click="selectLookup('{{ $key }}')"
                        aria-current="{{ $activeLookup === $key ? 'true' : 'false' }}"
                        @class(['w-full text-left px-2 py-1.5 border-b border-slate-100', 'bg-sky-100 font-medium' => $activeLookup === $key, 'hover:bg-slate-50' => $activeLookup !== $key])>
                        {{ str_replace('_', ' ', ucfirst($key)) }}
                    </button>
                </li>
            @endforeach
        </ul>
    </aside>

    <div class="flex-1 space-y-3">
        <x-desktop-panel>
            <x-slot:title>{{ str_replace('_', ' ', ucfirst($activeLookup)) }}</x-slot:title>

            @if (session('status'))
                <p class="mb-2 text-sm text-green-700" role="status">{{ session('status') }}</p>
            @endif

            <form wire:submit="save" class="mb-3 flex flex-wrap items-end gap-2 border-b border-slate-200 pb-3">
                @if ($activeLookup === 'categories')
                    <div>
                        <x-input-label for="parent_id" value="Department" />
                        <select id="parent_id" wire:model="parent_id" class="mt-1 block w-44 text-sm border-slate-300 rounded-sm">
                            <option value="">—</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($activeLookup === 'subcategories')
                    <div>
                        <x-input-label for="parent_id" value="Category" />
                        <select id="parent_id" wire:model="parent_id" class="mt-1 block w-44 text-sm border-slate-300 rounded-sm">
                            <option value="">—</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <x-input-label for="code" value="Code" />
                    <x-text-input wire:model="code" id="code" class="mt-1 block w-28 text-sm" />
                </div>
                <div class="flex-1 min-w-48">
                    <x-input-label for="name" value="Name" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full text-sm" />
                </div>
                <x-primary-button>Add</x-primary-button>
            </form>

            <x-data-grid>
                <x-slot:head>
                    <tr>
                        <th class="px-2 py-1.5">Code</th>
                        <th class="px-2 py-1.5">Name</th>
                        <th class="px-2 py-1.5">Active</th>
                    </tr>
                </x-slot:head>
                @forelse ($rows as $row)
                    <tr class="hover:bg-sky-50">
                        <td class="px-2 py-1 font-mono text-xs">{{ $row->code }}</td>
                        <td class="px-2 py-1">{{ $row->name }}</td>
                        <td class="px-2 py-1">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-2 py-4 text-slate-500">No records.</td></tr>
                @endforelse
            </x-data-grid>
        </x-desktop-panel>
    </div>
</div>
