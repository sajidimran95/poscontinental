<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\DiscountSchedule;
use App\Models\InventoryJournalEntry;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemPrice;
use App\Models\ItemSubstitute;
use App\Models\ItemSupplier;
use App\Models\ItemType;
use App\Models\ItemUpc;
use App\Models\PricingMethod;
use App\Models\PriceLevel;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\TaxSchedule;
use App\Models\UomSchedule;
use App\Support\ItemMedia;
use App\Support\TobaccoItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Item')] class extends Component
{

    public ?Item $item = null;

    /** View-only mode (same layout as edit, no changes). */
    public bool $viewMode = false;

    public string $activeTab = 'general';

    public string $item_code = '';

    public string $item_type = 'Standard Item';

    public string $class = '';

    public string $description = '';

    public string $extended_description = '';

    public string $product_highlights = '';

    public string $list_price = '';

    public string $msrp = '';

    public string $standard_cost = '';

    public string $current_cost = '';

    public string $last_cost = '';

    public string $average_cost = '';

    public string $quantity_in_stock = '';

    public string $allocated_qty = '';

    public string $on_order_qty = '';

    public string $back_order_qty = '';

    public string $reorder_point = '';

    public string $restock_level = '';

    public string $lead_time_days = '';

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

    public string $unit_of_measure = '';

    public bool $is_inactive = false;

    public bool $can_sell = true;

    public bool $can_order = true;

    public bool $allow_back_order = true;

    public bool $available_on_website = false;

    public bool $msa_reporting = false;

    public bool $state_reporting = false;

    public string $item_tracking = 'None';

    public string $barcode_format = 'UPC-A';

    public string $shipping_weight = '';

    public string $tare_weight = '';

    public string $manufacturer = '';

    public string $tobacco_product_type = '';

    public string $tobacco_brand_code = '';

    public string $cigarette_pack_size = '';

    public string $tobacco_total_oz = '';

    public string $tobacco_stick_count = '';

    public string $item_line_message = '';

    public string $comments = '';

    public string $manu_product_id = '';

    public string $manu_promotion_item = '';

    public string $manu_promotion_description = '';

    public string $manu_promotion_code = '';

    public string $manu_base_count = '';

    public string $primary_upc = '';

    public string $upcScan = '';

    public string $upcScanMessage = '';

    public ?string $image_path = null;

    public ?string $thumbnail_path = null;

    public bool $mediaUploading = false;

    /** @var array<int, array{upc:string,is_primary:bool}> */
    public array $upcs = [];

    /** @var array<int, array{uom:string,price:string,alias_code:string,price_level_id:?int}> */
    public array $prices = [];

    /** @var array<int, array{supplier_id:?int,supplier_item_code:string,lead_time:string,is_default:bool,last_cost:string,avg_cost:string,last_received_at:?string}> */
    public array $suppliers = [];

    /** @var array<int, array{substitute_item_id:?int,quantity:string,force_substitute:bool}> */
    public array $substitutes = [];

    /** @var array<int, array{batch_number:string,quantity:string,expiry_date:?string,received_at:?string,notes:string}> */
    public array $batches = [];

    public bool $showJournal = false;

    public function mount(?Item $item = null): void
    {
        $this->viewMode = request()->routeIs('inventory.items.show');

        if ($item?->exists) {
            abort_unless($item->company_id === auth()->user()->company_id, 403);
            $this->item = $item->load(['upcs', 'prices', 'itemSuppliers', 'substitutes', 'batches']);

            $data = $item->only([
                'item_code', 'item_type', 'class', 'description', 'extended_description', 'product_highlights',
                'list_price', 'msrp', 'standard_cost', 'current_cost', 'last_cost', 'average_cost',
                'quantity_in_stock', 'allocated_qty', 'on_order_qty', 'back_order_qty',
                'reorder_point', 'restock_level', 'lead_time_days',
                'department_id', 'category_id', 'subcategory_id', 'uom_schedule_id',
                'tax_schedule_id', 'promotion_schedule_id', 'pricing_method_id',
                'unit_of_measure', 'is_inactive', 'can_sell', 'can_order', 'allow_back_order',
                'available_on_website', 'item_tracking', 'barcode_format',
                'shipping_weight', 'tare_weight', 'manufacturer', 'tobacco_product_type', 'tobacco_brand_code',
                'cigarette_pack_size', 'tobacco_total_oz', 'tobacco_stick_count',
                'msa_reporting', 'state_reporting',
                'item_line_message', 'comments',
                'manu_product_id', 'manu_promotion_item', 'manu_promotion_description',
                'manu_promotion_code', 'manu_base_count', 'primary_upc', 'image_path', 'thumbnail_path',
            ]);

            foreach ([
                'item_code', 'item_type', 'class', 'description', 'extended_description', 'product_highlights',
                'list_price', 'msrp', 'standard_cost', 'current_cost', 'last_cost', 'average_cost',
                'quantity_in_stock', 'allocated_qty', 'on_order_qty', 'back_order_qty',
                'reorder_point', 'restock_level', 'lead_time_days', 'unit_of_measure',
                'item_tracking', 'barcode_format', 'shipping_weight', 'tare_weight',
                'manufacturer', 'tobacco_product_type', 'tobacco_brand_code', 'cigarette_pack_size',
                'tobacco_total_oz', 'tobacco_stick_count', 'item_line_message', 'comments',
                'manu_product_id', 'manu_promotion_item', 'manu_promotion_description',
                'manu_promotion_code', 'manu_base_count', 'primary_upc',
            ] as $stringProp) {
                if (! array_key_exists($stringProp, $data) || $data[$stringProp] === null) {
                    $data[$stringProp] = '';
                } else {
                    $data[$stringProp] = (string) $data[$stringProp];
                }
            }

            $this->fill($data);

            $this->msa_reporting = (bool) $item->msa_reporting;
            $this->state_reporting = (bool) $item->state_reporting;

            // Keep image paths as plain strings for reliable preview URLs.
            $this->image_path = filled($item->image_path) ? (string) $item->image_path : null;
            $this->thumbnail_path = filled($item->thumbnail_path) ? (string) $item->thumbnail_path : null;
            $this->forgetMissingMediaPaths();

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
                'price_level_id' => $p->price_level_id,
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

            $this->batches = $item->batches->map(fn (ItemBatch $b) => [
                'batch_number' => (string) $b->batch_number,
                'quantity' => (string) $b->quantity,
                'expiry_date' => optional($b->expiry_date)?->format('Y-m-d'),
                'received_at' => optional($b->received_at)?->format('Y-m-d'),
                'notes' => (string) ($b->notes ?? ''),
            ])->all();
        }

        if ($this->upcs === []) {
            $this->upcs[] = ['upc' => $this->primary_upc, 'is_primary' => true];
        }

        // Prefill UPC from barcode scan (?upc=) when creating a new item
        if (! $item?->exists) {
            $scanUpc = trim((string) request()->query('upc', ''));
            if ($scanUpc !== '') {
                $this->primary_upc = $scanUpc;
                $this->upcs = [['upc' => $scanUpc, 'is_primary' => true]];
            }
        }

        if ($this->prices === []) {
            $this->prices[] = [
                'uom' => strtoupper(trim($this->unit_of_measure)),
                'price' => $this->list_price,
                'alias_code' => '',
                'price_level_id' => null,
            ];
        }

        $this->syncPricingUomFromInventory();

        if ($this->suppliers === []) {
            $this->suppliers[] = [
                'supplier_id' => null,
                'supplier_item_code' => '',
                'lead_time' => '',
                'is_default' => true,
                'last_cost' => '',
                'avg_cost' => '',
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

        if ($this->batches === []) {
            $this->addBatch();
        }
    }

    public function updatedActiveTab($value): void
    {
        if ($value === 'pricing') {
            $this->syncPricingUomFromInventory();
        }
    }

    public function updatedUomScheduleId($value): void
    {
        if (! filled($value)) {
            return;
        }

        $schedule = UomSchedule::query()
            ->where('company_id', auth()->user()->company_id)
            ->find((int) $value);

        if ($schedule?->base_uom) {
            $this->unit_of_measure = strtoupper((string) $schedule->base_uom);
            $this->syncPricingUomFromInventory();
        }
    }

    public function updatedUnitOfMeasure($value): void
    {
        $this->unit_of_measure = strtoupper(trim((string) $value));
        $this->syncPricingUomFromInventory();
    }

    /**
     * Keep the primary Pricing row UOM in sync with Inventory Unit of Measure.
     */
    protected function syncPricingUomFromInventory(): void
    {
        $uom = strtoupper(trim($this->unit_of_measure));

        // Reassign the whole array so Livewire detects nested UOM changes.
        $prices = $this->prices;

        if ($prices === []) {
            $prices[] = [
                'uom' => $uom,
                'price' => $this->list_price,
                'alias_code' => '',
                'price_level_id' => null,
            ];
            $this->prices = $prices;

            return;
        }

        // Always mirror inventory on the first (default) price row — including empty.
        $prices[0]['uom'] = $uom;

        foreach ($prices as $i => $row) {
            if ($i === 0) {
                continue;
            }
            if (! filled($row['uom'] ?? null)) {
                $prices[$i]['uom'] = $uom;
            } else {
                $prices[$i]['uom'] = strtoupper(trim((string) $row['uom']));
            }
        }

        $this->prices = $prices;
    }

    /**
     * Standard unit codes for item default UOM and price rows (brief: selection, not free text).
     *
     * @return array<int, string>
     */
    protected function uomOptions(): array
    {
        $defaults = ['EA', 'BX', 'CS', 'CTN', 'PK', 'DZ', 'LB', 'KG', 'OZ', 'GAL', 'PLT', 'BAG', 'BOT', 'CAN'];
        $fromSchedules = UomSchedule::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereNotNull('base_uom')
            ->pluck('base_uom')
            ->map(fn ($u) => strtoupper(trim((string) $u)))
            ->filter()
            ->all();

        $current = filled($this->unit_of_measure) ? [strtoupper(trim($this->unit_of_measure))] : [];
        $fromPrices = collect($this->prices)
            ->pluck('uom')
            ->map(fn ($u) => strtoupper(trim((string) $u)))
            ->filter()
            ->all();

        return collect(array_merge($defaults, $fromSchedules, $current, $fromPrices))
            ->unique()
            ->sort()
            ->values()
            ->all();
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
            'uomOptions' => $this->uomOptions(),
            'taxSchedules' => TaxSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'promotionSchedules' => DiscountSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'pricingMethods' => PricingMethod::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'supplierOptions' => Supplier::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('name')->get(),
            'substituteOptions' => Item::query()->where('company_id', $companyId)
                ->when($this->item, fn ($q) => $q->where('id', '!=', $this->item->id))
                ->orderBy('item_code')->limit(500)->get(['id', 'item_code', 'description']),
            'sites' => Site::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'itemTypes' => ItemType::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->whenEmpty(fn () => collect([
                    (object) ['name' => 'Standard Item'],
                    (object) ['name' => 'Kit'],
                    (object) ['name' => 'Non-Inventory'],
                    (object) ['name' => 'Service'],
                ])),
            'trackingOptions' => ['None', 'Serial', 'Lot'],
            'barcodeFormats' => ['UPC-A', 'UPC-E', 'EAN-13', 'EAN-8', 'Code128', 'Code39'],
            'tabs' => [
                'general' => 'General',
                'inventory' => 'Inventory',
                'pricing' => 'Pricing',
                'extended' => 'Extended Description',
                'suppliers' => 'Suppliers',
                'substitutes' => 'Substitutes',
                'batches' => 'Batches',
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

    public function updatedTobaccoProductType(): void
    {
        if ($this->tobacco_product_type === '') {
            $this->tobacco_brand_code = '';
            $this->cigarette_pack_size = '';
            $this->tobacco_total_oz = '';
            $this->tobacco_stick_count = '';

            return;
        }

        if ($this->tobacco_product_type !== 'cigarettes') {
            $this->cigarette_pack_size = '';
        }
        if (! in_array($this->tobacco_product_type, ['ryo', 'otp'], true)) {
            $this->tobacco_total_oz = '';
        }
        if (! in_array($this->tobacco_product_type, ['pc1', 'otp'], true)) {
            $this->tobacco_stick_count = '';
        }
    }

    public function updatedCategoryId(): void
    {
        $this->subcategory_id = null;
        $this->suggestTobaccoTypeFromClassification();
    }

    public function updatedSubcategoryId(): void
    {
        $this->suggestTobaccoTypeFromClassification();
    }

    private function suggestTobaccoTypeFromClassification(): void
    {
        if ($this->tobacco_product_type !== '') {
            return;
        }

        $suggested = TobaccoItem::suggestedFormType($this->classificationItem());
        if ($suggested) {
            $this->tobacco_product_type = $suggested;
            $this->updatedTobaccoProductType();
        }
    }

    private function classificationItem(): Item
    {
        $item = new Item([
            'tobacco_product_type' => $this->tobacco_product_type !== '' ? $this->tobacco_product_type : null,
            'tobacco_brand_code' => $this->tobacco_brand_code !== '' ? $this->tobacco_brand_code : null,
            'tobacco_stick_count' => filled($this->tobacco_stick_count) ? (int) $this->tobacco_stick_count : 0,
            'tobacco_total_oz' => filled($this->tobacco_total_oz) ? $this->tobacco_total_oz : 0,
        ]);
        $item->setRelation(
            'category',
            $this->category_id ? Category::query()->find($this->category_id) : null
        );
        $item->setRelation(
            'subcategory',
            $this->subcategory_id ? Subcategory::query()->find($this->subcategory_id) : null
        );

        return $item;
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

    /**
     * Focus barcode field for scanner; if value present, apply UPC.
     */
    public function focusUpcScan(): void
    {
        if (trim($this->upcScan) !== '') {
            $this->applyUpcScan();

            return;
        }

        $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-scan")?.focus(); });');
    }

    /**
     * Scan / Enter: add barcode into Aliases / Primary UPC (first empty row, else new row).
     */
    public function applyUpcScan(?string $code = null): void
    {
        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code ?? $this->upcScan) ?? '');
        $this->upcScanMessage = '';

        if ($code === '') {
            $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-scan")?.focus(); });');

            return;
        }

        $this->assignScannedUpc($code);
        $this->upcScan = '';
        $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-scan")?.focus(); });');
    }

    /**
     * Scan / Enter into a specific UPC row.
     */
    public function applyUpcScanToRow(int $index, ?string $code = null): void
    {
        if (! isset($this->upcs[$index])) {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code ?? ($this->upcs[$index]['upc'] ?? '')) ?? '');
        $this->upcScanMessage = '';

        if ($code === '') {
            $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-row-'.$index.'")?.focus(); });');

            return;
        }

        $upcs = $this->upcs;
        $upcs[$index]['upc'] = $code;
        $this->upcs = $upcs;

        if ($upcs[$index]['is_primary'] ?? false) {
            $this->primary_upc = $code;
        }

        $this->warnIfUpcExistsElsewhere($code);
        $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-scan")?.focus(); });');
    }

    public function focusUpcRowScan(int $index): void
    {
        if (! isset($this->upcs[$index])) {
            return;
        }

        if (trim((string) ($this->upcs[$index]['upc'] ?? '')) !== '') {
            $this->applyUpcScanToRow($index);

            return;
        }

        $this->js('requestAnimationFrame(() => { document.getElementById("item-upc-row-'.$index.'")?.focus(); });');
    }

    protected function assignScannedUpc(string $code): void
    {
        $this->warnIfUpcExistsElsewhere($code);

        $upcs = $this->upcs;
        foreach ($upcs as $i => $row) {
            if (trim((string) ($row['upc'] ?? '')) === '') {
                $upcs[$i]['upc'] = $code;
                if ($upcs[$i]['is_primary'] ?? false) {
                    $this->primary_upc = $code;
                }
                $this->upcs = $upcs;

                return;
            }
            // Already on this item — ignore
            if (mb_strtolower(trim((string) $row['upc'])) === mb_strtolower($code)) {
                $this->upcScanMessage = 'Barcode already listed on this item.';

                return;
            }
        }

        $upcs[] = [
            'upc' => $code,
            'is_primary' => $upcs === [],
        ];
        if (count($upcs) === 1) {
            $this->primary_upc = $code;
            $upcs[0]['is_primary'] = true;
        }
        $this->upcs = $upcs;
    }

    protected function warnIfUpcExistsElsewhere(string $code): void
    {
        $companyId = (int) auth()->user()->company_id;
        $existing = Item::findByScanCode($companyId, $code, 'any');
        if (! $existing) {
            return;
        }
        if ($this->item?->exists && (int) $existing->id === (int) $this->item->id) {
            return;
        }

        $this->upcScanMessage = 'Barcode already used by item '.$existing->item_code.'.';
    }

    public function addPrice(): void
    {
        $uom = strtoupper(trim($this->unit_of_measure));
        $this->prices = [
            ...$this->prices,
            ['uom' => $uom, 'price' => '', 'alias_code' => '', 'price_level_id' => null],
        ];
    }

    public function removePrice(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
        if ($this->prices === []) {
            $uom = strtoupper(trim($this->unit_of_measure));
            $this->prices = [
                ['uom' => $uom, 'price' => $this->list_price, 'alias_code' => '', 'price_level_id' => null],
            ];
        }
    }

    public function addSupplierRow(): void
    {
        $this->suppliers[] = [
            'supplier_id' => null,
            'supplier_item_code' => '',
            'lead_time' => '',
            'is_default' => false,
            'last_cost' => '',
            'avg_cost' => '',
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

    public function addBatch(): void
    {
        if (! in_array($this->item_tracking, ['Lot', 'Serial'], true)) {
            $this->item_tracking = 'Lot';
        }

        $this->batches[] = [
            'batch_number' => '',
            'quantity' => '',
            'expiry_date' => null,
            'received_at' => null,
            'notes' => '',
        ];
    }

    public function removeBatch(int $index): void
    {
        unset($this->batches[$index]);
        $this->batches = array_values($this->batches);
        if ($this->batches === []) {
            $this->addBatch();
        }
    }

    public function mediaUrl(?string $path): ?string
    {
        return ItemMedia::url($path);
    }

    protected function forgetMissingMediaPaths(): void
    {
        $changed = false;

        if (filled($this->image_path) && ! ItemMedia::exists($this->image_path)) {
            $this->image_path = null;
            $changed = true;
        }

        if (filled($this->thumbnail_path) && ! ItemMedia::exists($this->thumbnail_path)) {
            $this->thumbnail_path = null;
            $changed = true;
        }

        if ($changed && $this->item?->exists) {
            $this->item->update([
                'image_path' => $this->image_path,
                'thumbnail_path' => $this->thumbnail_path,
            ]);
        }
    }

    public function uploadItemMedia(string $dataUrl, string $originalName, string $type = 'image'): void
    {
        if ($this->viewMode) {
            return;
        }

        if (! in_array($type, ['image', 'thumbnail'], true)) {
            return;
        }

        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $dataUrl, $matches)) {
            $this->addError($type === 'image' ? 'image_path' : 'thumbnail_path', 'Invalid image data.');

            return;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false || strlen($binary) < 24) {
            $this->addError($type === 'image' ? 'image_path' : 'thumbnail_path', 'Could not read the image file.');

            return;
        }

        if (strlen($binary) > 8 * 1024 * 1024) {
            $this->addError($type === 'image' ? 'image_path' : 'thumbnail_path', 'Image must be 8 MB or smaller.');

            return;
        }

        $mime = strtolower($matches[1]);
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];
        $ext = $extMap[$mime] ?? strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $this->addError($type === 'image' ? 'image_path' : 'thumbnail_path', 'Image must be JPG, PNG, GIF, WEBP, or BMP.');

            return;
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $directory = $type === 'thumbnail' ? 'items/thumbnails' : 'items/images';
        Storage::disk('public')->makeDirectory($directory);
        $path = $directory.'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($path, $binary);
        ItemMedia::publishToPublic($path);

        if ($type === 'image') {
            $this->assignImagePath($path);
        } else {
            $this->assignThumbnailPath($path);
        }
    }

    public function assignImagePath(string $path): void
    {
        if ($this->viewMode) {
            return;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (! ItemMedia::exists($path)) {
            $this->addError('image_path', 'Uploaded image was not found on disk.');

            return;
        }

        if (filled($this->image_path) && $this->image_path !== $path) {
            ItemMedia::forgetPublicCopy($this->image_path);
            if (Storage::disk('public')->exists($this->image_path)) {
                Storage::disk('public')->delete($this->image_path);
            }
        }

        ItemMedia::publishToPublic($path);
        $this->image_path = $path;
        $this->resetErrorBag('image_path');

        // Auto-fill thumbnail when empty.
        if (! filled($this->thumbnail_path)) {
            $this->copyImageToThumbnail();
        }

        $this->persistMediaPaths();
    }

    public function assignThumbnailPath(string $path): void
    {
        if ($this->viewMode) {
            return;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (! ItemMedia::exists($path)) {
            $this->addError('thumbnail_path', 'Uploaded thumbnail was not found on disk.');

            return;
        }

        if (filled($this->thumbnail_path) && $this->thumbnail_path !== $path) {
            ItemMedia::forgetPublicCopy($this->thumbnail_path);
            if (Storage::disk('public')->exists($this->thumbnail_path)) {
                Storage::disk('public')->delete($this->thumbnail_path);
            }
        }

        ItemMedia::publishToPublic($path);
        $this->thumbnail_path = $path;
        $this->resetErrorBag('thumbnail_path');
        $this->persistMediaPaths();
    }

    protected function persistMediaPaths(): void
    {
        if (! $this->item?->exists) {
            return;
        }

        $this->item->update([
            'image_path' => $this->image_path,
            'thumbnail_path' => $this->thumbnail_path,
        ]);
    }

    public function removeImage(): void
    {
        if ($this->viewMode) {
            return;
        }

        if (filled($this->image_path)) {
            ItemMedia::forgetPublicCopy($this->image_path);
            if (Storage::disk('public')->exists($this->image_path)) {
                Storage::disk('public')->delete($this->image_path);
            }
        }
        $this->image_path = null;
        $this->persistMediaPaths();
    }

    public function removeThumbnail(): void
    {
        if ($this->viewMode) {
            return;
        }

        if (filled($this->thumbnail_path)) {
            ItemMedia::forgetPublicCopy($this->thumbnail_path);
            if (Storage::disk('public')->exists($this->thumbnail_path)) {
                Storage::disk('public')->delete($this->thumbnail_path);
            }
        }
        $this->thumbnail_path = null;
        $this->persistMediaPaths();
    }

    public function copyImageToThumbnail(): void
    {
        if ($this->viewMode) {
            return;
        }

        $this->resetErrorBag('thumbnail_path');

        if (! filled($this->image_path) || ! ItemMedia::exists($this->image_path)) {
            $this->addError('thumbnail_path', 'Upload a main image first, then copy it to thumbnail.');

            return;
        }

        // Ensure source is on the public disk for copy().
        if (! Storage::disk('public')->exists($this->image_path)) {
            $this->addError('thumbnail_path', 'Main image file is missing on the server.');

            return;
        }

        $ext = pathinfo($this->image_path, PATHINFO_EXTENSION) ?: 'jpg';
        $newThumb = 'items/thumbnails/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->makeDirectory('items/thumbnails');

        if (filled($this->thumbnail_path) && $this->thumbnail_path !== $this->image_path) {
            ItemMedia::forgetPublicCopy($this->thumbnail_path);
            if (Storage::disk('public')->exists($this->thumbnail_path)) {
                Storage::disk('public')->delete($this->thumbnail_path);
            }
        }

        Storage::disk('public')->copy($this->image_path, $newThumb);
        ItemMedia::publishToPublic($newThumb);
        $this->thumbnail_path = $newThumb;
        $this->persistMediaPaths();
    }

    public function save(): void
    {
        abort_if($this->viewMode, 403);

        try {
            $this->validate([
                'item_code' => 'required|string|max:64',
                'description' => 'required|string|max:2000',
                'unit_of_measure' => 'nullable|string|max:16',
                'list_price' => 'nullable|numeric|min:0',
                'msrp' => 'nullable|numeric|min:0',
                'standard_cost' => 'nullable|numeric|min:0',
                'current_cost' => 'nullable|numeric|min:0',
                'reorder_point' => 'nullable|numeric|min:0',
                'restock_level' => 'nullable|numeric|min:0',
                'lead_time_days' => 'nullable|integer|min:0',
                'shipping_weight' => 'nullable|numeric|min:0',
                'tare_weight' => 'nullable|numeric|min:0',
                'image_path' => 'nullable|string|max:255',
                'thumbnail_path' => 'nullable|string|max:255',
                'upcs.*.upc' => 'nullable|string|max:64',
                'prices.*.uom' => 'nullable|string|max:16',
                'prices.*.price' => 'nullable|numeric',
                'suppliers.*.supplier_id' => 'nullable|integer|exists:suppliers,id',
                'substitutes.*.substitute_item_id' => 'nullable|integer|exists:items,id',
                'substitutes.*.quantity' => 'nullable|numeric',
                'batches.*.batch_number' => 'nullable|string|max:64',
                'batches.*.quantity' => 'nullable|numeric|min:0',
                'batches.*.expiry_date' => 'nullable|date',
                'batches.*.received_at' => 'nullable|date',
                'batches.*.notes' => 'nullable|string|max:500',
            ], [
                'item_code.required' => 'Item Code is required.',
                'description.required' => 'Description is required.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keys = array_keys($e->errors());
            if (array_intersect($keys, ['item_code', 'description', 'list_price'])) {
                $this->activeTab = 'general';
            } elseif (array_intersect($keys, ['reorder_point', 'restock_level'])) {
                $this->activeTab = 'inventory';
            } elseif (array_intersect($keys, ['image_path', 'thumbnail_path', 'extended_description', 'product_highlights'])) {
                $this->activeTab = 'extended';
            }
            throw $e;
        }

        $primary = collect($this->upcs)->firstWhere('is_primary', true);
        if ($primary && filled($primary['upc'] ?? null)) {
            $this->primary_upc = $primary['upc'];
        } else {
            $firstFilled = collect($this->upcs)->first(fn ($r) => filled($r['upc'] ?? null));
            $this->primary_upc = $firstFilled['upc'] ?? $this->primary_upc;
        }

        // Ensure thumbnail exists when image is set.
        if (filled($this->image_path) && ! filled($this->thumbnail_path) && Storage::disk('public')->exists($this->image_path)) {
            $this->copyImageToThumbnail();
        }

        $imagePath = filled($this->image_path) ? $this->image_path : null;
        $thumbPath = filled($this->thumbnail_path) ? $this->thumbnail_path : null;

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $amount = static fn ($v) => ($v === null || $v === '') ? 0 : $v;

        $this->suggestTobaccoTypeFromClassification();

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
            'list_price' => $amount($this->list_price),
            'msrp' => $amount($this->msrp),
            'standard_cost' => $amount($this->standard_cost),
            'current_cost' => $amount($this->current_cost),
            'last_cost' => $amount($this->last_cost),
            'average_cost' => $amount($this->average_cost),
            'reorder_point' => $amount($this->reorder_point),
            'restock_level' => $amount($this->restock_level),
            'lead_time_days' => (int) $amount($this->lead_time_days),
            'department_id' => $nullableId($this->department_id),
            'category_id' => $nullableId($this->category_id),
            'subcategory_id' => $nullableId($this->subcategory_id),
            'uom_schedule_id' => $nullableId($this->uom_schedule_id),
            'tax_schedule_id' => $nullableId($this->tax_schedule_id),
            'promotion_schedule_id' => $nullableId($this->promotion_schedule_id),
            'pricing_method_id' => $nullableId($this->pricing_method_id),
            'unit_of_measure' => $this->unit_of_measure !== '' ? $this->unit_of_measure : null,
            'is_inactive' => $this->is_inactive,
            'can_sell' => $this->can_sell,
            'can_order' => $this->can_order,
            'allow_back_order' => $this->allow_back_order,
            'available_on_website' => $this->available_on_website,
            'msa_reporting' => $this->msa_reporting,
            'state_reporting' => $this->state_reporting,
            'item_tracking' => $this->item_tracking,
            'barcode_format' => $this->barcode_format,
            'shipping_weight' => $amount($this->shipping_weight),
            'tare_weight' => $amount($this->tare_weight),
            'manufacturer' => $this->manufacturer,
            'tobacco_product_type' => $this->tobacco_product_type !== '' ? $this->tobacco_product_type : null,
            'tobacco_brand_code' => $this->tobacco_product_type !== '' && $this->tobacco_brand_code !== ''
                ? strtoupper($this->tobacco_brand_code)
                : null,
            'cigarette_pack_size' => $this->tobacco_product_type === 'cigarettes' && filled($this->cigarette_pack_size)
                ? (int) $this->cigarette_pack_size
                : null,
            'tobacco_total_oz' => in_array($this->tobacco_product_type, ['ryo', 'otp'], true) && filled($this->tobacco_total_oz)
                ? $this->tobacco_total_oz
                : null,
            'tobacco_stick_count' => in_array($this->tobacco_product_type, ['pc1', 'otp'], true) && filled($this->tobacco_stick_count)
                ? (int) $this->tobacco_stick_count
                : null,
            'item_line_message' => $this->item_line_message,
            'comments' => $this->comments,
            'manu_product_id' => $this->manu_product_id,
            'manu_promotion_item' => $this->manu_promotion_item,
            'manu_promotion_description' => $this->manu_promotion_description,
            'manu_promotion_code' => $this->manu_promotion_code,
            'manu_base_count' => $amount($this->manu_base_count),
            'primary_upc' => $this->primary_upc,
        ];

        $wasCreate = ! $this->item?->exists;

        $item = DB::transaction(function () use ($data, $amount) {
            if ($this->item) {
                $this->item->update($data);
                $item = $this->item->fresh();
            } else {
                $data['quantity_in_stock'] = $amount($this->quantity_in_stock);
                $data['allocated_qty'] = $amount($this->allocated_qty);
                $data['on_order_qty'] = $amount($this->on_order_qty);
                $data['back_order_qty'] = $amount($this->back_order_qty);
                $data['last_received_at'] = $this->last_received_at ?: null;
                $data['last_ordered_at'] = $this->last_ordered_at ?: null;
                $data['last_sold_at'] = $this->last_sold_at ?: null;
                $data['last_count_date'] = $this->last_count_date ?: null;
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
                    'price' => $amount($row['price'] ?? ''),
                    'alias_code' => $row['alias_code'] ?: null,
                    'price_level_id' => filled($row['price_level_id'] ?? null) ? (int) $row['price_level_id'] : null,
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
                    'lead_time' => (int) $amount($row['lead_time'] ?? ''),
                    'is_default' => (bool) ($row['is_default'] ?? false),
                    'last_cost' => $amount($row['last_cost'] ?? ''),
                    'avg_cost' => $amount($row['avg_cost'] ?? ''),
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

            $trackingType = in_array($this->item_tracking, ['Lot', 'Serial'], true)
                ? $this->item_tracking
                : 'Lot';

            $item->batches()->delete();
            foreach (array_values($this->batches) as $i => $row) {
                $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                if ($batchNumber === '') {
                    continue;
                }
                $item->batches()->create([
                    'company_id' => auth()->user()->company_id,
                    'batch_number' => $batchNumber,
                    'tracking_type' => $trackingType,
                    'quantity' => $amount($row['quantity'] ?? ''),
                    'expiry_date' => filled($row['expiry_date'] ?? null) ? $row['expiry_date'] : null,
                    'received_at' => filled($row['received_at'] ?? null) ? $row['received_at'] : null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                    'sort_order' => $i,
                ]);
            }

            return $item;
        });

        session()->flash(
            'status',
            'Item saved.'.($imagePath ? ' Image stored.' : '')
            .($wasCreate ? ' Marked as New for '.Item::NEW_ITEM_DAYS.' days (auto-clears after that).' : '')
        );

        $this->redirect(route('inventory.items.index'), navigate: true);
    }

    public function copyItem(): void
    {
        if (! $this->item?->exists) {
            session()->flash('status', 'Save the item first, then copy.');

            return;
        }

        $this->item = null;
        $this->item_code = '';
        $this->quantity_in_stock = '0';
        $this->allocated_qty = '0';
        session()->flash('status', 'Item copied. Enter a new item code and save.');
    }

    public function cancelAction(): mixed
    {
        return $this->redirect(route('inventory.items.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form" @class(['item-form-readonly' => $viewMode])>
        @php
            $pageTitle = $viewMode
                ? 'View Item — '.$item_code
                : ($item ? 'Edit Item — '.$item_code : 'New Item');
            $imageUrl = $this->mediaUrl($image_path);
            $thumbUrl = $this->mediaUrl($thumbnail_path);
        @endphp
        <x-action-bar :title="$pageTitle">
            <x-slot:menu>
                <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" :disabled="$viewMode" />
                <x-action-item label="Copy Item" kbd="Ctrl+O" sep wire:click="copyItem" :disabled="! $item" />
                <x-action-item label="Refresh" :disabled="true" sep />
                <x-action-item label="Cancel" kbd="Ctrl+Q" sep wire:click="cancelAction" />
            </x-slot:menu>
        </x-action-bar>

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <fieldset class="item-form-fields" @disabled($viewMode)>
        <div class="entity-body">
            <div class="entity-header">
                <div class="so-form-row so-form-row-pair entity-header-row">
                    <label class="so-form-lbl so-field-req" for="item_code">Item Code</label>
                    <input id="item_code" wire:model="item_code" class="so-input font-mono @error('item_code') is-invalid @enderror" @disabled($item) />
                    <span class="so-form-lbl">Status</span>
                    <div class="entity-status-btns">
                        <button type="button" wire:click="$set('is_inactive', false)" @class(['desk-btn desk-btn-sm', 'is-on' => ! $is_inactive])>Active</button>
                        <button type="button" wire:click="$set('is_inactive', true)" @class(['desk-btn desk-btn-sm', 'is-on-danger' => $is_inactive])>Inactive</button>
                    </div>
                </div>
                @if (($imageUrl || $thumbUrl) && $activeTab === 'extended')
                    <div class="item-header-media">
                        <div class="item-preview-frame item-preview-frame-sm">
                            <img src="{{ $imageUrl ?: $thumbUrl }}" alt="{{ $item_code }}" class="item-preview" />
                        </div>
                    </div>
                @endif
                @if ($activeTab === 'inventory')
                    <div class="entity-balance">Available: <strong>{{ number_format($availableQty, 2) }}</strong></div>
                @elseif ($activeTab === 'pricing' || $activeTab === 'general')
                    <div class="entity-balance">List: <strong>${{ number_format((float) $list_price, 2) }}</strong></div>
                @endif
            </div>

            @error('item_code') <p class="so-field-error mb-2" role="alert">{{ $message }}</p> @enderror

            @if ($activeTab === 'general')
                <div class="inv-top-grid item-tab-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Item identity</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="item_type">Item Type</label>
                            <div class="so-lookup-row">
                                <select id="item_type" wire:model="item_type" class="so-input">
                                    @foreach ($itemTypes as $type)
                                        <option value="{{ $type->name }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'item_types']) }}" wire:navigate class="desk-btn desk-btn-sm" title="Create item types in Lookups">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="class">Class</label>
                            <input id="class" wire:model="class" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side so-form-row-top">
                            <label class="so-form-lbl so-field-req" for="description">Description</label>
                            <textarea id="description" wire:model="description" rows="3" class="so-input so-input-area @error('description') is-invalid @enderror"></textarea>
                        </div>
                        @error('description') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="list_price_general">List Price</label>
                            <input id="list_price_general" wire:model.live="list_price" class="so-input text-right @error('list_price') is-invalid @enderror" style="max-width:8rem" placeholder="0" />
                        </div>
                        @error('list_price') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="inv-card" style="grid-column: span 2">
                        <div class="inv-card-title">Classification</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="department_id">Department</label>
                            <div class="so-lookup-row">
                                <select id="department_id" wire:model.live="department_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'departments']) }}" wire:navigate class="desk-btn desk-btn-sm" title="Create departments in Lookups">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="category_id">Category</label>
                            <div class="so-lookup-row">
                                <select id="category_id" wire:model.live="category_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'categories']) }}" wire:navigate class="desk-btn desk-btn-sm" title="Create categories in Lookups">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="subcategory_id">Sub Category</label>
                            <div class="so-lookup-row">
                                <select id="subcategory_id" wire:model="subcategory_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($subcategories as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'subcategories']) }}" wire:navigate class="desk-btn desk-btn-sm" title="Create sub categories in Lookups">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side so-form-row-top">
                            <span class="so-form-lbl">Reporting</span>
                            <div class="item-flag-list">
                                <label class="entity-check"><input type="checkbox" wire:model="msa_reporting" /> MSA Reporting</label>
                                <label class="entity-check"><input type="checkbox" wire:model="state_reporting" /> State Reporting</label>
                            </div>
                        </div>
                        <p class="item-hint" style="margin:0.25rem 0 0">
                            Check the box for each filing this item belongs in. If neither is checked, the item is left off MSA and Michigan state tobacco reports.
                        </p>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Aliases / Primary UPC</h3>
                        <button type="button" wire:click="addUpc" class="desk-btn desk-btn-sm">Add UPC</button>
                    </div>
                    <div class="so-entry item-upc-entry">
                        <span class="so-entry-label">Barcode</span>
                        <div class="so-scan-bar" role="search">
                            <button
                                type="button"
                                wire:click="focusUpcScan"
                                class="so-scan-btn"
                                title="Scan barcode into Primary UPC / aliases"
                            >
                                <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <input
                                id="item-upc-scan"
                                type="text"
                                wire:model.live="upcScan"
                                wire:keydown.enter.prevent="applyUpcScan($event.target.value)"
                                class="so-input so-entry-input"
                                placeholder="Scan barcode or type UPC / alias…"
                                autocomplete="off"
                                inputmode="text"
                            />
                            <button
                                type="button"
                                wire:click.prevent="applyUpcScan"
                                class="so-icon-btn so-entry-add-btn"
                                title="Add barcode"
                                aria-label="Add barcode"
                            >
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                            </button>
                        </div>
                    </div>
                    @if ($upcScanMessage !== '')
                        <div class="desk-flash" style="margin:0.5rem 0.75rem" role="status">{{ $upcScanMessage }}</div>
                    @endif
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table item-upc-table">
                            <colgroup>
                                <col class="col-primary" />
                                <col class="col-upc" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="text-center">Primary</th>
                                    <th>UPC / Alias</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcs as $i => $row)
                                    <tr>
                                        <td class="text-center">
                                            <input type="radio" name="primary_upc_radio" wire:click="setPrimaryUpc({{ $i }})" @checked($row['is_primary'] ?? false) aria-label="Primary UPC {{ $i + 1 }}" />
                                        </td>
                                        <td class="item-upc-row-cell">
                                            <div class="so-scan-bar item-upc-row-scan" role="search">
                                                <button
                                                    type="button"
                                                    wire:click="focusUpcRowScan({{ $i }})"
                                                    class="so-scan-btn"
                                                    title="Scan into this UPC row"
                                                >
                                                    <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                                        <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                                    </svg>
                                                    <span>Scan</span>
                                                </button>
                                                <input
                                                    id="item-upc-row-{{ $i }}"
                                                    type="text"
                                                    wire:model="upcs.{{ $i }}.upc"
                                                    wire:keydown.enter.prevent="applyUpcScanToRow({{ $i }}, $event.target.value)"
                                                    class="so-input font-mono item-cell-ctl"
                                                    placeholder="Scan or type UPC…"
                                                    autocomplete="off"
                                                />
                                            </div>
                                        </td>
                                        <td class="text-center"><button type="button" wire:click="removeUpc({{ $i }})" class="desk-btn desk-btn-sm">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($activeTab === 'inventory')
                <div class="inv-top-grid item-tab-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Units</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="uom_schedule_id">UOM Schedule</label>
                            <div class="so-lookup-row">
                                <select id="uom_schedule_id" wire:model.live="uom_schedule_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($uomSchedules as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}{{ $u->base_uom ? ' ('.$u->base_uom.')' : '' }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'uom_schedules']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="unit_of_measure">Unit of Measure</label>
                            <select id="unit_of_measure" wire:model.live="unit_of_measure" class="so-input @error('unit_of_measure') is-invalid @enderror" style="max-width:8rem">
                                <option value="">— Select —</option>
                                @foreach ($uomOptions as $uom)
                                    <option value="{{ $uom }}" @selected(strtoupper(trim((string) $unit_of_measure)) === $uom)>{{ $uom }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('unit_of_measure') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <p class="item-hint" style="border:0;margin:0.35rem 0 0;padding:0;font-size:0.75rem;color:#64748b">Also fills Pricing → U of M automatically.</p>
                        <div class="pt-2">
                            <button type="button" wire:click="openJournal" class="desk-btn" @disabled(! $item)>View Journal</button>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Reorder</div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="reorder_point">Reorder Point</label><input id="reorder_point" wire:model="reorder_point" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="restock_level">Restock Level</label><input id="restock_level" wire:model="restock_level" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="lead_time_days">Lead Time (days)</label><input id="lead_time_days" wire:model="lead_time_days" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">History</div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="last_received_at">Last Received</label><input id="last_received_at" type="date" wire:model="last_received_at" class="so-input" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="last_ordered_at">Last Ordered</label><input id="last_ordered_at" type="date" wire:model="last_ordered_at" class="so-input" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="last_sold_at">Last Sold</label><input id="last_sold_at" type="date" wire:model="last_sold_at" class="so-input" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="last_count_date">Last Count</label><input id="last_count_date" type="date" wire:model="last_count_date" class="so-input" /></div>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Current Quantities</h3>
                        <span class="entity-value">Available {{ number_format($availableQty, 2) }}</span>
                    </div>
                    <div class="desk-grid">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th class="desk-money">In Stock</th>
                                    <th class="desk-money">Allocated</th>
                                    <th class="desk-money">On Order</th>
                                    <th class="desk-money">Back Order</th>
                                    <th class="desk-money">Available</th>
                                    <th>Last Counted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sites as $site)
                                    <tr>
                                        <td class="desk-num">{{ $site->code }}</td>
                                        <td class="desk-money">{{ number_format((float) $quantity_in_stock, 2) }}</td>
                                        <td class="desk-money">{{ number_format((float) $allocated_qty, 2) }}</td>
                                        <td class="desk-money">{{ number_format((float) $on_order_qty, 2) }}</td>
                                        <td class="desk-money">{{ number_format((float) $back_order_qty, 2) }}</td>
                                        <td class="desk-money entity-value">{{ number_format($availableQty, 2) }}</td>
                                        <td>{{ $last_count_date ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty"><td colspan="7">No sites configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint">Quantities update from receiving, sales, and stock counts (read-only here).</p>
                </div>

            @elseif ($activeTab === 'pricing')
                <div class="item-price-summary">
                    <div class="item-price-stat">
                        <span>List Price</span>
                        <strong>${{ number_format((float) $list_price, 2) }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>MSRP</span>
                        <strong>${{ number_format((float) $msrp, 2) }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>Standard Cost</span>
                        <strong>${{ number_format((float) $standard_cost, 2) }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>Margin</span>
                        @php
                            $list = (float) $list_price;
                            $cost = (float) $standard_cost;
                            $margin = $list > 0 ? (($list - $cost) / $list) * 100 : 0;
                        @endphp
                        <strong>{{ number_format($margin, 1) }}%</strong>
                    </div>
                </div>

                <div class="inv-top-grid item-tab-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Sell prices</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="list_price">List Price</label>
                            <input id="list_price" wire:model.live="list_price" class="so-input text-right" style="max-width:8.5rem" placeholder="0" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="msrp">MSRP</label>
                            <input id="msrp" wire:model.live="msrp" class="so-input text-right" style="max-width:8.5rem" placeholder="0" />
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Costs</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="standard_cost">Standard Cost</label>
                            <input id="standard_cost" wire:model.live="standard_cost" class="so-input text-right" style="max-width:8.5rem" placeholder="0" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="current_cost">Current Cost</label>
                            <input id="current_cost" wire:model="current_cost" class="so-input text-right" style="max-width:8.5rem" placeholder="0" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="last_cost">Last Cost</label>
                            <input id="last_cost" wire:model="last_cost" class="so-input text-right so-input-ro" style="max-width:8.5rem" readonly placeholder="0" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="average_cost">Average Cost</label>
                            <input id="average_cost" wire:model="average_cost" class="so-input text-right so-input-ro" style="max-width:8.5rem" readonly placeholder="0" />
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Schedules</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="tax_schedule_id">Tax Schedule</label>
                            <div class="so-lookup-row">
                                <select id="tax_schedule_id" wire:model="tax_schedule_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($taxSchedules as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ rtrim(rtrim(number_format((float) $t->rate, 4, '.', ''), '0'), '.') }}%)</option>@endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'tax_schedules']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="promotion_schedule_id">Promotion</label>
                            <div class="so-lookup-row">
                                <select id="promotion_schedule_id" wire:model="promotion_schedule_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($promotionSchedules as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'discount_schedules']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pricing_method_id">Pricing Method</label>
                            <div class="so-lookup-row">
                                <select id="pricing_method_id" wire:model="pricing_method_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($pricingMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'pricing_methods']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Prices by UOM</h3>
                        <button type="button" wire:click="addPrice" class="desk-btn desk-btn-sm">Add Price</button>
                    </div>
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table item-price-table">
                            <colgroup>
                                <col class="col-uom" />
                                <col class="col-price" />
                                <col class="col-alias" />
                                <col style="width:8rem" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>U of M</th>
                                    <th class="text-center">Price</th>
                                    <th>Alias Code</th>
                                    <th>Price Level</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $i => $row)
                                    <tr>
                                        <td>
                                            <select wire:model.live="prices.{{ $i }}.uom" wire:key="price-uom-{{ $i }}-{{ $row['uom'] ?? '' }}" class="so-input item-cell-ctl">
                                                <option value="">—</option>
                                                @foreach ($uomOptions as $uom)
                                                    <option value="{{ $uom }}" @selected(strtoupper(trim((string) ($row['uom'] ?? ''))) === $uom)>{{ $uom }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center"><input wire:model="prices.{{ $i }}.price" class="so-input text-right item-cell-qty" placeholder="0" /></td>
                                        <td><input wire:model="prices.{{ $i }}.alias_code" class="so-input item-cell-ctl" /></td>
                                        <td>
                                            <select wire:model="prices.{{ $i }}.price_level_id" class="so-input item-cell-ctl">
                                                <option value="">List / Default</option>
                                                @foreach ($priceLevels as $pl)
                                                    <option value="{{ $pl->id }}">{{ $pl->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center"><button type="button" wire:click="removePrice({{ $i }})" class="desk-btn desk-btn-sm">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint">Default U of M comes from Inventory. Add rows per Price Level (e.g. Wholesale) so Sales Orders and Price Lists use the customer’s level.</p>
                </div>

            @elseif ($activeTab === 'extended')
                <div class="inv-top-grid item-tab-grid">
                    <div class="inv-card" style="grid-column: span 2">
                        <div class="inv-card-title">Descriptions</div>
                        <div class="item-stack-field">
                            <label class="item-stack-lbl" for="extended_description">Extended Description</label>
                            <textarea id="extended_description" wire:model="extended_description" rows="8" class="so-input so-input-area" placeholder="Full product description…"></textarea>
                        </div>
                        <div class="item-stack-field">
                            <label class="item-stack-lbl" for="product_highlights">Product Highlights</label>
                            <textarea id="product_highlights" wire:model="product_highlights" rows="6" class="so-input so-input-area" placeholder="One highlight per line"></textarea>
                        </div>
                    </div>
                    <div class="inv-card item-media-stack">
                        <div class="inv-card-title">Media</div>
                        @include('livewire.pages.inventory.items.partials.media', ['compact' => false])
                    </div>
                </div>

            @elseif ($activeTab === 'suppliers')
                <div class="entity-section" style="margin-top:0">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Item Suppliers</h3>
                        <button type="button" wire:click="addSupplierRow" class="desk-btn desk-btn-sm">Add Supplier</button>
                    </div>
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table item-sup-table">
                            <colgroup>
                                <col class="col-default" />
                                <col class="col-supplier" />
                                <col class="col-scode" />
                                <col class="col-lead" />
                                <col class="col-cost" />
                                <col class="col-cost" />
                                <col class="col-recv" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="text-center">Default</th>
                                    <th>Supplier</th>
                                    <th>Supplier Item Code</th>
                                    <th class="text-center">Lead Time</th>
                                    <th class="text-center">Last Cost</th>
                                    <th class="text-center">Avg Cost</th>
                                    <th>Last Received</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($suppliers as $i => $row)
                                    <tr>
                                        <td class="text-center">
                                            <input type="radio" name="default_supplier" wire:click="setDefaultSupplier({{ $i }})" @checked($row['is_default'] ?? false) aria-label="Default supplier {{ $i + 1 }}" />
                                        </td>
                                        <td>
                                            <select wire:model="suppliers.{{ $i }}.supplier_id" class="so-input item-cell-ctl">
                                                <option value="">—</option>
                                                @foreach ($supplierOptions as $sup)
                                                    <option value="{{ $sup->id }}">{{ $sup->supplier_id }} — {{ $sup->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input wire:model="suppliers.{{ $i }}.supplier_item_code" class="so-input font-mono item-cell-ctl" /></td>
                                        <td class="text-center"><input wire:model="suppliers.{{ $i }}.lead_time" class="so-input text-right item-cell-qty" placeholder="0" /></td>
                                        <td class="text-center"><input wire:model="suppliers.{{ $i }}.last_cost" class="so-input text-right item-cell-qty so-input-ro" readonly placeholder="0" /></td>
                                        <td class="text-center"><input wire:model="suppliers.{{ $i }}.avg_cost" class="so-input text-right item-cell-qty so-input-ro" readonly placeholder="0" /></td>
                                        <td><input type="date" wire:model="suppliers.{{ $i }}.last_received_at" class="so-input item-cell-ctl" /></td>
                                        <td class="text-center"><button type="button" wire:click="removeSupplierRow({{ $i }})" class="desk-btn desk-btn-sm">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint">Set one default supplier. Lead time and costs update from purchasing.</p>
                </div>

            @elseif ($activeTab === 'substitutes')
                <div class="entity-section" style="margin-top:0">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Substitutes</h3>
                        <button type="button" wire:click="addSubstitute" class="desk-btn desk-btn-sm">Add Substitute</button>
                    </div>
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table item-sub-table">
                            <colgroup>
                                <col class="col-code" />
                                <col class="col-desc" />
                                <col class="col-qty" />
                                <col class="col-force" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-center">Force Substitute</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($substitutes as $i => $row)
                                    @php $sub = $substituteOptions->firstWhere('id', $row['substitute_item_id'] ?? null); @endphp
                                    <tr>
                                        <td>
                                            <select wire:model.live="substitutes.{{ $i }}.substitute_item_id" class="so-input font-mono item-cell-ctl">
                                                <option value="">—</option>
                                                @foreach ($substituteOptions as $opt)
                                                    <option value="{{ $opt->id }}">{{ $opt->item_code }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="item-cell-desc" title="{{ $sub?->description }}">{{ $sub?->description ?: '—' }}</td>
                                        <td class="text-center">
                                            <input wire:model="substitutes.{{ $i }}.quantity" class="so-input text-right item-cell-qty" aria-label="Quantity {{ $i + 1 }}" />
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" wire:model="substitutes.{{ $i }}.force_substitute" class="item-cell-check" aria-label="Force substitute {{ $i + 1 }}" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" wire:click="removeSubstitute({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint">Suggested replacements when this item is out of stock.</p>
                </div>

            @elseif ($activeTab === 'batches')
                <div class="entity-section" style="margin-top:0">
                    <div class="entity-section-head" style="flex-wrap:wrap;gap:0.5rem">
                        <h3 class="entity-section-title">Item Batches</h3>
                        <div class="so-form-row so-form-row-side" style="margin:0;align-items:center">
                            <label class="so-form-lbl" for="batch_item_tracking" style="margin:0">Tracking</label>
                            <select id="batch_item_tracking" wire:model.live="item_tracking" class="so-input" style="max-width:9rem" @disabled($viewMode)>
                                @foreach ($trackingOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        @if (! $viewMode)
                            <button type="button" wire:click="addBatch" class="desk-btn desk-btn-sm desk-btn-primary">Add Batch</button>
                        @endif
                    </div>
                    @if (! in_array($item_tracking, ['Lot', 'Serial'], true))
                        <p class="item-hint" style="margin-bottom:0.75rem">
                            Choose <strong>Lot</strong> or <strong>Serial</strong> in Tracking above (or click <strong>Add Batch</strong> to start with Lot), then enter batch rows.
                        </p>
                    @endif
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table item-sub-table">
                            <colgroup>
                                <col style="width:18%" />
                                <col style="width:10%" />
                                <col style="width:14%" />
                                <col style="width:14%" />
                                <col />
                                <col style="width:7rem" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Batch #</th>
                                    <th class="text-center">Qty</th>
                                    <th>Expiry</th>
                                    <th>Received</th>
                                    <th>Notes</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($batches as $i => $row)
                                    <tr>
                                        <td>
                                            <input
                                                wire:model="batches.{{ $i }}.batch_number"
                                                class="so-input font-mono item-cell-ctl"
                                                placeholder="{{ $item_tracking === 'Serial' ? 'Serial #' : 'Lot #' }}"
                                                aria-label="Batch number {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td class="text-center">
                                            <input
                                                wire:model="batches.{{ $i }}.quantity"
                                                class="so-input text-right item-cell-qty"
                                                placeholder="0"
                                                aria-label="Batch quantity {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="date"
                                                wire:model="batches.{{ $i }}.expiry_date"
                                                class="so-input item-cell-ctl"
                                                aria-label="Expiry date {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="date"
                                                wire:model="batches.{{ $i }}.received_at"
                                                class="so-input item-cell-ctl"
                                                aria-label="Received date {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                wire:model="batches.{{ $i }}.notes"
                                                class="so-input item-cell-ctl"
                                                aria-label="Batch notes {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td class="text-center">
                                            @if (! $viewMode)
                                                <button type="button" wire:click="removeBatch({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint">Lot/serial batches for this item. Shown on Sales Order → Item Batch details.</p>
                </div>

            @else
                <div class="inv-top-grid item-tab-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Flags</div>
                        <div class="so-form-row so-form-row-side">
                            <span class="so-form-lbl">Status</span>
                            <div class="entity-status-btns">
                                <button type="button" wire:click="$set('is_inactive', false)" @class(['desk-btn desk-btn-sm', 'is-on' => ! $is_inactive])>Active</button>
                                <button type="button" wire:click="$set('is_inactive', true)" @class(['desk-btn desk-btn-sm', 'is-on-danger' => $is_inactive])>Inactive</button>
                            </div>
                        </div>
                        <div class="item-flag-list">
                            <label class="entity-check"><input type="checkbox" wire:model.live="available_on_website" /> Available on website</label>
                            <label class="entity-check"><input type="checkbox" wire:model="allow_back_order" /> Allow Back Order</label>
                            <label class="entity-check"><input type="checkbox" wire:model="can_sell" /> Can Sell</label>
                            <label class="entity-check"><input type="checkbox" wire:model="can_order" /> Can Order</label>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Tracking & weight</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="item_tracking">Item Tracking</label>
                            <select id="item_tracking" wire:model.live="item_tracking" class="so-input" style="max-width:10rem">
                                @foreach ($trackingOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="barcode_format">Barcode Format</label>
                            <select id="barcode_format" wire:model="barcode_format" class="so-input" style="max-width:10rem">
                                @foreach ($barcodeFormats as $fmt)<option value="{{ $fmt }}">{{ $fmt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="shipping_weight">Shipping Weight</label><input id="shipping_weight" wire:model="shipping_weight" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="tare_weight">Tare Weight</label><input id="tare_weight" wire:model="tare_weight" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="manufacturer">Manufacturer</label>
                            <input id="manufacturer" wire:model="manufacturer" class="so-input" />
                        </div>
                        <div class="item-flag-list" style="margin:0.35rem 0">
                            <label class="entity-check"><input type="checkbox" wire:model="msa_reporting" /> MSA Reporting</label>
                            <label class="entity-check"><input type="checkbox" wire:model="state_reporting" /> State Reporting</label>
                        </div>
                        <p class="item-hint" style="grid-column:1/-1;margin:0 0 0.35rem">
                            Unchecked items are excluded from that filing. Tobacco Type is only used for cigarettes vs OTP on reports that include this item.
                        </p>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="tobacco_product_type">Tobacco Type</label>
                            <select id="tobacco_product_type" wire:model.live="tobacco_product_type" class="so-input">
                                <option value="">— Not tobacco —</option>
                                <option value="cigarettes">Cigarettes</option>
                                <option value="otp">OTP</option>
                                <option value="pc1">Premium Cigar (PC1)</option>
                                <option value="ryo">RYO</option>
                            </select>
                        </div>
                        <p class="item-hint" style="grid-column:1/-1;margin:0 0 0.35rem">
                            Optional. Used to split cigarettes vs OTP when this item is included on a report.
                        </p>
                        @if ($tobacco_product_type !== '')
                            <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="tobacco_brand_code">Brand Code</label><input id="tobacco_brand_code" wire:model="tobacco_brand_code" class="so-input" placeholder="CIG / OTP / PC1 / 034…" /></div>
                            @if ($tobacco_product_type === 'cigarettes')
                                <div class="so-form-row so-form-row-side">
                                    <label class="so-form-lbl" for="cigarette_pack_size">Cig Pack Size</label>
                                    <input
                                        id="cigarette_pack_size"
                                        type="number"
                                        min="1"
                                        max="99"
                                        step="1"
                                        wire:model="cigarette_pack_size"
                                        class="so-input text-right"
                                        style="max-width:8rem"
                                        placeholder="20 / 25 / 35…"
                                    />
                                </div>
                                <p class="item-hint" style="grid-column:1/-1;margin:0.15rem 0 0.35rem">
                                    Enter any pack size used in your warehouse (e.g. 20, 25, 35).
                                    Michigan MSA XML only accepts <strong>20</strong> or <strong>25</strong> — other sizes are stored here but filed as 20 if not 20/25.
                                </p>
                            @endif
                            @if ($tobacco_product_type === 'ryo' || $tobacco_product_type === 'otp')
                                <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="tobacco_total_oz">Total Oz (RYO)</label><input id="tobacco_total_oz" wire:model="tobacco_total_oz" class="so-input text-right" style="max-width:8rem" /></div>
                            @endif
                            @if ($tobacco_product_type === 'pc1' || $tobacco_product_type === 'otp')
                                <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="tobacco_stick_count">Stick Count (PC1)</label><input id="tobacco_stick_count" wire:model="tobacco_stick_count" class="so-input text-right" style="max-width:8rem" /></div>
                            @endif
                        @endif
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Notes</div>
                        <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="item_line_message">Line Message</label><input id="item_line_message" wire:model="item_line_message" class="so-input" /></div>
                        <div class="so-form-row so-form-row-side so-form-row-top"><label class="so-form-lbl" for="comments">Comments</label><textarea id="comments" wire:model="comments" rows="4" class="so-input so-input-area"></textarea></div>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Manufacturer Promotion</h3>
                    </div>
                    <div class="entity-body-pad">
                        <div class="inv-top-grid" style="margin:0">
                            <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="manu_product_id">Product ID</label><input id="manu_product_id" wire:model="manu_product_id" class="so-input" /></div>
                            <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="manu_promotion_item">Promo Item</label><input id="manu_promotion_item" wire:model="manu_promotion_item" class="so-input" /></div>
                            <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="manu_promotion_code">Promo Code</label><input id="manu_promotion_code" wire:model="manu_promotion_code" class="so-input" /></div>
                            <div class="so-form-row so-form-row-side" style="grid-column:span 2"><label class="so-form-lbl" for="manu_promotion_description">Promo Desc</label><input id="manu_promotion_description" wire:model="manu_promotion_description" class="so-input" /></div>
                            <div class="so-form-row so-form-row-side"><label class="so-form-lbl" for="manu_base_count">Base Count</label><input id="manu_base_count" wire:model="manu_base_count" class="so-input text-right" style="max-width:8rem" placeholder="0" /></div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </fieldset>

        <div class="entity-footer">
            <div class="entity-tabs" role="tablist" aria-label="Item sections">
                @foreach ($tabs as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:click="$set('activeTab', '{{ $key }}')"
                        aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                        @class(['entity-tab', 'is-active' => $activeTab === $key])
                    >{{ $label }}</button>
                @endforeach
            </div>
            <div class="entity-footer-actions">
                <a href="{{ route('inventory.items.index') }}" wire:navigate class="desk-btn">{{ $viewMode ? 'Close' : 'Cancel' }}</a>
                @if ($viewMode && $item)
                    <a href="{{ route('inventory.items.edit', $item) }}" wire:navigate class="desk-btn desk-btn-primary">Edit Item</a>
                @elseif (! $viewMode)
                    <button
                        type="submit"
                        class="desk-btn desk-btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        <span wire:loading.remove wire:target="save">Save Changes</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                @endif
            </div>
        </div>
    </form>

    @if ($showJournal && $item)
        <div class="desk-modal-backdrop" wire:click.self="closeJournal" role="dialog" aria-modal="true" aria-label="Inventory journal">
            <div class="desk-modal" style="max-width:56rem">
                <div class="desk-modal-head">
                    <span>Inventory Journal — {{ $item->item_code }}</span>
                    <button type="button" wire:click="closeJournal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body">
                    <div class="desk-grid">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Site</th>
                                    <th>Source</th>
                                    <th>Reference</th>
                                    <th class="desk-money">Qty Change</th>
                                    <th class="desk-money">Qty After</th>
                                    <th class="desk-money">Unit Cost</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($journalEntries as $entry)
                                    <tr>
                                        <td>{{ optional($entry->created_at)?->format('n/j/Y g:ia') }}</td>
                                        <td class="desk-num">{{ $entry->site?->code ?: '—' }}</td>
                                        <td>{{ $entry->source_type }}</td>
                                        <td class="desk-num">{{ $entry->reference }}</td>
                                        <td class="desk-money">{{ number_format($entry->qty_change, 2) }}</td>
                                        <td class="desk-money">{{ number_format($entry->qty_after, 2) }}</td>
                                        <td class="desk-money">${{ number_format((float) $entry->unit_cost, 4) }}</td>
                                        <td>{{ $entry->notes }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty"><td colspan="8">No journal entries for this item.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
