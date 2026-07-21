<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\DiscountSchedule;
use App\Models\InventoryJournalEntry;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\ItemSubstitute;
use App\Models\ItemSupplier;
use App\Models\ItemUpc;
use App\Models\PricingMethod;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\TaxSchedule;
use App\Models\UomSchedule;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Item')] class extends Component
{
    use WithFileUploads;

    public ?Item $item = null;

    public string $activeTab = 'general';

    public string $item_code = '';

    public string $item_type = 'Standard Item';

    public string $class = '';

    public string $description = '';

    public string $extended_description = '';

    public string $product_highlights = '';

    public string $list_price = '0.00';

    public string $msrp = '0.00';

    public string $standard_cost = '0.00';

    public string $current_cost = '0.00';

    public string $last_cost = '0.00';

    public string $average_cost = '0.00';

    public string $quantity_in_stock = '0';

    public string $allocated_qty = '0';

    public string $on_order_qty = '0';

    public string $back_order_qty = '0';

    public string $reorder_point = '0';

    public string $restock_level = '0';

    public string $lead_time_days = '0';

    public ?string $last_received_at = null;

    public ?string $last_ordered_at = null;

    public ?string $last_sold_at = null;

    public ?string $last_count_date = null;

    public ?int $department_id = null;

    public ?int $category_id = null;

    public ?int $subcategory_id = null;

    public ?int $uom_schedule_id = null;

    public ?int $tax_schedule_id = null;

    public ?int $promotion_schedule_id = null;

    public ?int $pricing_method_id = null;

    public string $unit_of_measure = 'BX';

    public bool $is_inactive = false;

    public bool $can_sell = true;

    public bool $can_order = true;

    public bool $allow_back_order = true;

    public bool $available_on_website = false;

    public string $item_tracking = 'None';

    public string $barcode_format = 'UPC-A';

    public string $shipping_weight = '0';

    public string $tare_weight = '0';

    public string $manufacturer = '';

    public string $item_line_message = '';

    public string $comments = '';

    public string $manu_product_id = '';

    public string $manu_promotion_item = '';

    public string $manu_promotion_description = '';

    public string $manu_promotion_code = '';

    public string $manu_base_count = '0';

    public string $primary_upc = '';

    public ?string $image_path = null;

    public ?string $thumbnail_path = null;

    public $image_upload = null;

    public $thumbnail_upload = null;

    /** @var array<int, array{upc:string,is_primary:bool}> */
    public array $upcs = [];

    /** @var array<int, array{uom:string,price:string,alias_code:string}> */
    public array $prices = [];

    /** @var array<int, array{supplier_id:?int,supplier_item_code:string,lead_time:string,is_default:bool,last_cost:string,avg_cost:string,last_received_at:?string}> */
    public array $suppliers = [];

    /** @var array<int, array{substitute_item_id:?int,quantity:string,force_substitute:bool}> */
    public array $substitutes = [];

    public bool $showJournal = false;

    public function mount(?Item $item = null): void
    {
        if ($item?->exists) {
            abort_unless($item->company_id === auth()->user()->company_id, 403);
            $this->item = $item->load(['upcs', 'prices', 'itemSuppliers', 'substitutes']);

            $this->fill($item->only([
                'item_code', 'item_type', 'class', 'description', 'extended_description', 'product_highlights',
                'list_price', 'msrp', 'standard_cost', 'current_cost', 'last_cost', 'average_cost',
                'quantity_in_stock', 'allocated_qty', 'on_order_qty', 'back_order_qty',
                'reorder_point', 'restock_level', 'lead_time_days',
                'department_id', 'category_id', 'subcategory_id', 'uom_schedule_id',
                'tax_schedule_id', 'promotion_schedule_id', 'pricing_method_id',
                'unit_of_measure', 'is_inactive', 'can_sell', 'can_order', 'allow_back_order',
                'available_on_website', 'item_tracking', 'barcode_format',
                'shipping_weight', 'tare_weight', 'manufacturer', 'item_line_message', 'comments',
                'manu_product_id', 'manu_promotion_item', 'manu_promotion_description',
                'manu_promotion_code', 'manu_base_count', 'primary_upc', 'image_path', 'thumbnail_path',
            ]));

            $this->last_received_at = optional($item->last_received_at)?->format('Y-m-d');
            $this->last_ordered_at = optional($item->last_ordered_at)?->format('Y-m-d');
            $this->last_sold_at = optional($item->last_sold_at)?->format('Y-m-d');
            $this->last_count_date = optional($item->last_count_date)?->format('Y-m-d');

            $this->upcs = $item->upcs->map(fn (ItemUpc $u) => [
                'upc' => $u->upc,
                'is_primary' => (bool) $u->is_primary,
            ])->all();

            $this->prices = $item->prices->map(fn (ItemPrice $p) => [
                'uom' => $p->uom ?? '',
                'price' => (string) $p->price,
                'alias_code' => $p->alias_code ?? '',
            ])->all();

            $this->suppliers = $item->itemSuppliers->map(fn (ItemSupplier $s) => [
                'supplier_id' => $s->supplier_id,
                'supplier_item_code' => $s->supplier_item_code ?? '',
                'lead_time' => (string) $s->lead_time,
                'is_default' => (bool) $s->is_default,
                'last_cost' => (string) $s->last_cost,
                'avg_cost' => (string) $s->avg_cost,
                'last_received_at' => optional($s->last_received_at)?->format('Y-m-d'),
            ])->all();

            $this->substitutes = $item->substitutes->map(fn (ItemSubstitute $s) => [
                'substitute_item_id' => $s->substitute_item_id,
                'quantity' => (string) $s->quantity,
                'force_substitute' => (bool) $s->force_substitute,
            ])->all();
        }

        if ($this->upcs === []) {
            $this->upcs[] = ['upc' => $this->primary_upc, 'is_primary' => true];
        }

        if ($this->prices === []) {
            $this->prices[] = ['uom' => $this->unit_of_measure, 'price' => $this->list_price, 'alias_code' => ''];
        }

        if ($this->suppliers === []) {
            $this->suppliers[] = [
                'supplier_id' => null,
                'supplier_item_code' => '',
                'lead_time' => '0',
                'is_default' => true,
                'last_cost' => '0',
                'avg_cost' => '0',
                'last_received_at' => null,
            ];
        }

        if ($this->substitutes === []) {
            $this->substitutes[] = [
                'substitute_item_id' => null,
                'quantity' => '1',
                'force_substitute' => false,
            ];
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')->get(),
            'subcategories' => Subcategory::query()->where('company_id', $companyId)
                ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
                ->orderBy('name')->get(),
            'uomSchedules' => UomSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'taxSchedules' => TaxSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'promotionSchedules' => DiscountSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'pricingMethods' => PricingMethod::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'supplierOptions' => Supplier::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('name')->get(),
            'substituteOptions' => Item::query()->where('company_id', $companyId)
                ->when($this->item, fn ($q) => $q->where('id', '!=', $this->item->id))
                ->orderBy('item_code')->limit(500)->get(['id', 'item_code', 'description']),
            'sites' => Site::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'itemTypes' => ['Standard Item', 'Kit', 'Non-Inventory', 'Service'],
            'trackingOptions' => ['None', 'Serial', 'Lot'],
            'barcodeFormats' => ['UPC-A', 'UPC-E', 'EAN-13', 'EAN-8', 'Code128', 'Code39'],
            'tabs' => [
                'general' => 'General',
                'inventory' => 'Inventory',
                'pricing' => 'Pricing',
                'extended' => 'Extended Description',
                'suppliers' => 'Suppliers',
                'substitutes' => 'Substitutes',
                'options' => 'Options & Comments',
            ],
            'availableQty' => (float) $this->quantity_in_stock - (float) $this->allocated_qty,
            'journalEntries' => ($this->showJournal && $this->item)
                ? InventoryJournalEntry::query()
                    ->where('company_id', $companyId)
                    ->where('item_id', $this->item->id)
                    ->with('site')
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get()
                : collect(),
        ];
    }

    public function openJournal(): void
    {
        if (! $this->item) {
            return;
        }
        $this->showJournal = true;
    }

    public function closeJournal(): void
    {
        $this->showJournal = false;
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
        $this->subcategory_id = null;
    }

    public function updatedCategoryId(): void
    {
        $this->subcategory_id = null;
    }

    public function addUpc(): void
    {
        $this->upcs[] = ['upc' => '', 'is_primary' => false];
    }

    public function removeUpc(int $index): void
    {
        unset($this->upcs[$index]);
        $this->upcs = array_values($this->upcs);
        if ($this->upcs === []) {
            $this->upcs[] = ['upc' => '', 'is_primary' => true];
        }
    }

    public function setPrimaryUpc(int $index): void
    {
        foreach ($this->upcs as $i => $row) {
            $this->upcs[$i]['is_primary'] = $i === $index;
        }
        $this->primary_upc = $this->upcs[$index]['upc'] ?? '';
    }

    public function addPrice(): void
    {
        $this->prices[] = ['uom' => $this->unit_of_measure, 'price' => '0.00', 'alias_code' => ''];
    }

    public function removePrice(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
        if ($this->prices === []) {
            $this->prices[] = ['uom' => $this->unit_of_measure, 'price' => $this->list_price, 'alias_code' => ''];
        }
    }

    public function addSupplierRow(): void
    {
        $this->suppliers[] = [
            'supplier_id' => null,
            'supplier_item_code' => '',
            'lead_time' => '0',
            'is_default' => false,
            'last_cost' => '0',
            'avg_cost' => '0',
            'last_received_at' => null,
        ];
    }

    public function removeSupplierRow(int $index): void
    {
        unset($this->suppliers[$index]);
        $this->suppliers = array_values($this->suppliers);
        if ($this->suppliers === []) {
            $this->addSupplierRow();
        }
    }

    public function setDefaultSupplier(int $index): void
    {
        foreach ($this->suppliers as $i => $row) {
            $this->suppliers[$i]['is_default'] = $i === $index;
        }
    }

    public function addSubstitute(): void
    {
        $this->substitutes[] = [
            'substitute_item_id' => null,
            'quantity' => '1',
            'force_substitute' => false,
        ];
    }

    public function removeSubstitute(int $index): void
    {
        unset($this->substitutes[$index]);
        $this->substitutes = array_values($this->substitutes);
        if ($this->substitutes === []) {
            $this->addSubstitute();
        }
    }

    public function removeImage(): void
    {
        $this->image_path = null;
        $this->image_upload = null;
    }

    public function removeThumbnail(): void
    {
        $this->thumbnail_path = null;
        $this->thumbnail_upload = null;
    }

    public function save(): void
    {
        $this->validate([
            'item_code' => 'required|string|max:64',
            'description' => 'nullable|string',
            'list_price' => 'numeric',
            'msrp' => 'numeric',
            'standard_cost' => 'numeric',
            'current_cost' => 'numeric',
            'reorder_point' => 'numeric',
            'restock_level' => 'numeric',
            'lead_time_days' => 'integer|min:0',
            'shipping_weight' => 'numeric',
            'tare_weight' => 'numeric',
            'image_upload' => 'nullable|image|max:4096',
            'thumbnail_upload' => 'nullable|image|max:2048',
            'upcs.*.upc' => 'nullable|string|max:64',
            'prices.*.uom' => 'nullable|string|max:16',
            'prices.*.price' => 'nullable|numeric',
            'suppliers.*.supplier_id' => 'nullable|integer|exists:suppliers,id',
            'substitutes.*.substitute_item_id' => 'nullable|integer|exists:items,id',
            'substitutes.*.quantity' => 'nullable|numeric',
        ]);

        $primary = collect($this->upcs)->firstWhere('is_primary', true);
        if ($primary && filled($primary['upc'] ?? null)) {
            $this->primary_upc = $primary['upc'];
        } else {
            $firstFilled = collect($this->upcs)->first(fn ($r) => filled($r['upc'] ?? null));
            $this->primary_upc = $firstFilled['upc'] ?? $this->primary_upc;
        }

        $imagePath = $this->image_path;
        $thumbPath = $this->thumbnail_path;

        if ($this->image_upload) {
            $imagePath = $this->image_upload->store('items/images', 'public');
        }
        if ($this->thumbnail_upload) {
            $thumbPath = $this->thumbnail_upload->store('items/thumbnails', 'public');
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;

        $data = [
            'company_id' => auth()->user()->company_id,
            'item_code' => $this->item_code,
            'item_type' => $this->item_type,
            'class' => $this->class,
            'description' => $this->description,
            'extended_description' => $this->extended_description,
            'product_highlights' => $this->product_highlights,
            'image_path' => $imagePath,
            'thumbnail_path' => $thumbPath,
            'list_price' => $this->list_price,
            'msrp' => $this->msrp,
            'standard_cost' => $this->standard_cost,
            'current_cost' => $this->current_cost,
            'last_cost' => $this->last_cost,
            'average_cost' => $this->average_cost,
            'quantity_in_stock' => $this->quantity_in_stock,
            'allocated_qty' => $this->allocated_qty,
            'on_order_qty' => $this->on_order_qty,
            'back_order_qty' => $this->back_order_qty,
            'reorder_point' => $this->reorder_point,
            'restock_level' => $this->restock_level,
            'lead_time_days' => (int) $this->lead_time_days,
            'last_received_at' => $this->last_received_at ?: null,
            'last_ordered_at' => $this->last_ordered_at ?: null,
            'last_sold_at' => $this->last_sold_at ?: null,
            'last_count_date' => $this->last_count_date ?: null,
            'department_id' => $nullableId($this->department_id),
            'category_id' => $nullableId($this->category_id),
            'subcategory_id' => $nullableId($this->subcategory_id),
            'uom_schedule_id' => $nullableId($this->uom_schedule_id),
            'tax_schedule_id' => $nullableId($this->tax_schedule_id),
            'promotion_schedule_id' => $nullableId($this->promotion_schedule_id),
            'pricing_method_id' => $nullableId($this->pricing_method_id),
            'unit_of_measure' => $this->unit_of_measure,
            'is_inactive' => $this->is_inactive,
            'can_sell' => $this->can_sell,
            'can_order' => $this->can_order,
            'allow_back_order' => $this->allow_back_order,
            'available_on_website' => $this->available_on_website,
            'item_tracking' => $this->item_tracking,
            'barcode_format' => $this->barcode_format,
            'shipping_weight' => $this->shipping_weight,
            'tare_weight' => $this->tare_weight,
            'manufacturer' => $this->manufacturer,
            'item_line_message' => $this->item_line_message,
            'comments' => $this->comments,
            'manu_product_id' => $this->manu_product_id,
            'manu_promotion_item' => $this->manu_promotion_item,
            'manu_promotion_description' => $this->manu_promotion_description,
            'manu_promotion_code' => $this->manu_promotion_code,
            'manu_base_count' => $this->manu_base_count,
            'primary_upc' => $this->primary_upc,
        ];

        DB::transaction(function () use ($data) {
            if ($this->item) {
                $this->item->update($data);
                $item = $this->item->fresh();
            } else {
                $item = Item::query()->create($data);
            }

            $item->upcs()->delete();
            foreach (array_values($this->upcs) as $i => $row) {
                if (! filled($row['upc'] ?? null)) {
                    continue;
                }
                $item->upcs()->create([
                    'upc' => $row['upc'],
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                    'sort_order' => $i,
                ]);
            }

            $item->prices()->delete();
            foreach (array_values($this->prices) as $i => $row) {
                if (! filled($row['uom'] ?? null) && ! filled($row['alias_code'] ?? null) && (float) ($row['price'] ?? 0) == 0.0) {
                    continue;
                }
                $item->prices()->create([
                    'uom' => $row['uom'] ?: null,
                    'price' => $row['price'] ?? 0,
                    'alias_code' => $row['alias_code'] ?: null,
                    'sort_order' => $i,
                ]);
            }

            $item->itemSuppliers()->delete();
            foreach (array_values($this->suppliers) as $i => $row) {
                if (empty($row['supplier_id'])) {
                    continue;
                }
                $item->itemSuppliers()->create([
                    'supplier_id' => $row['supplier_id'],
                    'supplier_item_code' => $row['supplier_item_code'] ?: null,
                    'lead_time' => (int) ($row['lead_time'] ?? 0),
                    'is_default' => (bool) ($row['is_default'] ?? false),
                    'last_cost' => $row['last_cost'] ?? 0,
                    'avg_cost' => $row['avg_cost'] ?? 0,
                    'last_received_at' => $row['last_received_at'] ?: null,
                    'sort_order' => $i,
                ]);
            }

            $item->substitutes()->delete();
            foreach (array_values($this->substitutes) as $i => $row) {
                if (empty($row['substitute_item_id'])) {
                    continue;
                }
                $item->substitutes()->create([
                    'substitute_item_id' => $row['substitute_item_id'],
                    'quantity' => $row['quantity'] ?? 1,
                    'force_substitute' => (bool) ($row['force_substitute'] ?? false),
                    'sort_order' => $i,
                ]);
            }
        });

        $this->redirect(route('inventory.items.index'), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="save" class="chief-panel bg-white flex flex-col min-h-[72vh]">
        <x-action-bar :title="$item ? 'Edit Item — '.$item_code : 'New Item'" variant="green" />

        <div class="flex-1 p-3 overflow-auto">
            @error('item_code') <p class="text-red-600 text-xs mb-2">{{ $message }}</p> @enderror

            @if ($activeTab === 'general')
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-8 gap-y-1">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Item Code</label>
                            <input wire:model="item_code" class="chief-input w-44 font-mono" @disabled($item) />
                        </div>
                        <div class="chief-field">
                            <label>Item Type</label>
                            <select wire:model="item_type" class="chief-input w-56">
                                @foreach ($itemTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Class</label>
                            <input wire:model="class" class="chief-input w-56" />
                        </div>
                        <div class="chief-field chief-field-top">
                            <label>Description</label>
                            <textarea wire:model="description" rows="3" class="chief-input w-full max-w-md"></textarea>
                        </div>
                        <div class="chief-field">
                            <label>List Price</label>
                            <input wire:model="list_price" class="chief-input w-28 text-right" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Department</label>
                            <select wire:model.live="department_id" class="chief-input w-full max-w-xs">
                                <option value="">—</option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Category</label>
                            <select wire:model.live="category_id" class="chief-input w-full max-w-xs">
                                <option value="">—</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Sub Category</label>
                            <select wire:model="subcategory_id" class="chief-input w-full max-w-xs">
                                <option value="">—</option>
                                @foreach ($subcategories as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm pt-2 ms-[9.5rem]">
                            <span class="font-medium">Status:</span>
                            <button type="button" wire:click="$set('is_inactive', false)" @class(['chief-btn text-xs', 'chief-btn-primary' => ! $is_inactive])>Active</button>
                            <button type="button" wire:click="$set('is_inactive', true)" @class(['chief-btn text-xs', 'chief-btn-primary' => $is_inactive])>Inactive</button>
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-sm font-semibold text-slate-800">Aliases / Primary UPC</h3>
                        <button type="button" wire:click="addUpc" class="chief-btn text-xs">Add UPC</button>
                    </div>
                    <div class="chief-grid border border-slate-300 max-w-2xl">
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-16 text-center">Primary</th>
                                    <th>UPC / Alias</th>
                                    <th class="w-20"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcs as $i => $row)
                                    <tr>
                                        <td class="text-center">
                                            <input type="radio" name="primary_upc_radio" wire:click="setPrimaryUpc({{ $i }})" @checked($row['is_primary'] ?? false) />
                                        </td>
                                        <td>
                                            <input wire:model="upcs.{{ $i }}.upc" class="chief-input w-full font-mono" />
                                        </td>
                                        <td>
                                            <button type="button" wire:click="removeUpc({{ $i }})" class="text-xs text-red-700 hover:underline">Remove</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($activeTab === 'inventory')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-1 max-w-5xl">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>UOM Schedule</label>
                            <select wire:model="uom_schedule_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($uomSchedules as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Unit of Measure</label>
                            <input wire:model="unit_of_measure" class="chief-input w-24" />
                        </div>
                        <fieldset class="border border-slate-300 p-2 mt-3">
                            <legend class="px-1 text-xs font-semibold text-slate-700">History</legend>
                            <div class="chief-field">
                                <label>Last Received</label>
                                <input type="date" wire:model="last_received_at" class="chief-input" readonly />
                            </div>
                            <div class="chief-field">
                                <label>Last Ordered</label>
                                <input type="date" wire:model="last_ordered_at" class="chief-input" readonly />
                            </div>
                            <div class="chief-field">
                                <label>Last Sold</label>
                                <input type="date" wire:model="last_sold_at" class="chief-input" readonly />
                            </div>
                            <div class="chief-field">
                                <label>Last Count Date</label>
                                <input type="date" wire:model="last_count_date" class="chief-input" readonly />
                            </div>
                        </fieldset>
                    </div>
                    <div class="space-y-1">
                        <fieldset class="border border-slate-300 p-2">
                            <legend class="px-1 text-xs font-semibold text-slate-700">Reorder</legend>
                            <div class="chief-field">
                                <label>Reorder Point</label>
                                <input wire:model="reorder_point" class="chief-input w-28 text-right" />
                            </div>
                            <div class="chief-field">
                                <label>Restock Level</label>
                                <input wire:model="restock_level" class="chief-input w-28 text-right" />
                            </div>
                            <div class="chief-field">
                                <label>Lead Time (days)</label>
                                <input wire:model="lead_time_days" class="chief-input w-28 text-right" />
                            </div>
                        </fieldset>
                        <div class="pt-3">
                            <button type="button" wire:click="openJournal" class="chief-btn" @disabled(! $item)>View Journal</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-slate-800 mb-1">Current Quantities</h3>
                    <div class="chief-grid border border-slate-300 overflow-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th class="text-right">In Stock</th>
                                    <th class="text-right">Allocated</th>
                                    <th class="text-right">On Order</th>
                                    <th class="text-right">Back Order</th>
                                    <th class="text-right">Available</th>
                                    <th>Last Counted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sites as $site)
                                    <tr>
                                        <td class="font-mono">{{ $site->code }}</td>
                                        <td class="text-right">{{ number_format((float) $quantity_in_stock, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $allocated_qty, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $on_order_qty, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $back_order_qty, 2) }}</td>
                                        <td class="text-right font-semibold">{{ number_format($availableQty, 2) }}</td>
                                        <td>{{ $last_count_date ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-slate-500">No sites configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Quantities are maintained by receiving, sales, and stock counts (read-only here).</p>
                </div>

            @elseif ($activeTab === 'pricing')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-1 max-w-4xl">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>List Price</label>
                            <input wire:model="list_price" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>MSRP</label>
                            <input wire:model="msrp" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>Standard Cost</label>
                            <input wire:model="standard_cost" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>Current Cost</label>
                            <input wire:model="current_cost" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>Last Cost</label>
                            <input wire:model="last_cost" class="chief-input w-28 text-right bg-slate-50" readonly />
                        </div>
                        <div class="chief-field">
                            <label>Average Cost</label>
                            <input wire:model="average_cost" class="chief-input w-28 text-right bg-slate-50" readonly />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Tax Schedule</label>
                            <select wire:model="tax_schedule_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($taxSchedules as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Promotion Schedule</label>
                            <select wire:model="promotion_schedule_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($promotionSchedules as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Pricing Method</label>
                            <select wire:model="pricing_method_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($pricingMethods as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-sm font-semibold text-slate-800">Prices</h3>
                        <button type="button" wire:click="addPrice" class="chief-btn text-xs">Add Price</button>
                    </div>
                    <div class="chief-grid border border-slate-300 max-w-3xl">
                        <table>
                            <thead>
                                <tr>
                                    <th>U of M</th>
                                    <th class="text-right">Price</th>
                                    <th>Alias Code</th>
                                    <th class="w-20"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $i => $row)
                                    <tr>
                                        <td><input wire:model="prices.{{ $i }}.uom" class="chief-input w-20" /></td>
                                        <td><input wire:model="prices.{{ $i }}.price" class="chief-input w-28 text-right" /></td>
                                        <td><input wire:model="prices.{{ $i }}.alias_code" class="chief-input w-full" /></td>
                                        <td><button type="button" wire:click="removePrice({{ $i }})" class="text-xs text-red-700 hover:underline">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($activeTab === 'extended')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-5xl">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium mb-1">Extended Description</label>
                            <textarea wire:model="extended_description" rows="8" class="chief-input w-full"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Product Highlights</label>
                            <textarea wire:model="product_highlights" rows="6" class="chief-input w-full" placeholder="One highlight per line"></textarea>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1">Image</label>
                            <input type="file" wire:model="image_upload" accept="image/*" class="text-sm" />
                            <div wire:loading wire:target="image_upload" class="text-xs text-slate-500">Uploading…</div>
                            @error('image_upload') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                            @if ($image_upload)
                                <img src="{{ $image_upload->temporaryUrl() }}" alt="" class="mt-2 max-h-40 border border-slate-300" />
                            @elseif ($image_path)
                                <div class="mt-2 flex items-start gap-2">
                                    <img src="{{ asset('storage/'.$image_path) }}" alt="" class="max-h-40 border border-slate-300" />
                                    <button type="button" wire:click="removeImage" class="text-xs text-red-700 hover:underline">Remove</button>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Thumbnail</label>
                            <input type="file" wire:model="thumbnail_upload" accept="image/*" class="text-sm" />
                            <div wire:loading wire:target="thumbnail_upload" class="text-xs text-slate-500">Uploading…</div>
                            @error('thumbnail_upload') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                            @if ($thumbnail_upload)
                                <img src="{{ $thumbnail_upload->temporaryUrl() }}" alt="" class="mt-2 max-h-24 border border-slate-300" />
                            @elseif ($thumbnail_path)
                                <div class="mt-2 flex items-start gap-2">
                                    <img src="{{ asset('storage/'.$thumbnail_path) }}" alt="" class="max-h-24 border border-slate-300" />
                                    <button type="button" wire:click="removeThumbnail" class="text-xs text-red-700 hover:underline">Remove</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif ($activeTab === 'suppliers')
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-slate-800">Item Suppliers</h3>
                    <button type="button" wire:click="addSupplierRow" class="chief-btn text-xs">Add Supplier</button>
                </div>
                <div class="chief-grid border border-slate-300 overflow-auto">
                    <table>
                        <thead>
                            <tr>
                                <th class="w-14 text-center">Default</th>
                                <th>Supplier</th>
                                <th>Supplier Item Code</th>
                                <th class="text-right">Lead Time</th>
                                <th class="text-right">Last Cost</th>
                                <th class="text-right">Avg Cost</th>
                                <th>Last Received</th>
                                <th class="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($suppliers as $i => $row)
                                <tr>
                                    <td class="text-center">
                                        <input type="radio" name="default_supplier" wire:click="setDefaultSupplier({{ $i }})" @checked($row['is_default'] ?? false) />
                                    </td>
                                    <td>
                                        <select wire:model="suppliers.{{ $i }}.supplier_id" class="chief-input w-56">
                                            <option value="">—</option>
                                            @foreach ($supplierOptions as $sup)
                                                <option value="{{ $sup->id }}">{{ $sup->supplier_id }} — {{ $sup->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input wire:model="suppliers.{{ $i }}.supplier_item_code" class="chief-input w-36 font-mono" /></td>
                                    <td><input wire:model="suppliers.{{ $i }}.lead_time" class="chief-input w-20 text-right" /></td>
                                    <td><input wire:model="suppliers.{{ $i }}.last_cost" class="chief-input w-24 text-right bg-slate-50" readonly /></td>
                                    <td><input wire:model="suppliers.{{ $i }}.avg_cost" class="chief-input w-24 text-right bg-slate-50" readonly /></td>
                                    <td><input type="date" wire:model="suppliers.{{ $i }}.last_received_at" class="chief-input bg-slate-50" readonly /></td>
                                    <td><button type="button" wire:click="removeSupplierRow({{ $i }})" class="text-xs text-red-700 hover:underline">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @elseif ($activeTab === 'substitutes')
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-slate-800">Substitutes</h3>
                    <button type="button" wire:click="addSubstitute" class="chief-btn text-xs">Add Substitute</button>
                </div>
                <div class="chief-grid border border-slate-300 overflow-auto max-w-4xl">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-center">Force Substitute</th>
                                <th class="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($substitutes as $i => $row)
                                @php
                                    $sub = $substituteOptions->firstWhere('id', $row['substitute_item_id'] ?? null);
                                @endphp
                                <tr>
                                    <td>
                                        <select wire:model.live="substitutes.{{ $i }}.substitute_item_id" class="chief-input w-40 font-mono">
                                            <option value="">—</option>
                                            @foreach ($substituteOptions as $opt)
                                                <option value="{{ $opt->id }}">{{ $opt->item_code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="max-w-xs truncate text-slate-600">{{ $sub?->description ?: '—' }}</td>
                                    <td><input wire:model="substitutes.{{ $i }}.quantity" class="chief-input w-24 text-right" /></td>
                                    <td class="text-center"><input type="checkbox" wire:model="substitutes.{{ $i }}.force_substitute" /></td>
                                    <td><button type="button" wire:click="removeSubstitute({{ $i }})" class="text-xs text-red-700 hover:underline">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-1 max-w-5xl">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium w-28">Status:</span>
                            <button type="button" wire:click="$set('is_inactive', false)" @class(['chief-btn text-xs', 'chief-btn-primary' => ! $is_inactive])>Active</button>
                            <button type="button" wire:click="$set('is_inactive', true)" @class(['chief-btn text-xs', 'chief-btn-primary' => $is_inactive])>Inactive</button>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="available_on_website" /> Item is available on the website</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="allow_back_order" /> Allow Back Order</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="can_sell" /> Can Sell</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="can_order" /> Can Order</label>

                        <div class="chief-field mt-2">
                            <label>Item Tracking</label>
                            <select wire:model="item_tracking" class="chief-input w-40">
                                @foreach ($trackingOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Barcode Format</label>
                            <select wire:model="barcode_format" class="chief-input w-40">
                                @foreach ($barcodeFormats as $fmt)
                                    <option value="{{ $fmt }}">{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Shipping Weight</label>
                            <input wire:model="shipping_weight" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>Tare Weight</label>
                            <input wire:model="tare_weight" class="chief-input w-28 text-right" />
                        </div>
                        <div class="chief-field">
                            <label>Manufacturer</label>
                            <input wire:model="manufacturer" class="chief-input w-64" />
                        </div>
                        <div class="chief-field">
                            <label>Item Line Message</label>
                            <input wire:model="item_line_message" class="chief-input w-full max-w-md" />
                        </div>
                        <div class="chief-field chief-field-top">
                            <label>Comments</label>
                            <textarea wire:model="comments" rows="4" class="chief-input w-full max-w-md"></textarea>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <fieldset class="border border-slate-300 p-2">
                            <legend class="px-1 text-xs font-semibold text-slate-700">Manufacturer Promotion</legend>
                            <div class="chief-field">
                                <label>Manu. ProductID</label>
                                <input wire:model="manu_product_id" class="chief-input w-56" />
                            </div>
                            <div class="chief-field">
                                <label>Manu. Promotion Item</label>
                                <input wire:model="manu_promotion_item" class="chief-input w-56" />
                            </div>
                            <div class="chief-field">
                                <label>Manu. Promo Desc</label>
                                <input wire:model="manu_promotion_description" class="chief-input w-full max-w-xs" />
                            </div>
                            <div class="chief-field">
                                <label>Manu. Promo Code</label>
                                <input wire:model="manu_promotion_code" class="chief-input w-40" />
                            </div>
                            <div class="chief-field">
                                <label>Manu. BaseCount</label>
                                <input wire:model="manu_base_count" class="chief-input w-28 text-right" />
                            </div>
                        </fieldset>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-slate-300 bg-slate-100 px-1 flex-wrap gap-2">
            <div class="flex flex-wrap overflow-x-auto">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        @class([
                            'px-3 py-1.5 text-sm border-r border-slate-300 whitespace-nowrap',
                            'bg-white font-semibold text-sky-800' => $activeTab === $key,
                            'text-slate-600 hover:bg-slate-200' => $activeTab !== $key,
                        ])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2 py-2 pe-2">
                <a href="{{ route('inventory.items.index') }}" wire:navigate class="chief-btn">Cancel</a>
                <button type="submit" class="chief-btn-primary">Save Changes</button>
            </div>
        </div>
    </form>

    @if ($showJournal && $item)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeJournal">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-4xl max-h-[90vh] overflow-auto">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Inventory Journal — {{ $item->item_code }}</span>
                    <button type="button" wire:click="closeJournal" class="text-white hover:text-red-200">×</button>
                </div>
                <div class="p-3">
                    <div class="chief-grid border border-slate-300 overflow-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Site</th>
                                    <th>Source</th>
                                    <th>Reference</th>
                                    <th class="text-right">Qty Change</th>
                                    <th class="text-right">Qty After</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($journalEntries as $entry)
                                    <tr>
                                        <td>{{ optional($entry->created_at)?->format('n/j/Y g:ia') }}</td>
                                        <td class="font-mono">{{ $entry->site?->code ?: '—' }}</td>
                                        <td>{{ $entry->source_type }}</td>
                                        <td class="font-mono">{{ $entry->reference }}</td>
                                        <td class="text-right">{{ number_format($entry->qty_change, 2) }}</td>
                                        <td class="text-right">{{ number_format($entry->qty_after, 2) }}</td>
                                        <td class="text-right">${{ number_format((float) $entry->unit_cost, 4) }}</td>
                                        <td>{{ $entry->notes }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-slate-500 px-2 py-4">No journal entries for this item.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
