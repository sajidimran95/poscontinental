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
        ];
    }

    public function selectLookup(string $key): void
    {
        $this->activeLookup = $key;
        $this->reset('code', 'name');
    }

    public function save(): void
    {
        $this->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
        ]);

        $map = [
            'departments' => Department::class,
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
            $this->addError('code', 'Use seed data for category hierarchy, or add via Items later.');

            return;
        }

        $map[$this->activeLookup]::query()->create([
            'company_id' => auth()->user()->company_id,
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'is_active' => true,
        ]);

        $this->reset('code', 'name');
        session()->flash('status', 'Lookup saved.');
    }
}; ?>

<div class="flex gap-3">
    <aside class="w-56 shrink-0 border border-slate-400 bg-white">
        <div class="bg-slate-200 px-2 py-1 text-xs font-semibold uppercase text-slate-600">Lookup Tables</div>
        <ul class="text-sm max-h-[70vh] overflow-auto">
            @foreach ($lookupKeys as $key)
                <li>
                    <button type="button" wire:click="selectLookup('{{ $key }}')"
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
                <p class="mb-2 text-sm text-green-700">{{ session('status') }}</p>
            @endif

            @if (! in_array($activeLookup, ['categories', 'subcategories'], true))
                <form wire:submit="save" class="mb-3 flex flex-wrap items-end gap-2 border-b border-slate-200 pb-3">
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
            @else
                <p class="mb-2 text-xs text-slate-500">Category / Subcategory hierarchy is seeded. Manage via Items module in Phase 3.</p>
            @endif

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
