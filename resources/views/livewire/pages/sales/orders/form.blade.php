<?php

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemSubstitute;
use App\Models\PaymentTerm;
use App\Models\RouteLookup;
use App\Models\SalesOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('New Sales Order')] class extends Component
{
    public ?SalesOrder $salesOrder = null;

    public string $activeTab = 'general';

    public string $addressTab = 'bill';

    public string $itemEntry = '';

    public bool $showBrowse = false;

    public bool $browseNewOnly = false;

    public string $favorite = 'new';

    public string $order_number = '';

    public string $order_type = 'Sales Order';

    public string $status = 'New';

    public string $priority = 'Normal';

    public ?int $customer_id = null;

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

    public string $no_of_boxes = '0';

    public string $no_of_pallets = '0';

    public string $custom_field_1 = '';

    public string $custom_field_2 = '';

    public string $custom_field_3 = '';

    public string $custom_field_4 = '';

    public string $custom_field_5 = '';

    public string $comments = '';

    public string $freight = '0';

    public string $trade_discount = '0';

    public string $miscellaneous = '0';

    public string $tax = '0';

    public string $customerAlert = '';

    public string $creditWarning = '';

    public string $taxExemptWarning = '';

    public string $lineWarning = '';

    public bool $showSubstitutePrompt = false;

    public ?int $pendingItemId = null;

    public ?int $pendingLineIndex = null;

    /** @var array<int, array{id:int,item_code:string,description:string,available:float}> */
    public array $substituteOptions = [];

    public bool $showCustomerBrowse = false;

    public string $customerSearch = '';

    public bool $showShipBrowse = false;

    public bool $taxManual = false;

    public float $pendingTradePercent = 0;

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,qty_shipped:string,price:string,discount:string}> */
    public array $lines = [];

    /** @var array<int, array{box_number:string,tracking_number:string}> */
    public array $boxes = [];

    public function mount(?SalesOrder $salesOrder = null): void
    {
        if ($this->activeTab === 'expand') {
            $this->activeTab = 'items';
        }

        $companyId = auth()->user()->company_id;

        if ($salesOrder?->exists) {
            abort_unless($salesOrder->company_id === $companyId, 403);
            $this->salesOrder = $salesOrder->load(['lines', 'boxes', 'customer']);
            $this->fill($salesOrder->only([
                'order_number', 'order_type', 'status', 'priority', 'customer_id', 'ship_to_address_id',
                'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
                'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
                'customer_po_no', 'reference_no', 'sales_rep_id', 'payment_term_id', 'route_id', 'ship_via_id',
                'ship_from_site_id', 'no_of_boxes', 'no_of_pallets', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'custom_field_5', 'comments',
                'freight', 'trade_discount', 'miscellaneous', 'tax',
            ]));
            $this->order_date = optional($salesOrder->order_date)?->format('Y-m-d') ?? '';
            $this->required_date = optional($salesOrder->required_date)?->format('Y-m-d') ?? '';
            $this->ship_date = optional($salesOrder->ship_date)?->format('Y-m-d') ?? '';
            $this->no_of_boxes = (string) ($salesOrder->no_of_boxes ?? 0);
            $this->no_of_pallets = (string) ($salesOrder->no_of_pallets ?? 0);
            $this->customerAlert = $salesOrder->customer?->messages_alerts ?? '';
            $this->taxManual = true;
            $this->lines = $salesOrder->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'qty_ordered' => (string) $l->qty_ordered,
                'qty_shipped' => (string) ($l->qty_shipped ?? 0),
                'price' => (string) $l->price,
                'discount' => (string) $l->discount,
            ])->all();
            $this->boxes = $salesOrder->boxes->map(fn ($b) => [
                'box_number' => $b->box_number ?? '',
                'tracking_number' => $b->tracking_number ?? '',
            ])->all();
            $this->refreshCreditWarning();
        } else {
            $this->order_number = SalesOrder::nextNumber($companyId);
            $this->order_date = now()->toDateString();
            $this->required_date = now()->toDateString();
            $this->ship_date = now()->toDateString();
            $this->sales_rep_id = auth()->id();
            $this->ship_from_site_id = auth()->user()->site_id;
        }

        if ($this->boxes === []) {
            $this->boxes[] = ['box_number' => '', 'tracking_number' => ''];
        }
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

    protected function emptyLine(): array
    {
        return [
            'item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '',
            'qty_ordered' => '1', 'qty_shipped' => '0', 'price' => '0', 'discount' => '0',
        ];
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $filledLines = collect($this->lines)->filter(fn ($l) => filled($l['item_code'] ?? null));
        $subtotal = $filledLines->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        if ($this->pendingTradePercent > 0 && $subtotal > 0) {
            $this->trade_discount = number_format($subtotal * ($this->pendingTradePercent / 100), 2, '.', '');
        }
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        $customerQuery = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('company_name');

        $browseCustomers = collect();
        if ($this->showCustomerBrowse) {
            $browseCustomers = (clone $customerQuery)
                ->when(filled($this->customerSearch), function ($q) {
                    $term = '%'.$this->customerSearch.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term);
                    });
                })
                ->limit(80)
                ->get(['id', 'customer_id', 'company_name', 'contact', 'telephone', 'city', 'state']);
        }

        return [
            'customers' => $customerQuery->get(['id', 'customer_id', 'company_name']),
            'selectedCustomer' => $this->customer_id
                ? Customer::query()->with('shippingAddresses')->find($this->customer_id)
                : null,
            'salesReps' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'shipVias' => ShipVia::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'browseItems' => $this->showBrowse
                ? Item::query()
                    ->where('company_id', $companyId)
                    ->where('is_inactive', false)
                    ->where('can_sell', true)
                    ->when($this->browseNewOnly, fn ($q) => $q->newItems())
                    ->orderBy('item_code')
                    ->limit(80)
                    ->get(['id', 'item_code', 'description', 'unit_of_measure', 'list_price', 'created_at'])
                : collect(),
            'browseCustomers' => $browseCustomers,
            'subtotal' => $subtotal,
            'orderTotal' => $total,
            'totalLines' => $filledLines->count(),
            'totalItems' => $filledLines->count(),
            'totalQty' => $filledLines->sum(fn ($l) => (float) $l['qty_ordered']),
            'totalShipped' => $filledLines->sum(fn ($l) => (float) ($l['qty_shipped'] ?? 0)),
            'totalDiscounts' => $filledLines->sum(fn ($l) => (float) $l['discount']),
            'totalAllowances' => 0,
            'hasLines' => $filledLines->isNotEmpty(),
            'favorites' => [
                'all' => 'All Orders',
                'new' => 'New Orders',
                'not_invoiced' => 'Not Invoiced',
                'month' => 'This Month',
                'today' => 'Today & Yesterday',
            ],
        ];
    }

    public function updatedFavorite(string $value): void
    {
        $this->redirect(route('sales.orders.index', ['favorite' => $value]), navigate: true);
    }

    public function updatedCustomerId($value): void
    {
        $customer = Customer::query()->with('shippingAddresses')->find($value);
        if (! $customer) {
            $this->customerAlert = '';
            $this->creditWarning = '';
            $this->taxExemptWarning = '';

            return;
        }
        $this->customerAlert = $customer->messages_alerts ?? '';
        $this->bill_to_name = $customer->company_name ?: $customer->contact;
        $this->bill_to_phone = $customer->telephone ?? '';
        $this->bill_to_address = $customer->address ?? '';
        $this->bill_to_city = $customer->city ?? '';
        $this->bill_to_state = $customer->state ?? '';
        $this->bill_to_zip = $customer->zip_code ?? '';
        $this->payment_term_id = $customer->payment_term_id;
        $this->sales_rep_id = $customer->sales_rep_id ?: $this->sales_rep_id;
        $this->route_id = $customer->delivery_route_id;

        if ($customer->discount_schedule_id) {
            $schedule = \App\Models\DiscountSchedule::query()->find($customer->discount_schedule_id);
            if ($schedule && (float) $schedule->percent > 0) {
                // Soft-apply as percent of current subtotal (recomputed in with()); seed from schedule percent of 0 until lines exist
                $this->trade_discount = '0';
                $this->pendingTradePercent = (float) $schedule->percent;
            }
        }

        $this->refreshTaxExemptWarning($customer);

        $ship = $customer->shippingAddresses->firstWhere('is_primary', true) ?? $customer->shippingAddresses->first();
        if ($ship) {
            $this->ship_to_address_id = $ship->id;
            $this->applyShipAddress($ship);
        } else {
            $this->ship_to_address_id = null;
            $this->ship_to_name = $this->bill_to_name;
            $this->ship_to_phone = $this->bill_to_phone;
            $this->ship_to_address = $this->bill_to_address;
            $this->ship_to_city = $this->bill_to_city;
            $this->ship_to_state = $this->bill_to_state;
            $this->ship_to_zip = $this->bill_to_zip;
        }

        $this->showCustomerBrowse = false;
        $this->refreshCreditWarning();
        $this->suggestTax();
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
        $this->refreshCreditWarning();
    }

    public function updatedLines(): void
    {
        $this->refreshCreditWarning();
        $this->suggestTax();
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

    protected function applyShipAddress($ship): void
    {
        $this->ship_to_name = $ship->name ?? '';
        $this->ship_to_phone = $ship->telephone ?? '';
        $this->ship_to_address = $ship->address ?? '';
        $this->ship_to_city = $ship->city ?? '';
        $this->ship_to_state = $ship->state ?? '';
        $this->ship_to_zip = $ship->zip ?? '';
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
        $itemIds = $filled->pluck('item_id')->filter()->unique()->all();
        $items = Item::query()->with('taxSchedule')->whereIn('id', $itemIds)->get()->keyBy('id');
        $taxable = 0.0;
        $weighted = 0.0;
        foreach ($filled as $line) {
            $item = $items->get($line['item_id']);
            $rate = (float) ($item?->taxSchedule?->rate ?? 0);
            $lineNet = ((float) $line['qty_ordered'] * (float) $line['price']) - (float) $line['discount'];
            $taxable += $lineNet;
            $weighted += $lineNet * ($rate / 100);
        }
        $taxable = max(0, $taxable - (float) $this->trade_discount);
        if ($taxable <= 0) {
            $this->tax = '0';

            return;
        }
        // Scale suggested tax after trade discount proportionally
        $gross = $filled->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        $suggested = $gross > 0 ? $weighted * ($taxable / $gross) : 0;
        $this->tax = number_format($suggested, 2, '.', '');
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
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
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
            return;
        }
        if ($this->shouldPromptForceSubstitute($item)) {
            $this->pendingItemId = $item->id;
            $this->pendingLineIndex = $index;
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

            return;
        }
        $this->fillLineFromItem($index, $item);
    }

    public function addItemFromEntry(): void
    {
        $code = trim($this->itemEntry);
        if ($code === '') {
            return;
        }
        $item = $this->findItem($code);
        if (! $item) {
            return;
        }
        $this->itemEntry = '';
        $this->showBrowse = false;
        $this->queueItemOrPromptSubstitute($item);
    }

    public function pickBrowseItem(int $itemId): void
    {
        $item = Item::query()->with(['prices', 'taxSchedule', 'substitutes.substituteItem'])
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);
        if (! $item) {
            return;
        }
        $this->showBrowse = false;
        $this->itemEntry = '';
        $this->queueItemOrPromptSubstitute($item);
    }

    protected function queueItemOrPromptSubstitute(Item $item): void
    {
        if ($this->shouldPromptForceSubstitute($item)) {
            $this->pendingItemId = $item->id;
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

            return;
        }

        $this->appendItemLine($item);
    }

    protected function shouldPromptForceSubstitute(Item $item): bool
    {
        if ((float) $item->available_quantity > 0) {
            return false;
        }

        if (! $item->relationLoaded('substitutes')) {
            $item->load(['substitutes.substituteItem']);
        }

        return $item->substitutes->contains(fn (ItemSubstitute $s) => $s->force_substitute && $s->substitute_item_id);
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
        if ($lineIndex !== null && isset($this->lines[$lineIndex])) {
            $this->fillLineFromItem($lineIndex, $item);
        } else {
            $this->appendItemLine($item);
        }
        $this->lineWarning = 'Used force substitute '.$item->item_code.' (original out of stock).';
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
        if (! $item) {
            return;
        }
        if ($lineIndex !== null && isset($this->lines[$lineIndex])) {
            $this->fillLineFromItem($lineIndex, $item);
        } else {
            $this->appendItemLine($item);
        }
        $this->lineWarning = $item->item_code.' is out of stock; kept original per operator choice.';
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
        $this->lines[] = $this->emptyLine();
        $this->fillLineFromItem(count($this->lines) - 1, $item);
    }

    public function toggleBrowse(): void
    {
        $this->showBrowse = ! $this->showBrowse;
        $this->showCustomerBrowse = false;
        $this->showShipBrowse = false;
    }

    protected function findItem(string $code): ?Item
    {
        return Item::query()
            ->with(['prices', 'taxSchedule', 'substitutes.substituteItem'])
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)
                    ->orWhere('primary_upc', $code)
                    ->orWhereHas('prices', fn ($p) => $p->where('alias_code', $code));
            })
            ->first();
    }

    protected function resolveItemPrice(Item $item): string
    {
        $uom = $item->unit_of_measure ?? '';
        $prices = $item->relationLoaded('prices') ? $item->prices : $item->prices()->get();
        if ($uom !== '') {
            $match = $prices->firstWhere('uom', $uom);
            if ($match) {
                return (string) $match->price;
            }
        }
        $first = $prices->first();
        if ($first) {
            return (string) $first->price;
        }

        return (string) $item->list_price;
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $desc = trim((string) ($item->description ?? ''));
        if (filled($item->item_line_message)) {
            $desc = trim($desc.($desc !== '' ? ' | ' : '').$item->item_line_message);
        }
        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $desc;
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['price'] = $this->resolveItemPrice($item);
        $this->lines[$index]['qty_ordered'] = $this->lines[$index]['qty_ordered'] ?: '1';
        $this->lines[$index]['qty_shipped'] = $this->lines[$index]['qty_shipped'] ?? '0';
        $this->lines[$index]['discount'] = $this->lines[$index]['discount'] ?: '0';
        $this->taxManual = false;
        $this->refreshCreditWarning();
        $this->suggestTax();
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

    public function save(): void
    {
        $this->validate([
            'order_number' => 'required',
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        $hasLines = collect($this->lines)->contains(fn ($l) => filled($l['item_code'] ?? null) && (float) ($l['qty_ordered'] ?? 0) > 0);
        $this->lineWarning = $hasLines ? '' : 'Order saved without line items.';

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $subtotal = collect($this->lines)->sum(fn ($l) => filled($l['item_code'] ?? null)
            ? (((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount'])
            : 0);
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        $companyId = (int) auth()->user()->company_id;

        $data = [
            'company_id' => $companyId,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type,
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
            'customer_po_no' => $this->customer_po_no,
            'reference_no' => $this->reference_no,
            'sales_rep_id' => $nullableId($this->sales_rep_id),
            'payment_term_id' => $nullableId($this->payment_term_id),
            'route_id' => $nullableId($this->route_id),
            'ship_via_id' => $nullableId($this->ship_via_id),
            'ship_from_site_id' => $nullableId($this->ship_from_site_id),
            'ship_date' => $this->ship_date ?: null,
            'no_of_boxes' => (int) $this->no_of_boxes,
            'no_of_pallets' => (int) $this->no_of_pallets,
            'custom_field_1' => $this->custom_field_1,
            'custom_field_2' => $this->custom_field_2,
            'custom_field_3' => $this->custom_field_3,
            'custom_field_4' => $this->custom_field_4,
            'custom_field_5' => $this->custom_field_5,
            'comments' => $this->comments,
            'subtotal' => $subtotal,
            'trade_discount' => $this->trade_discount,
            'freight' => $this->freight,
            'miscellaneous' => $this->miscellaneous,
            'tax' => $this->tax,
            'total' => $total,
            'created_by' => $this->salesOrder?->created_by ?? auth()->id(),
        ];

        DB::transaction(function () use (&$data, $companyId) {
            if ($this->salesOrder) {
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
                $qty = (float) $line['qty_ordered'];
                $price = (float) $line['price'];
                $discount = (float) $line['discount'];
                $order->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty_ordered' => $qty,
                    'qty_shipped' => (float) ($line['qty_shipped'] ?? 0),
                    'price' => $price,
                    'discount' => $discount,
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
        });

        if ($this->lineWarning !== '') {
            session()->flash('status', $this->lineWarning);
        }

        $this->redirect(route('sales.orders.index'), navigate: true);
    }
}; ?>

<div class="so-page">
    <x-action-bar title="Action" class="so-action-full" />

    <form id="so-form" wire:submit="save" class="so-screen">
        @if (filled($customerAlert))
            <div class="mx-2 mt-1 border border-amber-400 bg-amber-50 px-2 py-1 text-xs text-amber-950" role="alert">
                <strong>Alert:</strong> {{ $customerAlert }}
            </div>
        @endif
        @if (filled($creditWarning))
            <div class="mx-2 mt-1 border border-orange-500 bg-orange-50 px-2 py-1 text-xs text-orange-950" role="alert">
                <strong>Credit:</strong> {{ $creditWarning }}
            </div>
        @endif
        @if (filled($taxExemptWarning))
            <div class="mx-2 mt-1 border border-red-500 bg-red-50 px-2 py-1 text-xs text-red-950" role="alert">
                <strong>Tax Exempt:</strong> {{ $taxExemptWarning }}
            </div>
        @endif
        @if (filled($lineWarning))
            <div class="mx-2 mt-1 border border-sky-400 bg-sky-50 px-2 py-1 text-xs text-sky-950" role="status">
                {{ $lineWarning }}
            </div>
        @endif
        @error('customer_id')
            <div class="mx-2 mt-1 border border-red-400 bg-red-50 px-2 py-1 text-xs text-red-900" role="alert">{{ $message }}</div>
        @enderror

        <div class="so-body">
            @if ($activeTab === 'general')
            <div class="so-header" id="mode-panel-general" role="tabpanel" aria-labelledby="mode-tab-general">
                <div class="so-form-card">
                    <div class="so-form-layout">
                        <div class="so-form-main" aria-label="Order customer and address">
                            <div class="so-form-row so-form-row-pair">
                                <label class="so-form-lbl" for="order_type">Order Type</label>
                                <select id="order_type" wire:model="order_type" class="so-input" aria-label="Order Type">
                                    <option>Sales Order</option>
                                    <option>Return</option>
                                </select>
                                <label class="so-form-lbl" for="order_number">Order No</label>
                                <div class="so-lookup-row">
                                    <input id="order_number" wire:model="order_number" class="so-input font-mono" aria-label="Order Number" readonly title="Auto-generated" />
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

                            <div class="so-form-row">
                                <label class="so-form-lbl" for="customer_id">Customer</label>
                                <div class="so-form-ctl">
                                    <div class="so-lookup-row">
                                        <select id="customer_id" wire:model.live="customer_id" class="so-input" aria-label="Customer">
                                            <option value="">—</option>
                                            @foreach ($customers as $c)
                                                <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="so-icon-btn" title="Favorite" tabindex="-1" aria-label="Favorite">
                                            <svg viewBox="0 0 12 12" fill="currentColor"><path d="M6 10.2l-3.5-2.1A2.7 2.7 0 016 2.4a2.7 2.7 0 013.5 5.7L6 10.2z"/></svg>
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
                                    @if ($showCustomerBrowse)
                                        <div class="so-lookup-panel" role="dialog" aria-label="Customer browse">
                                            <div class="so-lookup-panel-head">
                                                <input type="text" wire:model.live.debounce.200ms="customerSearch" class="so-input" placeholder="Search customer ID, name, phone…" aria-label="Search customers" />
                                                <button type="button" wire:click="$set('showCustomerBrowse', false)" class="so-icon-btn" title="Close" aria-label="Close">×</button>
                                            </div>
                                            <table class="so-lookup-table">
                                                <thead>
                                                    <tr><th>ID</th><th>Company</th><th>Contact</th><th>Phone</th><th>City</th></tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($browseCustomers as $bc)
                                                        <tr wire:click="pickCustomer({{ $bc->id }})" class="cursor-pointer hover:bg-sky-100">
                                                            <td class="font-mono">{{ $bc->customer_id }}</td>
                                                            <td>{{ $bc->company_name }}</td>
                                                            <td>{{ $bc->contact }}</td>
                                                            <td>{{ $bc->telephone }}</td>
                                                            <td>{{ $bc->city }}{{ $bc->state ? ', '.$bc->state : '' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="5" class="text-slate-500 px-2 py-2">No customers found.</td></tr>
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
                                        <button type="button" wire:click="toggleShipBrowse" class="so-icon-btn" title="Browse" aria-label="Browse ship-to" @disabled(! $customer_id)>
                                            <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                                        </button>
                                    </div>
                                    @if ($showShipBrowse && $selectedCustomer)
                                        <div class="so-lookup-panel" role="dialog" aria-label="Ship-to browse">
                                            <div class="so-lookup-panel-head">
                                                <span class="text-xs font-semibold text-slate-700">Ship-to addresses</span>
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
                                                        <tr><td colspan="5" class="text-slate-500 px-2 py-2">No ship-to addresses for this customer.</td></tr>
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
                                <label class="so-form-lbl" for="order_date">Order Date</label>
                                <input id="order_date" type="date" wire:model="order_date" class="so-input" aria-label="Order Date" />
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
                                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
            @elseif ($activeTab === 'items')
                <div class="so-items-wrap so-items-wrap-tall" id="mode-panel-items" role="tabpanel" aria-labelledby="mode-tab-items">
                    <div class="so-items-title">Items</div>
                    <div class="so-items-grid">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th>U of M</th>
                                    <th class="text-right">Qty Ordered</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">Total</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    <tr>
                                        <td>
                                            <input wire:model.blur="lines.{{ $i }}.item_code" wire:keydown.enter.prevent="lookupItem({{ $i }})" class="so-input font-mono" style="width:6.5rem" />
                                        </td>
                                        <td><input wire:model="lines.{{ $i }}.description" class="so-input w-full min-w-[10rem]" /></td>
                                        <td><input wire:model="lines.{{ $i }}.uom" class="so-input" style="width:3.5rem" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.qty_ordered" class="so-input text-right" style="width:4.5rem" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.price" class="so-input text-right" style="width:5rem" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.discount" class="so-input text-right" style="width:4.5rem" /></td>
                                        <td class="so-line-total text-right pe-2">${{ number_format(((float) $line['qty_ordered'] * (float) $line['price']) - (float) $line['discount'], 2) }}</td>
                                        <td><button type="button" wire:click="removeLine({{ $i }})" class="text-red-600 text-xs px-1">×</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @unless ($hasLines)
                            <div class="so-items-empty">Enter item code or click browse to add items</div>
                        @endunless
                    </div>
                    <div class="so-entry">
                        <span class="so-entry-label">Enter item code (F2)</span>
                        <input
                            wire:model="itemEntry"
                            wire:keydown.enter.prevent="addItemFromEntry"
                            wire:keydown.f2.prevent="toggleBrowse"
                            class="so-input so-entry-input"
                        />
                        <button type="button" wire:click="addItemFromEntry" class="so-icon-btn" title="Add" tabindex="-1" aria-label="Add item">
                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                        </button>
                        <button type="button" class="so-icon-btn" title="Print" tabindex="-1" aria-label="Print">
                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M3 4V2h6v2M3 8H2V5h8v3H9M3 7h6v3H3V7z"/></svg>
                        </button>
                        <button type="button" wire:click="removeLine({{ max(count($lines) - 1, 0) }})" class="so-icon-btn" title="Delete" tabindex="-1" aria-label="Delete line">
                            <svg viewBox="0 0 12 12" fill="none" stroke="#b91c1c" stroke-width="1.6"><path d="M3 3l6 6M9 3L3 9"/></svg>
                        </button>
                        <button type="button" wire:click="addLine" class="so-icon-btn" title="New line" aria-label="New line">
                            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                        </button>
                        <button type="button" wire:click="toggleBrowse" class="so-browse-btn">Browse</button>
                    </div>
                    @if ($showBrowse)
                        <div class="border-t border-slate-300 bg-white">
                            <label class="flex items-center gap-2 px-2 py-1 text-xs border-b border-slate-200">
                                <input type="checkbox" wire:model.live="browseNewOnly" />
                                New Items only (last 30 days)
                            </label>
                            <div class="max-h-40 overflow-auto">
                            <table class="w-full text-xs">
                                <thead><tr class="bg-slate-100"><th class="px-2 py-1 text-left">Code</th><th class="px-2 py-1 text-left">Description</th><th class="px-2 py-1 text-right">Price</th><th class="px-2 py-1 text-left">New</th></tr></thead>
                                <tbody>
                                    @foreach ($browseItems as $bi)
                                        <tr class="hover:bg-sky-50 cursor-pointer" wire:click="pickBrowseItem({{ $bi->id }})">
                                            <td class="px-2 py-0.5 font-mono">{{ $bi->item_code }}</td>
                                            <td class="px-2 py-0.5">{{ $bi->description }}</td>
                                            <td class="px-2 py-0.5 text-right">${{ number_format($bi->list_price, 2) }}</td>
                                            <td class="px-2 py-0.5">{{ $bi->created_at && $bi->created_at->gte(now()->subDays(30)) ? 'Yes' : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="so-footer">
                    <div class="so-counters">
                        <div class="so-counter-line">
                            <span>Total Lines: <strong>{{ $totalLines }}</strong></span>
                            <span>Total Discounts: <strong>${{ number_format($totalDiscounts, 2) }}</strong></span>
                        </div>
                        <div class="so-counter-line">
                            <span>Total Items: <strong>{{ $totalItems }}</strong></span>
                            <span>Total Allowances: <strong>${{ number_format($totalAllowances, 2) }}</strong></span>
                        </div>
                        <div class="so-counter-line">
                            <span>Total quantity ordered: <strong>{{ number_format($totalQty, 0) }}</strong></span>
                        </div>
                        <div class="so-counter-line">
                            <span>Total items Shipped: <strong>{{ $totalShipped }}</strong></span>
                        </div>
                    </div>
                    <div class="so-totals">
                        <div class="so-totals-row"><span class="so-totals-lbl">Subtotal:</span><span class="so-totals-amt">${{ number_format($subtotal, 2) }}</span></div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Trade Discount:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="trade_discount" class="so-totals-input" /></label>
                        </div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Freight:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="freight" class="so-totals-input" /></label>
                        </div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Miscellaneous:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="miscellaneous" class="so-totals-input" /></label>
                        </div>
                        <div class="so-totals-row">
                            <span class="so-totals-lbl">Tax:</span>
                            <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="tax" wire:change="markTaxManual" class="so-totals-input" /></label>
                        </div>
                        <div class="so-totals-row so-totals-final"><span class="so-totals-lbl">Total:</span><strong class="so-totals-amt">${{ number_format($orderTotal, 2) }}</strong></div>
                    </div>
                </div>
            @elseif ($activeTab === 'shipping')
                <div class="so-ship-panel" id="mode-panel-shipping" role="tabpanel" aria-labelledby="mode-tab-shipping">
                    <div class="so-ship-grid">
                        <div class="so-ship-col">
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="payment_term_id">Payment Terms:</label>
                                <select id="payment_term_id" wire:model="payment_term_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($paymentTerms as $pt)<option value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="route_id">Route:</label>
                                <select id="route_id" wire:model="route_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($routes as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_via_id">Ship Via:</label>
                                <select id="ship_via_id" wire:model="ship_via_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($shipVias as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_from_site_id">Ship From:</label>
                                <select id="ship_from_site_id" wire:model="ship_from_site_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="ship_date">Ship Date:</label>
                                <input id="ship_date" type="date" wire:model="ship_date" class="so-input" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="no_of_boxes">No. of Boxes:</label>
                                <input id="no_of_boxes" type="number" min="0" wire:model="no_of_boxes" class="so-input so-w-num" />
                            </div>
                            <div class="so-ship-row">
                                <label class="so-ship-lbl" for="no_of_pallets">No. of Pallets:</label>
                                <input id="no_of_pallets" type="number" min="0" wire:model="no_of_pallets" class="so-input so-w-num" />
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
            @endif
        </div>

    </form>

    <div class="so-bottom so-bottom-full">
        <div class="so-bottom-tabs">
            <x-mode-tabs
                :tabs="['general' => 'General', 'items' => 'Expand', 'shipping' => 'Shipping info.']"
                :active="$activeTab"
            />
        </div>
        <div class="so-bottom-actions">
            <a href="{{ route('sales.orders.index') }}" wire:navigate class="so-btn-cancel">Cancel</a>
            <button type="submit" form="so-form" class="so-btn-save">Save Changes</button>
        </div>
    </div>

    @if ($showSubstitutePrompt)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelSubstitutePrompt" role="dialog" aria-modal="true" aria-labelledby="sub-prompt-title">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-lg">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span id="sub-prompt-title">Force substitute suggested</span>
                    <button type="button" wire:click="cancelSubstitutePrompt" class="text-white hover:text-red-200" aria-label="Close">×</button>
                </div>
                <div class="p-3 space-y-2 text-sm">
                    <p>Selected item is out of stock. Choose a forced substitute, or keep the original.</p>
                    <ul class="border border-slate-300 divide-y max-h-48 overflow-auto">
                        @forelse ($substituteOptions as $opt)
                            <li class="flex items-center justify-between gap-2 px-2 py-1.5">
                                <span>
                                    <span class="font-mono">{{ $opt['item_code'] }}</span>
                                    — {{ $opt['description'] }}
                                    <span class="text-xs text-slate-500">(avail {{ number_format($opt['available'], 0) }})</span>
                                </span>
                                <button type="button" wire:click="acceptSubstitute({{ $opt['id'] }})" class="chief-btn-primary text-xs">Use</button>
                            </li>
                        @empty
                            <li class="px-2 py-2 text-slate-500">No substitute items configured.</li>
                        @endforelse
                    </ul>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="cancelSubstitutePrompt" class="chief-btn">Cancel</button>
                        <button type="button" wire:click="keepOriginalItem" class="chief-btn">Keep original</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
