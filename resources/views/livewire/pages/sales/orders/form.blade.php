<?php

use App\Models\Customer;
use App\Models\CustomerItemPrice;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemSubstitute;
use App\Models\PaymentTerm;
use App\Models\RouteLookup;
use App\Models\SalesOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\TaxSchedule;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ParkedSaleService;
use App\Services\SalesOrderWindowManager;
use App\Support\ExcelCsv;
use App\Support\ItemPricing;
use App\Support\ItemSearch;
use App\Support\SalesOrderLinePresentation;
use App\Support\StockPolicy;
use App\Livewire\Concerns\SortsItemBrowse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('New Sales Order')] class extends Component
{
    use SortsItemBrowse;
    public ?SalesOrder $salesOrder = null;

    /** View-only (same layout as edit, locked). */
    public bool $viewMode = false;

    /** Query: ?from=invoices — Save/Cancel go back to the invoice list. */
    #[Url]
    public string $from = '';

    /** Create-mode multi-window id (?w=). */
    public ?string $createWindowId = null;

    public bool $skipWindowDraftPersist = false;

    public bool $showPrintDialog = false;

    public bool $optCreateInvoicePayment = false;

    public bool $optPrintSalesOrder = false;

    public bool $optCreatePrintInvoice = false;

    public bool $optPrintPickList = false;

    /** Print / email choice after Print Invoice. */
    public bool $showInvoiceDeliveryDialog = false;

    public string $invoiceDeliveryMode = 'print'; // print | email | both

    public string $invoiceEmailTo = '';

    public string $invoiceEmailSubject = '';

    public string $activeTab = 'general';

    public string $addressTab = 'bill';

    public string $itemEntry = '';

    /** True after "Scan" click — next Enter/barcode auto-adds the line. */
    public bool $scanModeActive = false;

    public bool $showParkedSalesModal = false;

    public bool $showOpenOrderModal = false;

    public string $openOrderSearch = '';

    /** @var array<int, array{id:int,order_number:string,customer_label:string,total:float,order_date:?string}> */
    public array $openOrderList = [];

    public string $lastScanClaimCode = '';

    public int $lastScanClaimAt = 0;

    /** @var array<int, array{id:int,customer_label:?string,line_count:int,total:float,updated_at:?string}> */
    public array $parkedSalesList = [];

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

    /** Highlighted row (focus) for Action → insert/edit */
    public ?int $browseSelectedId = null;

    /** Multi-select checkboxes for insert-all */
    public array $browseCheckedIds = [];

    public int $browseChecksVersion = 0;

    public string $favorite = 'new';

    public bool $customerFavoritesOnly = false;

    public string $order_number = '';

    public string $order_type = 'Sales Order';

    public string $status = 'New';

    public string $priority = 'Normal';

    public ?int $customer_id = null;

    public ?int $customerPriceLevelId = null;

    /** @var array<int, float> */
    public array $itemTaxRateCache = [];

    public ?int $ship_to_address_id = null;

    public string $bill_to_name = '';

    public string $bill_to_phone = '';

    public string $bill_to_address = '';

    public string $bill_to_city = '';

    public string $bill_to_state = '';

    public string $bill_to_zip = '';

    public string $ship_to_name = '';

    public string $ship_to_phone = '';

    public string $ship_to_address = '';

    public string $ship_to_city = '';

    public string $ship_to_state = '';

    public string $ship_to_zip = '';

    public string $order_date = '';

    public string $required_date = '';

    public string $customer_po_no = '';

    public string $reference_no = '';

    public ?int $sales_rep_id = null;

    public ?int $payment_term_id = null;

    public ?int $route_id = null;

    public ?int $ship_via_id = null;

    public ?int $ship_from_site_id = null;

    public string $ship_date = '';

    public string $no_of_boxes = '';

    public string $no_of_pallets = '';

    public string $custom_field_1 = '';

    public string $custom_field_2 = '';

    public string $custom_field_3 = '';

    public string $custom_field_4 = '';

    public string $custom_field_5 = '';

    public string $comments = '';

    public string $freight = '';

    public string $trade_discount = '';

    public string $miscellaneous = '';

    public string $tax = '';

    public string $customerAlert = '';

    public string $creditWarning = '';

    public string $taxExemptWarning = '';

    public string $lineWarning = '';

    public string $lineWarningKind = 'error';

    public string $orderLockMessage = '';

    public bool $showUnknownScanModal = false;

    public string $unknownScanCode = '';

    public bool $showSubstitutePrompt = false;

    public ?int $pendingItemId = null;

    public ?int $pendingLineIndex = null;

    /** @var array<int, array{id:int,item_code:string,description:string,available:float}> */
    public array $substituteOptions = [];

    public ?int $selectedLineIndex = null;

    public bool $showLineSubstitutes = false;

    public bool $showLineMessageModal = false;

    public string $lineMessageEdit = '';

    public string $lineInstructionsEdit = '';

    public string $lineMsgItemCode = '';

    public string $lineMsgDescription = '';

    public string $orderLineMessagePopup = '';

    public string $orderLineInstructionsPopup = '';

    public bool $showLineMessageAlert = false;

    public string $selectedStockCode = '';

    public string $selectedStockOnHand = '';

    public string $selectedStockAllocated = '';

    public string $selectedStockAvailable = '';

    public string $selectedStockOrdered = '';

    public string $selectedStockRemaining = '';

    public bool $showUomModal = false;

    /** @var array<int, string> */
    public array $lineUomOptions = [];

    public bool $showBatchModal = false;

    public string $batchInfo = '';

    /** @var array<int, array{batch_number:string,tracking_type:string,quantity:string,expiry_date:string,received_at:string,notes:string}> */
    public array $batchRows = [];

    public bool $showCustomerBrowse = false;

    public string $customerSearch = '';

    public bool $showShipBrowse = false;

    public bool $showShipToModal = false;

    public string $newShipName = '';

    public string $newShipPhone = '';

    public string $newShipFax = '';

    public string $newShipAddress = '';

    public string $newShipCity = '';

    public string $newShipState = '';

    public string $newShipZip = '';

    public string $newShipClass = '';

    public bool $newShipPrimary = true;

    public string $shipToFlash = '';

    public bool $taxManual = false;

    public ?int $orderTaxScheduleId = null;

    public bool $showNewTaxSchedule = false;

    public string $newTaxRate = '';

    public string $newTaxName = '';

    public string $newTaxCode = '';

    public float $pendingTradePercent = 0;

    /** Chief-style: "Do you want to memorize this price for this customer?" */
    public bool $showMemorizePriceModal = false;

    public ?int $memorizeLineIndex = null;

    public string $memorizePriceValue = '';

    /** Chief-style: "Price is below allowed limit, are you sure you want to continue?" */
    public bool $showPriceBelowLimitModal = false;

    public ?int $priceBelowLimitLineIndex = null;

    /** Confirm customer selection to avoid order mistakes. */
    public bool $showCustomerConfirmModal = false;

    public string $customerConfirmLabel = '';

    /** Last customer the user confirmed (or loaded on mount). */
    public ?int $confirmedCustomerId = null;

    /** Previous customer to restore if user rejects the new selection. */
    public ?int $previousCustomerId = null;

    /** Skip customer confirm (mount / cancel / internal applies). */
    public bool $suppressCustomerConfirm = false;

    /** Skip price-memorize prompt while auto-repricing (e.g. customer change). */
    public bool $suppressPriceNotice = false;

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,qty_shipped:string,price:string,discount:string}> */
    public array $lines = [];

    /** @var array<int, array{box_number:string,tracking_number:string}> */
    public array $boxes = [];

    public function mount(mixed $salesOrder = null): void
    {
        if (! $salesOrder instanceof SalesOrder && is_numeric($salesOrder)) {
            $salesOrder = SalesOrder::query()->find((int) $salesOrder);
        }

        if ($this->activeTab === 'expand') {
            $this->activeTab = 'items';
        }

        // New order always opens on General (customer / order header).
        if (! ($salesOrder instanceof SalesOrder && $salesOrder->exists)) {
            $this->activeTab = 'general';
        }

        $this->viewMode = request()->routeIs('sales.orders.show');
        if (request()->query('from') === 'invoices') {
            $this->from = 'invoices';
        }
        $companyId = auth()->user()->company_id;

        if ($salesOrder instanceof SalesOrder && $salesOrder->exists) {
            abort_unless($salesOrder->company_id === $companyId, 403);
            $salesOrder->loadMissing('invoice');
            $this->salesOrder = $salesOrder->load([
                'lines' => fn ($q) => $q->orderBy('line_no'),
                'boxes',
                'customer:id,customer_id,company_name,messages_alerts,price_level_id,is_favorite',
                'invoice:id,invoice_number,sales_order_id,status',
            ]);
            if ($salesOrder->status === 'Invoiced' || $salesOrder->invoice) {
                $invNo = $salesOrder->invoice?->invoice_number;
                $this->orderLockMessage = $invNo
                    ? 'This order is invoiced (#'.$invNo.'). Saving updates the invoice, totals, and stock.'
                    : 'This order is invoiced. Saving updates the invoice, totals, and stock.';
            }
            $data = $salesOrder->only([
                'order_number', 'order_type', 'status', 'priority', 'customer_id', 'ship_to_address_id',
                'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
                'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
                'customer_po_no', 'reference_no', 'sales_rep_id', 'payment_term_id', 'route_id', 'ship_via_id',
                'ship_from_site_id', 'no_of_boxes', 'no_of_pallets', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'custom_field_5', 'comments',
                'freight', 'trade_discount', 'miscellaneous', 'tax',
            ]);

            foreach ([
                'order_number', 'order_type', 'status', 'priority',
                'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
                'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
                'customer_po_no', 'reference_no',
                'no_of_boxes', 'no_of_pallets', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'custom_field_5', 'comments',
                'freight', 'trade_discount', 'miscellaneous', 'tax',
            ] as $stringProp) {
                if (! array_key_exists($stringProp, $data) || $data[$stringProp] === null) {
                    $data[$stringProp] = '';
                } else {
                    $data[$stringProp] = (string) $data[$stringProp];
                }
            }

            $this->fill($data);
            $this->order_date = optional($salesOrder->order_date)?->format('Y-m-d') ?? '';
            $this->required_date = optional($salesOrder->required_date)?->format('Y-m-d') ?? '';
            $this->ship_date = optional($salesOrder->ship_date)?->format('Y-m-d') ?? '';
            $this->no_of_boxes = $this->blankZeroAmount($salesOrder->no_of_boxes ?? 0);
            $this->no_of_pallets = $this->blankZeroAmount($salesOrder->no_of_pallets ?? 0);
            $this->freight = $this->formatMoney($this->freight);
            $this->trade_discount = $this->formatMoney($this->trade_discount);
            $this->miscellaneous = $this->formatMoney($this->miscellaneous);
            $this->tax = $this->formatMoney($this->tax);
            $this->customerAlert = $salesOrder->customer?->messages_alerts ?? '';
            $this->customerPriceLevelId = $salesOrder->customer?->price_level_id
                ? (int) $salesOrder->customer->price_level_id
                : null;
            $this->taxManual = true;
            $this->lines = $salesOrder->lines->map(function ($l) {
                $qty = (float) $l->qty_ordered;
                $discount = (float) $l->discount;
                $unitDiscount = $qty > 0 ? round($discount / $qty, 4) : $discount;

                return [
                    'item_id' => $l->item_id,
                    'item_code' => $l->item_code ?? '',
                    'description' => $l->description ?? '',
                    'uom' => $l->uom ?? '',
                    'qty_ordered' => $this->formatQty($l->qty_ordered),
                    'qty_shipped' => $this->formatQty($l->qty_shipped ?? 0),
                    'price' => $this->formatMoney($l->price),
                    'system_price' => $this->formatMoney($l->price),
                    'unit_discount' => $this->formatMoney($unitDiscount),
                    'discount' => $this->formatMoney($l->discount),
                    'line_message' => (string) ($l->line_message ?? ''),
                    'instructions' => (string) ($l->instructions ?? ''),
                ];
            })->all();
            $this->boxes = $salesOrder->boxes->map(fn ($b) => [
                'box_number' => $b->box_number ?? '',
                'tracking_number' => $b->tracking_number ?? '',
            ])->all();
            $this->refreshCreditWarning();
            $this->confirmedCustomerId = $this->customer_id ? (int) $this->customer_id : null;
        } else {
            $windows = app(SalesOrderWindowManager::class);
            $requested = request()->query('w');
            $parkedParam = request()->query('parked');
            if (! is_string($requested) || $requested === '' || ! $windows->has($requested)) {
                $id = $windows->ensureOne();
                $params = ['w' => $id];
                if (filled($parkedParam)) {
                    $params['parked'] = $parkedParam;
                }
                $this->redirect(route('sales.orders.create', $params), navigate: false);

                return;
            }
            $this->createWindowId = $requested;
            $windows->setActive($requested);

            $draft = $windows->loadDraft($requested);
            if (is_array($draft) && $draft !== []) {
                $this->applyCreateWindowDraft($draft);
            } else {
                $this->order_number = SalesOrder::nextNumber($companyId);
                $this->order_date = now()->toDateString();
                $this->required_date = now()->toDateString();
                $this->ship_date = now()->toDateString();
                $this->sales_rep_id = auth()->id();
                $this->ship_from_site_id = auth()->user()->site_id;
                // Default customer: Walk-in (cash / counter) — no confirm dialog on open
                $walkIn = $this->resolveWalkInCustomer($companyId);
                $this->suppressCustomerConfirm = true;
                $this->customer_id = $walkIn->id;
                $this->updatedCustomerId($walkIn->id);
                $this->suppressCustomerConfirm = false;
                $this->confirmedCustomerId = (int) $walkIn->id;
                $this->persistCreateWindowDraft();
            }

            if (is_numeric($parkedParam) && (int) $parkedParam > 0) {
                $this->recallParkedSale((int) $parkedParam);
            } elseif ($parkedParam === 'list') {
                $this->openParkedSalesModal();
            }
        }

        if ($this->boxes === []) {
            $this->boxes[] = ['box_number' => '', 'tracking_number' => ''];
        }
    }

    /**
     * Default cash / counter customer for new orders (one per company).
     */
    protected function resolveWalkInCustomer(int $companyId): Customer
    {
        if (method_exists(Customer::class, 'ensureWalkIn')) {
            return Customer::ensureWalkIn($companyId);
        }

        // Fallback if production Customer model is outdated and missing ensureWalkIn().
        return Customer::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'customer_id' => defined(Customer::class.'::WALK_IN_CODE')
                    ? Customer::WALK_IN_CODE
                    : 'WALKIN',
            ],
            [
                'company_name' => defined(Customer::class.'::WALK_IN_NAME')
                    ? Customer::WALK_IN_NAME
                    : 'Walk-in Customer',
                'contact' => 'Walk-in Customer',
                'lead_source' => 'Walk-in',
                'customer_category' => 'Walk-in',
                'account_type' => 'Cash',
                'is_inactive' => false,
                'is_favorite' => true,
                'credit_limit' => 0,
                'balance' => 0,
                'customer_since' => now()->toDateString(),
                'messages_alerts' => null,
                'comments' => 'System default — use for walk-in sales without a named account.',
            ]
        );
    }

    public function regenerateOrderNumber(): void
    {
        if ($this->salesOrder?->exists) {
            return;
        }

        $companyId = (int) auth()->user()->company_id;
        $current = (int) preg_replace('/\D/', '', (string) $this->order_number);
        $base = (int) SalesOrder::nextNumber($companyId);
        $n = max($current + 1, $base);

        while (
            SalesOrder::query()
                ->where('company_id', $companyId)
                ->where('order_number', (string) $n)
                ->exists()
        ) {
            $n++;
        }

        $this->order_number = (string) $n;
    }

    public function dehydrate(): void
    {
        if (! empty($this->skipWindowDraftPersist)) {
            return;
        }
        if ($this->createWindowId && ! $this->salesOrder?->exists) {
            $this->persistCreateWindowDraft();
        }
    }

    #[On('so-windows-open')]
    public function openAnotherCreateWindow(): void
    {
        if ($this->salesOrder?->exists || ! $this->createWindowId) {
            return;
        }

        $this->persistCreateWindowDraft();
        $windows = app(SalesOrderWindowManager::class);
        if ($windows->count() >= SalesOrderWindowManager::MAX_WINDOWS
            || ($windows->count() + app(\App\Services\DocumentTabManager::class)->count()) >= \App\Services\DocumentTabManager::MAX_OPEN_WINDOWS) {
            $this->notifyAlert(\App\Services\DocumentTabManager::tabLimitMessage(), 'error');

            return;
        }

        $id = $windows->open();
        if ($id === '') {
            $this->notifyAlert(\App\Services\DocumentTabManager::tabLimitMessage(), 'error');

            return;
        }
        $this->redirect(route('sales.orders.create', ['w' => $id]), navigate: false);
    }

    #[On('so-windows-switch')]
    public function switchCreateWindow(string $id): void
    {
        if ($this->salesOrder?->exists || ! $this->createWindowId) {
            return;
        }

        if ($id === $this->createWindowId) {
            return;
        }

        $windows = app(SalesOrderWindowManager::class);
        if (! $windows->has($id)) {
            return;
        }

        $this->persistCreateWindowDraft();
        $windows->setActive($id);
        $this->redirect(route('sales.orders.create', ['w' => $id]), navigate: false);
    }

    #[On('so-windows-close')]
    public function closeCreateWindow(string $id): void
    {
        $this->skipWindowDraftPersist = true;

        $windows = app(SalesOrderWindowManager::class);
        $stayOn = $this->createWindowId;
        $next = $windows->close($id);
        if ($next === null) {
            $this->createWindowId = null;
            $this->redirect(route('home'), navigate: false);

            return;
        }

        $stay = ($stayOn && $stayOn !== $id && $windows->has($stayOn))
            ? $stayOn
            : $next;

        $this->redirect(route('sales.orders.create', ['w' => $stay]), navigate: false);
    }

    /**
     * @return list<string>
     */
    protected function createWindowDraftKeys(): array
    {
        return [
            'activeTab', 'addressTab',
            'order_number', 'order_type', 'status', 'priority',
            'customer_id', 'ship_to_address_id', 'confirmedCustomerId',
            'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
            'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
            'order_date', 'required_date', 'customer_po_no', 'reference_no',
            'sales_rep_id', 'payment_term_id', 'route_id', 'ship_via_id', 'ship_from_site_id', 'ship_date',
            'no_of_boxes', 'no_of_pallets',
            'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5',
            'comments', 'freight', 'trade_discount', 'miscellaneous', 'tax', 'taxManual', 'orderTaxScheduleId',
            'lines', 'boxes', 'customerAlert', 'creditWarning', 'taxExemptWarning',
            'itemEntry', 'browseSearch', 'showBrowse', 'browseCategoryId', 'browseSubcategoryId', 'browseNewOnly',
        ];
    }

    public function updatedActiveTab(): void
    {
        $this->persistCreateWindowDraft();
    }

    public function exportLinesToExcel(): mixed
    {
        $rows = collect($this->lines)->filter(fn ($line) => filled($line['item_code'] ?? null));
        if ($rows->isEmpty()) {
            $this->notifyAlert('Add at least one item before exporting.', 'warning');

            return null;
        }

        $export = [];
        foreach ($rows as $line) {
            $qty = (float) ($line['qty_ordered'] ?? 0);
            $price = (float) ($line['price'] ?? 0);
            $disc = (float) ($line['discount'] ?? ($qty * (float) ($line['unit_discount'] ?? 0)));
            $export[] = [
                $line['item_code'] ?? '',
                $line['description'] ?? '',
                $line['uom'] ?? '',
                $qty,
                $price,
                $disc,
                round(($qty * $price) - $disc, 2),
            ];
        }

        $filename = 'order-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $this->order_number).'-lines.csv';

        return ExcelCsv::download($filename, ['Item Code', 'Description', 'UOM', 'Qty Ordered', 'Price', 'Discount', 'Line Total'], $export);
    }

    public function exportBrowseToExcel(): mixed
    {
        $companyId = (int) auth()->user()->company_id;
        $query = $this->applyBrowseOrder($this->browseBaseQuery($companyId));
        if (! $query->exists()) {
            $this->notifyAlert('No browse items to export.', 'warning');

            return null;
        }

        $rows = (static function () use ($query) {
            foreach ($query->cursor() as $row) {
                yield [
                    (string) $row->item_code,
                    (string) ($row->description ?? ''),
                    (string) ($row->unit_of_measure ?? ''),
                    $row->list_price,
                    (float) $row->quantity_in_stock - (float) $row->allocated_qty,
                    (float) $row->quantity_in_stock,
                ];
            }
        })();

        return ExcelCsv::download('browse-items.csv', ['Item Code', 'Description', 'UOM', 'Price', 'Available', 'On Hand'], $rows);
    }

    protected function persistCreateWindowDraft(): void
    {
        if (! $this->createWindowId) {
            return;
        }

        $draft = [];
        foreach ($this->createWindowDraftKeys() as $key) {
            $draft[$key] = $this->{$key};
        }

        app(SalesOrderWindowManager::class)->saveDraft($this->createWindowId, $draft);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function applyCreateWindowDraft(array $draft): void
    {
        $this->suppressCustomerConfirm = true;
        $this->suppressPriceNotice = true;

        foreach ($this->createWindowDraftKeys() as $key) {
            if (! array_key_exists($key, $draft)) {
                continue;
            }
            $this->{$key} = $draft[$key];
        }

        if ($this->boxes === []) {
            $this->boxes[] = ['box_number' => '', 'tracking_number' => ''];
        }

        $this->suppressCustomerConfirm = false;
        $this->suppressPriceNotice = false;
        $this->refreshCreditWarning();
    }

    public function shouldReturnToInvoiceList(): bool
    {
        return $this->from === 'invoices' || (bool) $this->salesOrder?->invoice;
    }

    protected function finishCreateWindowAndRedirect(?string $fallbackRoute = null): void
    {
        $fallbackRoute ??= $this->shouldReturnToInvoiceList() ? 'sales.invoices.index' : 'sales.orders.index';

        $windows = app(SalesOrderWindowManager::class);
        if ($this->createWindowId && $windows->has($this->createWindowId)) {
            $next = $windows->close($this->createWindowId);
            $this->createWindowId = null;
            if ($next !== null) {
                $this->redirect(route('sales.orders.create', ['w' => $next]), navigate: false);

                return;
            }
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->redirect(route($fallbackRoute), navigate: true);
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '',
            'qty_ordered' => '1', 'qty_shipped' => '', 'price' => '',
            'system_price' => '',
            'unit_discount' => '', 'discount' => '',
            'line_message' => '', 'instructions' => '',
        ];
    }

    /** Show empty input with placeholder 0 instead of a real zero value. */
    protected function blankZeroAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value) && abs((float) $value) < 0.0000001) {
            return '';
        }

        return is_string($value) ? $value : (string) $value;
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

    /** Money display/storage: always 2 decimal places (e.g. 9.99, never 9.990000). */
    protected function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_numeric($value)) {
            return '';
        }
        $n = round((float) $value, 2);
        if (abs($n) < 0.0000001) {
            return '';
        }

        return number_format($n, 2, '.', '');
    }

    public function selectLine(int $index): void
    {
        $this->selectedLineIndex = $index;
        $this->syncLineContextHeader($index);
        $this->refreshSelectedLineStock($index);
        // Do not clear price / customer confirm dialogs
        if (! $this->showMemorizePriceModal && ! $this->showCustomerConfirmModal && ! $this->showPriceBelowLimitModal) {
            $this->lineWarning = '';
        }
        // Do not re-open the message popup on every click — only when an item is added.
    }

    public function dismissLineMessageAlert(): void
    {
        $this->showLineMessageAlert = false;
        $this->orderLineMessagePopup = '';
        $this->orderLineInstructionsPopup = '';
        $this->lineWarning = '';
    }

    protected function dismissTransientOverlays(): void
    {
        $this->showLineMessageAlert = false;
        $this->orderLineMessagePopup = '';
        $this->orderLineInstructionsPopup = '';
        $this->showLineMessageModal = false;
        $this->showBrowse = false;
        $this->showBatchModal = false;
        $this->showLineSubstitutes = false;
    }

    protected function clearSelectedStock(): void
    {
        $this->selectedStockCode = '';
        $this->selectedStockOnHand = '';
        $this->selectedStockAllocated = '';
        $this->selectedStockAvailable = '';
        $this->selectedStockOrdered = '';
        $this->selectedStockRemaining = '';
    }

    protected function orderQtyDeltaForItem(int $itemId): float
    {
        if ($itemId <= 0) {
            return 0.0;
        }

        $current = 0.0;
        foreach ($this->lines as $line) {
            if ((int) ($line['item_id'] ?? 0) === $itemId) {
                $current += (float) ($line['qty_ordered'] ?? 0);
            }
        }

        $previous = 0.0;
        if ($this->salesOrder?->exists) {
            $this->salesOrder->loadMissing('lines');
            $previous = (float) $this->salesOrder->lines
                ->where('item_id', $itemId)
                ->sum('qty_ordered');
        }

        return $current - $previous;
    }

    protected function refreshSelectedLineStock(?int $index = null, ?Item $knownItem = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i]) || empty($this->lines[$i]['item_id'])) {
            $this->clearSelectedStock();

            return;
        }

        $itemId = (int) $this->lines[$i]['item_id'];
        $item = ($knownItem && (int) $knownItem->id === $itemId)
            ? $knownItem
            : Item::query()
                ->where('company_id', auth()->user()->company_id)
                ->find($itemId);

        if (! $item) {
            $this->clearSelectedStock();

            return;
        }

        $onHand = (float) $item->quantity_in_stock;
        $allocated = (float) $item->allocated_qty;
        $orderedOnOrder = 0.0;
        foreach ($this->lines as $line) {
            if ((int) ($line['item_id'] ?? 0) === $itemId) {
                $orderedOnOrder += (float) ($line['qty_ordered'] ?? 0);
            }
        }

        $delta = $this->orderQtyDeltaForItem($itemId);
        $invoiced = (bool) $this->salesOrder?->invoice;
        if ($invoiced) {
            $onHand -= $delta;
            $available = $onHand - $allocated;
        } else {
            $allocated += $delta;
            $available = $onHand - $allocated;
        }

        $this->selectedStockCode = (string) $item->item_code;
        $this->selectedStockOnHand = number_format($onHand, 0);
        $this->selectedStockAllocated = number_format($allocated, 0);
        $this->selectedStockAvailable = number_format($available, 0);
        $this->selectedStockOrdered = number_format($orderedOnOrder, 0);
        $this->selectedStockRemaining = number_format($available, 0);
    }

    protected function syncLineContextHeader(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            $this->lineMsgItemCode = '';
            $this->lineMsgDescription = '';

            return;
        }
        $this->lineMsgItemCode = (string) ($this->lines[$i]['item_code'] ?? '');
        $this->lineMsgDescription = (string) ($this->lines[$i]['description'] ?? '');
    }

    public function removeSelectedLine(): void
    {
        if ($this->selectedLineIndex === null) {
            return;
        }
        $this->removeLine($this->selectedLineIndex);
        $this->selectedLineIndex = null;
        $this->orderLineMessagePopup = '';
        $this->orderLineInstructionsPopup = '';
        $this->showLineMessageAlert = false;
        $this->syncLineContextHeader(null);
        $this->clearSelectedStock();
    }

    public function openLineSubstitutes(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        $this->selectedLineIndex = $i;
        $this->syncLineContextHeader($i);
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        if ($itemId <= 0) {
            $this->notifyAlert('Select a line with an item first.', 'warning');

            return;
        }

        $item = Item::query()
            ->with(['substitutes.substituteItem'])
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);

        if (! $item) {
            $this->notifyAlert('Item not found.', 'error');

            return;
        }

        $this->pendingLineIndex = $i;
        $this->substituteOptions = $item->substitutes
            ->filter(fn (ItemSubstitute $s) => $s->substituteItem)
            ->map(fn (ItemSubstitute $s) => [
                'id' => $s->substituteItem->id,
                'item_code' => $s->substituteItem->item_code,
                'description' => $s->substituteItem->description,
                'available' => (float) $s->substituteItem->available_quantity,
            ])
            ->values()
            ->all();
        $this->showLineSubstitutes = true;
    }

    public function closeLineSubstitutes(): void
    {
        $this->showLineSubstitutes = false;
        $this->substituteOptions = [];
    }

    public function applyLineSubstitute(int $substituteItemId): void
    {
        $i = $this->pendingLineIndex ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }

        $item = Item::query()->with(['prices', 'taxSchedule'])
            ->where('company_id', auth()->user()->company_id)
            ->find($substituteItemId);

        $this->showLineSubstitutes = false;
        $this->substituteOptions = [];

        if (! $item || ! $this->canAddItemToOrder($item)) {
            return;
        }

        $this->fillLineFromItem($i, $item);
        $this->syncLineContextHeader($i);
        $this->notifyAlert('Line replaced with substitute '.$item->item_code.'.', 'success');
    }

    public function openLineMessage(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        if (! filled($this->lines[$i]['item_code'] ?? null)) {
            $this->notifyAlert('Select a line with an item first.', 'warning');

            return;
        }

        $this->selectedLineIndex = $i;
        $this->syncLineContextHeader($i);
        $this->refreshSelectedLineStock($i);
        $this->lineMessageEdit = (string) ($this->lines[$i]['line_message'] ?? '');
        $this->lineInstructionsEdit = (string) ($this->lines[$i]['instructions'] ?? '');
        $this->showLineMessageAlert = false;
        $this->showLineMessageModal = true;
    }

    public function saveLineMessage(): void
    {
        $i = $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            $this->showLineMessageModal = false;

            return;
        }
        $this->lines[$i]['line_message'] = trim($this->lineMessageEdit);
        $this->lines[$i]['instructions'] = trim($this->lineInstructionsEdit);
        $this->showLineMessageModal = false;
        $this->showLineMessageAlert = false;
        $this->orderLineMessagePopup = '';
        $this->orderLineInstructionsPopup = '';
    }

    public function cancelLineMessage(): void
    {
        $this->showLineMessageModal = false;
        $this->lineMessageEdit = '';
        $this->lineInstructionsEdit = '';
    }

    protected function flushPendingLineMessageEdits(): void
    {
        if (! $this->showLineMessageModal || $this->selectedLineIndex === null) {
            return;
        }

        $i = $this->selectedLineIndex;
        if (! isset($this->lines[$i])) {
            return;
        }

        $this->lines[$i]['line_message'] = trim($this->lineMessageEdit);
        $this->lines[$i]['instructions'] = trim($this->lineInstructionsEdit);
        $this->showLineMessageModal = false;
    }

    protected function persistableLineMessage(array $line): ?string
    {
        $message = trim((string) ($line['line_message'] ?? ''));

        return $message !== '' ? $message : null;
    }

    public function openLineUom(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        $this->selectedLineIndex = $i;
        $this->syncLineContextHeader($i);
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        $options = [];
        if ($itemId > 0) {
            $item = Item::query()->with('prices')->find($itemId);
            if ($item) {
                if (filled($item->unit_of_measure)) {
                    $options[] = $item->unit_of_measure;
                }
                foreach ($item->prices as $p) {
                    if (filled($p->uom)) {
                        $options[] = $p->uom;
                    }
                }
            }
        }
        $current = (string) ($this->lines[$i]['uom'] ?? '');
        if ($current !== '') {
            $options[] = $current;
        }
        $this->lineUomOptions = array_values(array_unique(array_filter($options)));
        if ($this->lineUomOptions === []) {
            $this->lineUomOptions = ['EA', 'BX', 'CS', 'CTN', 'PK', 'RL'];
        }
        $this->showUomModal = true;
    }

    public function setLineUom(string $uom): void
    {
        $i = $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        $this->lines[$i]['uom'] = $uom;
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        if ($itemId > 0) {
            $item = Item::query()->with('prices')->find($itemId);
            if ($item) {
                $price = $this->formatMoney($this->resolveItemPrice($item, $uom));
                $match = $item->prices->first(fn ($p) => strcasecmp((string) $p->uom, $uom) === 0);
                if ($match) {
                    $price = $this->formatMoney((string) $match->price);
                }
                $this->lines[$i]['price'] = $price;
                $this->lines[$i]['system_price'] = $price;
            }
        }
        $this->syncLineContextHeader($i);
        $this->showUomModal = false;
        $this->taxManual = false;
        $this->suggestTax();
    }

    public function openBatchDetails(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        $this->selectedLineIndex = $i;
        $this->syncLineContextHeader($i);
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        $item = $itemId > 0
            ? Item::query()->with('batches')->where('company_id', auth()->user()->company_id)->find($itemId)
            : null;
        $this->batchRows = [];
        if (! $item) {
            $this->batchInfo = 'No item on this line.';
        } else {
            $tracking = $item->item_tracking ?: 'None';
            $this->batchInfo = 'Tracking: '.$tracking
                ."\nIn Stock: ".number_format((float) $item->quantity_in_stock, 2)
                ."\nAllocated: ".number_format((float) $item->allocated_qty, 2)
                ."\nAvailable: ".number_format((float) $item->available_quantity, 2)
                ."\nUOM: ".($item->unit_of_measure ?: '—');
            $this->batchRows = $item->batches->map(fn ($b) => [
                'batch_number' => (string) $b->batch_number,
                'tracking_type' => (string) $b->tracking_type,
                'quantity' => number_format((float) $b->quantity, 2),
                'expiry_date' => optional($b->expiry_date)?->format('Y-m-d') ?? '—',
                'received_at' => optional($b->received_at)?->format('Y-m-d') ?? '—',
                'notes' => (string) ($b->notes ?? ''),
            ])->values()->all();
        }
        $this->showBatchModal = true;
    }

    public function openItemRecord(?int $index = null): void
    {
        $i = $index ?? $this->selectedLineIndex;
        if ($i === null || ! isset($this->lines[$i])) {
            return;
        }
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        if ($itemId <= 0) {
            $this->notifyAlert('No item linked on this line.', 'warning');

            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($itemId);
        if (! $item) {
            $this->notifyAlert('Item not found.', 'error');

            return;
        }

        $this->dispatch('open-item-record', url: route('inventory.items.edit', $item));
    }

    public function enterEditMode(): mixed
    {
        $this->ensureSalesOrderModel();
        $order = $this->salesOrder;
        if (! $order instanceof SalesOrder || ! $order->exists) {
            $this->notifyAlert('Open a saved order first.', 'warning');
            $this->openOpenOrderModal();

            return null;
        }

        $this->viewMode = false;
        $url = route('sales.orders.edit', $order);
        if ($this->shouldReturnToInvoiceList()) {
            $url .= '?from=invoices';
        }

        return $this->redirect($url, navigate: false);
    }

    public function hydrate(): void
    {
        $this->viewMode = request()->routeIs('sales.orders.show');
        $this->ensureSalesOrderModel();
    }

    protected function ensureSalesOrderModel(): void
    {
        if ($this->salesOrder instanceof SalesOrder) {
            return;
        }

        $id = 0;
        if (is_numeric($this->salesOrder)) {
            $id = (int) $this->salesOrder;
        } elseif (is_string($this->salesOrder) && $this->salesOrder !== '') {
            $id = (int) $this->salesOrder;
        }

        if ($id < 1) {
            $routeOrder = request()->route('salesOrder');
            if ($routeOrder instanceof SalesOrder) {
                $this->salesOrder = $routeOrder;

                return;
            }
            if (is_numeric($routeOrder)) {
                $id = (int) $routeOrder;
            }
        }

        $this->salesOrder = $id > 0
            ? SalesOrder::query()->with(['invoice', 'customer'])->find($id)
            : null;
    }

    public function with(): array
    {
        $this->ensureSalesOrderModel();
        $companyId = auth()->user()->company_id;
        $filledLines = collect($this->lines)->filter(fn ($l) => filled($l['item_code'] ?? null));
        $subtotal = $filledLines->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        if ($this->pendingTradePercent > 0 && $subtotal > 0) {
            $this->trade_discount = $this->formatMoney(number_format($subtotal * ($this->pendingTradePercent / 100), 2, '.', ''));
        }
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        $onGeneral = $this->activeTab === 'general';
        $onShipping = $this->activeTab === 'shipping';
        $needShipCustomer = $onGeneral || $this->showShipBrowse || $this->showShipToModal;

        $browseCustomers = collect();
        if ($this->showCustomerBrowse) {
            $browseCustomers = Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->when($this->customerFavoritesOnly, fn ($q) => $q->where('is_favorite', true))
                ->when(filled($this->customerSearch), function ($q) {
                    $term = '%'.$this->customerSearch.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term);
                    });
                })
                ->orderByDesc('is_favorite')
                ->orderBy('company_name')
                ->limit(80)
                ->get(['id', 'customer_id', 'company_name', 'contact', 'telephone', 'city', 'state', 'is_favorite']);
        }

        // Only hydrate full customer list on General (not on F2 / Items tab).
        $customers = collect();
        if ($onGeneral) {
            $customers = Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->when($this->customerFavoritesOnly, fn ($q) => $q->where('is_favorite', true))
                ->orderByDesc('is_favorite')
                ->orderBy('company_name')
                ->limit(80)
                ->get(['id', 'customer_id', 'company_name', 'is_favorite']);

            if ($this->customer_id && $customers->every(fn ($c) => (int) $c->id !== (int) $this->customer_id)) {
                $selectedOpt = Customer::query()->find($this->customer_id, ['id', 'customer_id', 'company_name', 'is_favorite']);
                if ($selectedOpt) {
                    $customers = $customers->prepend($selectedOpt);
                }
            }
        }

        $selectedCustomer = null;
        if ($this->customer_id && $needShipCustomer) {
            $selectedCustomer = Customer::query()
                ->with('shippingAddresses')
                ->find($this->customer_id);
        } elseif ($this->customer_id) {
            $selectedCustomer = Customer::query()
                ->find($this->customer_id, ['id', 'customer_id', 'company_name', 'is_favorite']);
        }

        return [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'selectedCustomerIsFavorite' => (bool) ($selectedCustomer?->is_favorite),
            'pageTitle' => $this->viewMode
                ? 'View Sales Order — '.($this->order_number ?: '—')
                : ($this->salesOrder?->exists
                    ? (($this->salesOrder->invoice)
                        ? 'Edit Invoice / Order — '.$this->order_number
                        : 'Edit Sales Order — '.$this->order_number)
                    : 'New Sales Order'),
            'returnToInvoiceList' => $this->shouldReturnToInvoiceList(),
            'salesReps' => $onGeneral
                ? collect(Cache::remember(
                    'lookups.sales_reps.v2.'.$companyId.'.'.(int) $this->sales_rep_id,
                    120,
                    fn () => User::assignableSalesRepsQuery($companyId, $this->sales_rep_id)
                        ->get(['id', 'name'])
                        ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                        ->values()
                        ->all()
                ))->filter(fn ($r) => is_array($r) && isset($r['id']))
                : collect(),
            'paymentTerms' => $onShipping
                ? collect(Cache::remember(
                    'lookups.payment_terms.v2.'.$companyId,
                    180,
                    fn () => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])
                        ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                        ->values()
                        ->all()
                ))->filter(fn ($r) => is_array($r) && isset($r['id']))
                : collect(),
            'routes' => $onShipping
                ? collect(Cache::remember(
                    'lookups.routes.v2.'.$companyId,
                    180,
                    fn () => RouteLookup::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])
                        ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                        ->values()
                        ->all()
                ))->filter(fn ($r) => is_array($r) && isset($r['id']))
                : collect(),
            'shipVias' => $onShipping
                ? collect(Cache::remember(
                    'lookups.ship_vias.v2.'.$companyId,
                    180,
                    fn () => ShipVia::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])
                        ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                        ->values()
                        ->all()
                ))->filter(fn ($r) => is_array($r) && isset($r['id']))
                : collect(),
            'sites' => $onShipping
                ? collect(Cache::remember(
                    'lookups.sites.v2.'.$companyId,
                    180,
                    fn () => Site::query()->where('company_id', $companyId)->orderBy('code')->get(['id', 'code'])
                        ->map(fn ($r) => ['id' => (int) $r->id, 'code' => (string) $r->code])
                        ->values()
                        ->all()
                ))->filter(fn ($r) => is_array($r) && isset($r['id']))
                : collect(),
            'browseItems' => collect($this->browseRows)->map(function (array $row) {
                $id = (int) ($row['id'] ?? 0);
                $delta = $this->orderQtyDeltaForItem($id);
                $onHand = (float) ($row['on_hand'] ?? 0);
                $available = (float) ($row['available'] ?? 0);
                $invoiced = (bool) $this->salesOrder?->invoice;
                if ($invoiced) {
                    $onHand -= $delta;
                    $row['on_hand'] = $onHand;
                    $row['available'] = $onHand;
                } else {
                    $row['available'] = $available - $delta;
                }

                return $row;
            }),
            'browseCustomers' => $browseCustomers,
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
            'subtotal' => $subtotal,
            'orderTotal' => $total,
            'totalLines' => $filledLines->count(),
            'totalItems' => $filledLines->count(),
            'totalQty' => $filledLines->sum(fn ($l) => (float) $l['qty_ordered']),
            'totalShipped' => $filledLines->sum(fn ($l) => (float) ($l['qty_shipped'] ?? 0)),
            'totalDiscounts' => $filledLines->sum(fn ($l) => (float) $l['discount']),
            'totalAllowances' => 0,
            'hasLines' => $filledLines->isNotEmpty(),
            'parkedCount' => app(ParkedSaleService::class)->listFor(auth()->user())->count(),
            'taxSchedules' => TaxSchedule::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('rate')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'rate']),
            'canChangePrice' => $this->userCanChangeOrderPrice(),
            'itemNewDays' => defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30,
            'oversellingOn' => StockPolicy::allowsNegativeStock(),
            'favorites' => [
                'not_invoiced' => 'Not Invoiced',
                'all' => 'All Open Orders',
                'new' => 'New Orders',
                'invoiced' => 'Invoices',
                'month' => 'This Month',
                'today' => 'Today & Yesterday',
            ],
        ];
    }

    /**
     * Progressive F2 list — fast open (first page), scroll loads the rest.
     */
    protected const BROWSE_PAGE_SIZE = 80;

    public function updatedBrowseSearch(): void
    {
        if (! $this->showBrowse) {
            return;
        }

        if ($this->autoAddBrowseIfExactMatch()) {
            return;
        }

        $this->resetBrowseAndLoadFirstPage();
    }

    /**
     * When the browse search box is a complete unique item/UPC code, add that item to the cart.
     */
    protected function autoAddBrowseIfExactMatch(): bool
    {
        if ($this->viewMode) {
            return false;
        }

        $code = trim($this->browseSearch);
        if ($code === '' || mb_strlen($code) < 2) {
            return false;
        }

        $item = $this->findItem($code);
        if (! $item || $this->codeIsPrefixOfLongerItemCode($code)) {
            return false;
        }

        $this->browseSearch = '';
        $this->lineWarning = '';
        $this->pickBrowseItem((int) $item->id, true);
        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch();

        return true;
    }

    public function updatedBrowseNewOnly(): void
    {
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function updatedBrowseCategoryId(): void
    {
        $this->browseSubcategoryId = null;
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    public function updatedBrowseSubcategoryId(): void
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
        $this->skipRender();
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
        if ($count === 0) {
            $this->browseSelectedId = null;
        } elseif ($count === 1) {
            $this->browseSelectedId = (int) $this->browseCheckedIds[0];
        } else {
            $this->browseSelectedId = null;
        }
    }

    public function updatedBrowseCheckedIds(): void
    {
        // Keep string ids so Livewire checkbox wire:model stays in sync with value="".
        $this->browseCheckedIds = collect($this->browseCheckedIds)
            ->map(fn ($v) => (string) (int) $v)
            ->filter(fn (string $v) => $v !== '' && $v !== '0')
            ->unique()
            ->values()
            ->all();

        $count = count($this->browseCheckedIds);
        if ($count === 0) {
            return;
        }

        if ($count === 1) {
            $this->browseSelectedId = (int) $this->browseCheckedIds[0];

            return;
        }

        // Multi-select: clear single-row focus so Edit stays inactive.
        $this->browseSelectedId = null;
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

        if (count($ids) === 1) {
            $this->browseSelectedId = (int) $ids[0];
        } else {
            $this->browseSelectedId = null;
        }
    }

    public function clearBrowseChecked(): void
    {
        $this->browseCheckedIds = [];
    }

    public function insertBrowseSelected(): void
    {
        if (! $this->browseHasSingleSelection()) {
            $this->notifyAlert(
                count($this->browseCheckedIds) > 1
                    ? 'Multiple items checked — use Insert All Checked, or uncheck to a single item.'
                    : 'Select one item first.',
                'warning'
            );

            return;
        }

        $id = $this->resolveBrowseTargetId();
        if ($id === null) {
            $this->notifyAlert('Select an item first.', 'warning');

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

        $companyId = (int) auth()->user()->company_id;
        $added = 0;
        $deferredSubstitute = null;

        foreach ($ids as $itemId) {
            $item = Item::query()
                ->with(['prices', 'taxSchedule'])
                ->where('company_id', $companyId)
                ->find($itemId);
            if (! $item) {
                continue;
            }
            if ($this->shouldPromptForceSubstitute($item)) {
                if ($deferredSubstitute === null) {
                    $deferredSubstitute = $item;
                }

                continue;
            }
            if ($this->canAddItemToOrder($item)) {
                $this->appendItemLine($item);
                $added++;
            }
        }

        $this->itemEntry = '';
        $this->browseSelectedId = null;
        $this->browseCheckedIds = [];
        $this->browseChecksVersion++;
        $this->dispatch('browse-checks-cleared');
        $this->js('window.dispatchEvent(new CustomEvent("browse-checks-cleared"))');

        if ($deferredSubstitute !== null && $added === 0 && count($ids) === 1) {
            $this->queueItemOrPromptSubstitute($deferredSubstitute);

            return;
        }

        if ($added === 0) {
            $this->notifyAlert('No checked items could be added to this order.', 'error');

            return;
        }

        if ($deferredSubstitute !== null) {
            $this->notifyAlert($added.' item(s) added. Some out-of-stock items were skipped (need substitute).', 'warning');
        }

        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
            $this->focusBrowseSearch();
        }
    }

    public function openBrowseNewItem(): void
    {
        if (! Route::has('inventory.items.create')) {
            $this->notifyAlert('Item create screen is not available.', 'error');

            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.create'));
    }

    public function openBrowseEditSelected(?int $itemId = null): void
    {
        if ($itemId && $itemId > 0) {
            $this->browseSelectedId = $itemId;
            $this->browseCheckedIds = [(string) $itemId];
        }

        if (! $this->browseHasSingleSelection()) {
            $this->notifyAlert(
                count($this->browseCheckedIds) > 1
                    ? 'Select only one item to edit.'
                    : 'Select an item to edit.',
                'warning'
            );

            return;
        }

        $id = $this->resolveBrowseTargetId();
        if ($id === null) {
            $this->notifyAlert('Select an item to edit.', 'warning');

            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($id);
        if (! $item) {
            $this->notifyAlert('Item not found.', 'error');

            return;
        }
        if (! Route::has('inventory.items.edit')) {
            $this->notifyAlert('Item edit screen is not available.', 'error');

            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.edit', $item));
    }

    /** Edit / Insert Selected only when exactly one item is the target. */
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

    public function toggleBrowse(): void
    {
        $this->showBrowse = ! $this->showBrowse;
        $this->showCustomerBrowse = false;
        $this->showShipBrowse = false;
        if ($this->showBrowse) {
            $this->browseSearch = trim($this->itemEntry);
            $this->activeTab = 'items';
            $this->resetBrowseAndLoadFirstPage();
        } else {
            $this->clearBrowseState();
        }
    }

    /**
     * Open Browse item list filtered by typed/scanned search text.
     */
    public function openBrowseForSearch(?string $term = null): void
    {
        abort_if($this->viewMode, 403);

        $term = trim($term ?? $this->itemEntry);
        $this->browseSearch = $term;
        $this->showBrowse = true;
        $this->showCustomerBrowse = false;
        $this->showShipBrowse = false;
        $this->activeTab = 'items';
        $this->resetBrowseAndLoadFirstPage();
    }

    public function closeBrowse(): void
    {
        $this->showBrowse = false;
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

    protected function notifyAlert(string $message, string $kind = 'error'): void
    {
        $this->lineWarning = $message;
        $this->lineWarningKind = $kind;
        $this->playPosSound($kind);
        $this->js('window.scheduleSoBannerDismiss && window.scheduleSoBannerDismiss("line")');
    }

    public function dismissLineWarning(): void
    {
        $this->lineWarning = '';
    }

    public function dismissFormErrors(): void
    {
        $this->resetErrorBag();
    }

    protected function playPosSound(string $kind = 'error'): void
    {
        $this->dispatch('pos-alert', kind: $kind);
        $this->js('window.playPosAlert && window.playPosAlert('.json_encode($kind).')');
    }

    protected function alertUnknownScan(string $code): void
    {
        $this->unknownScanCode = $code;
        $this->showUnknownScanModal = true;
        $this->scanModeActive = false;
        $this->itemEntry = $code;
        $this->browseSearch = $code;
        $this->lineWarning = '';
        $this->js('window.startPosScanMissAlarm && window.startPosScanMissAlarm()');
    }

    public function acknowledgeUnknownScan(): void
    {
        $this->showUnknownScanModal = false;
        $this->unknownScanCode = '';
        $this->itemEntry = '';
        $this->browseSearch = '';
        $this->js('window.stopPosScanMissAlarm && window.stopPosScanMissAlarm()');
        if ($this->showBrowse) {
            $this->focusBrowseSearch();

            return;
        }
        $this->scanModeActive = true;
        $this->clearAndFocusEntry();
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
                'quantity_in_stock',
                'allocated_qty',
                'created_at',
            ]);

        $mapped = $rows->map(function ($row) use ($newSince) {
            $created = $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at) : null;

            return [
                'id' => (int) $row->id,
                'item_code' => (string) $row->item_code,
                'description' => $row->description,
                'unit_of_measure' => $row->unit_of_measure,
                'list_price' => $row->list_price,
                'on_hand' => (float) $row->quantity_in_stock,
                'available' => (float) $row->quantity_in_stock - (float) $row->allocated_qty,
                'is_new' => $created !== null && $created->gte($newSince),
            ];
        })->all();

        $this->browseRows = array_values(array_merge($this->browseRows, $mapped));
        $this->browseHasMore = count($this->browseRows) < $this->browseTotal;
    }

    /**
     * @param  list<int>  $itemIds
     */
    protected function refreshBrowseStockFromDatabase(array $itemIds): void
    {
        $ids = array_values(array_unique(array_filter($itemIds)));
        if ($ids === [] || $this->browseRows === []) {
            return;
        }

        $fresh = Item::query()
            ->whereIn('id', $ids)
            ->get(['id', 'quantity_in_stock', 'allocated_qty'])
            ->keyBy('id');

        foreach ($this->browseRows as $i => $row) {
            $item = $fresh->get((int) ($row['id'] ?? 0));
            if (! $item) {
                continue;
            }
            $this->browseRows[$i]['on_hand'] = (float) $item->quantity_in_stock;
            $this->browseRows[$i]['available'] = (float) $item->quantity_in_stock - (float) $item->allocated_qty;
        }
    }

    /**
     * Shared filters for F2 browse (no ORDER / LIMIT).
     */
    protected function browseBaseQuery(int $companyId)
    {
        $newDays = defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30;
        $newSince = now()->subDays($newDays);

        return DB::table('items')
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->when($this->browseNewOnly, fn ($q) => $q->where('created_at', '>=', $newSince))
            ->when($this->browseQtyLtZero, fn ($q) => $q->where('quantity_in_stock', '<', 0))
            ->when($this->browseCategoryId, fn ($q) => $q->where('category_id', $this->browseCategoryId))
            ->when($this->browseSubcategoryId, fn ($q) => $q->where('subcategory_id', $this->browseSubcategoryId))
            ->when(filled($this->browseSearch), fn ($q) => ItemSearch::constrain($q, $this->browseSearch));
    }

    /**
     * Browse search / scanner: exact barcode/code match adds the item; otherwise filters the list.
     */
    public function scanBrowseAndPick(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        if ($this->showUnknownScanModal) {
            return;
        }

        if ($code !== null) {
            $this->browseSearch = trim($code);
        }

        $resolved = trim($this->browseSearch);
        if ($resolved === '') {
            $this->focusBrowseSearch();

            return;
        }

        $item = $this->findItem($resolved);
        if ($item) {
            $this->browseSearch = '';
            $this->pickBrowseItem((int) $item->id, true);
            $this->focusItemEntry();

            return;
        }

        $this->alertUnknownScan($resolved);
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

    protected function focusBrowseSearch(bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-browse-search"); if (el) { el.focus();'.$selectJs.' } });');
    }

    public function toggleCustomerFavoriteIcon(): void
    {
        if ($this->viewMode) {
            return;
        }

        // With a customer selected: mark/unmark that customer as favorite.
        if ($this->customer_id) {
            $customer = Customer::query()
                ->where('company_id', auth()->user()->company_id)
                ->find($this->customer_id);
            if (! $customer) {
                $this->notifyAlert('Customer not found.', 'error');

                return;
            }
            $customer->is_favorite = ! (bool) $customer->is_favorite;
            $customer->save();
            $this->notifyAlert(
                $customer->is_favorite
                    ? $customer->customer_id.' added to favorites.'
                    : $customer->customer_id.' removed from favorites.',
                'success'
            );

            return;
        }

        // No customer selected: toggle favorites-only filter on the dropdown/browse list.
        $this->customerFavoritesOnly = ! $this->customerFavoritesOnly;
        $this->notifyAlert(
            $this->customerFavoritesOnly
                ? 'Showing favorite customers only. Click the star again to show all.'
                : 'Showing all customers.',
            'info'
        );
    }

    public function toggleBrowseCustomerFavorite(int $customerId): void
    {
        if ($this->viewMode) {
            return;
        }
        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($customerId);
        if (! $customer) {
            return;
        }
        $customer->is_favorite = ! (bool) $customer->is_favorite;
        $customer->save();
    }

    public function updatedFavorite(string $value): void
    {
        if ($value === 'invoiced') {
            $this->redirect(route('sales.invoices.index'), navigate: true);

            return;
        }

        $this->redirect(route('sales.orders.index', ['favorite' => $value]), navigate: true);
    }

    public function updatedCustomerId($value): void
    {
        $newId = $value !== null && $value !== '' ? (int) $value : null;

        if (! $newId) {
            $this->customerAlert = '';
            $this->creditWarning = '';
            $this->taxExemptWarning = '';
            $this->customerPriceLevelId = null;
            $this->itemTaxRateCache = [];
            $this->showCustomerConfirmModal = false;
            $this->customerConfirmLabel = '';

            return;
        }

        $customer = Customer::query()->with('shippingAddresses')->find($newId);
        if (! $customer) {
            $this->customerAlert = '';
            $this->creditWarning = '';
            $this->taxExemptWarning = '';

            return;
        }

        // Ask for confirmation on intentional customer changes (prevent wrong customer)
        $needConfirm = ! $this->suppressCustomerConfirm
            && ! $this->viewMode
            && $newId !== $this->confirmedCustomerId;

        if ($needConfirm) {
            $this->previousCustomerId = $this->confirmedCustomerId;
            $this->customerConfirmLabel = trim(
                (string) ($customer->customer_id ?: '')
                .(filled($customer->company_name) ? ' — '.$customer->company_name : '')
            );
            $this->showCustomerConfirmModal = true;
            $this->playPosSound('warning');
        }

        $this->applySelectedCustomer($customer);
    }

    protected function applySelectedCustomer(Customer $customer): void
    {
        $this->customerAlert = $customer->messages_alerts ?? '';
        $this->customerPriceLevelId = $customer->price_level_id ? (int) $customer->price_level_id : null;

        // Bill To always from customer master
        $this->bill_to_name = trim((string) ($customer->company_name ?: $customer->contact)) ?: '';
        $this->bill_to_phone = trim((string) ($customer->telephone ?: $customer->mobile ?: $customer->telephone2)) ?: '';
        $this->bill_to_address = trim((string) ($customer->address ?? '')) ?: '';
        $this->bill_to_city = trim((string) ($customer->city ?? '')) ?: '';
        $this->bill_to_state = trim((string) ($customer->state ?? '')) ?: '';
        $this->bill_to_zip = trim((string) ($customer->zip_code ?? '')) ?: '';

        $this->payment_term_id = $customer->payment_term_id;
        $this->sales_rep_id = $customer->sales_rep_id ?: $this->sales_rep_id;
        $this->route_id = $customer->delivery_route_id;

        if ($customer->discount_schedule_id) {
            $schedule = \App\Models\DiscountSchedule::query()->find($customer->discount_schedule_id);
            if ($schedule && (float) $schedule->percent > 0) {
                $this->trade_discount = '';
                $this->pendingTradePercent = (float) $schedule->percent;
            }
        }

        $this->refreshTaxExemptWarning($customer);

        // Ship To: primary saved ship-to, else first, else copy Bill To / customer billing
        $this->fillShipToFromCustomer($customer);
        $this->addressTab = 'ship';

        $this->showCustomerBrowse = false;
        $this->showShipToModal = false;
        $this->shipToFlash = '';
        $this->refreshCreditWarning();
        $this->suggestTax();
        $this->repriceLinesForCustomer();
    }

    /**
     * Auto-fill Ship To when a customer is selected (shipping address or billing fallback).
     */
    protected function fillShipToFromCustomer(Customer $customer): void
    {
        $addresses = $customer->relationLoaded('shippingAddresses')
            ? $customer->shippingAddresses
            : $customer->shippingAddresses()->orderByDesc('is_primary')->orderBy('sort_order')->get();

        $ship = $addresses->firstWhere('is_primary', true) ?? $addresses->first();

        if ($ship) {
            $this->ship_to_address_id = (int) $ship->id;
            $this->applyShipAddress($ship);
            // Backfill any blank ship fields from bill / customer master
            if (trim($this->ship_to_name) === '') {
                $this->ship_to_name = $this->bill_to_name;
            }
            if (trim($this->ship_to_phone) === '') {
                $this->ship_to_phone = $this->bill_to_phone;
            }
            if (trim($this->ship_to_address) === '') {
                $this->ship_to_address = $this->bill_to_address;
            }
            if (trim($this->ship_to_city) === '') {
                $this->ship_to_city = $this->bill_to_city;
            }
            if (trim($this->ship_to_state) === '') {
                $this->ship_to_state = $this->bill_to_state;
            }
            if (trim($this->ship_to_zip) === '') {
                $this->ship_to_zip = $this->bill_to_zip;
            }
        } else {
            $this->ship_to_address_id = null;
            $this->ship_to_name = $this->bill_to_name;
            $this->ship_to_phone = $this->bill_to_phone;
            $this->ship_to_address = $this->bill_to_address;
            $this->ship_to_city = $this->bill_to_city;
            $this->ship_to_state = $this->bill_to_state;
            $this->ship_to_zip = $this->bill_to_zip;
        }
    }

    protected function applyShipAddress($ship): void
    {
        $this->ship_to_name = trim((string) ($ship->name ?? '')) ?: '';
        $this->ship_to_phone = trim((string) ($ship->telephone ?? '')) ?: '';
        $this->ship_to_address = trim((string) ($ship->address ?? '')) ?: '';
        $this->ship_to_city = trim((string) ($ship->city ?? '')) ?: '';
        $this->ship_to_state = trim((string) ($ship->state ?? '')) ?: '';
        $this->ship_to_zip = trim((string) ($ship->zip ?? $ship->zip_code ?? '')) ?: '';
    }

    public function confirmCustomerSelection(): void
    {
        $this->confirmedCustomerId = $this->customer_id ? (int) $this->customer_id : null;
        $this->previousCustomerId = null;
        $this->showCustomerConfirmModal = false;
        $this->customerConfirmLabel = '';
    }

    public function rejectCustomerSelection(): void
    {
        $restoreId = $this->previousCustomerId;
        $this->showCustomerConfirmModal = false;
        $this->customerConfirmLabel = '';
        $this->previousCustomerId = null;

        $this->suppressCustomerConfirm = true;
        if ($restoreId) {
            $this->customer_id = $restoreId;
            $this->updatedCustomerId($restoreId);
            $this->confirmedCustomerId = $restoreId;
        } else {
            $companyId = (int) auth()->user()->company_id;
            $walkIn = $this->resolveWalkInCustomer($companyId);
            $this->customer_id = $walkIn->id;
            $this->updatedCustomerId($walkIn->id);
            $this->confirmedCustomerId = (int) $walkIn->id;
        }
        $this->suppressCustomerConfirm = false;
    }

    protected function repriceLinesForCustomer(): void
    {
        $this->suppressPriceNotice = true;
        foreach ($this->lines as $i => $line) {
            if (empty($line['item_id'])) {
                continue;
            }
            $item = Item::query()->with('prices')->find($line['item_id']);
            if ($item) {
                $price = $this->formatMoney($this->resolveItemPrice($item));
                $this->lines[$i]['price'] = $price;
                $this->lines[$i]['system_price'] = $price;
            }
        }
        $this->suppressPriceNotice = false;
    }

    public function updatedShipToAddressId($value): void
    {
        if (! $value || ! $this->customer_id) {
            return;
        }
        $customer = Customer::query()->with('shippingAddresses')->find($this->customer_id);
        $ship = $customer?->shippingAddresses->firstWhere('id', (int) $value);
        if ($ship) {
            $this->applyShipAddress($ship);
            $this->addressTab = 'ship';
            $this->showShipBrowse = false;
        }
    }

    public function updatedTradeDiscount(): void
    {
        $this->pendingTradePercent = 0;
        $this->refreshCreditWarning();
        $this->suggestTax();
    }

    public function updatedFreight(): void
    {
        $this->refreshCreditWarning();
    }

    public function updatedMiscellaneous(): void
    {
        $this->refreshCreditWarning();
    }

    public function markTaxManual(): void
    {
        $this->taxManual = true;
        $this->orderTaxScheduleId = null;
        $this->refreshCreditWarning();
    }

    public function updatedOrderTaxScheduleId($value): void
    {
        $this->taxManual = false;
        $this->orderTaxScheduleId = $value !== null && $value !== '' ? (int) $value : null;
        $this->suggestTax();
        $this->refreshCreditWarning();
    }

    public function toggleNewTaxSchedule(): void
    {
        $this->showNewTaxSchedule = ! $this->showNewTaxSchedule;
        $this->resetErrorBag(['newTaxRate', 'newTaxName', 'newTaxCode']);
        if ($this->showNewTaxSchedule && $this->newTaxRate === '') {
            $this->newTaxRate = '6';
            $this->newTaxName = '6% Sales Tax';
            $this->newTaxCode = 'T6';
        }
    }

    public function updatedNewTaxRate($value): void
    {
        $rate = (float) str_replace(['%', ','], '', (string) $value);
        if ($rate < 0) {
            $rate = 0;
        }
        $label = rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');
        if ($this->newTaxName === '' || preg_match('/^\d/', $this->newTaxName) || str_ends_with($this->newTaxName, 'Sales Tax')) {
            $this->newTaxName = $label.'% Sales Tax';
        }
        $codeRate = str_replace('.', '_', $label);
        if ($this->newTaxCode === '' || str_starts_with($this->newTaxCode, 'T')) {
            $this->newTaxCode = 'T'.$codeRate;
        }
    }

    public function saveNewTaxSchedule(): void
    {
        if ($this->viewMode) {
            return;
        }
        $this->validate([
            'newTaxRate' => 'required|numeric|min:0|max:100',
            'newTaxName' => 'required|string|max:255',
            'newTaxCode' => 'required|string|max:32',
        ], [], [
            'newTaxRate' => 'tax %',
            'newTaxName' => 'schedule name',
            'newTaxCode' => 'code',
        ]);

        $companyId = (int) auth()->user()->company_id;
        $code = strtoupper(trim($this->newTaxCode));
        $exists = TaxSchedule::query()->where('company_id', $companyId)->where('code', $code)->exists();
        if ($exists) {
            $this->addError('newTaxCode', 'This tax code already exists in Tax Schedules.');

            return;
        }

        $row = TaxSchedule::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => trim($this->newTaxName),
            'rate' => round((float) $this->newTaxRate, 4),
            'is_active' => true,
        ]);

        $this->orderTaxScheduleId = $row->id;
        $this->taxManual = false;
        $this->showNewTaxSchedule = false;
        $this->newTaxRate = '';
        $this->newTaxName = '';
        $this->newTaxCode = '';
        $this->suggestTax();
        $this->refreshCreditWarning();
        session()->flash('status', 'Tax schedule '.$row->code.' ('.$row->rate.'%) saved. Same list as Lookups → Tax Schedules.');
    }

    public function updatedLines($value = null, $key = null): void
    {
        if (is_string($key) && preg_match('/^(\d+)\.(qty_ordered|unit_discount)$/', $key, $m)) {
            $i = (int) $m[1];
            if ($m[2] === 'qty_ordered' && isset($this->lines[$i])) {
                $raw = trim((string) ($this->lines[$i]['qty_ordered'] ?? ''));
                if ($raw !== '' && is_numeric($raw) && ! str_ends_with($raw, '.')) {
                    $this->lines[$i]['qty_ordered'] = $this->formatQty($raw);
                }
            }
            $this->recalcLineDiscount($i);
        }
        if (is_string($key) && (str_ends_with($key, 'qty_ordered') || str_ends_with($key, 'item_id'))) {
            $this->refreshSelectedLineStock();
        }
        if (is_string($key) && preg_match('/^(\d+)\.price$/', $key, $m)) {
            $i = (int) $m[1];
            if (! $this->userCanChangeOrderPrice()) {
                $this->restoreLineSystemPrice($i);
            } else {
                if (isset($this->lines[$i])) {
                    $this->lines[$i]['price'] = $this->formatMoney($this->lines[$i]['price'] ?? '');
                }
                $this->considerPriceChange($i);
            }
        }
        $this->refreshCreditWarning();
        $this->suggestTax();
    }

    public function adjustLineQty(int $index, int $delta): void
    {
        $this->nudgeLineField($index, 'qty_ordered', (float) $delta);
    }

    public function nudgeLineField(int $index, string $field, float $delta): void
    {
        if ($this->viewMode || ! isset($this->lines[$index])) {
            return;
        }
        if (! in_array($field, ['qty_ordered', 'price', 'unit_discount'], true)) {
            return;
        }
        if ($field === 'price' && ! $this->userCanChangeOrderPrice()) {
            return;
        }
        if (! in_array($field, ['price'], true) && ! filled($this->lines[$index]['item_code'] ?? null)) {
            return;
        }

        $current = (float) ($this->lines[$index][$field] ?? 0);
        $next = max(0, round($current + $delta, 4));
        if (in_array($field, ['qty_ordered'], true)) {
            $this->lines[$index][$field] = $this->formatQty($next);
            if ($field === 'qty_ordered') {
                $this->recalcLineDiscount($index);
            }
        } elseif ($field === 'unit_discount') {
            $this->lines[$index][$field] = $this->blankZeroAmount((string) (int) round($next));
            $this->recalcLineDiscount($index);
        } else {
            $this->lines[$index][$field] = $this->formatMoney($next);
            if ($field === 'price') {
                $this->considerPriceChange($index);
            }
        }

        $this->selectedLineIndex = $index;
        $this->syncLineContextHeader($index);
        if ($field === 'qty_ordered') {
            $this->taxManual = false;
            $this->refreshSelectedLineStock($index);
        }
        $this->refreshCreditWarning();
        $this->suggestTax();
    }

    /** Admin always can; other users only when Change Order Price (edit) is granted to them. */
    protected function userCanChangeOrderPrice(): bool
    {
        return auth()->user()?->canAccessFeature('sales.price_override', 'edit') ?? false;
    }

    protected function restoreLineSystemPrice(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $restore = $this->lines[$index]['system_price'] ?? '';
        $this->lines[$index]['price'] = $restore !== ''
            ? $this->formatMoney($restore)
            : $this->formatMoney($this->lines[$index]['price'] ?? '');
    }

    /**
     * After unit price changes: check allowed limit first, then optional memorize dialog.
     */
    public function considerPriceChange(int $index): void
    {
        if ($this->viewMode || $this->suppressPriceNotice) {
            return;
        }
        if (! $this->userCanChangeOrderPrice()) {
            $this->restoreLineSystemPrice($index);

            return;
        }
        if ($this->showMemorizePriceModal || $this->showPriceBelowLimitModal) {
            return;
        }
        if (! isset($this->lines[$index])) {
            return;
        }
        if (! filled($this->lines[$index]['item_code'] ?? null) || empty($this->lines[$index]['item_id'])) {
            return;
        }

        $priceRaw = $this->lines[$index]['price'] ?? '';
        if ($priceRaw === '' || ! is_numeric($priceRaw)) {
            return;
        }

        $newPrice = round((float) $priceRaw, 2);
        $this->lines[$index]['price'] = $this->formatMoney($newPrice);

        $systemRaw = $this->lines[$index]['system_price'] ?? null;
        if ($systemRaw === null || $systemRaw === '') {
            $this->lines[$index]['system_price'] = $this->formatMoney($newPrice);

            return;
        }

        $systemPrice = round((float) $systemRaw, 2);
        if (abs($newPrice - $systemPrice) < 0.005) {
            return;
        }

        $this->priceBelowLimitLineIndex = $index;
        $this->memorizeLineIndex = $index;
        $this->memorizePriceValue = $this->formatMoney($newPrice);

        // Below cost / allowed floor → Chief warning first
        $floor = $this->itemAllowedPriceFloor((int) $this->lines[$index]['item_id']);
        if ($floor > 0 && $newPrice + 0.0001 < $floor) {
            $this->showPriceBelowLimitModal = true;
            $this->playPosSound('warning');

            return;
        }

        $this->openMemorizePriceIfPossible($index, $newPrice);
    }

    /**
     * Lowest allowed sell price for an item (highest known cost). 0 = no limit.
     */
    protected function itemAllowedPriceFloor(int $itemId): float
    {
        if ($itemId <= 0) {
            return 0.0;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId, ['id', 'current_cost', 'standard_cost', 'last_cost', 'average_cost']);

        if (! $item) {
            return 0.0;
        }

        $costs = [
            (float) ($item->current_cost ?? 0),
            (float) ($item->standard_cost ?? 0),
            (float) ($item->last_cost ?? 0),
            (float) ($item->average_cost ?? 0),
        ];

        $floor = 0.0;
        foreach ($costs as $c) {
            if ($c > $floor) {
                $floor = $c;
            }
        }

        return round($floor, 2);
    }

    protected function openMemorizePriceIfPossible(int $index, float $newPrice): void
    {
        if (! $this->customer_id || $this->suppressPriceNotice) {
            // No customer memorize — just lock baseline for this order
            $this->lines[$index]['system_price'] = $this->formatMoney($newPrice);

            return;
        }

        $this->memorizeLineIndex = $index;
        $this->memorizePriceValue = $this->formatMoney($newPrice);
        $this->showMemorizePriceModal = true;
        $this->playPosSound('warning');
    }

    public function confirmPriceBelowLimit(): void
    {
        $index = $this->priceBelowLimitLineIndex ?? $this->memorizeLineIndex;
        $this->showPriceBelowLimitModal = false;
        $this->priceBelowLimitLineIndex = null;

        if (! $this->userCanChangeOrderPrice()) {
            if ($index !== null) {
                $this->restoreLineSystemPrice($index);
            }

            return;
        }

        if ($index === null || ! isset($this->lines[$index])) {
            return;
        }

        $priceRaw = $this->lines[$index]['price'] ?? $this->memorizePriceValue;
        $newPrice = is_numeric($priceRaw) ? round((float) $priceRaw, 2) : 0.0;
        $this->openMemorizePriceIfPossible($index, $newPrice);
    }

    public function rejectPriceBelowLimit(): void
    {
        $index = $this->priceBelowLimitLineIndex ?? $this->memorizeLineIndex;
        $this->showPriceBelowLimitModal = false;
        $this->priceBelowLimitLineIndex = null;

        // Restore previous accepted price
        if ($index !== null && isset($this->lines[$index])) {
            $restore = $this->formatMoney($this->lines[$index]['system_price'] ?? '');
            $this->lines[$index]['price'] = $restore;
        }

        $this->memorizeLineIndex = null;
        $this->memorizePriceValue = '';
        $this->refreshCreditWarning();
        $this->suggestTax();
    }

    public function confirmMemorizePrice(): void
    {
        $index = $this->memorizeLineIndex;
        if (! $this->userCanChangeOrderPrice()) {
            $this->rejectMemorizePrice(false);
            if ($index !== null) {
                $this->restoreLineSystemPrice($index);
            }

            return;
        }
        if ($index === null || ! isset($this->lines[$index]) || ! $this->customer_id) {
            $this->rejectMemorizePrice(false);

            return;
        }

        $itemId = (int) ($this->lines[$index]['item_id'] ?? 0);
        if ($itemId <= 0) {
            $this->rejectMemorizePrice(false);

            return;
        }

        $priceRaw = $this->lines[$index]['price'] ?? $this->memorizePriceValue;
        $price = is_numeric($priceRaw) ? round((float) $priceRaw, 2) : round((float) $this->memorizePriceValue, 2);
        $uom = (string) ($this->lines[$index]['uom'] ?? '');

        CustomerItemPrice::memorize(
            (int) auth()->user()->company_id,
            (int) $this->customer_id,
            $itemId,
            $uom !== '' ? $uom : null,
            $price
        );

        $stored = $this->formatMoney($price);
        $this->lines[$index]['price'] = $stored;
        $this->lines[$index]['system_price'] = $stored;

        $this->showMemorizePriceModal = false;
        $this->memorizeLineIndex = null;
        $this->memorizePriceValue = '';
    }

    public function rejectMemorizePrice(bool $keepOrderPrice = true): void
    {
        $index = $this->memorizeLineIndex;
        if ($keepOrderPrice && $index !== null && isset($this->lines[$index])) {
            $this->lines[$index]['system_price'] = $this->formatMoney(
                $this->lines[$index]['price'] ?? ''
            );
            $this->lines[$index]['price'] = $this->formatMoney($this->lines[$index]['price'] ?? '');
        }

        $this->showMemorizePriceModal = false;
        $this->memorizeLineIndex = null;
        $this->memorizePriceValue = '';
    }

    public function nudgeAmount(string $field, float $delta): void
    {
        if ($this->viewMode) {
            return;
        }
        if (! in_array($field, ['trade_discount', 'freight', 'miscellaneous', 'tax', 'no_of_boxes', 'no_of_pallets'], true)) {
            return;
        }

        $current = (float) ($this->{$field} ?? 0);
        $next = max(0, (int) round($current + $delta));
        $this->{$field} = $this->blankZeroAmount((string) $next);

        if ($field === 'tax') {
            $this->taxManual = true;
        }
        if (in_array($field, ['trade_discount', 'freight', 'miscellaneous', 'tax'], true)) {
            $this->refreshCreditWarning();
            if ($field === 'trade_discount' && ! $this->taxManual) {
                $this->suggestTax();
            }
        }
    }

    protected function recalcLineDiscount(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        $qty = (float) ($this->lines[$index]['qty_ordered'] ?? 0);
        $unit = (float) ($this->lines[$index]['unit_discount'] ?? 0);
        $this->lines[$index]['discount'] = $this->blankZeroAmount(number_format(max(0, $qty * $unit), 4, '.', ''));
    }

    protected function refreshTaxExemptWarning($customer): void
    {
        $this->taxExemptWarning = '';
        if (! $customer?->is_tax_exempt || ! $customer->tax_certificate_exp) {
            return;
        }
        $exp = $customer->tax_certificate_exp->copy()->startOfDay();
        $today = now()->startOfDay();
        if ($exp->lt($today)) {
            $this->taxExemptWarning = 'Tax exemption certificate expired on '.$exp->format('n/j/Y').'.';
        } elseif ($exp->lte($today->copy()->addDays(30))) {
            $this->taxExemptWarning = 'Tax exemption certificate expires on '.$exp->format('n/j/Y').' (within 30 days).';
        }
    }

    protected function orderTotalAmount(): float
    {
        $subtotal = collect($this->lines)
            ->filter(fn ($l) => filled($l['item_code'] ?? null))
            ->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);

        return $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;
    }

    protected function refreshCreditWarning(): void
    {
        $this->creditWarning = '';
        if (! $this->customer_id) {
            return;
        }
        $customer = Customer::query()->find($this->customer_id);
        if (! $customer || (float) $customer->credit_limit <= 0) {
            return;
        }
        $available = (float) $customer->available_credit;
        $total = $this->orderTotalAmount();
        if ($total > $available) {
            $this->creditWarning = sprintf(
                'Order total $%s exceeds available credit $%s (limit $%s − balance $%s).',
                number_format($total, 2),
                number_format($available, 2),
                number_format((float) $customer->credit_limit, 2),
                number_format((float) $customer->balance, 2),
            );
        }
    }

    protected function suggestTax(): void
    {
        if ($this->taxManual) {
            return;
        }
        $filled = collect($this->lines)->filter(fn ($l) => filled($l['item_code'] ?? null) && ! empty($l['item_id']));
        if ($filled->isEmpty()) {
            return;
        }
        $gross = $filled->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        $taxable = max(0, $gross - (float) $this->trade_discount);
        if ($taxable <= 0) {
            $this->tax = '';

            return;
        }

        if ($this->orderTaxScheduleId) {
            $sched = TaxSchedule::query()
                ->where('company_id', auth()->user()->company_id)
                ->find($this->orderTaxScheduleId);
            $rate = (float) ($sched?->rate ?? 0);
            $this->tax = $this->formatMoney(number_format($taxable * ($rate / 100), 2, '.', ''));

            return;
        }

        $itemIds = $filled->pluck('item_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        $missing = array_values(array_filter($itemIds, fn (int $id) => ! array_key_exists($id, $this->itemTaxRateCache)));
        if ($missing !== []) {
            $items = Item::query()->with('taxSchedule')->whereIn('id', $missing)->get();
            foreach ($items as $item) {
                $this->itemTaxRateCache[(int) $item->id] = (float) ($item->taxSchedule?->rate ?? 0);
            }
        }
        $weighted = 0.0;
        foreach ($filled as $line) {
            $rate = (float) ($this->itemTaxRateCache[(int) $line['item_id']] ?? 0);
            $lineNet = ((float) $line['qty_ordered'] * (float) $line['price']) - (float) $line['discount'];
            $weighted += $lineNet * ($rate / 100);
        }
        $suggested = $gross > 0 ? $weighted * ($taxable / $gross) : 0;
        $this->tax = $this->formatMoney(number_format($suggested, 2, '.', ''));
    }

    public function toggleCustomerBrowse(): void
    {
        $this->showCustomerBrowse = ! $this->showCustomerBrowse;
        $this->showShipBrowse = false;
        if ($this->showCustomerBrowse) {
            $this->customerSearch = '';
        }
    }

    public function pickCustomer(int $customerId): void
    {
        $this->customer_id = $customerId;
        $this->updatedCustomerId($customerId);
    }

    public function toggleShipBrowse(): void
    {
        if (! $this->customer_id) {
            return;
        }
        $this->showShipBrowse = ! $this->showShipBrowse;
        $this->showCustomerBrowse = false;
    }

    public function pickShipTo(int $addressId): void
    {
        $this->ship_to_address_id = $addressId;
        $this->updatedShipToAddressId($addressId);
        $this->showShipToModal = false;
    }

    public function openShipToModal(): void
    {
        if (! $this->customer_id || $this->viewMode) {
            return;
        }

        $this->showShipBrowse = false;
        $this->showCustomerBrowse = false;
        $this->showShipToModal = true;
        $this->shipToFlash = '';
        $this->resetErrorBag(['newShipName', 'newShipAddress', 'newShipCity', 'newShipState', 'newShipZip', 'newShipPhone', 'newShipFax', 'newShipClass']);

        $customer = Customer::query()->with('shippingAddresses')->find($this->customer_id);
        if (! $customer) {
            return;
        }

        // Prefill from current ship / bill fields so the form is ready
        $this->newShipName = trim($this->ship_to_name) !== ''
            ? trim($this->ship_to_name)
            : trim((string) ($this->bill_to_name ?: $customer->company_name ?: $customer->contact));
        $this->newShipPhone = trim($this->ship_to_phone) !== ''
            ? trim($this->ship_to_phone)
            : trim((string) ($this->bill_to_phone ?: $customer->telephone));
        $this->newShipFax = trim((string) ($customer->fax ?? ''));
        $this->newShipAddress = trim($this->ship_to_address) !== ''
            ? trim($this->ship_to_address)
            : trim((string) ($this->bill_to_address ?: $customer->address));
        $this->newShipCity = trim($this->ship_to_city) !== ''
            ? trim($this->ship_to_city)
            : trim((string) ($this->bill_to_city ?: $customer->city));
        $this->newShipState = trim($this->ship_to_state) !== ''
            ? trim($this->ship_to_state)
            : trim((string) ($this->bill_to_state ?: $customer->state));
        $this->newShipZip = trim($this->ship_to_zip) !== ''
            ? trim($this->ship_to_zip)
            : trim((string) ($this->bill_to_zip ?: $customer->zip_code));
        $this->newShipClass = '';
        $this->newShipPrimary = $customer->shippingAddresses->isEmpty();
    }

    public function closeShipToModal(): void
    {
        $this->showShipToModal = false;
        $this->resetErrorBag(['newShipName', 'newShipAddress', 'newShipCity', 'newShipState', 'newShipZip', 'newShipPhone', 'newShipFax', 'newShipClass']);
    }

    public function saveShipToAddress(): void
    {
        if (! $this->customer_id || $this->viewMode) {
            return;
        }

        $this->validate([
            'newShipName' => ['required', 'string', 'max:120'],
            'newShipAddress' => ['required', 'string', 'max:255'],
            'newShipCity' => ['nullable', 'string', 'max:100'],
            'newShipState' => ['nullable', 'string', 'max:20'],
            'newShipZip' => ['nullable', 'string', 'max:20'],
            'newShipPhone' => ['nullable', 'string', 'max:40'],
            'newShipFax' => ['nullable', 'string', 'max:40'],
            'newShipClass' => ['nullable', 'string', 'max:50'],
            'newShipPrimary' => ['boolean'],
        ], [], [
            'newShipName' => 'name',
            'newShipAddress' => 'address',
        ]);

        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->customer_id);

        if (! $customer) {
            $this->addError('newShipName', 'Customer not found.');

            return;
        }

        $isFirst = $customer->shippingAddresses()->count() === 0;
        $makePrimary = $this->newShipPrimary || $isFirst;

        if ($makePrimary) {
            $customer->shippingAddresses()->update(['is_primary' => false]);
        }

        $maxSort = (int) $customer->shippingAddresses()->max('sort_order');

        $ship = $customer->shippingAddresses()->create([
            'name' => trim($this->newShipName),
            'address' => trim($this->newShipAddress),
            'city' => trim($this->newShipCity),
            'state' => trim($this->newShipState),
            'zip' => trim($this->newShipZip),
            'telephone' => trim($this->newShipPhone),
            'fax' => trim($this->newShipFax) !== '' ? trim($this->newShipFax) : null,
            'class' => trim($this->newShipClass) !== '' ? trim($this->newShipClass) : null,
            'is_primary' => $makePrimary,
            'sort_order' => $maxSort + 1,
        ]);

        $this->ship_to_address_id = $ship->id;
        $this->updatedShipToAddressId($ship->id);
        $this->showShipToModal = false;
        $this->shipToFlash = 'Ship-to address saved for this customer.';
        $this->addressTab = 'ship';
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        if ($this->selectedLineIndex === $i) {
            $this->selectedLineIndex = null;
            $this->clearSelectedStock();
        } elseif ($this->selectedLineIndex !== null && $this->selectedLineIndex > $i) {
            $this->selectedLineIndex--;
        }
        $this->refreshSelectedLineStock();
        $this->refreshCreditWarning();
        $this->suggestTax();
    }

    public function lookupItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            return;
        }
        $item = $this->findItem($code);
        if (! $item) {
            $this->notifyAlert('Item "'.$code.'" was not found.', 'error');

            return;
        }
        if ($this->shouldPromptForceSubstitute($item)) {
            $this->openSubstitutePrompt($item, $index);

            return;
        }
        if (! $this->canAddItemToOrder($item)) {
            $this->lines[$index]['item_id'] = null;
            $this->lines[$index]['item_code'] = '';
            $this->lines[$index]['description'] = '';

            return;
        }

        // Same item already on another line → increase qty there instead of duplicating.
        $existingIndex = $this->findLineIndexForItem((int) $item->id);
        if ($existingIndex !== null && $existingIndex !== $index) {
            $qty = (float) ($this->lines[$existingIndex]['qty_ordered'] ?? 0);
            $this->lines[$existingIndex]['qty_ordered'] = $this->formatQty($qty + 1);
            $this->recalcLineDiscount($existingIndex);
            $this->lines[$index] = $this->emptyLine();
            $this->selectedLineIndex = $existingIndex;
            $this->syncLineContextHeader($existingIndex);
            $this->taxManual = false;
            $this->refreshCreditWarning();
            $this->suggestTax();
            $this->notifyAlert($item->item_code.' quantity increased to '.$this->lines[$existingIndex]['qty_ordered'].'.', 'success');
            $this->refreshSelectedLineStock($existingIndex, $item);
            $this->highlightScannedLine($existingIndex);

            return;
        }

        $this->fillLineFromItem($index, $item);
        $this->playPosSound('success');
    }

    public function printInvoiceStyle(): void
    {
        if (! $this->salesOrder?->exists) {
            $this->notifyAlert('Save the sales order first, then print.', 'warning');

            return;
        }

        $this->lineWarning = '';
        $this->salesOrder->loadMissing('customer', 'invoice');
        $this->invoiceEmailTo = (string) ($this->salesOrder->customer?->email ?? '');
        $label = $this->salesOrder->invoice?->invoice_number ?: ('SO-'.$this->salesOrder->order_number);
        $this->invoiceEmailSubject = 'Invoice '.$label;
        $this->invoiceDeliveryMode = filled($this->invoiceEmailTo) ? 'both' : 'print';
        $this->showInvoiceDeliveryDialog = true;
    }

    public function cancelInvoiceDeliveryDialog(): void
    {
        $this->showInvoiceDeliveryDialog = false;
    }

    public function confirmInvoiceDeliveryDialog(): void
    {
        if (! $this->salesOrder?->exists) {
            $this->showInvoiceDeliveryDialog = false;

            return;
        }

        $mode = $this->invoiceDeliveryMode;
        $print = in_array($mode, ['print', 'both'], true);
        $email = in_array($mode, ['email', 'both'], true);

        if ($email) {
            $to = trim($this->invoiceEmailTo);
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->addError('invoiceEmailTo', 'Enter a valid customer email address.');

                return;
            }

            try {
                app(\App\Services\DocumentPdfService::class)->emailSalesOrderInvoiceStyle(
                    $this->salesOrder->fresh(['customer', 'invoice', 'lines']),
                    $to,
                    auth()->user(),
                    $this->invoiceEmailSubject !== '' ? $this->invoiceEmailSubject : null
                );
                $this->notifyAlert('Invoice emailed to '.$to, 'success');
            } catch (\Throwable $e) {
                $this->notifyAlert('Could not email invoice: '.$e->getMessage(), 'error');

                return;
            }
        }

        $this->showInvoiceDeliveryDialog = false;
        $this->resetErrorBag('invoiceEmailTo');

        if ($print) {
            $this->dispatch('open-order-invoice-pdf', url: route('sales.orders.invoice', $this->salesOrder));
        }
    }

    #[On('pos-shortcut-f2')]
    public function shortcutFocusItemEntry(): void
    {
        if ($this->viewMode) {
            return;
        }
        $this->activeTab = 'items';
        $this->focusItemEntry(true);
    }

    #[On('pos-shortcut-f3')]
    public function shortcutBrowseItems(): void
    {
        if ($this->viewMode) {
            return;
        }
        $this->openBrowseForSearch();
    }

    #[On('pos-shortcut-f4')]
    public function shortcutFocusSearch(): void
    {
        if ($this->viewMode) {
            return;
        }
        if (! $this->showBrowse) {
            $this->openBrowseForSearch();
        }
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-browse-search"); if (el) { el.focus(); el.select?.(); } });');
    }

    #[On('pos-shortcut-save')]
    public function shortcutSave(): void
    {
        if ($this->viewMode) {
            return;
        }
        $this->save();
    }

    protected function filledParkLines()
    {
        return collect($this->lines)->filter(fn ($l) => filled($l['item_code'] ?? null) && ! empty($l['item_id']) && (float) ($l['qty_ordered'] ?? 0) > 0);
    }

    public function refreshParkedList(): void
    {
        $this->parkedSalesList = app(ParkedSaleService::class)
            ->listFor(auth()->user())
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'customer_label' => $p->customer_label,
                'line_count' => (int) $p->line_count,
                'total' => (float) $p->total,
                'updated_at' => optional($p->updated_at)->format('n/j/Y g:i A'),
            ])
            ->all();
    }

    public function openParkedSalesModal(): void
    {
        $this->refreshParkedList();
        $this->showParkedSalesModal = true;
    }

    public function closeParkedSalesModal(): void
    {
        $this->showParkedSalesModal = false;
    }

    public function updatedOpenOrderSearch(): void
    {
        if ($this->showOpenOrderModal) {
            $this->refreshOpenOrderList();
        }
    }

    public function openOpenOrderModal(): void
    {
        $this->openOrderSearch = '';
        $this->refreshOpenOrderList();
        $this->showOpenOrderModal = true;
        $this->showParkedSalesModal = false;
    }

    public function closeOpenOrderModal(): void
    {
        $this->showOpenOrderModal = false;
    }

    public function refreshOpenOrderList(): void
    {
        $companyId = (int) auth()->user()->company_id;
        $term = trim($this->openOrderSearch);

        $query = SalesOrder::query()
            ->with('customer:id,customer_id,company_name')
            ->where('company_id', $companyId)
            ->where('status', 'New')
            ->orderByDesc('id')
            ->limit(40);

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function ($q) use ($like) {
                $q->where('order_number', 'like', $like)
                    ->orWhereHas('customer', function ($c) use ($like) {
                        $c->where('company_name', 'like', $like)
                            ->orWhere('customer_id', 'like', $like);
                    });
            });
        }

        $this->openOrderList = $query->get()->map(fn (SalesOrder $o) => [
            'id' => (int) $o->id,
            'order_number' => (string) $o->order_number,
            'customer_label' => trim((string) ($o->customer?->company_name ?: $o->customer?->customer_id ?: 'Customer')),
            'total' => (float) $o->total,
            'order_date' => optional($o->order_date)?->format('n/j/Y'),
        ])->all();
    }

    public function openExistingOrder(int $id): mixed
    {
        abort_if($this->viewMode, 403);

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereKey($id)
            ->first();

        if (! $order) {
            $this->notifyAlert('Order not found.', 'error');

            return null;
        }

        $this->showOpenOrderModal = false;

        return $this->redirect(route('sales.orders.edit', $order), navigate: true);
    }

    public function parkSale(): void
    {
        abort_if($this->viewMode, 403);

        $filled = $this->filledParkLines();
        if ($filled->isEmpty()) {
            $this->notifyAlert('Add at least one item before parking this sale.', 'warning');

            return;
        }
        if (! $this->customer_id) {
            $this->notifyAlert('Select a customer first.', 'warning');

            return;
        }

        $customer = Customer::query()->find($this->customer_id);
        if (! $customer || (int) $customer->company_id !== (int) auth()->user()->company_id) {
            $this->notifyAlert('Select a valid customer.', 'error');

            return;
        }

        $form = [];
        foreach ($this->createWindowDraftKeys() as $key) {
            $form[$key] = $this->{$key};
        }

        $pwaLines = $filled->map(fn ($l) => [
            'product_id' => (int) $l['item_id'],
            'variation_id' => (int) $l['item_id'],
            'name' => trim(($l['description'] ?? '').(filled($l['item_code'] ?? null) ? ' ('.$l['item_code'].')' : '')),
            'unit_price' => (float) ($l['price'] ?? 0),
            'quantity' => (float) ($l['qty_ordered'] ?? 0),
            'allow_decimal' => 1,
        ])->values()->all();

        $total = (float) $filled->sum(fn ($l) => ((float) ($l['qty_ordered'] ?? 0) * (float) ($l['price'] ?? 0)) - (float) ($l['discount'] ?? 0));
        $label = trim($this->bill_to_name ?: ($customer->company_name ?: $customer->contact ?: ''));

        try {
            app(ParkedSaleService::class)->park(auth()->user(), $customer, $label, [
                'source' => 'desktop',
                'form' => $form,
                'customer_id' => (int) $customer->id,
                'customer_label' => $label,
                'lines' => $pwaLines,
                'location_id' => $this->ship_from_site_id,
                'shipping' => [
                    'ship_to_address_id' => $this->ship_to_address_id,
                    'ship_to_name' => $this->ship_to_name,
                    'ship_to_address' => $this->ship_to_address,
                    'ship_to_city' => $this->ship_to_city,
                    'ship_to_state' => $this->ship_to_state,
                    'ship_to_zip' => $this->ship_to_zip,
                    'ship_to_phone' => $this->ship_to_phone,
                    'ship_via_id' => $this->ship_via_id,
                    'payment_term_id' => $this->payment_term_id,
                    'route_id' => $this->route_id,
                    'ship_date' => $this->ship_date,
                    'sale_note' => $this->comments,
                    'location_id' => $this->ship_from_site_id,
                ],
            ], $filled->count(), $total);
        } catch (ValidationException $e) {
            $this->notifyAlert(collect($e->errors())->flatten()->first() ?: 'Could not park this sale.', 'error');

            return;
        }

        if (! $this->salesOrder?->exists) {
            $this->resetBlankNewOrder();
        }
        $this->refreshParkedList();
        $this->notifyAlert('Sale parked. Use Parked Sales to recall it.', 'success');
    }

    protected function resetBlankNewOrder(): void
    {
        $companyId = (int) auth()->user()->company_id;
        $this->lines = [];
        $this->boxes = [['box_number' => '', 'tracking_number' => '']];
        $this->comments = '';
        $this->customer_po_no = '';
        $this->reference_no = '';
        $this->freight = '';
        $this->trade_discount = '';
        $this->miscellaneous = '';
        $this->tax = '';
        $this->taxManual = false;
        $this->orderTaxScheduleId = null;
        $this->custom_field_1 = '';
        $this->custom_field_2 = '';
        $this->custom_field_3 = '';
        $this->custom_field_4 = '';
        $this->custom_field_5 = '';
        $this->itemEntry = '';
        $this->order_number = SalesOrder::nextNumber($companyId);
        $this->order_date = now()->toDateString();
        $this->required_date = now()->toDateString();
        $this->ship_date = now()->toDateString();
        $this->sales_rep_id = auth()->id();
        $this->ship_from_site_id = auth()->user()->site_id;
        $this->activeTab = 'general';
        $walkIn = $this->resolveWalkInCustomer($companyId);
        $this->suppressCustomerConfirm = true;
        $this->customer_id = $walkIn->id;
        $this->updatedCustomerId($walkIn->id);
        $this->suppressCustomerConfirm = false;
        $this->confirmedCustomerId = (int) $walkIn->id;
        $this->persistCreateWindowDraft();
    }

    public function recallParkedSale(int $id): void
    {
        abort_if($this->viewMode, 403);

        if ($this->salesOrder?->exists) {
            $this->notifyAlert('Open a new sales order to recall a parked sale.', 'warning');

            return;
        }

        $row = app(ParkedSaleService::class)->findOwn(auth()->user(), $id);
        $payload = is_array($row->payload) ? $row->payload : [];

        if (isset($payload['form']) && is_array($payload['form'])) {
            $this->applyCreateWindowDraft($payload['form']);
        } else {
            $this->applyParkedSaleAppPayload($payload);
        }

        $this->regenerateOrderNumber();
        $row->delete();
        $this->showParkedSalesModal = false;
        $this->refreshParkedList();
        $this->persistCreateWindowDraft();
        if ($this->filledParkLines()->isNotEmpty()) {
            $this->activeTab = 'items';
        }
        $this->notifyAlert('Parked sale recalled.', 'success');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function applyParkedSaleAppPayload(array $payload): void
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->suppressCustomerConfirm = true;
            $this->customer_id = $customerId;
            $this->updatedCustomerId($customerId);
            $this->suppressCustomerConfirm = false;
            $this->confirmedCustomerId = $customerId;
        }

        $this->lines = [];
        foreach ($payload['lines'] ?? [] as $l) {
            $itemId = (int) ($l['product_id'] ?? $l['variation_id'] ?? $l['item_id'] ?? 0);
            if ($itemId < 1) {
                continue;
            }
            $item = Item::query()->where('company_id', auth()->user()->company_id)->find($itemId);
            $line = $this->emptyLine();
            $line['item_id'] = $itemId;
            $line['item_code'] = $item?->item_code ?: (string) ($l['sku'] ?? '');
            $line['description'] = $item?->description ?: (string) ($l['name'] ?? '');
            $line['uom'] = $item?->unit_of_measure ?: '';
            $line['qty_ordered'] = $this->formatQty($l['quantity'] ?? $l['qty_ordered'] ?? 1);
            $line['price'] = $this->formatMoney($l['unit_price'] ?? $l['price'] ?? 0);
            $line['system_price'] = $line['price'];
            $this->lines[] = $line;
        }

        $ship = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : [];
        foreach ([
            'ship_to_name', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip', 'ship_to_phone',
        ] as $key) {
            if (array_key_exists($key, $ship)) {
                $this->{$key} = (string) $ship[$key];
            }
        }
        if (! empty($ship['ship_to_address_id'])) {
            $this->ship_to_address_id = (int) $ship['ship_to_address_id'];
        }
        if (! empty($ship['payment_term_id'])) {
            $this->payment_term_id = (int) $ship['payment_term_id'];
        }
        if (! empty($ship['route_id'])) {
            $this->route_id = (int) $ship['route_id'];
        }
        if (! empty($ship['ship_via_id'])) {
            $this->ship_via_id = (int) $ship['ship_via_id'];
        }
        if (! empty($ship['ship_date'])) {
            $this->ship_date = (string) $ship['ship_date'];
        }
        if (! empty($ship['sale_note'])) {
            $this->comments = (string) $ship['sale_note'];
        }
        if (! empty($payload['location_id'])) {
            $this->ship_from_site_id = (int) $payload['location_id'];
        }
    }

    public function discardParkedSale(int $id): void
    {
        app(ParkedSaleService::class)->discard(auth()->user(), $id);
        $this->refreshParkedList();
        $this->notifyAlert('Parked sale discarded.', 'success');
    }

    #[On('pos-shortcut-print')]
    public function shortcutPrint(): void
    {
        $this->printInvoiceStyle();
    }

    public function printPickList(): void
    {
        if (! $this->salesOrder?->exists) {
            $this->notifyAlert('Save the sales order first, then print pick list.', 'warning');

            return;
        }

        $this->lineWarning = '';
        $this->dispatch('open-order-invoice-pdf', url: route('sales.orders.pick-list', $this->salesOrder).'?v='.time());
    }

    /**
     * Block Enter + auto-add (or CR+LF) from counting as two scans of the same code.
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
     * Add item from entry bar (✓ tick or Enter).
     * Exact match → add line. Unknown scan → blocking alert (stay on this order).
     */
    public function addItemFromEntry(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        if ($this->showUnknownScanModal) {
            return;
        }

        // Do not fall back to Livewire itemEntry — the scan box uses wire:ignore.self,
        // so a previous SKU (e.g. 2234b) can still be in state while the box shows "12".
        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? '')) ?? '');
        $this->itemEntry = $code;

        if ($code === '') {
            $this->skipRender();
            $this->focusItemEntry(true);

            return;
        }

        if (! $this->claimScanAdd($code)) {
            $this->itemEntry = '';
            $this->skipRender();
            $this->clearAndFocusEntry();

            return;
        }

        $item = $this->findItem($code);
        if ($item) {
            $this->itemEntry = '';
            $this->lineWarning = '';
            $this->queueItemOrPromptSubstitute($item);
            // Stay ready for continuous gun scans.
            $this->scanModeActive = true;
            $this->clearAndFocusEntry();

            return;
        }

        $this->alertUnknownScan($code);
    }

    /**
     * After input timing pause: add only when the full typed code is an exact match
     * and cannot still be the start of a longer code (e.g. "25" while "2593a" exists).
     */
    public function autoAddIfExactMatch(?string $code = null): void
    {
        abort_if($this->viewMode, 403);

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? '')) ?? '');
        // Need a complete code — ignore very short noise while typing.
        if ($code === '' || mb_strlen($code) < 2) {
            $this->skipRender();

            return;
        }

        // 1) Full exact match only (item_code / UPC / alias) — never LIKE/% partial.
        $item = $this->findItem($code);
        if (! $item) {
            $this->skipRender();

            return;
        }

        if (! $this->claimScanAdd($code)) {
            $this->skipRender();

            return;
        }

        // Full exact match — add after typing pause (no Enter needed).
        $this->itemEntry = '';
        $this->lineWarning = '';
        $this->queueItemOrPromptSubstitute($item);
        $this->scanModeActive = true;
        $this->clearAndFocusEntry();
    }

    /**
     * True when any sellable item/UPC/alias starts with $code but is strictly longer.
     * Prevents auto-adding "25" while the user is still typing "2593a".
     */
    protected function codeIsPrefixOfLongerItemCode(string $code): bool
    {
        $companyId = (int) auth()->user()->company_id;
        $lower = mb_strtolower(trim($code));
        $len = mb_strlen($lower);
        if ($len < 1) {
            return false;
        }
        // Full UPC / long barcode is already complete — skip expensive prefix scan.
        if ($len >= 8) {
            return false;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower).'%';

        return Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_sell', true)
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
        $this->lineWarning = '';

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('so-item-entry');
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

    public function clearItemEntry(): void
    {
        $this->itemEntry = '';
        $this->scanModeActive = false;
        if (str_contains(strtolower($this->lineWarning), 'was not found')
            || str_contains(strtolower($this->lineWarning), 'not found')) {
            $this->lineWarning = '';
        }
        $this->clearAndFocusEntry();
    }

    protected function clearAndFocusEntry(): void
    {
        $this->itemEntry = '';
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('so-item-entry');
                if (!el) return;
                el.value = '';
                el.focus();
            });
        JS);
    }

    protected function focusItemEntry(bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-item-entry"); if (el) { el.focus();'.$selectJs.' } });');
    }

    public function pickBrowseItem(int $itemId, bool $keepBrowseOpen = false): void
    {
        abort_if($this->viewMode, 403);

        $item = Item::query()
            ->with(['prices', 'taxSchedule'])
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);
        if (! $item) {
            return;
        }
        $this->itemEntry = '';
        $this->browseSelectedId = $itemId;
        $this->lineWarning = '';
        if ($keepBrowseOpen) {
            $this->browseCheckedIds = [];
            $this->browseSelectedId = null;
            $this->dispatch('browse-checks-cleared');
            $this->queueItemOrPromptSubstitute($item);
            if ($this->showBrowse) {
                $this->resetBrowseAndLoadFirstPage();
                $this->focusBrowseSearch();
            }
        } else {
            $this->closeBrowse();
            $this->queueItemOrPromptSubstitute($item);
        }
    }

    protected function queueItemOrPromptSubstitute(Item $item): void
    {
        if ($this->shouldPromptForceSubstitute($item)) {
            $this->openSubstitutePrompt($item, null);

            return;
        }

        if (! $this->canAddItemToOrder($item)) {
            return;
        }

        $this->appendItemLine($item);
    }

    protected function openSubstitutePrompt(Item $item, ?int $lineIndex): void
    {
        if (! $item->relationLoaded('substitutes')) {
            $item->load(['substitutes.substituteItem']);
        }

        $this->pendingItemId = $item->id;
        $this->pendingLineIndex = $lineIndex;
        $this->substituteOptions = $item->substitutes
            ->filter(fn (ItemSubstitute $s) => $s->force_substitute && $s->substituteItem)
            ->map(fn (ItemSubstitute $s) => [
                'id' => $s->substituteItem->id,
                'item_code' => $s->substituteItem->item_code,
                'description' => $s->substituteItem->description,
                'available' => (float) $s->substituteItem->available_quantity,
            ])
            ->values()
            ->all();
        $this->showSubstitutePrompt = true;
    }

    protected function shouldPromptForceSubstitute(Item $item): bool
    {
        if (StockPolicy::allowsOversell(null, $item)) {
            return false;
        }

        if ((float) $item->available_quantity > 0) {
            return false;
        }

        if (! $item->relationLoaded('substitutes')) {
            $item->load(['substitutes.substituteItem']);
        }

        return $item->substitutes->contains(fn (ItemSubstitute $s) => $s->force_substitute && $s->substitute_item_id);
    }

    /**
     * Out-of-stock: still allowed when company allows negative stock / oversell.
     */
    protected function canAddItemToOrder(Item $item): bool
    {
        $available = (float) $item->available_quantity;
        if ($available > 0) {
            return true;
        }

        if (StockPolicy::allowsOversell(null, $item)) {
            $this->notifyAlert(
                $item->item_code.' has no stock on hand — order will go negative on invoice (overselling is ON).',
                'warning'
            );

            return true;
        }

        $this->notifyAlert($item->item_code.' has no stock available and cannot be added to this order.', 'error');

        return false;
    }

    public function acceptSubstitute(int $substituteItemId): void
    {
        $item = Item::query()->with(['prices', 'taxSchedule'])
            ->where('company_id', auth()->user()->company_id)
            ->find($substituteItemId);
        $lineIndex = $this->pendingLineIndex;
        $this->showSubstitutePrompt = false;
        $this->pendingItemId = null;
        $this->pendingLineIndex = null;
        $this->substituteOptions = [];
        if (! $item) {
            return;
        }
        if (! $this->canAddItemToOrder($item)) {
            return;
        }
        if ($lineIndex !== null && isset($this->lines[$lineIndex])) {
            $this->fillLineFromItem($lineIndex, $item);
        } else {
            $this->appendItemLine($item);
        }
        $this->notifyAlert('Used force substitute '.$item->item_code.' (original out of stock).', 'warning');
    }

    public function keepOriginalItem(): void
    {
        $item = $this->pendingItemId
            ? Item::query()->with(['prices', 'taxSchedule'])->where('company_id', auth()->user()->company_id)->find($this->pendingItemId)
            : null;
        $lineIndex = $this->pendingLineIndex;
        $this->showSubstitutePrompt = false;
        $this->pendingItemId = null;
        $this->pendingLineIndex = null;
        $this->substituteOptions = [];
        if ($item && $this->canAddItemToOrder($item)) {
            if ($lineIndex !== null && isset($this->lines[$lineIndex])) {
                $this->fillLineFromItem($lineIndex, $item);
            } else {
                $this->appendItemLine($item);
            }

            return;
        }
        $code = $item?->item_code ?? 'Item';
        $this->notifyAlert($code.' has no stock available and cannot be added to this order.', 'error');
    }

    public function cancelSubstitutePrompt(): void
    {
        $this->showSubstitutePrompt = false;
        $this->pendingItemId = null;
        $this->pendingLineIndex = null;
        $this->substituteOptions = [];
    }

    protected function appendItemLine(Item $item): void
    {
        $existingIndex = $this->findLineIndexForItem((int) $item->id);
        if ($existingIndex !== null) {
            $qty = (float) ($this->lines[$existingIndex]['qty_ordered'] ?? 0);
            $this->lines[$existingIndex]['qty_ordered'] = $this->formatQty($qty + 1);
            $this->recalcLineDiscount($existingIndex);
            $msg = trim((string) ($this->lines[$existingIndex]['line_message'] ?? ''));
            $instr = trim((string) ($this->lines[$existingIndex]['instructions'] ?? ''));
            $this->orderLineMessagePopup = $msg;
            $this->orderLineInstructionsPopup = $instr;
            $this->showLineMessageAlert = $msg !== '' || $instr !== '';
            $this->refreshSelectedLineStock($existingIndex, $item);
            $this->taxManual = false;
            $this->refreshCreditWarning();
            $this->suggestTax();
            $this->highlightScannedLine($existingIndex);
            $this->playPosSound('success');

            return;
        }

        $this->lines[] = $this->emptyLine();
        $this->fillLineFromItem(count($this->lines) - 1, $item);
        $this->highlightScannedLine(count($this->lines) - 1);
        $this->playPosSound('success');
    }

    protected function highlightScannedLine(int $index): void
    {
        $this->selectedLineIndex = $index;
        $this->syncLineContextHeader($index);
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-line-row-'.$index.'"); if (el) el.scrollIntoView({ block: "nearest" }); });');
    }

    protected function findLineIndexForItem(int $itemId): ?int
    {
        foreach ($this->lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === $itemId) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * Resolve sellable item by item code, Primary UPC / aliases, price alias, supplier code.
     */
    protected function findItem(string $code): ?Item
    {
        $item = Item::findByScanCode((int) auth()->user()->company_id, $code, 'sell');
        if ($item) {
            $item->load(['prices', 'taxSchedule']);
        }

        return $item;
    }

    protected function resolveItemPrice(Item $item, ?string $uom = null): string
    {
        $levelId = $this->customerPriceLevelId;
        if ($levelId === null && $this->customer_id) {
            $levelId = Customer::query()->whereKey($this->customer_id)->value('price_level_id');
            $this->customerPriceLevelId = $levelId ? (int) $levelId : null;
            $levelId = $this->customerPriceLevelId;
        }

        return (string) ItemPricing::resolve(
            $item,
            $levelId,
            $uom ?? ($item->unit_of_measure ?: null),
            $this->customer_id ? (int) $this->customer_id : null
        );
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $desc = trim((string) ($item->description ?? ''));
        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $desc;
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $price = $this->formatMoney($this->resolveItemPrice($item));
        $this->lines[$index]['price'] = $price;
        $this->lines[$index]['system_price'] = $price;
        $this->lines[$index]['qty_ordered'] = $this->formatQty($this->lines[$index]['qty_ordered'] ?: '1');
        $this->lines[$index]['qty_shipped'] = $this->blankZeroAmount($this->lines[$index]['qty_shipped'] ?? '');
        if (! isset($this->lines[$index]['unit_discount']) || $this->lines[$index]['unit_discount'] === '' || (float) $this->lines[$index]['unit_discount'] == 0.0) {
            $this->lines[$index]['unit_discount'] = '';
        }
        $this->recalcLineDiscount($index);
        // Do not auto-fill from item master — Msg/Inst icons only after user adds them on this order.
        $this->lines[$index]['line_message'] = '';
        $this->lines[$index]['instructions'] = '';
        $this->orderLineMessagePopup = '';
        $this->orderLineInstructionsPopup = '';
        $this->showLineMessageAlert = false;
        $this->refreshSelectedLineStock($index, $item);
        if ($item->relationLoaded('taxSchedule') || $item->tax_schedule_id) {
            $this->itemTaxRateCache[(int) $item->id] = (float) ($item->taxSchedule?->rate ?? 0);
        }
        $this->taxManual = false;
        $this->refreshCreditWarning();
        $this->suggestTax();
        $this->highlightScannedLine($index);
    }

    public function addBox(): void
    {
        $this->boxes[] = ['box_number' => '', 'tracking_number' => ''];
    }

    public function removeBox(int $i): void
    {
        unset($this->boxes[$i]);
        $this->boxes = array_values($this->boxes) ?: [['box_number' => '', 'tracking_number' => '']];
    }

    protected function syncLinkedInvoiceFromOrder(SalesOrder $order, float $oldInvoiceTotal): void
    {
        $invoice = $order->invoice;
        if (! $invoice) {
            return;
        }

        $invoice->loadMissing(['payments', 'credits']);
        $lineDiscount = (float) $order->lines->sum('discount');
        $newTotal = round((float) $order->total, 4);
        $applied = (float) $invoice->payments->sum('amount') + (float) $invoice->credits->sum('amount');
        if ($newTotal + 0.0001 < $applied) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice total cannot be less than payments and credits already applied ($'.number_format($applied, 2).').',
            ]);
        }

        $oldCustomerId = $invoice->customer_id ? (int) $invoice->customer_id : null;
        $newCustomerId = $order->customer_id ? (int) $order->customer_id : null;

        $invoice->update([
            'customer_id' => $newCustomerId,
            'subtotal' => $order->subtotal,
            'total_discount' => $lineDiscount,
            'trade_discount' => $order->trade_discount,
            'freight' => $order->freight,
            'miscellaneous' => $order->miscellaneous,
            'tax' => $order->tax,
            'invoice_total' => $newTotal,
            'status' => ($newTotal - $applied) <= 0.0001 ? 'PAID' : 'NOT PAID',
        ]);

        if ($oldCustomerId && $oldCustomerId !== $newCustomerId) {
            $oldCustomer = Customer::query()->lockForUpdate()->find($oldCustomerId);
            if ($oldCustomer) {
                $oldCustomer->update([
                    'balance' => (float) $oldCustomer->balance - $oldInvoiceTotal,
                ]);
            }
            if ($newCustomerId) {
                $newCustomer = Customer::query()->lockForUpdate()->find($newCustomerId);
                if ($newCustomer) {
                    $newCustomer->update([
                        'balance' => (float) $newCustomer->balance + $newTotal,
                    ]);
                }
            }
        } elseif ($newCustomerId) {
            $delta = $newTotal - $oldInvoiceTotal;
            if (abs($delta) > 0.0001) {
                $customer = Customer::query()->lockForUpdate()->find($newCustomerId);
                if ($customer) {
                    $customer->update([
                        'balance' => (float) $customer->balance + $delta,
                    ]);
                }
            }
        }
    }

    public function save(): void
    {
        abort_if($this->viewMode, 403);

        $this->flushPendingLineMessageEdits();

        if ($this->salesOrder?->exists) {
            $this->salesOrder->refresh()->loadMissing('invoice');
        }

        try {
            $this->validate([
                'order_number' => 'required|string|max:64',
                'customer_id' => 'required|integer|exists:customers,id',
                'order_date' => 'required|date',
                'order_type' => 'required|string|max:64',
            ], [
                'order_number.required' => 'Order number is required.',
                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Select a valid customer.',
                'order_date.required' => 'Order date is required.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->activeTab = 'general';
            throw $e;
        }

        $hasLines = collect($this->lines)->contains(fn ($l) => filled($l['item_code'] ?? null) && (float) ($l['qty_ordered'] ?? 0) > 0);
        if (! $hasLines) {
            $this->addError('lines', 'Add at least one line item with an item code and quantity.');
            $this->activeTab = 'items';

            return;
        }

        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null) || (float) ($line['qty_ordered'] ?? 0) <= 0) {
                continue;
            }
            if (empty($line['item_id'])) {
                $resolved = $this->findItem(trim((string) $line['item_code']));
                if ($resolved) {
                    $this->lines[$i]['item_id'] = $resolved->id;
                    $line['item_id'] = $resolved->id;
                } else {
                    $this->addError('lines', 'Item "'.$line['item_code'].'" is not a valid sellable item.');
                    $this->activeTab = 'items';

                    return;
                }
            }
        }

        $neededByItem = [];
        foreach ($this->lines as $line) {
            if (empty($line['item_id']) || (float) ($line['qty_ordered'] ?? 0) <= 0) {
                continue;
            }
            $itemId = (int) $line['item_id'];
            $neededByItem[$itemId] = ($neededByItem[$itemId] ?? 0) + (float) $line['qty_ordered'];
        }

        $previousByItem = [];
        if ($this->salesOrder?->exists) {
            $this->salesOrder->loadMissing('lines');
            foreach ($this->salesOrder->lines as $prev) {
                if (! $prev->item_id) {
                    continue;
                }
                $oldQty = (float) $prev->qty_ordered;
                $shipped = (float) ($prev->qty_shipped ?? 0);
                if ($shipped > 0) {
                    $oldQty = $shipped;
                }
                $previousByItem[(int) $prev->item_id] = ($previousByItem[(int) $prev->item_id] ?? 0) + $oldQty;
            }
        }

        $isInvoicedDoc = (bool) $this->salesOrder?->invoice;
        foreach ($neededByItem as $itemId => $needed) {
            $item = Item::query()->find($itemId);
            if (! $item) {
                $this->addError('lines', 'One or more items could not be found.');
                $this->activeTab = 'items';

                return;
            }
            $oldQty = (float) ($previousByItem[$itemId] ?? 0);
            if ($isInvoicedDoc) {
                $extra = $needed - $oldQty;
                if ($extra > 0) {
                    $err = StockPolicy::invoiceQtyError($item, $extra, (float) $item->quantity_in_stock);
                    if ($err) {
                        $this->addError('lines', $err);
                        $this->activeTab = 'items';

                        return;
                    }
                }
            } else {
                $available = (float) $item->available_quantity + $oldQty;
                $err = StockPolicy::orderQtyError($item, $needed, $available);
                if ($err) {
                    $this->addError('lines', $err);
                    $this->activeTab = 'items';

                    return;
                }
            }
        }

        $this->lineWarning = '';

        if (! $this->userCanChangeOrderPrice()) {
            foreach ($this->lines as $i => $line) {
                $this->restoreLineSystemPrice($i);
            }
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $decimal = static fn ($v): float => ($v === null || $v === '') ? 0.0 : (float) $v;
        $subtotal = collect($this->lines)->sum(fn ($l) => filled($l['item_code'] ?? null)
            ? (((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount'])
            : 0);
        $tradeDiscount = $decimal($this->trade_discount);
        $freight = $decimal($this->freight);
        $miscellaneous = $decimal($this->miscellaneous);
        $tax = $decimal($this->tax);
        $total = $subtotal - $tradeDiscount + $freight + $miscellaneous + $tax;

        $companyId = (int) auth()->user()->company_id;
        $isNewOrder = ! $this->salesOrder?->exists;

        $data = [
            'company_id' => $companyId,
            'order_number' => $this->order_number,
            'order_type' => 'Sales Order',
            'status' => $this->status,
            'priority' => $this->priority,
            'customer_id' => $nullableId($this->customer_id),
            'ship_to_address_id' => $nullableId($this->ship_to_address_id),
            'bill_to_name' => $this->bill_to_name,
            'bill_to_phone' => $this->bill_to_phone,
            'bill_to_address' => $this->bill_to_address,
            'bill_to_city' => $this->bill_to_city,
            'bill_to_state' => $this->bill_to_state,
            'bill_to_zip' => $this->bill_to_zip,
            'ship_to_name' => $this->ship_to_name,
            'ship_to_phone' => $this->ship_to_phone,
            'ship_to_address' => $this->ship_to_address,
            'ship_to_city' => $this->ship_to_city,
            'ship_to_state' => $this->ship_to_state,
            'ship_to_zip' => $this->ship_to_zip,
            'order_date' => $this->order_date ?: null,
            'required_date' => $this->required_date ?: null,
            'customer_po_no' => $this->customer_po_no !== '' ? $this->customer_po_no : null,
            'reference_no' => $this->reference_no !== '' ? $this->reference_no : null,
            'sales_rep_id' => $nullableId($this->sales_rep_id),
            'payment_term_id' => $nullableId($this->payment_term_id),
            'route_id' => $nullableId($this->route_id),
            'ship_via_id' => $nullableId($this->ship_via_id),
            'ship_from_site_id' => $nullableId($this->ship_from_site_id),
            'ship_date' => $this->ship_date ?: null,
            'no_of_boxes' => (int) ($this->no_of_boxes !== '' ? $this->no_of_boxes : 0),
            'no_of_pallets' => (int) ($this->no_of_pallets !== '' ? $this->no_of_pallets : 0),
            'custom_field_1' => $this->custom_field_1 !== '' ? $this->custom_field_1 : null,
            'custom_field_2' => $this->custom_field_2 !== '' ? $this->custom_field_2 : null,
            'custom_field_3' => $this->custom_field_3 !== '' ? $this->custom_field_3 : null,
            'custom_field_4' => $this->custom_field_4 !== '' ? $this->custom_field_4 : null,
            'custom_field_5' => $this->custom_field_5 !== '' ? $this->custom_field_5 : null,
            'comments' => $this->comments !== '' ? $this->comments : null,
            'subtotal' => $subtotal,
            'trade_discount' => $tradeDiscount,
            'freight' => $freight,
            'miscellaneous' => $miscellaneous,
            'tax' => $tax,
            'total' => $total,
            'created_by' => $this->salesOrder?->created_by ?? auth()->id(),
        ];

        if ($isNewOrder) {
            $data['order_source'] = \App\Models\SalesOrder::SOURCE_POS;
        }

        if ($this->salesOrder?->invoice) {
            $data['status'] = 'Invoiced';
        }

        $previousItemIds = [];
        if ($this->salesOrder?->exists) {
            $this->salesOrder->loadMissing('lines');
            $previousItemIds = $this->salesOrder->lines
                ->pluck('item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $savedOrder = null;
        DB::transaction(function () use (&$data, $companyId, &$savedOrder, $previousItemIds) {
            $revisingInvoice = false;
            $oldQtyByItem = [];
            $oldInvoiceTotal = 0.0;
            $linkedInvoice = null;

            if ($this->salesOrder) {
                $this->salesOrder->load(['lines', 'invoice']);
                $linkedInvoice = $this->salesOrder->invoice
                    ?? Invoice::query()->where('sales_order_id', $this->salesOrder->id)->first();
                $revisingInvoice = $linkedInvoice !== null;
                if ($revisingInvoice) {
                    $oldInvoiceTotal = (float) $linkedInvoice->invoice_total;
                }
                foreach ($this->salesOrder->lines as $oldLine) {
                    if (! $oldLine->item_id) {
                        continue;
                    }
                    $qty = (float) $oldLine->qty_ordered;
                    $shipped = (float) ($oldLine->qty_shipped ?? 0);
                    if ($shipped > 0) {
                        $qty = $shipped;
                    }
                    $oldQtyByItem[(int) $oldLine->item_id] = ($oldQtyByItem[(int) $oldLine->item_id] ?? 0) + $qty;
                }
                $this->salesOrder->update($data);
                $order = $this->salesOrder->fresh();
                $order->lines()->delete();
                $order->boxes()->delete();
            } else {
                $candidate = filled($this->order_number) ? (string) $this->order_number : SalesOrder::nextNumber($companyId);
                if (
                    SalesOrder::query()
                        ->where('company_id', $companyId)
                        ->where('order_number', $candidate)
                        ->exists()
                ) {
                    $candidate = SalesOrder::nextNumber($companyId);
                }
                $data['order_number'] = $candidate;
                $this->order_number = $candidate;
                $order = SalesOrder::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null)) {
                    continue;
                }
                if (empty($line['item_id'])) {
                    continue;
                }
                $qty = (float) $line['qty_ordered'];
                if ($qty <= 0) {
                    continue;
                }
                $price = (float) $line['price'];
                $unitDiscount = (float) ($line['unit_discount'] ?? 0);
                $discount = isset($line['unit_discount'])
                    ? round($qty * $unitDiscount, 4)
                    : (float) ($line['discount'] ?? 0);
                $order->lines()->create([
                    'item_id' => (int) $line['item_id'],
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty_ordered' => $qty,
                    'qty_shipped' => $revisingInvoice ? $qty : 0,
                    'price' => $price,
                    'discount' => $discount,
                    'line_message' => $this->persistableLineMessage($line),
                    'instructions' => filled($line['instructions'] ?? null) ? $line['instructions'] : null,
                    'line_total' => ($qty * $price) - $discount,
                    'line_no' => $i + 1,
                ]);
            }

            foreach (array_values($this->boxes) as $i => $box) {
                if (! filled($box['box_number'] ?? null) && ! filled($box['tracking_number'] ?? null)) {
                    continue;
                }
                $order->boxes()->create([
                    'box_number' => $box['box_number'] ?: null,
                    'tracking_number' => $box['tracking_number'] ?: null,
                    'sort_order' => $i,
                ]);
            }

            $savedOrder = $order->fresh(['lines', 'invoice']);
            $invoiceForRevision = $savedOrder?->invoice ?? $linkedInvoice;
            if ($savedOrder && $this->salesOrder) {
                if ($invoiceForRevision) {
                    $savedOrder->setRelation('invoice', $invoiceForRevision);
                }
                app(InventoryService::class)->applyOrderQtyRevision(
                    $savedOrder,
                    $oldQtyByItem,
                    $revisingInvoice ? $invoiceForRevision : null
                );
                if ($revisingInvoice && $invoiceForRevision) {
                    $this->syncLinkedInvoiceFromOrder($savedOrder, $oldInvoiceTotal);
                }
            }
        });

        $itemIds = collect($this->lines)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $itemIds = array_values(array_unique(array_merge($itemIds, $previousItemIds)));
        if ($savedOrder) {
            $itemIds = array_values(array_unique(array_merge(
                $itemIds,
                $savedOrder->lines()->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all()
            )));
            $this->salesOrder = $savedOrder->load(['lines.item', 'invoice']);
            $this->lines = $this->salesOrder->lines->map(function ($l) {
                $qty = (float) $l->qty_ordered;
                $discount = (float) $l->discount;
                $unitDiscount = $qty > 0 ? round($discount / $qty, 4) : $discount;
                $price = $this->formatMoney($l->price);

                return [
                    'item_id' => $l->item_id,
                    'item_code' => $l->item_code ?? '',
                    'description' => $l->description ?? '',
                    'uom' => $l->uom ?? '',
                    'qty_ordered' => $this->formatQty($l->qty_ordered),
                    'qty_shipped' => $this->formatQty($l->qty_shipped ?? 0),
                    'price' => $price,
                    'system_price' => $price,
                    'unit_discount' => $this->formatMoney($unitDiscount),
                    'discount' => $this->formatMoney($l->discount),
                    'line_message' => (string) ($l->line_message ?? ''),
                    'instructions' => (string) ($l->instructions ?? ''),
                ];
            })->values()->all();
            if ($this->selectedLineIndex !== null && isset($this->lines[$this->selectedLineIndex])) {
                $this->selectLine($this->selectedLineIndex);
            }
        }
        app(InventoryService::class)->syncAllocatedQty($itemIds);
        $this->refreshBrowseStockFromDatabase($itemIds);
        $this->refreshSelectedLineStock();

        if ($this->shouldReturnToInvoiceList()) {
            session()->flash('status', 'Invoice updated.');
            $this->redirect(route('sales.invoices.index'), navigate: true);

            return;
        }

        $this->dismissTransientOverlays();
        $this->showPrintDialog = true;
        $this->optCreateInvoicePayment = false;
        $this->optPrintSalesOrder = false;
        $this->optCreatePrintInvoice = false;
        $this->optPrintPickList = false;
        $this->notifyAlert(
            $isNewOrder
                ? 'Order created. Choose print options, then OK.'
                : ($this->salesOrder?->invoice
                    ? 'Invoice and order saved. Stock and totals were updated.'
                    : 'Order saved. Choose print options, then OK.'),
            'success'
        );
    }

    public function confirmPrintDialog(): void
    {
        if (! $this->salesOrder?->exists) {
            $this->showPrintDialog = false;
            $this->finishCreateWindowAndRedirect();

            return;
        }

        $order = $this->salesOrder->fresh(['lines', 'customer', 'invoice']);

        $canPay = auth()->user()?->canAccessFeature('sales.payments', 'edit') ?? false;
        $canInvoice = auth()->user()?->canAccessFeature('sales.invoices', 'edit') ?? false;
        if ($this->optCreateInvoicePayment && ! $canPay) {
            $this->optCreateInvoicePayment = false;
        }
        if ($this->optCreatePrintInvoice && ! $canInvoice) {
            $this->optCreatePrintInvoice = false;
        }

        $needInvoice = $this->optCreateInvoicePayment || $this->optCreatePrintInvoice;
        $invoice = $order->invoice;

        if ($needInvoice && (! $invoice || $order->status !== 'Invoiced')) {
            try {
                $invoice = $this->createInvoiceForOrder($order);
                $order = $order->fresh(['lines', 'customer', 'invoice']);
            } catch (\Throwable $e) {
                $this->notifyAlert('Could not create invoice: '.$e->getMessage(), 'error');
                $this->showPrintDialog = false;

                return;
            }
        }

        $urls = [];
        if ($this->optPrintSalesOrder) {
            $urls[] = route('sales.orders.print', $order);
        }
        if ($this->optCreatePrintInvoice && $invoice) {
            $urls[] = route('sales.invoices.pdf', $invoice);
        }
        if ($this->optPrintPickList) {
            $urls[] = route('sales.orders.pick-list', $order).'?v='.time();
        }

        $this->showPrintDialog = false;

        if ($urls !== []) {
            $this->dispatch('open-order-print-urls', urls: $urls);
        }

        if ($this->optCreateInvoicePayment && $invoice) {
            session()->flash('status', 'Invoice '.$invoice->invoice_number.' created. Open Payments to collect.');
            $this->finishCreateWindowAndRedirect();

            return;
        }

        if ($this->optCreatePrintInvoice && $invoice) {
            session()->flash('status', 'Invoice '.$invoice->invoice_number.' created.');
        } else {
            session()->flash('status', 'Order '.$order->order_number.' saved.');
        }

        $this->finishSaveStayOnOrder();
    }

    public function cancelPrintDialog(): void
    {
        $this->showPrintDialog = false;
        $number = $this->salesOrder?->order_number;
        session()->flash('status', $number !== '' && $number !== null ? 'Order '.$number.' saved.' : 'Order saved.');
        $this->finishSaveStayOnOrder();
    }

    /**
     * After save/print: keep editing a New order instead of dumping to Park-only create.
     */
    protected function finishSaveStayOnOrder(): void
    {
        if ($this->shouldReturnToInvoiceList()) {
            $this->finishCreateWindowAndRedirect();

            return;
        }

        $order = $this->salesOrder?->fresh(['invoice']);
        if ($order?->exists && $order->status !== 'Invoiced' && ! $order->invoice) {
            $this->createWindowId = null;
            $this->redirect(route('sales.orders.edit', $order), navigate: true);

            return;
        }

        $this->finishCreateWindowAndRedirect();
    }

    protected function createInvoiceForOrder(SalesOrder $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $order = SalesOrder::query()->with(['lines', 'customer', 'invoice'])->lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $order->company_id === (int) auth()->user()->company_id, 403);

            if ($order->invoice instanceof Invoice) {
                return $order->invoice;
            }

            $lineDiscount = (float) $order->lines->sum('discount');
            $invoice = Invoice::query()->create([
                'company_id' => (int) $order->company_id,
                'invoice_number' => Invoice::nextNumber((int) $order->company_id),
                'invoice_date' => now()->toDateString(),
                'sales_order_id' => (int) $order->getKey(),
                'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
                'status' => 'NOT PAID',
                'subtotal' => $order->subtotal,
                'total_discount' => $lineDiscount,
                'trade_discount' => $order->trade_discount,
                'freight' => $order->freight,
                'miscellaneous' => $order->miscellaneous,
                'tax' => $order->tax,
                'invoice_total' => $order->total,
                'driver' => null,
            ]);

            app(InventoryService::class)->applyInvoiceStock($order, $invoice);
            $order->update(['status' => 'Invoiced']);

            $customer = $order->customer()->first();
            if ($customer instanceof \App\Models\Customer) {
                $updates = [
                    'last_order_on' => $order->order_date ?? now()->toDateString(),
                    'number_of_orders' => (int) $customer->number_of_orders + 1,
                    'total_sales' => (float) $customer->total_sales + (float) $order->total,
                    'balance' => (float) $customer->balance + (float) $order->total,
                ];
                if (blank($customer->customer_since)) {
                    $updates['customer_since'] = $order->order_date ?? now()->toDateString();
                }
                $customer->update($updates);
            }

            return $invoice;
        });
    }

    public function cancelAction(): mixed
    {
        if ($this->salesOrder?->exists) {
            return $this->redirect(route('sales.orders.index'), navigate: true);
        }

        if ($this->createWindowId) {
            $this->closeCreateWindow($this->createWindowId);

            return null;
        }

        return $this->redirect(route('sales.orders.index'), navigate: true);
    }
}; ?>

<div class="so-page" wire:key="so-{{ $createWindowId ?? (($salesOrder instanceof \App\Models\SalesOrder) ? $salesOrder->id : 'new') }}-{{ $viewMode ? 'view' : 'edit' }}">
    <x-action-bar :title="$pageTitle" class="so-action-full">
        <x-slot:menu>
            <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" :disabled="$viewMode" />
            @if ($viewMode)
                <x-action-item label="Edit Order" kbd="Ctrl+E" sep wire:click="enterEditMode" />
            @else
                <x-action-item label="Edit" kbd="Ctrl+E" sep wire:click="openOpenOrderModal" />
            @endif
            <x-action-item label="Export Lines to Excel" sep wire:click="exportLinesToExcel" />
            <x-action-item label="Cancel" kbd="Ctrl+Z" sep wire:click="cancelAction" />
        </x-slot:menu>
    </x-action-bar>

    <form id="so-form" wire:submit="save" class="so-screen" @class(['so-form-readonly' => $viewMode])>
        <fieldset class="so-form-fields" @disabled($viewMode)>
        @if (session('status'))
            <div class="so-msg so-msg-info" role="status" x-data x-init="window.scheduleSoBannerDismiss && window.scheduleSoBannerDismiss('status', $el)">{{ session('status') }}</div>
        @endif
        @if (filled($orderLockMessage))
            <div class="so-msg so-msg-info" role="status">
                {{ $orderLockMessage }}
            </div>
        @endif
        @if ($errors->any())
            <div
                class="so-msg so-msg-danger"
                role="alert"
                wire:key="so-form-errors-{{ md5(implode('|', $errors->all())) }}"
                x-data
                x-init="window.scheduleSoBannerDismiss && window.scheduleSoBannerDismiss('errors')"
            >
                <strong>Error:</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.15rem">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (filled($customerAlert))
            <div class="so-msg so-msg-alert" role="alert">
                <strong>Alert:</strong> {{ $customerAlert }}
            </div>
        @endif
        @if (filled($creditWarning))
            <div class="so-msg so-msg-credit" role="alert">
                <strong>Credit:</strong> {{ $creditWarning }}
            </div>
        @endif
        @if (filled($taxExemptWarning))
            <div class="so-msg so-msg-danger" role="alert">
                <strong>Tax Exempt:</strong> {{ $taxExemptWarning }}
            </div>
        @endif
        @if (filled($lineWarning))
            <div
                class="so-msg {{ in_array($lineWarningKind, ['error', 'danger'], true) ? 'so-msg-danger' : (in_array($lineWarningKind, ['success', 'info'], true) ? 'so-msg-info' : 'so-msg-alert') }}"
                role="alert"
                wire:key="so-line-warning-{{ md5($lineWarning.'|'.$lineWarningKind) }}"
                x-data
                x-init="window.scheduleSoBannerDismiss && window.scheduleSoBannerDismiss('line')"
            >
                <strong>{{ in_array($lineWarningKind, ['error', 'danger'], true) ? 'Alert' : (in_array($lineWarningKind, ['success', 'info'], true) ? 'Note' : 'Warning') }}:</strong>
                {{ $lineWarning }}
            </div>
        @endif

        <div class="so-body">
            <div class="so-header" id="mode-panel-general" role="tabpanel" aria-labelledby="mode-tab-general" @style(['display: none' => $activeTab !== 'general'])>
                <div class="so-form-card">
                    <div class="so-form-layout">
                        <div class="so-form-main" aria-label="Order customer and address">
                            <div class="so-form-row so-form-row-pair">
                                <label class="so-form-lbl" for="order_type">Order Type</label>
                                <input id="order_type" class="so-input" value="Sales Order" readonly aria-label="Order Type" />
                                <label class="so-form-lbl so-field-req" for="order_number">Order No</label>
                                <div class="so-lookup-row">
                                    <input id="order_number" wire:model="order_number" class="so-input font-mono @error('order_number') is-invalid @enderror" aria-label="Order Number" readonly title="Auto-generated" />
                                    <button
                                        type="button"
                                        wire:click="regenerateOrderNumber"
                                        class="so-icon-btn"
                                        title="Regenerate order number"
                                        aria-label="Regenerate order number"
                                        @disabled($salesOrder?->exists)
                                    >
                                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 6h8M6 2v8"/></svg>
                                    </button>
                                </div>
                            </div>
                            @error('order_number') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror

                            <div class="so-form-row">
                                <label class="so-form-lbl so-field-req" for="customer_id">Customer</label>
                                <div class="so-form-ctl">
                                    <div class="so-lookup-row">
                                        <select id="customer_id" wire:model.live="customer_id" class="so-input @error('customer_id') is-invalid @enderror" aria-label="Customer">
                                            <option value="">—</option>
                                            @foreach ($customers as $c)
                                                <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                                            @endforeach
                                        </select>
                                        <button
                                            type="button"
                                            wire:click="toggleCustomerFavoriteIcon"
                                            @class(['so-icon-btn', 'is-fav-on' => $customerFavoritesOnly || $selectedCustomerIsFavorite])
                                            title="{{ $customer_id ? ($selectedCustomerIsFavorite ? 'Remove from favorites' : 'Add to favorites') : ($customerFavoritesOnly ? 'Show all customers' : 'Show favorite customers only') }}"
                                            aria-label="Customer favorite"
                                            aria-pressed="{{ ($customerFavoritesOnly || $selectedCustomerIsFavorite) ? 'true' : 'false' }}"
                                            @disabled($viewMode)
                                        >
                                            <svg viewBox="0 0 12 12" fill="{{ ($customerFavoritesOnly || $selectedCustomerIsFavorite) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.2"><path d="M6 10.2l-3.5-2.1A2.7 2.7 0 016 2.4a2.7 2.7 0 013.5 5.7L6 10.2z"/></svg>
                                        </button>
                                        <button type="button" wire:click="toggleCustomerBrowse" class="so-icon-btn" title="Search" aria-label="Search customer">
                                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="5" r="3.2"/><path d="M7.5 7.5L10.5 10.5"/></svg>
                                        </button>
                                        <a href="{{ route('sales.customers.create') }}" wire:navigate class="so-icon-btn" title="New" aria-label="New customer">
                                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                                        </a>
                                        <button type="button" wire:click="toggleCustomerBrowse" class="so-icon-btn" title="Browse" aria-label="Browse customers">
                                            <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                                        </button>
                                    </div>
                                    @error('customer_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                                    @if ($showCustomerBrowse)
                                        <div class="so-lookup-panel" role="dialog" aria-label="Customer browse">
                                            <div class="so-lookup-panel-head">
                                                <input type="text" wire:model.live.debounce.200ms="customerSearch" class="so-input" placeholder="Search customer ID, name, phone…" aria-label="Search customers" />
                                                <button type="button" wire:click="$set('showCustomerBrowse', false)" class="so-icon-btn" title="Close" aria-label="Close">×</button>
                                            </div>
                                            <table class="so-lookup-table">
                                                <thead>
                                                    <tr><th style="width:2rem"></th><th>ID</th><th>Company</th><th>Contact</th><th>Phone</th><th>City</th></tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($browseCustomers as $bc)
                                                        <tr wire:click="pickCustomer({{ $bc->id }})" class="cursor-pointer so-lookup-row-pick">
                                                            <td class="text-center" wire:click.stop>
                                                                <button
                                                                    type="button"
                                                                    wire:click="toggleBrowseCustomerFavorite({{ $bc->id }})"
                                                                    @class(['so-icon-btn', 'so-icon-btn-sm', 'is-fav-on' => $bc->is_favorite])
                                                                    title="{{ $bc->is_favorite ? 'Remove favorite' : 'Add favorite' }}"
                                                                    aria-label="Toggle favorite"
                                                                >
                                                                    <svg viewBox="0 0 12 12" fill="{{ $bc->is_favorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.2"><path d="M6 10.2l-3.5-2.1A2.7 2.7 0 016 2.4a2.7 2.7 0 013.5 5.7L6 10.2z"/></svg>
                                                                </button>
                                                            </td>
                                                            <td class="font-mono">{{ $bc->customer_id }}</td>
                                                            <td>{{ $bc->company_name }}</td>
                                                            <td>{{ $bc->contact }}</td>
                                                            <td>{{ $bc->telephone }}</td>
                                                            <td>{{ $bc->city }}{{ $bc->state ? ', '.$bc->state : '' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="6" class="text-slate-500 px-2 py-2">{{ $customerFavoritesOnly ? 'No favorite customers found.' : 'No customers found.' }}</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="so-form-row">
                                <label class="so-form-lbl" for="ship_to_address_id">Ship to</label>
                                <div class="so-form-ctl">
                                    <div class="so-lookup-row">
                                        <select id="ship_to_address_id" wire:model.live="ship_to_address_id" class="so-input" aria-label="Ship to">
                                            <option value="">—</option>
                                            @if ($selectedCustomer)
                                                @foreach ($selectedCustomer->shippingAddresses as $addr)
                                                    <option value="{{ $addr->id }}">{{ $addr->name ?: $addr->address ?: 'Ship-To #'.$addr->id }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                        <button
                                            type="button"
                                            wire:click="openShipToModal"
                                            class="so-icon-btn"
                                            title="{{ $customer_id ? 'Add ship-to address' : 'Select a customer first' }}"
                                            aria-label="Add ship-to address"
                                            @disabled(! $customer_id || $viewMode)
                                        >
                                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                                        </button>
                                        <button type="button" wire:click="toggleShipBrowse" class="so-icon-btn" title="Browse" aria-label="Browse ship-to" @disabled(! $customer_id)>
                                            <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                                        </button>
                                    </div>
                                    @if ($shipToFlash !== '')
                                        <p class="so-field-hint" style="color:#065f46;margin:.25rem 0 0;font-size:.78rem;" role="status">{{ $shipToFlash }}</p>
                                    @endif
                                    @if (! $viewMode && $customer_id && $selectedCustomer && $selectedCustomer->shippingAddresses->isEmpty())
                                        <p class="so-field-hint" style="margin:.25rem 0 0;font-size:.78rem;color:#9a3412;">
                                            No ship-to for this customer — click <strong>+</strong> to add one.
                                        </p>
                                    @endif

                                    @if ($showShipBrowse && $selectedCustomer)
                                        <div class="so-lookup-panel" role="dialog" aria-label="Ship-to browse">
                                            <div class="so-lookup-panel-head">
                                                <span class="text-xs font-semibold text-slate-700">Ship-to addresses</span>
                                                <button type="button" wire:click="openShipToModal" class="so-icon-btn" title="Add ship-to" aria-label="Add ship-to" @disabled($viewMode)>
                                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                                                </button>
                                                <button type="button" wire:click="$set('showShipBrowse', false)" class="so-icon-btn" title="Close" aria-label="Close">×</button>
                                            </div>
                                            <table class="so-lookup-table">
                                                <thead>
                                                    <tr><th>Name</th><th>Address</th><th>City</th><th>Phone</th><th></th></tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($selectedCustomer->shippingAddresses as $addr)
                                                        <tr wire:click="pickShipTo({{ $addr->id }})" class="cursor-pointer hover:bg-sky-100">
                                                            <td>{{ $addr->name }}@if ($addr->is_primary) <span class="text-green-700">●</span>@endif</td>
                                                            <td>{{ $addr->address }}</td>
                                                            <td>{{ collect([$addr->city, $addr->state, $addr->zip])->filter()->implode(', ') }}</td>
                                                            <td>{{ $addr->telephone }}</td>
                                                            <td class="text-sky-700 underline text-xs">Select</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="text-slate-500 px-2 py-2">
                                                                No ship-to addresses.
                                                                @if (! $viewMode)
                                                                    <button type="button" wire:click="openShipToModal" class="text-sky-700 underline ml-1">Add ship-to</button>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="so-addr-block">
                                <div class="so-addr-tabs" role="tablist" aria-label="Address type">
                                    <button type="button" role="tab" aria-selected="{{ $addressTab === 'bill' ? 'true' : 'false' }}" wire:click="$set('addressTab', 'bill')" @class(['so-addr-tab', 'so-addr-tab-active' => $addressTab === 'bill'])>Bill To Address</button>
                                    <button type="button" role="tab" aria-selected="{{ $addressTab === 'ship' ? 'true' : 'false' }}" wire:click="$set('addressTab', 'ship')" @class(['so-addr-tab', 'so-addr-tab-active' => $addressTab === 'ship'])>Ship To Address</button>
                                </div>
                                <div class="so-addr-fields">
                                    @if ($addressTab === 'bill')
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="bill_to_name">Name</label>
                                            <input id="bill_to_name" wire:model="bill_to_name" class="so-input" aria-label="Bill to name" />
                                        </div>
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="bill_to_phone">Phone No.</label>
                                            <input id="bill_to_phone" wire:model="bill_to_phone" class="so-input" aria-label="Bill to phone" />
                                        </div>
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="bill_to_address">Address</label>
                                            <input id="bill_to_address" wire:model="bill_to_address" class="so-input" aria-label="Bill to address" />
                                        </div>
                                        <div class="so-form-row so-form-row-city">
                                            <label class="so-form-lbl" for="bill_to_city">City</label>
                                            <input id="bill_to_city" wire:model="bill_to_city" class="so-input" aria-label="Bill to city" />
                                            <label class="so-form-lbl so-form-lbl-sm" for="bill_to_state">State</label>
                                            <input id="bill_to_state" wire:model="bill_to_state" class="so-input so-w-state" aria-label="Bill to state" />
                                            <label class="so-form-lbl so-form-lbl-sm" for="bill_to_zip">ZIP</label>
                                            <input id="bill_to_zip" wire:model="bill_to_zip" class="so-input so-w-zip" aria-label="Bill to ZIP" />
                                        </div>
                                    @else
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="ship_to_name">Name</label>
                                            <input id="ship_to_name" wire:model="ship_to_name" class="so-input" aria-label="Ship to name" />
                                        </div>
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="ship_to_phone">Phone No.</label>
                                            <input id="ship_to_phone" wire:model="ship_to_phone" class="so-input" aria-label="Ship to phone" />
                                        </div>
                                        <div class="so-form-row">
                                            <label class="so-form-lbl" for="ship_to_address">Address</label>
                                            <input id="ship_to_address" wire:model="ship_to_address" class="so-input" aria-label="Ship to address" />
                                        </div>
                                        <div class="so-form-row so-form-row-city">
                                            <label class="so-form-lbl" for="ship_to_city">City</label>
                                            <input id="ship_to_city" wire:model="ship_to_city" class="so-input" aria-label="Ship to city" />
                                            <label class="so-form-lbl so-form-lbl-sm" for="ship_to_state">State</label>
                                            <input id="ship_to_state" wire:model="ship_to_state" class="so-input so-w-state" aria-label="Ship to state" />
                                            <label class="so-form-lbl so-form-lbl-sm" for="ship_to_zip">ZIP</label>
                                            <input id="ship_to_zip" wire:model="ship_to_zip" class="so-input so-w-zip" aria-label="Ship to ZIP" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <aside class="so-form-side" aria-label="Order status and dates">
                            <div class="so-side-title">Order details</div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="status">Status</label>
                                <input id="status" wire:model="status" class="so-input" readonly aria-label="Order Status" />
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="priority">Priority</label>
                                <select id="priority" wire:model="priority" class="so-input" aria-label="Priority">
                                    <option>Normal</option>
                                    <option>High</option>
                                    <option>Low</option>
                                </select>
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl so-field-req" for="order_date">Order Date</label>
                                <div class="so-form-ctl">
                                    <input id="order_date" type="date" wire:model="order_date" class="so-input @error('order_date') is-invalid @enderror" aria-label="Order Date" />
                                    @error('order_date') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="required_date">Required</label>
                                <input id="required_date" type="date" wire:model="required_date" class="so-input" aria-label="Required Date" />
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="customer_po_no">Customer PO</label>
                                <input id="customer_po_no" wire:model="customer_po_no" class="so-input" aria-label="Customer PO Number" />
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="reference_no">Reference</label>
                                <input id="reference_no" wire:model="reference_no" class="so-input" aria-label="Reference Number" />
                            </div>
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl" for="sales_rep_id">Sales Rep</label>
                                <select id="sales_rep_id" wire:model="sales_rep_id" class="so-input" aria-label="Sales Rep">
                                    <option value="">—</option>
                                    @foreach ($salesReps as $r)
                                        <option value="{{ $r['id'] ?? '' }}">{{ $r['name'] ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
                <div class="so-expand-panel" id="mode-panel-items" role="tabpanel" aria-labelledby="mode-tab-items" @style(['display: none' => $activeTab !== 'items'])
                    x-data="{
                        ctxOpen: false,
                        ctxX: 0,
                        ctxY: 0,
                        ctxLine: null,
                        openCtx(e, i) {
                            e.preventDefault();
                            this.ctxLine = i;
                            this.ctxX = Math.min(e.clientX, window.innerWidth - 220);
                            this.ctxY = Math.min(e.clientY, window.innerHeight - 220);
                            this.ctxOpen = true;
                            $wire.selectLine(i);
                        },
                        closeCtx() { this.ctxOpen = false; this.ctxLine = null; }
                    }"
                    @click="closeCtx()"
                    @keydown.escape.window="closeCtx()"
                >
                <div class="so-expand-main">
                <div class="so-items-wrap so-items-wrap-tall">
                    <div class="so-items-title">Items</div>
                    <div class="so-items-grid">
                        <table class="so-lines-table" data-excel-grid>
                            <colgroup>
                                <col class="col-code" />
                                <col class="col-desc" />
                                <col class="col-uom" />
                                <col class="col-qty" />
                                <col class="col-num" />
                                <col class="col-num" />
                                <col class="col-num" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-code">Item Code</th>
                                    <th class="col-desc">Description</th>
                                    <th class="col-uom">U of M</th>
                                    <th class="col-qty">Qty Ordered</th>
                                    <th class="col-num">Price</th>
                                    <th class="col-num">Discount</th>
                                    <th class="col-num">Total</th>
                                    <th class="col-action"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    @php
                                        $filled = filled($line['item_code'] ?? null);
                                        if (! $filled) {
                                            continue;
                                        }
                                        $qty = (float) ($line['qty_ordered'] ?? 0);
                                        $unitDisc = (float) ($line['unit_discount'] ?? 0);
                                        $lineDisc = (float) ($line['discount'] ?? ($qty * $unitDisc));
                                    @endphp
                                    <tr
                                        wire:key="so-line-{{ $i }}"
                                        id="so-line-row-{{ $i }}"
                                        @class(['is-selected' => $selectedLineIndex === $i, 'is-filled' => $filled])
                                        wire:click="selectLine({{ $i }})"
                                        @contextmenu="openCtx($event, {{ $i }})"
                                    >
                                        <td class="col-code desk-num" data-excel-value="{{ $filled ? $line['item_code'] : '' }}">{{ $filled ? $line['item_code'] : '—' }}</td>
                                        <td class="col-desc" title="{{ $line['description'] ?? '' }}">
                                            <span class="so-line-desc-text">{{ $filled ? ($line['description'] ?: '—') : '—' }}</span>
                                            @if ($filled && filled($line['line_message'] ?? null))
                                                <button
                                                    type="button"
                                                    class="so-line-msg-flag is-on"
                                                    title="Line message"
                                                    aria-label="Open line message"
                                                    wire:click.stop="openLineMessage({{ $i }})"
                                                >Msg</button>
                                            @endif
                                            @if ($filled && filled($line['instructions'] ?? null))
                                                <button
                                                    type="button"
                                                    class="so-line-inst-flag is-on"
                                                    title="Instructions"
                                                    aria-label="Open instructions"
                                                    wire:click.stop="openLineMessage({{ $i }})"
                                                >Inst</button>
                                            @endif
                                        </td>
                                        <td class="col-uom">{{ $filled ? ($line['uom'] ?: '—') : '—' }}</td>
                                        <td class="col-qty">
                                            @if ($selectedLineIndex === $i && ! $viewMode && $filled)
                                                <div class="so-qty-stepper" wire:click.stop>
                                                    <button type="button" class="so-qty-btn" wire:click="adjustLineQty({{ $i }}, -1)" aria-label="Decrease qty">−</button>
                                                    <input
                                                        type="text"
                                                        wire:model.blur="lines.{{ $i }}.qty_ordered"
                                                        wire:keydown.up.prevent="nudgeLineField({{ $i }}, 'qty_ordered', 1)"
                                                        wire:keydown.down.prevent="nudgeLineField({{ $i }}, 'qty_ordered', -1)"
                                                        wire:keydown.enter.prevent="$wire.selectLine({{ $i + 1 }}).then(() => requestAnimationFrame(() => document.querySelector('#so-line-row-{{ $i + 1 }} .so-cell-input')?.focus()))"
                                                        class="so-cell-input text-right"
                                                        placeholder="0"
                                                        size="4"
                                                        step="0.01"
                                                        inputmode="decimal"
                                                    />
                                                    <button type="button" class="so-qty-btn" wire:click="adjustLineQty({{ $i }}, 1)" aria-label="Increase qty">+</button>
                                                </div>
                                            @else
                                                {{ $filled ? $this->formatQty($qty) : '' }}
                                            @endif
                                        </td>
                                        <td class="col-num">
                                            @if ($selectedLineIndex === $i && ! $viewMode && $filled && $canChangePrice)
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    wire:model.blur="lines.{{ $i }}.price"
                                                    wire:blur="considerPriceChange({{ $i }})"
                                                    wire:click.stop
                                                    wire:keydown.enter.prevent="considerPriceChange({{ $i }})"
                                                    wire:keydown.up.prevent="nudgeLineField({{ $i }}, 'price', 1)"
                                                    wire:keydown.down.prevent="nudgeLineField({{ $i }}, 'price', -1)"
                                                    class="so-cell-input text-right"
                                                    placeholder="0"
                                                    aria-label="Line price"
                                                />
                                            @else
                                                <span @if ($filled && ! $viewMode && ! $canChangePrice) title="Price is locked. An administrator can enable Change Order Price for your user." @endif>
                                                    {{ $filled && (float) ($line['price'] ?? 0) != 0.0 ? number_format((float) $line['price'], 2) : '' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="col-num">
                                            @if ($selectedLineIndex === $i && ! $viewMode && $filled)
                                                <input
                                                    wire:model.live="lines.{{ $i }}.unit_discount"
                                                    wire:click.stop
                                                    wire:keydown.up.prevent="nudgeLineField({{ $i }}, 'unit_discount', 1)"
                                                    wire:keydown.down.prevent="nudgeLineField({{ $i }}, 'unit_discount', -1)"
                                                    class="so-cell-input text-right"
                                                    placeholder="0"
                                                    title="{{ $lineDisc > 0 ? 'Line discount: $'.number_format($lineDisc, 2).' (per unit × qty)' : 'Discount per unit' }}"
                                                />
                                            @else
                                                {{ $filled && $lineDisc > 0 ? number_format($lineDisc, 2) : '' }}
                                            @endif
                                        </td>
                                        <td class="col-num so-line-total">
                                            @if ($filled)
                                                ${{ number_format(($qty * (float) $line['price']) - $lineDisc, 2) }}
                                            @endif
                                        </td>
                                        <td class="col-action">
                                            @if ($filled && ! $viewMode)
                                                <button
                                                    type="button"
                                                    wire:click.stop="removeLine({{ $i }})"
                                                    class="so-icon-btn so-icon-btn-sm so-line-remove-btn"
                                                    title="Remove item"
                                                    aria-label="Remove item {{ $line['item_code'] }}"
                                                >
                                                    <svg viewBox="0 0 12 12" fill="none" stroke="#b91c1c" stroke-width="1.6" aria-hidden="true"><path d="M3 3l6 6M9 3L3 9"/></svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @unless ($hasLines)
                            <div class="so-items-empty" role="status">Enter item code or click Browse to add items</div>
                        @endunless
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
                                id="so-item-entry"
                                data-pos-item-entry
                                name="so_item_entry"
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
                                    // Wait for FULL code (e.g. 2593a). Resets on every key — never add on '25' mid-type.
                                    scheduleAuto() {
                                        clearTimeout(this.timer);
                                        const delay = this.rapid ? 80 : 400;
                                        this.timer = setTimeout(() => {
                                            const el = document.getElementById('so-item-entry');
                                            const v = (el?.value || '').trim();
                                            if (v.length < 2) {
                                                this.rapid = false;
                                                return;
                                            }
                                            if (!this.claim(v)) {
                                                this.rapid = false;
                                                return;
                                            }
                                            $wire.autoAddIfExactMatch(v);
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
                                            $wire.openBrowseForSearch(($el.value || '').trim());
                                            return;
                                        }
                                        const now = Date.now();
                                        if (this.lastKeyAt && (now - this.lastKeyAt) < 50) {
                                            this.rapid = true;
                                        }
                                        this.lastKeyAt = now;
                                    },
                                    onInput() {
                                        // Each character restarts the wait — '2','25','259','2593','2593a'
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
                                    wire:click="clearItemEntry"
                                    class="so-icon-btn"
                                    title="Clear item code"
                                    aria-label="Clear item code"
                                >
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 3l6 6M9 3L3 9"/></svg>
                                </button>
                                <button
                                    type="button"
                                    x-on:click.prevent="
                                        const el = document.getElementById('so-item-entry');
                                        const v = (el?.value || '').trim();
                                        $wire.addItemFromEntry(v);
                                    "
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
                            <button type="button" wire:click="printInvoiceStyle" class="so-icon-btn" title="Print invoice (F10)" tabindex="-1" aria-label="Print invoice" data-pos-print>
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M3 4V2h6v2M3 8H2V5h8v3H9M3 7h6v3H3V7z"/></svg>
                            </button>
                            <button type="button" wire:click="printPickList" class="so-icon-btn" title="Print pick list" tabindex="-1" aria-label="Print pick list">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M2 2h8v8H2zM4 4h4M4 6h4M4 8h2"/></svg>
                            </button>
                            <button type="button" wire:click="removeSelectedLine" class="so-icon-btn" title="Delete selected line" tabindex="-1" aria-label="Delete line">
                                <svg viewBox="0 0 12 12" fill="none" stroke="#b91c1c" stroke-width="1.6"><path d="M3 3l6 6M9 3L3 9"/></svg>
                            </button>
                            <button type="button" wire:click="addLine" class="so-icon-btn" title="New line" aria-label="New line">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                            </button>
                            <button type="button" wire:click="openBrowseForSearch" class="so-browse-btn" title="Item list (F3)" data-pos-browse>Browse (F3)</button>
                            <button type="button" wire:click="exportLinesToExcel" class="so-browse-btn" title="Download Excel, or select rows then Ctrl+C / drag into Excel">Excel</button>
                            </div>
                        @endunless
                    </div>
                </div>

                {{-- Right-click context menu (Chief-style) --}}
                <div
                    x-show="ctxOpen"
                    x-cloak
                    class="so-ctx-menu"
                    :style="`left:${ctxX}px;top:${ctxY}px`"
                    @click.stop
                    role="menu"
                    aria-label="Line actions"
                >
                    <button type="button" role="menuitem" @click="closeCtx(); window.posExcelCopyRowIndex && window.posExcelCopyRowIndex('so-line-row-', ctxLine)">Copy for Excel</button>
                    <button type="button" role="menuitem" @click="closeCtx(); $wire.openLineSubstitutes(ctxLine)">Substitutes</button>
                    <button type="button" role="menuitem" class="is-danger" @click="closeCtx(); $wire.removeLine(ctxLine)">Remove Item</button>
                    <button type="button" role="menuitem" @click="closeCtx(); $wire.openLineUom(ctxLine)">Unit of Measures</button>
                    <button type="button" role="menuitem" @click="closeCtx(); $wire.openBatchDetails(ctxLine)">Item Batch details</button>
                    <button type="button" role="menuitem" @click="closeCtx(); $wire.openItemRecord(ctxLine)">View/Edit item record</button>
                    <button type="button" role="menuitem" @click="if (ctxLine !== null) { $wire.openLineMessage(ctxLine); } closeCtx()">Line Message &amp; Instructions</button>
                </div>

                <div class="so-footer">
                    <div class="so-counters">
                        <div class="so-counter-col">
                            <div>Total Lines: <strong>{{ $totalLines }}</strong></div>
                            <div>Total Items: <strong>{{ $totalItems }}</strong></div>
                            <div>Total quantity ordered: <strong>{{ $this->formatQty($totalQty) }}</strong></div>
                            <div>Total items Shipped: <strong>{{ $this->formatQty($totalShipped) ?: '0' }}</strong></div>
                        </div>
                        <div class="so-counter-col">
                            <div>Total Discounts: <strong>${{ number_format($totalDiscounts, 2) }}</strong></div>
                            <div>Total Allowances: <strong>${{ number_format($totalAllowances, 2) }}</strong></div>
                        </div>
                    </div>
                    <div class="so-totals">
                        <div class="so-totals-row"><span class="so-totals-lbl">Subtotal:</span><span class="so-totals-amt">${{ number_format($subtotal, 2) }}</span></div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Trade Discount:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="trade_discount" wire:keydown.up.prevent="nudgeAmount('trade_discount', 1)" wire:keydown.down.prevent="nudgeAmount('trade_discount', -1)" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                        </div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Freight:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="freight" wire:keydown.up.prevent="nudgeAmount('freight', 1)" wire:keydown.down.prevent="nudgeAmount('freight', -1)" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                        </div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Miscellaneous:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="miscellaneous" wire:keydown.up.prevent="nudgeAmount('miscellaneous', 1)" wire:keydown.down.prevent="nudgeAmount('miscellaneous', -1)" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                        </div>
                        <div class="so-totals-row so-totals-tax">
                            <span class="so-totals-lbl">Tax:</span>
                            <div class="so-tax-edit">
                                <div class="so-tax-edit__row">
                                    <select
                                        id="orderTaxScheduleId"
                                        wire:model.live="orderTaxScheduleId"
                                        class="so-input so-tax-edit__sched"
                                        @disabled($viewMode)
                                        aria-label="Tax percent schedule"
                                    >
                                        <option value="">Item rates / type $</option>
                                        @foreach ($taxSchedules as $ts)
                                            <option value="{{ $ts->id }}">{{ rtrim(rtrim(number_format((float) $ts->rate, 4, '.', ''), '0'), '.') }}% — {{ $ts->name }}</option>
                                        @endforeach
                                    </select>
                                    @unless ($viewMode)
                                        <button type="button" class="desk-btn desk-btn-sm" wire:click="toggleNewTaxSchedule" title="Add a tax % (Tax Schedules)">+</button>
                                    @endunless
                                </div>
                                <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="tax" wire:change="markTaxManual" wire:keydown.up.prevent="nudgeAmount('tax', 1)" wire:keydown.down.prevent="nudgeAmount('tax', -1)" class="so-totals-input" placeholder="0" @disabled($viewMode) /></label>
                                @if ($showNewTaxSchedule && ! $viewMode)
                                    <div class="so-tax-new">
                                        <div class="so-tax-new__grid">
                                            <label>Rate %
                                                <input type="text" inputmode="decimal" wire:model.live="newTaxRate" class="so-input" placeholder="6">
                                            </label>
                                            <label>Code
                                                <input type="text" wire:model="newTaxCode" class="so-input" placeholder="T6" maxlength="32">
                                            </label>
                                            <label class="so-tax-new__name">Name
                                                <input type="text" wire:model="newTaxName" class="so-input" placeholder="6% Sales Tax">
                                            </label>
                                        </div>
                                        @error('newTaxRate') <p class="so-tax-new__err">{{ $message }}</p> @enderror
                                        @error('newTaxCode') <p class="so-tax-new__err">{{ $message }}</p> @enderror
                                        @error('newTaxName') <p class="so-tax-new__err">{{ $message }}</p> @enderror
                                        <button type="button" class="desk-btn desk-btn-primary desk-btn-sm" wire:click="saveNewTaxSchedule">Save % to Tax Schedules</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="so-totals-row so-totals-final"><span class="so-totals-lbl">Total:</span><strong class="so-totals-amt">${{ number_format($orderTotal, 2) }}</strong></div>
                    </div>
                </div>
                </div>{{-- /.so-expand-main --}}
                </div>
                <div class="so-ship-panel" id="mode-panel-shipping" role="tabpanel" aria-labelledby="mode-tab-shipping" @style(['display: none' => $activeTab !== 'shipping'])>
                    <div class="so-ship-grid">
                        <div class="so-ship-col">
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="payment_term_id">Payment Terms:</label>
                                <select id="payment_term_id" wire:model="payment_term_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($paymentTerms as $pt)<option value="{{ $pt['id'] ?? '' }}">{{ $pt['name'] ?? '' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="route_id">Route:</label>
                                <select id="route_id" wire:model="route_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($routes as $route)<option value="{{ $route['id'] ?? '' }}">{{ $route['name'] ?? '' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_via_id">Ship Via:</label>
                                <select id="ship_via_id" wire:model="ship_via_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($shipVias as $sv)<option value="{{ $sv['id'] ?? '' }}">{{ $sv['name'] ?? '' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_from_site_id">Ship From:</label>
                                <select id="ship_from_site_id" wire:model="ship_from_site_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($sites as $s)<option value="{{ $s['id'] ?? '' }}">{{ $s['code'] ?? '' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_date">Ship Date:</label>
                                <input id="ship_date" type="date" wire:model="ship_date" class="so-input" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="no_of_boxes">No. of Boxes:</label>
                                <input id="no_of_boxes" type="number" min="0" wire:model="no_of_boxes" wire:keydown.up.prevent="nudgeAmount('no_of_boxes', 1)" wire:keydown.down.prevent="nudgeAmount('no_of_boxes', -1)" class="so-input so-w-num" placeholder="0" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="no_of_pallets">No. of Pallets:</label>
                                <input id="no_of_pallets" type="number" min="0" wire:model="no_of_pallets" wire:keydown.up.prevent="nudgeAmount('no_of_pallets', 1)" wire:keydown.down.prevent="nudgeAmount('no_of_pallets', -1)" class="so-input so-w-num" placeholder="0" />
                            </div>
                        </div>
                        <div class="so-ship-col">
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="custom_field_1">Custom Field 1:</label>
                                <input id="custom_field_1" wire:model="custom_field_1" class="so-input" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="custom_field_2">Custom Field 2:</label>
                                <input id="custom_field_2" wire:model="custom_field_2" class="so-input" />
                            </div>
                            <div class="so-ship-row so-ship-row-top">
                                <label class="so-ship-lbl" for="custom_field_3">Custom Field 3:</label>
                                <textarea id="custom_field_3" wire:model="custom_field_3" rows="2" class="so-input so-input-area"></textarea>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="custom_field_4">Custom Field 4:</label>
                                <input id="custom_field_4" wire:model="custom_field_4" class="so-input" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="custom_field_5">Custom Field 5:</label>
                                <input id="custom_field_5" wire:model="custom_field_5" class="so-input" />
                            </div>
                            <div class="so-ship-row so-ship-row-top">
                                <label class="so-ship-lbl" for="comments">Comments:</label>
                                <textarea id="comments" wire:model="comments" rows="3" class="so-input so-input-area"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="so-box-block">
                        <div class="so-box-head">
                            <span class="so-items-title" style="padding:0">Box Number / Tracking Number</span>
                            <button type="button" wire:click="addBox" class="so-browse-btn">Add Box</button>
                        </div>
                        <table class="so-box-table">
                            <thead>
                                <tr>
                                    <th style="width:40%">Box Number</th>
                                    <th>Tracking Number</th>
                                    <th style="width:4rem"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($boxes as $bi => $box)
                                    <tr>
                                        <td><input wire:model="boxes.{{ $bi }}.box_number" class="so-input w-full" /></td>
                                        <td><input wire:model="boxes.{{ $bi }}.tracking_number" class="so-input w-full" /></td>
                                        <td class="text-center">
                                            <button type="button" wire:click="removeBox({{ $bi }})" class="text-xs text-red-700 hover:underline">Remove</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
        </fieldset>
    </form>

    @include('livewire.pages.sales.orders.partials.item-browse-panel')

    @if ($showOpenOrderModal && ! $viewMode)
        <div
            class="desk-modal-backdrop desk-modal-top"
            wire:click.self="closeOpenOrderModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="open-order-title"
        >
            <div class="desk-modal" style="max-width:36rem;" wire:keydown.escape.window="closeOpenOrderModal">
                <div class="desk-modal-head">
                    <span id="open-order-title">Open / edit order</span>
                    <button type="button" wire:click="closeOpenOrderModal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="padding:.75rem 1rem 0;">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="openOrderSearch"
                        class="so-input"
                        placeholder="Search order # or customer…"
                        aria-label="Search orders"
                        autofocus
                    />
                </div>
                <div class="desk-modal-body" style="padding:0; max-height:22rem; overflow:auto;">
                    @forelse ($openOrderList as $row)
                        <button
                            type="button"
                            wire:click="openExistingOrder({{ $row['id'] }})"
                            style="display:block; width:100%; text-align:left; border:0; border-bottom:1px solid #e2e8f0; background:#fff; padding:.85rem 1rem; cursor:pointer;"
                        >
                            <div style="font-weight:700;">{{ $row['order_number'] }} · {{ $row['customer_label'] }}</div>
                            <div style="font-size:.8rem; color:#64748b;">${{ number_format($row['total'], 2) }}@if($row['order_date']) · {{ $row['order_date'] }}@endif</div>
                        </button>
                    @empty
                        <p style="padding:1rem; color:#64748b; margin:0;">No New orders found.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if ($showParkedSalesModal && ! $viewMode)
        <div
            class="desk-modal-backdrop desk-modal-top"
            wire:click.self="closeParkedSalesModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="parked-sales-title"
        >
            <div class="desk-modal" style="max-width:32rem;" wire:keydown.escape.window="closeParkedSalesModal">
                <div class="desk-modal-head">
                    <span id="parked-sales-title">Parked sales</span>
                    <button type="button" wire:click="closeParkedSalesModal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="padding:0; max-height:22rem; overflow:auto;">
                    @forelse ($parkedSalesList as $parked)
                        <div style="display:flex; align-items:stretch; border-bottom:1px solid #e2e8f0;">
                            <button
                                type="button"
                                wire:click="recallParkedSale({{ $parked['id'] }})"
                                style="flex:1; text-align:left; border:0; background:#fff; padding:.85rem 1rem; cursor:pointer;"
                            >
                                <div style="font-weight:700;">{{ $parked['customer_label'] ?: 'Customer' }}</div>
                                <div style="font-size:.8rem; color:#64748b;">{{ $parked['line_count'] }} item(s) · ${{ number_format($parked['total'], 2) }}@if($parked['updated_at']) · {{ $parked['updated_at'] }}@endif</div>
                            </button>
                            <button
                                type="button"
                                wire:click="discardParkedSale({{ $parked['id'] }})"
                                wire:confirm="Discard this parked sale?"
                                class="desk-modal-close"
                                style="width:2.75rem; position:static;"
                                aria-label="Discard"
                            >×</button>
                        </div>
                    @empty
                        <p style="padding:1rem; color:#64748b; margin:0;">No parked sales.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <div class="so-bottom so-bottom-full">
        <div class="so-bottom-tabs">
            <x-mode-tabs
                :tabs="['general' => 'General', 'items' => 'Expand', 'shipping' => 'Shipping info.']"
                :active="$activeTab"
            />
        </div>
        <div class="so-bottom-actions">
            <a href="{{ $returnToInvoiceList ? route('sales.invoices.index') : route('sales.orders.index') }}" wire:navigate class="so-btn-cancel">{{ $viewMode ? 'Close' : 'Cancel' }}</a>
            @if ($viewMode && $salesOrder instanceof \App\Models\SalesOrder)
                <button type="button" wire:click="enterEditMode" class="so-btn-save">
                    {{ $salesOrder->invoice ? 'Edit Invoice' : 'Edit Order' }}
                </button>
                <button type="button" wire:click="printInvoiceStyle" class="so-btn-save" data-pos-print>Print Invoice</button>
                <button type="button" wire:click="printPickList" class="so-btn-save">Print Pick List</button>
            @elseif (! $viewMode)
                <button type="button" wire:click="openOpenOrderModal" class="so-btn-save" title="Edit an existing New order">Edit</button>
                <button type="button" wire:click="parkSale" class="so-btn-cancel" title="Hold this sale and start another">Park Sale</button>
                <button type="button" wire:click="openParkedSalesModal" class="so-btn-cancel">
                    Parked{{ $parkedCount ? ' ('.$parkedCount.')' : '' }}
                </button>
                <button type="submit" form="so-form" class="so-btn-save" data-pos-save>Save Changes</button>
            @endif
        </div>
    </div>

    @if ($showUnknownScanModal && ! $viewMode)
        <div
            class="desk-modal-backdrop desk-modal-top desk-chief-prompt"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="unknown-scan-title"
            aria-describedby="unknown-scan-msg"
        >
            <div class="desk-modal desk-modal-sm" style="max-width:28rem;" wire:keydown.enter.window.prevent="acknowledgeUnknownScan" wire:keydown.escape.window="acknowledgeUnknownScan">
                <div class="desk-modal-head">
                    <span id="unknown-scan-title">Item not in system</span>
                </div>
                <div class="desk-modal-body" style="display:flex; gap:.75rem; align-items:flex-start; padding:1rem 1.1rem;">
                    <div
                        aria-hidden="true"
                        style="flex-shrink:0;width:2.25rem;height:2.25rem;border-radius:9999px;background:#b91c1c;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;line-height:1;"
                    >!</div>
                    <div style="flex:1; min-width:0;">
                        <p id="unknown-scan-msg" style="margin:0;font-size:.95rem;line-height:1.4;">
                            Scanned item is not in the system.
                            @if (trim($unknownScanCode) !== '')
                                <br><strong style="word-break:break-all;">{{ $unknownScanCode }}</strong>
                            @endif
                        </p>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem;padding:0 1rem 1rem;">
                    <button type="button" wire:click="acknowledgeUnknownScan" class="desk-btn desk-btn-primary" autofocus>OK</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showPriceBelowLimitModal)
        <div
            class="desk-modal-backdrop desk-modal-top desk-chief-prompt"
            wire:click.self="rejectPriceBelowLimit"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="price-below-limit-title"
        >
            <div class="desk-modal desk-modal-sm" style="max-width:26rem;" wire:keydown.escape.window="rejectPriceBelowLimit">
                <div class="desk-modal-head">
                    <span id="price-below-limit-title">Chief</span>
                    <button type="button" wire:click="rejectPriceBelowLimit" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="display:flex; gap:.75rem; align-items:flex-start; padding:1rem 1.1rem;">
                    <div
                        aria-hidden="true"
                        style="flex-shrink:0;width:2.25rem;height:2.25rem;border-radius:9999px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;line-height:1;"
                    >?</div>
                    <div style="flex:1; min-width:0;">
                        <p style="margin:0;font-size:.95rem;line-height:1.4;">
                            Price is below allowed limit, are you sure you want to continue?
                        </p>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem;padding:0 1rem 1rem;">
                    <button type="button" wire:click="confirmPriceBelowLimit" class="desk-btn desk-btn-primary" autofocus>Yes</button>
                    <button type="button" wire:click="rejectPriceBelowLimit" class="desk-btn">No</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showMemorizePriceModal)
        <div
            class="desk-modal-backdrop desk-modal-top desk-chief-prompt"
            wire:click.self="rejectMemorizePrice"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="memorize-price-title"
        >
            <div class="desk-modal desk-modal-sm" style="max-width:24rem;" wire:keydown.escape.window="rejectMemorizePrice">
                <div class="desk-modal-head">
                    <span id="memorize-price-title">Chief</span>
                    <button type="button" wire:click="rejectMemorizePrice" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="display:flex; gap:.75rem; align-items:flex-start; padding:1rem 1.1rem;">
                    <div
                        aria-hidden="true"
                        style="flex-shrink:0;width:2.25rem;height:2.25rem;border-radius:9999px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;line-height:1;"
                    >?</div>
                    <div style="flex:1; min-width:0;">
                        <p style="margin:0;font-size:.95rem;line-height:1.4;">
                            Do you want to memorize this price for this customer?
                        </p>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem;padding:0 1rem 1rem;">
                    <button type="button" wire:click="confirmMemorizePrice" class="desk-btn desk-btn-primary" autofocus>Yes</button>
                    <button type="button" wire:click="rejectMemorizePrice" class="desk-btn">No</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showCustomerConfirmModal)
        <div
            class="desk-modal-backdrop desk-modal-top desk-chief-prompt"
            wire:click.self="rejectCustomerSelection"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="customer-confirm-title"
        >
            <div class="desk-modal desk-modal-sm" style="max-width:28rem;" wire:keydown.escape.window="rejectCustomerSelection">
                <div class="desk-modal-head">
                    <span id="customer-confirm-title">Chief</span>
                    <button type="button" wire:click="rejectCustomerSelection" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="display:flex; gap:.75rem; align-items:flex-start; padding:1rem 1.1rem;">
                    <div
                        aria-hidden="true"
                        style="flex-shrink:0;width:2.25rem;height:2.25rem;border-radius:9999px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;line-height:1;"
                    >?</div>
                    <div style="flex:1; min-width:0;">
                        <p style="margin:0;font-size:.95rem;line-height:1.4;">
                            Is this the correct customer for this order?
                        </p>
                        @if ($customerConfirmLabel !== '')
                            <p style="margin:.55rem 0 0;font-size:.9rem;font-weight:700;color:#0f172a;line-height:1.35;">
                                {{ $customerConfirmLabel }}
                            </p>
                        @endif
                        <p style="margin:.45rem 0 0;font-size:.8rem;color:#64748b;line-height:1.35;">
                            Confirm to avoid billing the wrong customer by mistake.
                        </p>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem;padding:0 1rem 1rem;">
                    <button type="button" wire:click="confirmCustomerSelection" class="desk-btn desk-btn-primary" autofocus>Yes</button>
                    <button type="button" wire:click="rejectCustomerSelection" class="desk-btn">No</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showShipToModal)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="closeShipToModal" role="dialog" aria-modal="true" aria-labelledby="ship-to-modal-title">
            <div class="desk-modal desk-modal-sm" style="max-width:28rem;" wire:keydown.escape.window="closeShipToModal">
                <div class="desk-modal-head">
                    <span id="ship-to-modal-title">
                        New Ship-To Address
                        @if ($selectedCustomer)
                            — {{ $selectedCustomer->company_name ?: $selectedCustomer->customer_id }}
                        @endif
                    </span>
                    <button type="button" wire:click="closeShipToModal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body">
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl so-field-req" for="modal_ship_name">Name</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_name" wire:model="newShipName" class="so-input @error('newShipName') is-invalid @enderror" autofocus />
                            @error('newShipName') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl so-field-req" for="modal_ship_address">Address</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_address" wire:model="newShipAddress" class="so-input @error('newShipAddress') is-invalid @enderror" />
                            @error('newShipAddress') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl" for="modal_ship_city">City</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_city" wire:model="newShipCity" class="so-input" />
                        </div>
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;gap:.5rem;">
                        <label class="so-form-lbl" for="modal_ship_state">State</label>
                        <input id="modal_ship_state" wire:model="newShipState" class="so-input so-w-state" style="width:4.5rem;" />
                        <label class="so-form-lbl so-form-lbl-sm" for="modal_ship_zip">ZIP</label>
                        <input id="modal_ship_zip" wire:model="newShipZip" class="so-input so-w-zip" style="width:6rem;" />
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl" for="modal_ship_phone">Phone</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_phone" wire:model="newShipPhone" class="so-input" />
                        </div>
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl" for="modal_ship_fax">Fax</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_fax" wire:model="newShipFax" class="so-input" />
                        </div>
                    </div>
                    <div class="so-form-row" style="margin-bottom:.5rem;">
                        <label class="so-form-lbl" for="modal_ship_class">Class</label>
                        <div class="so-form-ctl" style="flex:1;">
                            <input id="modal_ship_class" wire:model="newShipClass" class="so-input" />
                        </div>
                    </div>
                    <label style="display:flex;align-items:center;gap:.45rem;font-size:.82rem;margin:.35rem 0 .85rem;cursor:pointer;">
                        <input type="checkbox" wire:model="newShipPrimary" />
                        Primary ship-to for this customer
                    </label>
                    <div style="display:flex;justify-content:flex-end;gap:.45rem;">
                        <button type="button" wire:click="closeShipToModal" class="desk-btn">Cancel</button>
                        <button type="button" wire:click="saveShipToAddress" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveShipToAddress">Save ship-to</span>
                            <span wire:loading wire:target="saveShipToAddress">Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showLineSubstitutes)
        <div class="desk-modal-backdrop" wire:click.self="closeLineSubstitutes" role="dialog" aria-modal="true" aria-labelledby="line-sub-title">
            <div class="desk-modal so-msg-modal">
                <div class="desk-modal-head">
                    <span id="line-sub-title">Substitutes</span>
                    <button type="button" wire:click="closeLineSubstitutes" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-msg-modal-body">
                    <div class="so-msg-modal-row so-msg-modal-row-pair">
                        <label class="so-msg-modal-lbl">Item code</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgItemCode }}" readonly wire:key="sub-code-{{ $lineMsgItemCode }}" />
                        <label class="so-msg-modal-lbl">Description</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgDescription }}" readonly wire:key="sub-desc-{{ $lineMsgItemCode }}" />
                    </div>
                    <ul class="border border-slate-300 divide-y max-h-56 overflow-auto" style="border-radius:4px">
                        @forelse ($substituteOptions as $opt)
                            <li class="flex items-center justify-between gap-2 px-2 py-1.5 text-sm">
                                <span>
                                    <span class="font-mono">{{ $opt['item_code'] }}</span>
                                    — {{ $opt['description'] }}
                                    <span class="text-xs text-slate-500">(avail {{ number_format($opt['available'], 0) }})</span>
                                </span>
                                @if ($opt['available'] > 0)
                                    <button type="button" wire:click="applyLineSubstitute({{ $opt['id'] }})" class="desk-btn desk-btn-sm desk-btn-primary">Use</button>
                                @else
                                    <span class="text-xs text-red-700">No stock</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-2 py-3 text-slate-500 text-sm">No substitutes configured for this item.</li>
                        @endforelse
                    </ul>
                    <div class="so-msg-modal-actions">
                        <button type="button" wire:click="closeLineSubstitutes" class="desk-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showUomModal)
        <div class="desk-modal-backdrop" wire:click.self="$set('showUomModal', false)" role="dialog" aria-modal="true" aria-labelledby="line-uom-title">
            <div class="desk-modal so-msg-modal">
                <div class="desk-modal-head">
                    <span id="line-uom-title">Unit of Measures</span>
                    <button type="button" wire:click="$set('showUomModal', false)" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-msg-modal-body">
                    <div class="so-msg-modal-row so-msg-modal-row-pair">
                        <label class="so-msg-modal-lbl">Item code</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgItemCode }}" readonly wire:key="uom-code-{{ $lineMsgItemCode }}" />
                        <label class="so-msg-modal-lbl">Description</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgDescription }}" readonly wire:key="uom-desc-{{ $lineMsgItemCode }}" />
                    </div>
                    <div class="so-msg-modal-row">
                        <label class="so-msg-modal-lbl">Current UOM</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $selectedLineIndex !== null ? ($lines[$selectedLineIndex]['uom'] ?? '') : '' }}" readonly />
                    </div>
                    <div class="space-y-1.5">
                        @foreach ($lineUomOptions as $uomOpt)
                            <button
                                type="button"
                                wire:click="setLineUom({{ json_encode($uomOpt) }})"
                                @class([
                                    'desk-btn w-full',
                                    'desk-btn-primary' => $selectedLineIndex !== null && strcasecmp((string) ($lines[$selectedLineIndex]['uom'] ?? ''), $uomOpt) === 0,
                                ])
                                style="justify-content:flex-start"
                            >{{ $uomOpt }}</button>
                        @endforeach
                    </div>
                    <div class="so-msg-modal-actions">
                        <button type="button" wire:click="$set('showUomModal', false)" class="desk-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showBatchModal)
        <div class="desk-modal-backdrop" wire:click.self="$set('showBatchModal', false)" role="dialog" aria-modal="true" aria-labelledby="line-batch-title">
            <div class="desk-modal so-msg-modal" style="max-width:42rem">
                <div class="desk-modal-head">
                    <span id="line-batch-title">Item Batch details</span>
                    <button type="button" wire:click="$set('showBatchModal', false)" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-msg-modal-body">
                    <div class="so-msg-modal-row so-msg-modal-row-pair">
                        <label class="so-msg-modal-lbl">Item code</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgItemCode }}" readonly wire:key="batch-code-{{ $lineMsgItemCode }}" />
                        <label class="so-msg-modal-lbl">Description</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgDescription }}" readonly wire:key="batch-desc-{{ $lineMsgItemCode }}" />
                    </div>
                    <pre class="text-sm font-mono" style="white-space:pre-wrap;margin:0;padding:0.65rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px">{{ $batchInfo }}</pre>
                    <div class="desk-grid" style="max-height:14rem;overflow:auto;border:1px solid #cbd5e1;border-radius:4px">
                        <table class="desk-table" style="margin:0">
                            <thead>
                                <tr>
                                    <th>Batch #</th>
                                    <th>Type</th>
                                    <th class="text-right">Qty</th>
                                    <th>Expiry</th>
                                    <th>Received</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batchRows as $row)
                                    <tr>
                                        <td class="font-mono">{{ $row['batch_number'] }}</td>
                                        <td>{{ $row['tracking_type'] }}</td>
                                        <td class="text-right">{{ $row['quantity'] }}</td>
                                        <td>{{ $row['expiry_date'] }}</td>
                                        <td>{{ $row['received_at'] }}</td>
                                        <td>{{ $row['notes'] !== '' ? $row['notes'] : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-slate-500 text-sm" style="padding:0.75rem">
                                            No batches set up. Add them on Item → Batches.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="so-msg-modal-actions">
                        <button type="button" wire:click="$set('showBatchModal', false)" class="desk-btn desk-btn-primary">OK</button>
                        <button type="button" wire:click="$set('showBatchModal', false)" class="desk-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showLineMessageAlert && (filled($orderLineMessagePopup) || filled($orderLineInstructionsPopup)))
        <div class="desk-modal-backdrop so-line-msg-alert" wire:click.self="dismissLineMessageAlert" role="dialog" aria-modal="true" aria-labelledby="line-msg-alert-title" style="z-index:90">
            <div class="desk-modal so-msg-modal" style="max-width:28rem">
                <div class="desk-modal-head">
                    <span id="line-msg-alert-title">Line Message &amp; Instructions</span>
                    <button type="button" wire:click="dismissLineMessageAlert" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-msg-modal-body">
                    <div class="so-msg-modal-row so-msg-modal-row-pair">
                        <label class="so-msg-modal-lbl">Item code</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgItemCode }}" readonly />
                        <label class="so-msg-modal-lbl">Description</label>
                        <input type="text" class="so-input so-input-ro" value="{{ $lineMsgDescription }}" readonly />
                    </div>
                    @if (filled($orderLineMessagePopup))
                        <div>
                            <div class="item-hint" style="margin:0 0 0.25rem;font-weight:700;color:#334155">Line message</div>
                            <p style="margin:0;padding:0.65rem;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;font-size:14px;color:#111827">
                                {{ $orderLineMessagePopup }}
                            </p>
                        </div>
                    @endif
                    @if (filled($orderLineInstructionsPopup))
                        <div>
                            <div class="item-hint" style="margin:0 0 0.25rem;font-weight:700;color:#334155">Instructions</div>
                            <p style="margin:0;padding:0.65rem;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;font-size:14px;color:#111827;white-space:pre-wrap">
                                {{ $orderLineInstructionsPopup }}
                            </p>
                        </div>
                    @endif
                    <p class="item-hint" style="margin:0">Line message shows on the order screen. Instructions also print on the pick list.</p>
                    <div class="so-msg-modal-actions">
                        <button type="button" wire:click="dismissLineMessageAlert" class="desk-btn desk-btn-primary">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showLineMessageModal)
        <div class="desk-modal-backdrop" wire:click.self="cancelLineMessage" role="dialog" aria-modal="true" aria-labelledby="line-msg-title" style="z-index:95">
            <div class="desk-modal so-msg-modal">
                <div class="desk-modal-head">
                    <span id="line-msg-title">Line Message &amp; Instructions</span>
                    <button type="button" wire:click="cancelLineMessage" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-msg-modal-body">
                    <div class="so-msg-modal-row so-msg-modal-row-pair">
                        <label class="so-msg-modal-lbl" for="line-msg-code">Item code</label>
                        <input id="line-msg-code" type="text" class="so-input so-input-ro" value="{{ $lineMsgItemCode }}" readonly />
                        <label class="so-msg-modal-lbl" for="line-msg-desc">Description</label>
                        <input id="line-msg-desc" type="text" class="so-input so-input-ro" value="{{ $lineMsgDescription }}" readonly />
                    </div>
                    <div class="so-msg-modal-row">
                        <label class="so-msg-modal-lbl" for="line-msg-field">Line Message</label>
                        <div>
                            <input id="line-msg-field" type="text" wire:model.live="lineMessageEdit" class="so-input" />
                            <p class="item-hint" style="margin:0.25rem 0 0">Shows on Create/Edit Order (banner + popup).</p>
                        </div>
                    </div>
                    <div class="so-msg-modal-row so-msg-modal-row-top">
                        <label class="so-msg-modal-lbl" for="line-msg-instr">Instructions</label>
                        <div>
                            <textarea id="line-msg-instr" wire:model.live="lineInstructionsEdit" rows="4" class="so-input so-input-area"></textarea>
                            <p class="item-hint" style="margin:0.25rem 0 0">Shows on Create/Edit Order and on Pick List print.</p>
                        </div>
                    </div>
                    <div class="so-msg-modal-actions">
                        <button type="button" wire:click="saveLineMessage" class="desk-btn desk-btn-primary">OK</button>
                        <button type="button" wire:click="cancelLineMessage" class="desk-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showSubstitutePrompt)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelSubstitutePrompt" role="dialog" aria-modal="true" aria-labelledby="sub-prompt-title">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-lg">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span id="sub-prompt-title">Force substitute suggested</span>
                    <button type="button" wire:click="cancelSubstitutePrompt" class="text-white hover:text-red-200" aria-label="Close">×</button>
                </div>
                <div class="p-3 space-y-2 text-sm">
                    <p>
                        @if ($oversellingOn)
                            Selected item has no stock on hand. Overselling is ON — you can add it anyway, or use a substitute.
                        @else
                            Selected item is out of stock. Choose a substitute that has stock, or cancel.
                        @endif
                    </p>
                    <ul class="border border-slate-300 divide-y max-h-48 overflow-auto">
                        @forelse ($substituteOptions as $opt)
                            <li class="flex items-center justify-between gap-2 px-2 py-1.5">
                                <span>
                                    <span class="font-mono">{{ $opt['item_code'] }}</span>
                                    — {{ $opt['description'] }}
                                    <span class="text-xs text-slate-500">(avail {{ number_format($opt['available'], 0) }})</span>
                                </span>
                                @if ($opt['available'] > 0 || $oversellingOn)
                                    <button type="button" wire:click="acceptSubstitute({{ $opt['id'] }})" class="chief-btn-primary text-xs">Use</button>
                                @else
                                    <span class="text-xs text-red-700">No stock</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-2 py-2 text-slate-500">No substitute items configured.</li>
                        @endforelse
                    </ul>
                    <div class="flex justify-end gap-2 pt-1">
                        @if ($oversellingOn)
                            <button type="button" wire:click="keepOriginalItem" class="chief-btn-primary">Add original</button>
                        @endif
                        <button type="button" wire:click="cancelSubstitutePrompt" class="chief-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showPrintDialog)
        <div class="desk-modal-backdrop so-print-dialog-backdrop" wire:click.self="cancelPrintDialog" role="dialog" aria-modal="true" aria-labelledby="so-print-dialog-title">
            <div class="desk-modal so-print-dialog">
                <div class="desk-modal-head">
                    <span id="so-print-dialog-title">Print Dialog</span>
                    <button type="button" wire:click="cancelPrintDialog" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-print-dialog-body">
                    @php
                        $canPay = auth()->user()?->canAccessFeature('sales.payments', 'edit') ?? false;
                        $canInvoice = auth()->user()?->canAccessFeature('sales.invoices', 'edit') ?? false;
                    @endphp
                    <label @class(['so-print-opt', 'chief-menu-inactive' => ! $canPay])>
                        <input type="checkbox" wire:model="optCreateInvoicePayment" @disabled(! $canPay) />
                        <span>Create/Edit Invoice &amp; Payment{{ $canPay ? '' : ' (no permission)' }}</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="checkbox" wire:model="optPrintSalesOrder" />
                        <span>Print Sales order Document</span>
                    </label>
                    <label @class(['so-print-opt', 'chief-menu-inactive' => ! $canInvoice])>
                        <input type="checkbox" wire:model="optCreatePrintInvoice" @disabled(! $canInvoice) />
                        <span>Create &amp; Print Invoice{{ $canInvoice ? '' : ' (no permission)' }}</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="checkbox" wire:model="optPrintPickList" />
                        <span>Print Pick list</span>
                    </label>
                    <div class="so-print-dialog-actions">
                        <button type="button" wire:click="confirmPrintDialog" class="desk-btn desk-btn-primary">OK</button>
                        <button type="button" wire:click="cancelPrintDialog" class="desk-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showInvoiceDeliveryDialog)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="cancelInvoiceDeliveryDialog" role="dialog" aria-modal="true" aria-labelledby="so-inv-delivery-title">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span id="so-inv-delivery-title">Invoice delivery</span>
                    <button type="button" wire:click="cancelInvoiceDeliveryDialog" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body space-y-3">
                    <p class="inv-email-note" style="margin:0">Print the invoice, email it to the customer, or both.</p>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="print" />
                        <span>Print only</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="email" />
                        <span>Email only</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="both" />
                        <span>Print &amp; email</span>
                    </label>
                    @if (in_array($invoiceDeliveryMode, ['email', 'both'], true))
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="so-inv-email">To</label>
                            <input id="so-inv-email" type="email" wire:model="invoiceEmailTo" class="so-input @error('invoiceEmailTo') is-invalid @enderror" placeholder="customer@email.com" />
                        </div>
                        @error('invoiceEmailTo') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="so-inv-subject">Subject</label>
                            <input id="so-inv-subject" type="text" wire:model="invoiceEmailSubject" class="so-input" />
                        </div>
                    @endif
                    <div class="entity-footer-actions" style="justify-content:flex-end;gap:0.5rem">
                        <button type="button" wire:click="cancelInvoiceDeliveryDialog" class="desk-btn">Cancel</button>
                        <button type="button" wire:click="confirmInvoiceDeliveryDialog" class="desk-btn desk-btn-primary">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('open-order-invoice-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });

    // Keep unknown-item alarm going until OK; stop extra scans from leaving the page.
    $wire.$watch('showUnknownScanModal', (open) => {
        if (open) {
            window.startPosScanMissAlarm && window.startPosScanMissAlarm();
            return;
        }
        window.stopPosScanMissAlarm && window.stopPosScanMissAlarm();
    });

    (function () {
        let buf = '';
        let last = 0;
        document.addEventListener('keydown', function (e) {
            if ($wire.showUnknownScanModal) {
                if (e.key === 'Enter' || e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    $wire.acknowledgeUnknownScan();
                } else if (e.key.length === 1 || e.key === 'Tab') {
                    e.preventDefault();
                    e.stopPropagation();
                }
                return;
            }

            // Barcode guns sometimes send Ctrl+L then a URL, which jumps to the address bar.
            if ((e.ctrlKey || e.metaKey) && (e.key === 'l' || e.key === 'L')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            const now = Date.now();
            if (now - last > 90) buf = '';
            last = now;
            if (e.key.length === 1 && ! e.ctrlKey && ! e.metaKey && ! e.altKey) {
                buf += e.key;
            }
            if (e.key !== 'Enter' || buf.length < 4) return;
            const looksUrl = /^https?:\/\//i.test(buf) || /^www\./i.test(buf);
            if (! looksUrl) {
                buf = '';
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const code = buf;
            buf = '';
            $wire.addItemFromEntry(code);
        }, true);
    })();

    $wire.on('open-order-print-urls', (payload) => {
        const urls = payload?.urls ?? payload?.[0]?.urls ?? [];
        (Array.isArray(urls) ? urls : []).forEach((url, i) => {
            if (!url) return;
            setTimeout(() => window.open(url, '_blank'), i * 250);
        });
    });

    $wire.on('open-item-record', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });

    // Focus search when item browse panel opens (ready for barcode gun).
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

    const posKindFromText = (text) => {
        const t = String(text || '').toLowerCase();
        if (t.indexOf('not found') !== -1 || t.indexOf('not available') !== -1 || t.indexOf('cannot') !== -1 || t.indexOf('could not') !== -1 || t.indexOf('error') !== -1) return 'error';
        if (t.indexOf('no stock') !== -1 || t.indexOf('oversell') !== -1 || t.indexOf('skipped') !== -1 || t.indexOf('substitute') !== -1) return 'warning';
        if (t.indexOf('added') !== -1 || t.indexOf('increased') !== -1 || t.indexOf('saved') !== -1 || t.indexOf('created') !== -1) return 'success';
        return 'warning';
    };

    const playLineSound = (value) => {
        if (!value) return;
        window.playPosAlert && window.playPosAlert(posKindFromText(value));
    };

    const soBannerTimers = { line: null, errors: null, status: null };
    window.scheduleSoBannerDismiss = (which, statusEl) => {
        const delay = 2500;
        if (which === 'line') {
            clearTimeout(soBannerTimers.line);
            soBannerTimers.line = setTimeout(() => $wire.dismissLineWarning(), delay);
            return;
        }
        if (which === 'errors') {
            clearTimeout(soBannerTimers.errors);
            soBannerTimers.errors = setTimeout(() => $wire.dismissFormErrors(), delay);
            return;
        }
        if (which === 'status' && statusEl) {
            clearTimeout(soBannerTimers.status);
            soBannerTimers.status = setTimeout(() => { statusEl.style.display = 'none'; }, delay);
        }
    };

    $wire.$watch('lineWarning', (value) => {
        playLineSound(value);
        if (value) window.scheduleSoBannerDismiss('line');
    });
    $wire.$watch('customerAlert', (value) => playLineSound(value));
    $wire.$watch('creditWarning', (value) => playLineSound(value));
    $wire.$watch('taxExemptWarning', (value) => playLineSound(value));
    $wire.$watch('orderLockMessage', (value) => playLineSound(value));
    $wire.$watch('showLineMessageAlert', (open) => {
        if (open) window.playPosAlert && window.playPosAlert('warning');
    });
    $wire.$watch('showSubstitutePrompt', (open) => {
        if (open) window.playPosAlert && window.playPosAlert('warning');
    });
    $wire.$watch('showMemorizePriceModal', (open) => {
        if (open) window.playPosAlert && window.playPosAlert('warning');
    });
    $wire.$watch('showPriceBelowLimitModal', (open) => {
        if (open) window.playPosAlert && window.playPosAlert('warning');
    });
    $wire.$watch('showCustomerConfirmModal', (open) => {
        if (open) window.playPosAlert && window.playPosAlert('warning');
    });

    $wire.on('pos-alert', (e) => {
        const kind = (e && e.kind) || (Array.isArray(e) && e[0] && e[0].kind) || 'error';
        window.playPosAlert && window.playPosAlert(kind);
    });
</script>
@endscript
