<?php

use App\Livewire\Concerns\SortsItemBrowse;
use App\Models\Category;
use App\Models\Item;
use App\Models\PaymentTerm;
use App\Models\PurchaseOrder;
use App\Models\InventoryReceiving;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\ExcelCsv;
use App\Support\ItemSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Purchase Order')] class extends Component
{
    use SortsItemBrowse;
    public ?PurchaseOrder $purchaseOrder = null;

    /** View-only (same layout as edit, locked). */
    public bool $viewMode = false;

    public string $activeTab = 'general';

    public string $po_number = '';

    public string $order_type = 'Standard';

    public string $reference_no = '';

    public string $requisition_date = '';

    public string $status = 'New';

    public ?int $buyer_id = null;

    public string $required_date = '';

    public ?int $ship_to_site_id = null;

    public ?int $supplier_id = null;

    public string $ship_from = '';

    public ?int $payment_term_id = null;

    public ?int $ship_via_id = null;

    public string $comments = '';

    public string $freight = '';

    public string $trade_discount = '';

    public string $miscellaneous = '';

    public string $tax = '';

    public string $itemLookup = '';

    public bool $showItemBrowse = false;

    public bool $showBrowse = false;

    public bool $browseNewOnly = false;

    public string $browseSearch = '';

    public ?int $browseCategoryId = null;

    public ?int $browseSubcategoryId = null;

    public bool $browseSavedSearchOpen = false;

    public bool $browseQtyLtZero = false;

    /** @var array<int, array{id:int,item_code:string,description:?string,unit_of_measure:?string,list_price:float|string|null,on_hand:float,available:float,is_new:bool}> */
    public array $browseRows = [];

    public int $browseTotal = 0;

    public bool $browseHasMore = false;

    public bool $browseLoadingMore = false;

    public ?int $browseSelectedId = null;

    public array $browseCheckedIds = [];

    public int $browseChecksVersion = 0;

    public string $lineWarning = '';

    public string $lineWarningKind = 'error';

    public ?int $browseLineIndex = null;

    public string $itemBrowseSearch = '';

    public string $lookupMessage = '';

    /** Entry bar armed for Scan / barcode gun. */
    public bool $scanModeActive = false;

    public string $lastScanClaimCode = '';

    public int $lastScanClaimAt = 0;

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,qty_received:string,unit_cost:string}> */
    public array $lines = [];

    public ?int $selectedLineIndex = null;

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $companyId = auth()->user()->company_id;

        if ($purchaseOrder?->exists) {
            abort_unless($purchaseOrder->company_id === $companyId, 403);

            $this->viewMode = request()->routeIs('purchasing.orders.show');
            $this->purchaseOrder = $purchaseOrder->load('lines');
            $this->po_number = (string) ($purchaseOrder->po_number ?? '');
            $this->order_type = (string) ($purchaseOrder->order_type ?: 'Standard');
            $this->reference_no = (string) ($purchaseOrder->reference_no ?? '');
            $this->status = (string) ($purchaseOrder->status ?: 'New');
            $this->buyer_id = $purchaseOrder->buyer_id ? (int) $purchaseOrder->buyer_id : null;
            $this->ship_to_site_id = $purchaseOrder->ship_to_site_id ? (int) $purchaseOrder->ship_to_site_id : null;
            $this->supplier_id = $purchaseOrder->supplier_id ? (int) $purchaseOrder->supplier_id : null;
            $this->ship_from = (string) ($purchaseOrder->ship_from ?? '');
            $this->payment_term_id = $purchaseOrder->payment_term_id ? (int) $purchaseOrder->payment_term_id : null;
            $this->ship_via_id = $purchaseOrder->ship_via_id ? (int) $purchaseOrder->ship_via_id : null;
            $this->comments = (string) ($purchaseOrder->comments ?? '');
            $this->freight = $this->blankZeroAmount($purchaseOrder->freight);
            $this->trade_discount = $this->blankZeroAmount($purchaseOrder->trade_discount);
            $this->miscellaneous = $this->blankZeroAmount($purchaseOrder->miscellaneous);
            $this->tax = $this->blankZeroAmount($purchaseOrder->tax);
            $this->requisition_date = optional($purchaseOrder->requisition_date)?->format('Y-m-d') ?? '';
            $this->required_date = optional($purchaseOrder->required_date)?->format('Y-m-d') ?? '';
            $this->lines = $purchaseOrder->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'qty_ordered' => $this->formatQty($l->qty_ordered),
                'qty_received' => $this->formatQty($l->qty_received),
                'unit_cost' => $this->formatTwoDecimals($l->unit_cost),
                'list_price' => $this->formatTwoDecimals($l->unit_cost),
            ])->all();
        } else {
            $this->viewMode = false;
            $this->po_number = PurchaseOrder::nextNumber($companyId);
            $this->requisition_date = now()->toDateString();
            $this->buyer_id = auth()->id();
            $this->ship_to_site_id = auth()->user()->site_id;
        }

        if ($this->lines === []) {
            $this->lines[] = $this->emptyLine();
        }
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null,
            'item_code' => '',
            'description' => '',
            'uom' => '',
            'qty_ordered' => '',
            'qty_received' => '',
            'unit_cost' => '',
            'list_price' => '',
        ];
    }

    /** Empty string when value is null/blank/zero so inputs show placeholder 0. */
    protected function blankZeroAmount(mixed $value): string
    {
        $formatted = $this->formatTwoDecimals($value);
        if ($formatted === '' || $formatted === '0.00') {
            return '';
        }

        return $formatted;
    }

    /** Always 2 decimal places (1.00, 25.61) — never 1.0000. */
    protected function formatTwoDecimals(mixed $value): string
    {
        if (is_string($value)) {
            $trim = trim(str_replace(',', '', $value));
            if ($trim === '') {
                return '';
            }
            if (preg_match('/^-?\d+\.$/', $trim)) {
                return $trim;
            }
        }
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        return number_format(round((float) $value, 2), 2, '.', '');
    }

    public function exportLinesToExcel(): mixed
    {
        $rows = collect($this->lines)->filter(fn ($line) => filled($line['item_code'] ?? null));
        if ($rows->isEmpty()) {
            $this->lookupMessage = 'Add at least one item before exporting.';

            return null;
        }

        $export = $rows->map(function ($line) {
            $qty = (float) ($line['qty_ordered'] ?? 0);
            $cost = (float) ($line['unit_cost'] ?? 0);

            return [
                $line['item_code'] ?? '',
                $line['description'] ?? '',
                $line['uom'] ?? '',
                $qty,
                $cost,
                round($qty * $cost, 2),
            ];
        })->all();

        $filename = 'po-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $this->po_number).'-lines.csv';

        return ExcelCsv::download($filename, ['Item Code', 'Description', 'UOM', 'Qty Ordered', 'Cost', 'Extended Cost'], $export);
    }

    public function exportBrowseToExcel(): mixed
    {
        $rows = collect($this->browseRows);
        if ($rows->isEmpty()) {
            $this->lookupMessage = 'No browse items to export.';

            return null;
        }

        $export = $rows->map(fn ($row) => [
            $row['item_code'] ?? '',
            $row['description'] ?? '',
            $row['unit_of_measure'] ?? '',
            $row['list_price'] ?? '',
            $row['available'] ?? '',
            $row['on_hand'] ?? '',
        ])->all();

        return ExcelCsv::download('browse-items.csv', ['Item Code', 'Description', 'UOM', 'Price', 'Available', 'On Hand'], $export);
    }

    public function updatedLines($value, string $key): void
    {
        if (! preg_match('/^(\d+)\.(qty_ordered|qty_received|unit_cost)$/', $key, $m)) {
            return;
        }
        $i = (int) $m[1];
        if (! isset($this->lines[$i])) {
            return;
        }
        $raw = trim((string) ($this->lines[$i][$m[2]] ?? ''));
        if ($raw === '' || str_ends_with($raw, '.')) {
            return;
        }
        $this->lines[$i][$m[2]] = $m[2] === 'unit_cost'
            ? $this->formatTwoDecimals($raw)
            : $this->formatQty($raw);
    }

    /** Persist empty amount fields as 0. */
    protected function amountOrZero(mixed $value): float
    {
        return ($value === null || $value === '') ? 0.0 : (float) $value;
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $subtotal = collect($this->lines)->sum(fn ($l) => (float) ($l['qty_ordered'] ?? 0) * (float) ($l['unit_cost'] ?? 0));
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        // Stable signature so the lines table fully re-renders when items change.
        $linesSig = md5(json_encode(array_map(static fn ($l) => [
            (int) ($l['item_id'] ?? 0),
            (string) ($l['item_code'] ?? ''),
        ], $this->lines)));

        return [
            'suppliers' => Supplier::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('name')->get(),
            'buyers' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'shipVias' => ShipVia::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'selectedSupplier' => $this->supplier_id
                ? Supplier::query()->find($this->supplier_id)
                : null,
            'subtotal' => $subtotal,
            'orderTotal' => $total,
            'totalItemsOrdered' => collect($this->lines)->sum(fn ($l) => (float) ($l['qty_ordered'] ?? 0)),
            'totalItemsReceived' => collect($this->lines)->sum(fn ($l) => (float) ($l['qty_received'] ?? 0)),
            'linesSig' => $linesSig,
            'filledLineCount' => collect($this->lines)->filter(
                fn ($l) => filled($l['item_code'] ?? null) || (int) ($l['item_id'] ?? 0) > 0
            )->count(),
            'tabs' => [
                'general' => 'General',
                'items' => 'Items',
            ],
            'browseItems' => collect($this->browseRows),
            'browseCategories' => $this->showBrowse
                ? Category::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name'])
                : collect(),
            'browseSubcategories' => ($this->showBrowse && $this->browseCategoryId)
                ? Subcategory::query()
                    ->where('company_id', $companyId)
                    ->where('category_id', $this->browseCategoryId)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'category_id'])
                : collect(),
            'itemNewDays' => defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30,
            'oversellingOn' => true,
        ];
    }

    public function openItemBrowse(?int $lineIndex = null): void
    {
        abort_if($this->viewMode, 403);

        $this->browseLineIndex = $lineIndex;
        $code = '';
        if ($lineIndex !== null && isset($this->lines[$lineIndex])) {
            $code = trim((string) ($this->lines[$lineIndex]['item_code'] ?? ''));
        }
        $this->browseSearch = $code !== '' ? $code : ($this->itemLookup !== '' ? trim($this->itemLookup) : '');
        $this->itemBrowseSearch = $this->browseSearch;
        $this->lookupMessage = '';
        $this->lineWarning = '';
        $this->showBrowse = true;
        $this->showItemBrowse = true;
        $this->activeTab = 'items';
        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch();
    }

    public function closeItemBrowse(): void
    {
        $this->closeBrowse();
    }

    public function closeBrowse(): void
    {
        $this->showBrowse = false;
        $this->showItemBrowse = false;
        $this->browseLineIndex = null;
        $this->itemBrowseSearch = '';
        $this->clearBrowseState();
    }

    public function browseEscape(): void
    {
        if ($this->browseSavedSearchOpen) {
            $this->browseSavedSearchOpen = false;

            return;
        }

        $this->closeBrowse();
    }

    public function pickBrowseItem(int $itemId, bool $keepBrowseOpen = false): void
    {
        abort_if($this->viewMode, 403);

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('is_inactive', false)
            ->where('can_order', true)
            ->find($itemId);

        if (! $item) {
            return;
        }

        $this->browseSelectedId = $itemId;
        $this->lineWarning = '';
        $this->lookupMessage = '';
        if ($keepBrowseOpen) {
            $this->applyItemToOrder($item);
            $this->browseCheckedIds = [];
            $this->browseSelectedId = null;
            $this->dispatch('browse-checks-cleared');
            $this->resetBrowseAndLoadFirstPage();
            $this->focusBrowseSearch();
        } else {
            $this->closeBrowse();
            $this->applyItemToOrder($item);
            $this->clearAndFocusEntry();
        }
    }

    protected const BROWSE_PAGE_SIZE = 80;

    public function updatedBrowseSearch(): void
    {
        if (! $this->showBrowse) {
            return;
        }
        $this->itemBrowseSearch = $this->browseSearch;
        $this->resetBrowseAndLoadFirstPage();
    }

    public function updatedBrowseNewOnly(): void
    {
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function setBrowseCategory(?int $categoryId = null): void
    {
        $this->browseCategoryId = $categoryId;
        $this->browseSubcategoryId = null;
        if ($categoryId === null) {
            $this->browseQtyLtZero = false;
        }
        $this->browseSavedSearchOpen = true;
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function setBrowseQtyLtZero(bool $on = true): void
    {
        $this->browseQtyLtZero = $on;
        $this->browseSavedSearchOpen = true;
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function setBrowseSubcategory(?int $subcategoryId = null): void
    {
        $this->browseSubcategoryId = $subcategoryId;
        $this->browseSavedSearchOpen = true;
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function clearBrowseFilters(): void
    {
        $this->browseSearch = '';
        $this->itemBrowseSearch = '';
        $this->browseNewOnly = false;
        $this->browseCategoryId = null;
        $this->browseSubcategoryId = null;
        $this->browseQtyLtZero = false;
        $this->browseSelectedId = null;
        $this->browseCheckedIds = [];
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function toggleBrowseSavedSearch(): void
    {
        $this->browseSavedSearchOpen = ! $this->browseSavedSearchOpen;
    }

    public function closeBrowseSavedSearch(): void
    {
        $this->browseSavedSearchOpen = false;
    }

    public function selectBrowseRow(int $itemId): void
    {
        $this->toggleBrowseChecked($itemId);
    }

    public function toggleBrowseChecked(int $itemId): void
    {
        $id = (int) $itemId;
        if ($id <= 0) {
            return;
        }

        $ids = collect($this->browseCheckedIds)
            ->map(fn ($v) => (string) (int) $v)
            ->filter(fn (string $v) => $v !== '' && $v !== '0')
            ->unique()
            ->values()
            ->all();

        $key = (string) $id;
        if (in_array($key, $ids, true)) {
            $this->browseCheckedIds = array_values(array_filter($ids, fn (string $v) => $v !== $key));
        } else {
            $ids[] = $key;
            $this->browseCheckedIds = $ids;
        }

        $count = count($this->browseCheckedIds);
        $this->browseSelectedId = $count === 1 ? (int) $this->browseCheckedIds[0] : null;
    }

    public function updatedBrowseCheckedIds(): void
    {
        $this->browseCheckedIds = collect($this->browseCheckedIds)
            ->map(fn ($v) => (string) (int) $v)
            ->filter(fn (string $v) => $v !== '' && $v !== '0')
            ->unique()
            ->values()
            ->all();

        $count = count($this->browseCheckedIds);
        $this->browseSelectedId = $count === 1 ? (int) $this->browseCheckedIds[0] : null;
    }

    public function selectAllBrowseVisible(): void
    {
        $ids = collect($this->browseRows)
            ->pluck('id')
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $id !== '' && $id !== '0')
            ->unique()
            ->values()
            ->all();

        $this->browseCheckedIds = $ids;
        $this->browseSelectedId = count($ids) === 1 ? (int) $ids[0] : null;
    }

    public function insertBrowseSelected(): void
    {
        $id = $this->resolveBrowseTargetId();
        if ($id === null) {
            $this->lineWarning = count($this->browseCheckedIds) > 1
                ? 'Multiple items checked — use Insert All Checked, or uncheck to a single item.'
                : 'Select one item first.';
            $this->lineWarningKind = 'warning';

            return;
        }
        $this->pickBrowseItem($id, true);
    }

    public function insertBrowseChecked($ids = null): void
    {
        if (is_array($ids) && $ids !== []) {
            $this->browseCheckedIds = array_values(array_unique(array_map('intval', $ids)));
        }

        $ids = array_values(array_unique(array_map('intval', $this->browseCheckedIds)));
        if ($ids === []) {
            $this->insertBrowseSelected();

            return;
        }

        foreach ($ids as $itemId) {
            $item = Item::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('is_inactive', false)
                ->where('can_order', true)
                ->find($itemId);
            if ($item) {
                $this->applyItemToOrder($item);
            }
        }

        $this->browseCheckedIds = [];
        $this->browseSelectedId = null;
        $this->browseChecksVersion++;
        $this->dispatch('browse-checks-cleared');
        $this->js('window.dispatchEvent(new CustomEvent("browse-checks-cleared"))');
        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch();
    }

    public function openBrowseNewItem(): void
    {
        if (! Route::has('inventory.items.create')) {
            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.create'));
    }

    public function openBrowseEditSelected(): void
    {
        $id = $this->resolveBrowseTargetId();
        if ($id === null || ! Route::has('inventory.items.edit')) {
            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($id);
        if (! $item) {
            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.edit', $item));
    }

    public function openLineItemRecord(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        $itemId = (int) ($this->lines[$index]['item_id'] ?? 0);
        if ($itemId <= 0 || ! Route::has('inventory.items.edit')) {
            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($itemId);
        if (! $item) {
            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.edit', $item));
    }

    protected function browseHasSingleSelection(): bool
    {
        $checked = array_values(array_unique(array_map('intval', $this->browseCheckedIds)));
        if (count($checked) > 1) {
            return false;
        }
        if (count($checked) === 1) {
            return true;
        }

        return (int) ($this->browseSelectedId ?? 0) > 0;
    }

    protected function resolveBrowseTargetId(): ?int
    {
        if (! $this->browseHasSingleSelection()) {
            return null;
        }
        $checked = array_values(array_unique(array_map('intval', $this->browseCheckedIds)));
        if (count($checked) === 1) {
            return $checked[0];
        }
        $id = (int) ($this->browseSelectedId ?? 0);

        return $id > 0 ? $id : null;
    }

    public function refreshBrowseItems(): void
    {
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function loadMoreBrowseItems(): void
    {
        if (! $this->showBrowse || ! $this->browseHasMore || $this->browseLoadingMore) {
            return;
        }
        $this->browseLoadingMore = true;
        $this->appendBrowsePage(count($this->browseRows));
        $this->browseLoadingMore = false;
    }

    protected function clearBrowseState(): void
    {
        $this->browseRows = [];
        $this->browseTotal = 0;
        $this->browseHasMore = false;
        $this->browseLoadingMore = false;
        $this->browseCategoryId = null;
        $this->browseSubcategoryId = null;
        $this->browseQtyLtZero = false;
        $this->browseSavedSearchOpen = false;
        $this->browseSelectedId = null;
        $this->browseCheckedIds = [];
    }

    protected function resetBrowseAndLoadFirstPage(): void
    {
        $this->browseRows = [];
        $this->browseHasMore = false;
        $this->browseLoadingMore = false;
        $companyId = (int) auth()->user()->company_id;
        $this->browseTotal = $this->browseBaseQuery($companyId)->count();
        $this->appendBrowsePage(0);
    }

    protected function appendBrowsePage(int $offset): void
    {
        $companyId = (int) auth()->user()->company_id;
        $newDays = defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30;
        $newSince = now()->subDays($newDays);

        $rows = $this->applyBrowseOrder($this->browseBaseQuery($companyId))
            ->offset($offset)
            ->limit(self::BROWSE_PAGE_SIZE)
            ->get([
                'id',
                'item_code',
                'description',
                'unit_of_measure',
                'list_price',
                'current_cost',
                'standard_cost',
                'quantity_in_stock',
                'allocated_qty',
                'created_at',
            ]);

        $mapped = $rows->map(function ($row) use ($newSince) {
            $created = $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at) : null;
            $cost = $row->current_cost ?: $row->standard_cost ?: $row->list_price;

            return [
                'id' => (int) $row->id,
                'item_code' => (string) $row->item_code,
                'description' => $row->description,
                'unit_of_measure' => $row->unit_of_measure,
                'list_price' => $cost,
                'on_hand' => (float) $row->quantity_in_stock,
                'available' => (float) $row->quantity_in_stock - (float) $row->allocated_qty,
                'is_new' => $created !== null && $created->gte($newSince),
            ];
        })->all();

        $this->browseRows = array_values(array_merge($this->browseRows, $mapped));
        $this->browseHasMore = count($this->browseRows) < $this->browseTotal;
    }

    protected function browseBaseQuery(int $companyId)
    {
        $newDays = defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30;
        $newSince = now()->subDays($newDays);

        return DB::table('items')
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_order', true)
            ->when($this->browseNewOnly, fn ($q) => $q->where('created_at', '>=', $newSince))
            ->when($this->browseQtyLtZero, fn ($q) => $q->where('quantity_in_stock', '<', 0))
            ->when($this->browseCategoryId, fn ($q) => $q->where('category_id', $this->browseCategoryId))
            ->when($this->browseSubcategoryId, fn ($q) => $q->where('subcategory_id', $this->browseSubcategoryId))
            ->when(filled($this->browseSearch), fn ($q) => ItemSearch::constrain($q, $this->browseSearch));
    }

    protected function focusBrowseSearch(bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-browse-search"); if (el) { el.focus();'.$selectJs.' } });');
    }

    public function addLine(): void
    {
        abort_if($this->viewMode, 403);
        $this->lines[] = $this->emptyLine();
    }

    public function removeSelectedLine(): void
    {
        if ($this->selectedLineIndex === null) {
            return;
        }
        $this->removeLine($this->selectedLineIndex);
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->selectedLineIndex === $index) {
            $this->selectedLineIndex = null;
        } elseif ($this->selectedLineIndex !== null && $this->selectedLineIndex > $index) {
            $this->selectedLineIndex--;
        }
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function adjustLineQty(int $index, int $delta): void
    {
        if ($this->viewMode || ! isset($this->lines[$index])) {
            return;
        }
        if (! filled($this->lines[$index]['item_code'] ?? null) && (int) ($this->lines[$index]['item_id'] ?? 0) <= 0) {
            return;
        }

        $next = max(0, round((float) ($this->lines[$index]['qty_ordered'] ?? 0) + $delta, 2));
        $this->lines[$index]['qty_ordered'] = $this->formatQty($next);
        $this->selectedLineIndex = $index;
    }

    protected function formatQty(mixed $value): string
    {
        if (is_string($value)) {
            $trim = trim(str_replace(',', '', $value));
            if ($trim === '') {
                return '';
            }
            if (preg_match('/^-?\d+\.$/', $trim)) {
                return $trim;
            }
        }
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        $n = round((float) $value, 4);
        if (abs($n) < 0.0000001) {
            return '';
        }

        $formatted = number_format($n, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Resolve orderable item by item code, Primary UPC / aliases, price alias, supplier code.
     */
    protected function findPurchaseItem(string $code): ?Item
    {
        return Item::findByScanCode((int) auth()->user()->company_id, $code, 'order');
    }

    /**
     * Block Enter + auto-add from counting as two scans of the same code.
     */
    protected function claimScanAdd(string $code): bool
    {
        $norm = mb_strtolower(trim($code));
        if ($norm === '') {
            return false;
        }

        $now = (int) floor(microtime(true) * 1000);
        if ($this->lastScanClaimCode === $norm && ($now - $this->lastScanClaimAt) < 400) {
            return false;
        }

        $this->lastScanClaimCode = $norm;
        $this->lastScanClaimAt = $now;

        return true;
    }

    /**
     * After entry typing pause / gun scan: full exact match only → add line.
     */
    public function autoAddEntryIfExactMatch(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        if ($code === '' || mb_strlen($code) < 2) {
            return;
        }

        $item = $this->findPurchaseItem($code);
        if (! $item) {
            return;
        }

        if (! $this->claimScanAdd($code)) {
            return;
        }

        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->browseLineIndex = null;
        $this->applyItemToOrder($item);
        $this->scanModeActive = true;
        $this->clearAndFocusEntry();
    }

    /**
     * ✓ / Enter: add if match, else open item list filtered by code.
     */
    public function addItemFromEntry(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        $this->itemLookup = $code;

        if ($code === '') {
            $this->clearAndFocusEntry();

            return;
        }

        if (! $this->claimScanAdd($code)) {
            $this->itemLookup = '';
            $this->clearAndFocusEntry();

            return;
        }

        $item = $this->findPurchaseItem($code);
        if ($item) {
            $this->itemLookup = '';
            $this->lookupMessage = '';
            $this->browseLineIndex = null;
            $this->applyItemToOrder($item);
            $this->scanModeActive = true;
            $this->clearAndFocusEntry();

            return;
        }

        $this->playPosSound('error');
        $this->lookupMessage = 'Item '.$code.' was not found.';
        $this->browseSearch = $code;
        $this->itemBrowseSearch = $code;
        $this->openItemBrowse(null);
    }

    /**
     * True when a longer orderable item code/UPC starts with $code (user still typing).
     */
    protected function codeIsPrefixOfLongerOrderableCode(string $code): bool
    {
        $companyId = (int) auth()->user()->company_id;
        $lower = mb_strtolower(trim($code));
        $len = mb_strlen($lower);
        if ($len < 1) {
            return false;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower).'%';

        return Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_order', true)
            ->where(function ($q) use ($len, $like) {
                $q->where(function ($inner) use ($len, $like) {
                    $inner->whereRaw('CHAR_LENGTH(item_code) > ?', [$len])
                        ->whereRaw('LOWER(item_code) LIKE ?', [$like]);
                })
                    ->orWhere(function ($inner) use ($len, $like) {
                        $inner->whereRaw('CHAR_LENGTH(COALESCE(primary_upc, ?)) > ?', ['', $len])
                            ->whereRaw('LOWER(COALESCE(primary_upc, ?)) LIKE ?', ['', $like]);
                    })
                    ->orWhereHas('upcs', function ($upc) use ($len, $like) {
                        $upc->whereRaw('CHAR_LENGTH(upc) > ?', [$len])
                            ->whereRaw('LOWER(upc) LIKE ?', [$like]);
                    })
                    ->orWhereHas('prices', function ($p) use ($len, $like) {
                        $p->whereRaw('CHAR_LENGTH(COALESCE(alias_code, ?)) > ?', ['', $len])
                            ->whereRaw('LOWER(COALESCE(alias_code, ?)) LIKE ?', ['', $like]);
                    })
                    ->orWhereHas('itemSuppliers', function ($s) use ($len, $like) {
                        $s->whereRaw('CHAR_LENGTH(COALESCE(supplier_item_code, ?)) > ?', ['', $len])
                            ->whereRaw('LOWER(COALESCE(supplier_item_code, ?)) LIKE ?', ['', $like]);
                    });
            })
            ->exists();
    }

    /**
     * Scan button: arm field. If a code is already in the box, add it immediately.
     */
    public function focusScanAndAdd(): void
    {
        abort_if($this->viewMode, 403);

        $this->scanModeActive = true;
        $this->lookupMessage = '';

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('po-item-entry');
                if (!el) return;
                el.focus();
                const v = (el.value || '').trim();
                if (v.length >= 2) {
                    $wire.addItemFromEntry(v);
                } else {
                    el.select();
                }
            });
        JS);
    }

    public function clearItemLookup(): void
    {
        $this->itemLookup = '';
        $this->scanModeActive = false;
        if (str_contains(strtolower($this->lookupMessage), 'was not found')) {
            $this->lookupMessage = '';
        }
        $this->clearAndFocusEntry();
    }

    protected function clearAndFocusEntry(): void
    {
        $this->itemLookup = '';
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('po-item-entry');
                if (!el) return;
                el.value = '';
                el.focus();
            });
        JS);
    }

    /**
     * Bump qty on existing line, else fill empty line / new line.
     */
    protected function applyItemToOrder(Item $item): void
    {
        $lines = array_values($this->lines);

        foreach ($lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === (int) $item->id) {
                $qty = (float) ($line['qty_ordered'] ?? 0);
                $lines[$i]['qty_ordered'] = $this->formatQty($qty + 1);
                // Keep code/desc in sync (fixes display if earlier fill was partial).
                $lines[$i]['item_code'] = (string) $item->item_code;
                $lines[$i]['description'] = (string) ($item->description ?? $lines[$i]['description'] ?? '');
                if (! filled($lines[$i]['uom'] ?? null)) {
                    $lines[$i]['uom'] = filled($item->unit_of_measure)
                        ? (string) $item->unit_of_measure
                        : 'EA';
                }
                $this->lines = $lines;
                $this->highlightPoLine($i);
                $this->playPosSound('success');

                return;
            }
        }

        $target = null;
        if ($this->browseLineIndex !== null && isset($lines[$this->browseLineIndex])) {
            $target = (int) $this->browseLineIndex;
            $this->browseLineIndex = null;
            $this->lines = $lines;
            $this->fillLineFromItem($target, $item);
            $this->highlightPoLine($target);
            $this->playPosSound('success');

            return;
        }

        $filled = array_values(array_filter(
            $lines,
            fn ($l) => filled($l['item_code'] ?? null) || (int) ($l['item_id'] ?? 0) > 0
        ));
        $filled[] = $this->emptyLine();
        $target = count($filled) - 1;
        $this->lines = $filled;
        $this->fillLineFromItem($target, $item);
        $this->lines[] = $this->emptyLine();
        $this->highlightPoLine($target);
        $this->playPosSound('success');
    }

    protected function highlightPoLine(int $index): void
    {
        $this->selectedLineIndex = $index;
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("po-line-row-'.$index.'"); if (el) el.scrollIntoView({ block: "nearest" }); });');
    }

    protected function playPosSound(string $kind = 'error'): void
    {
        $this->dispatch('pos-alert', kind: $kind);
        $this->js('window.playPosAlert && window.playPosAlert('.json_encode($kind).')');
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $supplierCost = $item->itemSuppliers()
            ->when($this->supplier_id, fn ($q) => $q->where('supplier_id', $this->supplier_id))
            ->orderByDesc('is_default')
            ->first();

        $cost = $supplierCost?->last_cost ?: $item->current_cost ?: $item->standard_cost;

        $lines = array_values($this->lines);
        if (! isset($lines[$index])) {
            $lines[$index] = $this->emptyLine();
        }

        $lines[$index]['item_id'] = (int) $item->id;
        $lines[$index]['item_code'] = (string) $item->item_code;
        $lines[$index]['description'] = (string) ($item->description ?? '');
        $lines[$index]['uom'] = filled($item->unit_of_measure)
            ? (string) $item->unit_of_measure
            : 'EA';
        $lines[$index]['unit_cost'] = $this->formatTwoDecimals($cost !== null && $cost !== '' ? $cost : 0);
        $lines[$index]['list_price'] = $this->formatTwoDecimals($item->list_price ?? $cost ?? 0);
        if (! filled($lines[$index]['qty_ordered'] ?? null) || (float) $lines[$index]['qty_ordered'] <= 0) {
            $lines[$index]['qty_ordered'] = $this->formatQty(1);
        }
        if (! array_key_exists('qty_received', $lines[$index]) || $lines[$index]['qty_received'] === null) {
            $lines[$index]['qty_received'] = '';
        }

        $this->lines = array_values($lines);
    }

    /**
     * UOMs allowed for a PO line: item standard + pricing UOMs (no free text).
     *
     * @return list<string>
     */
    public function uomOptionsForLine(int $index): array
    {
        if (! isset($this->lines[$index])) {
            return ['EA'];
        }

        $current = trim((string) ($this->lines[$index]['uom'] ?? ''));
        $itemId = (int) ($this->lines[$index]['item_id'] ?? 0);
        $options = [];

        if ($itemId > 0) {
            $item = Item::query()->with('prices')->find($itemId);
            if ($item) {
                if (filled($item->unit_of_measure)) {
                    $options[] = (string) $item->unit_of_measure;
                }
                foreach ($item->prices as $p) {
                    if (filled($p->uom)) {
                        $options[] = (string) $p->uom;
                    }
                }
            }
        }

        if ($current !== '') {
            $options[] = $current;
        }

        $options = array_values(array_unique(array_filter($options, fn ($u) => trim((string) $u) !== '')));

        return $options !== [] ? $options : ['EA'];
    }

    /**
     * @deprecated Use addItemFromEntry
     */
    public function addItemFromScan(?string $code = null): void
    {
        $this->addItemFromEntry($code);
    }

    /**
     * Browse search / scanner: exact match adds; otherwise keeps list filter.
     */
    public function scanBrowseAndPick(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        if ($code !== null) {
            $this->browseSearch = trim($code);
            $this->itemBrowseSearch = $this->browseSearch;
        }

        $resolved = trim($this->browseSearch);
        if ($resolved === '') {
            $this->focusBrowseSearch();

            return;
        }

        $item = $this->findPurchaseItem($resolved);
        if ($item) {
            $this->browseSearch = '';
            $this->itemBrowseSearch = '';
            $this->pickBrowseItem((int) $item->id);
            $this->resetBrowseAndLoadFirstPage();
            $this->focusBrowseSearch();

            return;
        }

        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch(true);
    }

    public function focusBrowseScan(): void
    {
        abort_if($this->viewMode, 403);

        if (trim($this->browseSearch) !== '') {
            $this->scanBrowseAndPick();

            return;
        }

        $this->focusBrowseSearch();
    }

    public function clearLineItemCode(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $lines = $this->lines;
        $lines[$index]['item_id'] = null;
        $lines[$index]['item_code'] = '';
        $lines[$index]['description'] = '';
        $lines[$index]['uom'] = '';
        $lines[$index]['unit_cost'] = '';
        $this->lines = $lines;
        if (str_contains(strtolower($this->lookupMessage), 'was not found')) {
            $this->lookupMessage = '';
        }
    }

    protected function focusLineCode(int $index, bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("po-line-code-'.$index.'"); if (el) { el.focus();'.$selectJs.' } });');
    }

    protected function focusNextEmptyLineCode(): void
    {
        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null)) {
                $this->focusLineCode((int) $i);

                return;
            }
        }
    }

    public function save(): mixed
    {
        abort_if($this->viewMode, 403);

        try {
            $this->validate([
                'po_number' => 'required|string|max:64',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'requisition_date' => 'nullable|date',
                'required_date' => 'nullable|date',
                'lines.*.item_code' => 'nullable|string|max:64',
                'lines.*.qty_ordered' => 'nullable|numeric',
                'lines.*.unit_cost' => 'nullable|numeric',
            ], [
                'po_number.required' => 'PO number is required.',
                'supplier_id.required' => 'Supplier is required.',
                'supplier_id.exists' => 'Select a valid supplier.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->activeTab = 'general';
            throw $e;
        }

        $hasLines = collect($this->lines)->contains(function ($l) {
            $hasItem = (int) ($l['item_id'] ?? 0) > 0 || filled(trim((string) ($l['item_code'] ?? '')));

            return $hasItem && (float) ($l['qty_ordered'] ?? 0) > 0;
        });
        if (! $hasLines) {
            $this->addError('lines', 'This purchase order is not complete. Add at least one item before saving.');
            $this->lineWarning = 'This purchase order is not complete. Add at least one item before saving.';
            $this->lineWarningKind = 'error';
            $this->activeTab = 'items';

            return null;
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $subtotal = collect($this->lines)->sum(fn ($l) => $this->amountOrZero($l['qty_ordered'] ?? null) * $this->amountOrZero($l['unit_cost'] ?? null));
        $total = $subtotal
            - $this->amountOrZero($this->trade_discount)
            + $this->amountOrZero($this->freight)
            + $this->amountOrZero($this->miscellaneous)
            + $this->amountOrZero($this->tax);

        $data = [
            'company_id' => auth()->user()->company_id,
            'po_number' => $this->po_number,
            'order_type' => $this->order_type,
            'reference_no' => $this->reference_no !== '' ? $this->reference_no : null,
            'requisition_date' => $this->requisition_date ?: null,
            'status' => $this->status,
            'buyer_id' => $nullableId($this->buyer_id),
            'required_date' => $this->required_date ?: null,
            'ship_to_site_id' => $nullableId($this->ship_to_site_id),
            'supplier_id' => $nullableId($this->supplier_id),
            'ship_from' => $this->ship_from,
            'payment_term_id' => $nullableId($this->payment_term_id),
            'ship_via_id' => $nullableId($this->ship_via_id),
            'comments' => $this->comments,
            'subtotal' => $subtotal,
            'trade_discount' => $this->amountOrZero($this->trade_discount),
            'freight' => $this->amountOrZero($this->freight),
            'miscellaneous' => $this->amountOrZero($this->miscellaneous),
            'tax' => $this->amountOrZero($this->tax),
            'total' => $total,
        ];

        $previousPoItemIds = [];
        if ($this->purchaseOrder?->exists) {
            $this->purchaseOrder->loadMissing('lines');
            $previousPoItemIds = $this->purchaseOrder->lines
                ->pluck('item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $po = null;
        DB::transaction(function () use ($data, &$po) {
            if ($this->purchaseOrder) {
                $this->purchaseOrder->update($data);
                $po = $this->purchaseOrder->fresh();
                $po->lines()->delete();
            } else {
                $po = PurchaseOrder::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null) && empty($line['item_id'])) {
                    continue;
                }
                $qty = $this->amountOrZero($line['qty_ordered'] ?? null);
                $cost = $this->amountOrZero($line['unit_cost'] ?? null);
                $po->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'] ?: null,
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty_ordered' => $qty,
                    'qty_received' => $this->amountOrZero($line['qty_received'] ?? null),
                    'unit_cost' => $cost,
                    'extended_cost' => $qty * $cost,
                    'line_no' => $i + 1,
                ]);

                if (! empty($line['item_id'])) {
                    Item::query()->where('id', $line['item_id'])->update([
                        'last_ordered_at' => $data['requisition_date'] ?? now()->toDateString(),
                    ]);
                }
            }
        });

        $itemIds = collect($this->lines)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all();
        $itemIds = array_values(array_unique(array_merge($itemIds, $previousPoItemIds)));
        app(InventoryService::class)->syncOnOrderQty($itemIds);

        session()->flash('status', 'Purchase order saved.');

        return $this->redirect(route('purchasing.orders.edit', $po), navigate: true);
    }

    public function cancelAction(): mixed
    {
        return $this->redirect(route('purchasing.orders.index'), navigate: true);
    }

    public function receiveThisOrder(): mixed
    {
        if (! $this->purchaseOrder?->exists) {
            session()->flash('status', 'Save the purchase order before receiving.');

            return null;
        }

        $po = $this->purchaseOrder->load('lines');
        if ($po->lines->isEmpty()) {
            $this->addError('lines', 'This purchase order is not complete. Add at least one item before receiving.');
            $this->lineWarning = 'This purchase order is not complete. Add at least one item before receiving.';
            $this->lineWarningKind = 'error';
            $this->activeTab = 'items';

            return null;
        }
        $receiving = InventoryReceiving::query()->create([
            'company_id' => $po->company_id,
            'receipt_number' => InventoryReceiving::nextNumber($po->company_id),
            'receipt_date' => now()->toDateString(),
            'purchase_order_id' => $po->id,
            'status' => 'New',
            'supplier_id' => $po->supplier_id,
            'buyer_id' => $po->buyer_id,
            'site_id' => $po->ship_to_site_id,
            'received_by' => auth()->user()->name,
        ]);

        foreach ($po->lines as $i => $line) {
            $remaining = max(0, (float) $line->qty_ordered - (float) $line->qty_received);
            if ($remaining <= 0) {
                continue;
            }
            $receiving->lines()->create([
                'purchase_order_line_id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'description' => $line->description,
                'uom' => $line->uom,
                'qty_ordered' => $line->qty_ordered,
                'qty_received' => $remaining,
                'unit_cost' => $line->unit_cost,
                'line_no' => $i + 1,
            ]);
        }

        return $this->redirect(route('purchasing.receivings.edit', $receiving), navigate: true);
    }

    public function printThisOrder(): void
    {
        if (! $this->purchaseOrder?->exists) {
            session()->flash('status', 'Save the purchase order before printing.');

            return;
        }

        $url = route('purchasing.orders.print', $this->purchaseOrder);
        $this->dispatch('open-purchase-order-pdf', url: $url);
        $this->js('window.open('.json_encode($url).', "_blank")');
    }
}; ?>

<div class="desk-page entity-page po-page">
    <form wire:submit="save" class="desk-main entity-form item-form po-form" @class(['item-form-readonly' => $viewMode])>
        <x-action-bar :title="$purchaseOrder ? 'PO '.$po_number : 'New Purchase Order'">
            <x-slot:menu>
                <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" :disabled="$viewMode" />
                <x-action-item label="Receive Purchase Order" sep wire:click="receiveThisOrder" :disabled="! $purchaseOrder" />
                <x-action-item label="Refresh" :disabled="true" sep />
                <x-action-item label="Import Purchase Order" :disabled="true" sep />
                <x-action-item label="Print" sep wire:click="printThisOrder" :disabled="! $purchaseOrder" />
                <x-action-item label="Cancel" kbd="Ctrl+Q" sep wire:click="cancelAction" />
            </x-slot:menu>
        </x-action-bar>
        <fieldset class="so-form-fields" @disabled($viewMode)>

        <div class="entity-body">
            <div class="entity-header">
                @if ($activeTab === 'items')
                    <div class="po-items-head">
                        <label class="so-form-lbl" for="po_number_items">Purchase Order No</label>
                        <input id="po_number_items" wire:model="po_number" class="so-input font-mono po-head-po" @disabled($purchaseOrder) />
                        <label class="so-form-lbl" for="supplier_id_items">Supplier ID</label>
                        <select id="supplier_id_items" wire:model.live="supplier_id" class="so-input po-head-supplier">
                            <option value="">—</option>
                            @foreach ($suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="so-form-row so-form-row-pair entity-header-row">
                        <label class="so-form-lbl so-field-req" for="po_number">PO No.</label>
                        <div class="so-form-ctl">
                            <input id="po_number" wire:model="po_number" class="so-input font-mono @error('po_number') is-invalid @enderror" @disabled($purchaseOrder) />
                            @error('po_number') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <span class="so-form-lbl">Status</span>
                        <span @class([
                            'desk-pill',
                            'desk-pill-new' => in_array($status, ['New', 'Partially Received'], true),
                            'desk-pill-invoiced' => $status === 'Received',
                            'desk-pill-muted' => ! in_array($status, ['New', 'Partially Received', 'Received'], true),
                        ])>{{ $status }}</span>
                    </div>
                @endif
            </div>

            @if ($activeTab === 'general')
                <div class="sc-general-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Order header</div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="order_type">Order Type</label>
                            <select id="order_type" wire:model="order_type" class="so-input">
                                <option>Standard</option>
                                <option>Drop Ship</option>
                                <option>Blanket</option>
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="reference_no">Reference No.</label>
                            <input id="reference_no" wire:model="reference_no" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="requisition_date">Requisition Date</label>
                            <input id="requisition_date" type="date" wire:model="requisition_date" class="so-input sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="status">Order Status</label>
                            <input id="status" wire:model="status" class="so-input so-input-ro sc-date" readonly />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="buyer_id">Buyer / Requester</label>
                            <select id="buyer_id" wire:model="buyer_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($buyers as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="required_date">Required Date</label>
                            <input id="required_date" type="date" wire:model="required_date" class="so-input sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="ship_to_site_id">Ship To</label>
                            <select id="ship_to_site_id" wire:model="ship_to_site_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="inv-card">
                        <div class="inv-card-title">Supplier & shipping</div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl so-field-req" for="supplier_id">Supplier</label>
                            <div class="so-form-ctl">
                                <div class="so-lookup-row">
                                    <select id="supplier_id" wire:model.live="supplier_id" class="so-input @error('supplier_id') is-invalid @enderror">
                                        <option value="">— Select supplier —</option>
                                        @foreach ($suppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->supplier_id }} — {{ $sup->name }}</option>
                                        @endforeach
                                    </select>
                                    <a href="{{ route('purchasing.suppliers.create') }}" wire:navigate class="desk-btn desk-btn-sm" title="New supplier">+</a>
                                </div>
                                @error('supplier_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl">Supplier ID</label>
                            <input
                                type="text"
                                class="so-input so-input-ro"
                                readonly
                                value="{{ $selectedSupplier?->supplier_id ?: '—' }}"
                                aria-label="Supplier ID"
                            />
                        </div>
                        @if ($selectedSupplier)
                            <div class="so-form-row so-form-row-side sc-field">
                                <span class="so-form-lbl"></span>
                                <div class="po-supplier-addr">
                                    {{ $selectedSupplier->address }}<br>
                                    {{ collect([$selectedSupplier->city, $selectedSupplier->state, $selectedSupplier->zip_code])->filter()->implode(', ') }}
                                </div>
                            </div>
                        @endif
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="ship_from">Ship From</label>
                            <input id="ship_from" wire:model="ship_from" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="payment_term_id">Terms</label>
                            <div class="so-lookup-row">
                                <select id="payment_term_id" wire:model="payment_term_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($paymentTerms as $pt)
                                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'payment_terms']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="ship_via_id">Ship Via</label>
                            <div class="so-lookup-row">
                                <select id="ship_via_id" wire:model="ship_via_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($shipVias as $sv)
                                        <option value="{{ $sv->id }}">{{ $sv->name }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()?->canAccessFeature('lookups', 'edit'))
                                    <a href="{{ route('lookups.index', ['activeLookup' => 'ship_vias']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
                                @endif
                            </div>
                        </div>
                        <div class="so-form-row so-form-row-side so-form-row-top sc-field">
                            <label class="so-form-lbl" for="comments">Comments</label>
                            <textarea id="comments" wire:model="comments" rows="4" class="so-input so-input-area" placeholder="Optional notes…"></textarea>
                        </div>
                    </div>

                    <div class="inv-card" style="grid-column:1 / -1">
                        <div class="inv-card-title">Order totals</div>
                        <div class="sc-general-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:0.75rem 1.25rem">
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl">Order Subtotal</label>
                                <span class="entity-value text-right" style="display:block;width:100%">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl" for="trade_discount_general">Discount</label>
                                <input id="trade_discount_general" wire:model.live="trade_discount" class="so-input text-right" placeholder="0" />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl" for="freight_general">Freight</label>
                                <input id="freight_general" wire:model.live="freight" class="so-input text-right" placeholder="0" />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl">Order Total</label>
                                <strong class="entity-value text-right" style="display:block;width:100%;font-size:1.1rem">${{ number_format($orderTotal, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <div class="so-expand-panel po-expand-panel">
                <div class="so-expand-main">
                @error('lines')
                    <div class="so-browse-alert so-browse-alert-error" role="alert">{{ $message }}</div>
                @enderror
                <div class="so-items-wrap so-items-wrap-tall">
                    <div class="so-items-grid" wire:key="po-lines-wrap-{{ $linesSig }}">
                        <table class="so-lines-table po-lines-table" data-excel-grid>
                            <colgroup>
                                <col class="col-code" />
                                <col class="col-desc" />
                                <col class="col-uom" />
                                <col class="col-price" />
                                <col class="col-qty-ord" />
                                <col class="col-qty-rcv" />
                                <col class="col-cost" />
                                <col class="col-ext" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-code">Item Code</th>
                                    <th class="col-desc">Description</th>
                                    <th class="col-uom text-center">U of M</th>
                                    <th class="col-price text-center">Price</th>
                                    <th class="col-qty-ord text-center">Qty Ordered</th>
                                    <th class="col-qty-rcv text-center">Qty Received</th>
                                    <th class="col-cost text-center">Cost</th>
                                    <th class="col-ext text-center">Extended Cost</th>
                                </tr>
                            </thead>
                            <tbody wire:key="po-lines-body-{{ $linesSig }}">
                                @foreach ($lines as $i => $line)
                                    @php
                                        $filled = filled($line['item_code'] ?? null) || (int) ($line['item_id'] ?? 0) > 0;
                                    @endphp
                                    @if ($filled)
                                        <tr
                                            wire:key="po-line-row-{{ $i }}-{{ $line['item_id'] ?? 0 }}-{{ $line['item_code'] ?? '' }}"
                                            id="po-line-row-{{ $i }}"
                                            @class(['is-selected' => $selectedLineIndex === $i])
                                            wire:click="$set('selectedLineIndex', {{ $i }})"
                                            wire:dblclick="openLineItemRecord({{ $i }})"
                                        >
                                            <td class="col-code font-mono desk-num" data-excel-value="{{ $line['item_code'] ?? '' }}" title="{{ $line['item_code'] ?? '' }}">
                                                {{ filled($line['item_code'] ?? null) ? $line['item_code'] : '—' }}
                                            </td>
                                            <td class="col-desc">
                                                <input
                                                    wire:model="lines.{{ $i }}.description"
                                                    wire:click.stop
                                                    class="so-input item-cell-ctl po-line-desc-input"
                                                    @disabled($viewMode)
                                                />
                                            </td>
                                            <td class="col-uom text-center">
                                                @if ($viewMode)
                                                    <span class="font-mono">{{ $line['uom'] ?: '—' }}</span>
                                                @else
                                                    @php $uomOpts = $this->uomOptionsForLine($i); @endphp
                                                    @if (count($uomOpts) <= 1)
                                                        {{-- Item standard UOM only — show, no manual typing --}}
                                                        <span class="font-mono" style="display:inline-block;min-width:2.5rem">
                                                            {{ $line['uom'] ?: ($uomOpts[0] ?? 'EA') }}
                                                        </span>
                                                    @else
                                                        {{-- Item has multiple UOMs — select among item standards only --}}
                                                        <select
                                                            wire:model="lines.{{ $i }}.uom"
                                                            class="so-input text-center item-cell-ctl"
                                                            style="max-width:5.5rem;margin:0 auto"
                                                            aria-label="Unit of measure line {{ $i + 1 }}"
                                                        >
                                                            @foreach ($uomOpts as $uomOpt)
                                                                <option value="{{ $uomOpt }}">{{ $uomOpt }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="col-price desk-money">
                                                {{ (float) ($line['list_price'] ?? $line['unit_cost'] ?? 0) != 0.0 ? number_format((float) ($line['list_price'] ?? $line['unit_cost'] ?? 0), 2) : '' }}
                                            </td>
                                            <td class="col-qty-ord text-center">
                                                @php $qty = (float) ($line['qty_ordered'] ?? 0); @endphp
                                                @if ($selectedLineIndex === $i && ! $viewMode)
                                                    <div class="so-qty-stepper" wire:click.stop>
                                                        <button type="button" class="so-qty-btn" wire:click="adjustLineQty({{ $i }}, -1)" aria-label="Decrease qty">−</button>
                                                        <input
                                                            type="text"
                                                            wire:model.blur="lines.{{ $i }}.qty_ordered"
                                                            wire:keydown.up.prevent="adjustLineQty({{ $i }}, 1)"
                                                            wire:keydown.down.prevent="adjustLineQty({{ $i }}, -1)"
                                                            wire:keydown.enter.prevent="$wire.set('selectedLineIndex', {{ $i + 1 }}); $nextTick(() => document.querySelector('#po-line-row-{{ $i + 1 }} .so-cell-input')?.focus())"
                                                            class="so-cell-input text-right"
                                                            placeholder="0"
                                                            size="4"
                                                            step="0.01"
                                                            inputmode="decimal"
                                                            aria-label="Qty ordered"
                                                        />
                                                        <button type="button" class="so-qty-btn" wire:click="adjustLineQty({{ $i }}, 1)" aria-label="Increase qty">+</button>
                                                    </div>
                                                @else
                                                    {{ $qty != 0.0 ? $this->formatQty($qty) : '' }}
                                                @endif
                                            </td>
                                            <td class="col-qty-rcv text-center">
                                                <input
                                                    wire:model="lines.{{ $i }}.qty_received"
                                                    class="so-input text-right item-cell-qty so-input-ro"
                                                    readonly
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td class="col-cost text-center">
                                                <input
                                                    wire:model.blur="lines.{{ $i }}.unit_cost"
                                                    wire:click.stop
                                                    class="so-input text-right item-cell-qty"
                                                    placeholder="0"
                                                    @disabled($viewMode)
                                                />
                                            </td>
                                            <td class="col-ext desk-money">
                                                ${{ number_format((float) ($line['qty_ordered'] ?? 0) * (float) ($line['unit_cost'] ?? 0), 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                        @if ($filledLineCount === 0)
                            <div class="so-items-empty" role="status">Enter item code or click Browse to add items</div>
                        @endif
                    </div>
                </div>

                    <div class="so-entry">
                        <span class="so-entry-label">Item code / barcode (F2) · Browse (F3)</span>
                        <div
                            class="so-scan-bar"
                            role="search"
                            @class(['is-scan-ready' => $scanModeActive])
                        >
                            <button
                                type="button"
                                wire:click="focusScanAndAdd"
                                class="so-scan-btn"
                                title="Scan: click to focus, or add the code already in the box"
                                @disabled($viewMode)
                            >
                                <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <input
                                wire:ignore.self
                                type="text"
                                class="so-input so-entry-input"
                                id="po-item-entry"
                                data-pos-item-entry
                                placeholder="Scan or type full code — adds when it matches"
                                autocomplete="off"
                                inputmode="text"
                                @disabled($viewMode)
                                x-data="{
                                    timer: null,
                                    lastKeyAt: 0,
                                    rapid: false,
                                    lastClaim: '',
                                    lastClaimAt: 0,
                                    claim(v) {
                                        const n = (v || '').trim().toLowerCase();
                                        if (!n) return false;
                                        const now = Date.now();
                                        if (n === this.lastClaim && (now - this.lastClaimAt) < 400) return false;
                                        this.lastClaim = n;
                                        this.lastClaimAt = now;
                                        return true;
                                    },
                                    scheduleAuto() {
                                        clearTimeout(this.timer);
                                        const delay = this.rapid ? 80 : 400;
                                        this.timer = setTimeout(() => {
                                            const v = ($el.value || '').trim();
                                            if (v.length < 2) { this.rapid = false; return; }
                                            if (!this.claim(v)) { this.rapid = false; return; }
                                            $wire.autoAddEntryIfExactMatch(v);
                                            this.rapid = false;
                                        }, delay);
                                    },
                                    onKey(e) {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            clearTimeout(this.timer);
                                            const v = ($el.value || '').trim();
                                            $el.value = '';
                                            if (v && this.claim(v)) {
                                                $wire.addItemFromEntry(v);
                                            }
                                            this.rapid = false;
                                            return;
                                        }
                                        if (e.key === 'F2') {
                                            e.preventDefault();
                                            clearTimeout(this.timer);
                                            $el.focus();
                                            $el.select?.();
                                            return;
                                        }
                                        if (e.key === 'F3') {
                                            e.preventDefault();
                                            clearTimeout(this.timer);
                                            $wire.openItemBrowse();
                                            return;
                                        }
                                        const now = Date.now();
                                        if (this.lastKeyAt && (now - this.lastKeyAt) < 50) this.rapid = true;
                                        this.lastKeyAt = now;
                                    },
                                    onInput() {
                                        this.scheduleAuto();
                                    }
                                }"
                                x-on:keydown="onKey($event)"
                                x-on:input="onInput()"
                                x-on:paste.prevent="
                                    clearTimeout(timer);
                                    const t = ($event.clipboardData || window.clipboardData).getData('text') || '';
                                    $el.value = t.replace(/[\x00-\x1F\x7F]+/g, '').trim();
                                    rapid = false;
                                    const v = ($el.value || '').trim();
                                    if (v.length >= 2 && claim(v)) {
                                        $el.value = '';
                                        $wire.addItemFromEntry(v);
                                    }
                                "
                            />
                            @unless ($viewMode)
                                <button
                                    type="button"
                                    wire:click="clearItemLookup"
                                    class="so-icon-btn"
                                    title="Clear item code"
                                    aria-label="Clear item code"
                                >
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 3l6 6M9 3L3 9"/></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click.prevent="$wire.addItemFromEntry(document.getElementById('po-item-entry')?.value || '')"
                                    class="so-icon-btn so-entry-add-btn"
                                    title="Add item (✓) — use after typing item code"
                                    aria-label="Add item"
                                    wire:loading.attr="disabled"
                                    wire:target="addItemFromEntry,focusScanAndAdd"
                                >
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                                </button>
                            @endunless
                        </div>
                        @unless ($viewMode)
                            <div class="so-entry-tools">
                                <button type="button" wire:click="printThisOrder" class="so-icon-btn" title="Print" tabindex="-1" aria-label="Print" @disabled(! $purchaseOrder)>
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M3 4V2h6v2M3 8H2V5h8v3H9M3 7h6v3H3V7z"/></svg>
                                </button>
                                <button type="button" wire:click="removeSelectedLine" class="so-icon-btn" title="Delete selected line" tabindex="-1" aria-label="Delete line">
                                    <svg viewBox="0 0 12 12" fill="none" stroke="#b91c1c" stroke-width="1.6"><path d="M3 3l6 6M9 3L3 9"/></svg>
                                </button>
                                <button type="button" wire:click="addLine" class="so-icon-btn" title="New line" aria-label="New line">
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                                </button>
                                <button type="button" wire:click="openItemBrowse" class="so-browse-btn" title="Item list (F3)">Browse (F3)</button>
                            </div>
                        @endunless
                    </div>
                    <div class="so-footer">
                        <div class="so-counters">
                            <div class="so-counter-col">
                                <div>Total items ordered: <strong>{{ $this->formatQty($totalItemsOrdered) ?: '0' }}</strong></div>
                                <div>Total items received: <strong>{{ $this->formatQty($totalItemsReceived) ?: '0' }}</strong></div>
                            </div>
                        </div>
                        <div class="so-totals">
                            <div class="so-totals-row"><span class="so-totals-lbl">Subtotal:</span><span class="so-totals-amt">${{ number_format($subtotal, 2) }}</span></div>
                            <div class="so-totals-row">
                                <span class="so-totals-lbl">Trade Discount:</span>
                                <label class="so-totals-amt">$<input type="text" inputmode="decimal" id="trade_discount" wire:model.live="trade_discount" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                            </div>
                            <div class="so-totals-row">
                                <span class="so-totals-lbl">Freight:</span>
                                <label class="so-totals-amt">$<input type="text" inputmode="decimal" id="freight" wire:model.live="freight" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                            </div>
                            <div class="so-totals-row">
                                <span class="so-totals-lbl">Miscellaneous:</span>
                                <label class="so-totals-amt">$<input type="text" inputmode="decimal" id="miscellaneous" wire:model.live="miscellaneous" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                            </div>
                            <div class="so-totals-row">
                                <span class="so-totals-lbl">Tax:</span>
                                <label class="so-totals-amt">$<input type="text" inputmode="decimal" id="tax" wire:model.live="tax" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                            </div>
                            <div class="so-totals-row so-totals-final"><span class="so-totals-lbl">Total:</span><strong class="so-totals-amt">${{ number_format($orderTotal, 2) }}</strong></div>
                        </div>
                    </div>
                </div>
                </div>
            @endif
        </div>
        </fieldset>

        <div class="entity-footer">
            <div class="entity-tabs" role="tablist" aria-label="Purchase order sections">
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
                <a href="{{ route('purchasing.orders.index') }}" wire:navigate class="desk-btn">{{ $viewMode ? 'Close' : 'Cancel' }}</a>
                @if ($viewMode && $purchaseOrder)
                    <a href="{{ route('purchasing.orders.edit', $purchaseOrder) }}" wire:navigate class="desk-btn desk-btn-primary">Edit PO</a>
                @elseif (! $viewMode)
                    <button type="submit" class="desk-btn desk-btn-primary">Save Changes</button>
                @endif
            </div>
        </div>
    </form>

    @include('livewire.pages.sales.orders.partials.item-browse-panel')
</div>

@script
<script>
    $wire.on('open-item-record', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });
    $wire.on('pos-alert', (e) => {
        const kind = (e && e.kind) || (Array.isArray(e) && e[0] && e[0].kind) || 'error';
        window.playPosAlert && window.playPosAlert(kind);
    });
    $wire.$watch('showBrowse', (open) => {
        if (!open) return;
        requestAnimationFrame(() => {
            const el = document.getElementById('so-browse-search');
            if (el) {
                el.focus();
                el.select?.();
            }
        });
    });
</script>
@endscript
