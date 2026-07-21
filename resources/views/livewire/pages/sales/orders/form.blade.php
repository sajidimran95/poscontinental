<?php

use App\Models\Customer;
use App\Models\Item;
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

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,price:string,discount:string}> */
    public array $lines = [];

    /** @var array<int, array{box_number:string,tracking_number:string}> */
    public array $boxes = [];

    public function mount(?SalesOrder $salesOrder = null): void
    {
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
            $this->customerAlert = $salesOrder->customer?->messages_alerts ?? '';
            $this->lines = $salesOrder->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'qty_ordered' => (string) $l->qty_ordered,
                'price' => (string) $l->price,
                'discount' => (string) $l->discount,
            ])->all();
            $this->boxes = $salesOrder->boxes->map(fn ($b) => [
                'box_number' => $b->box_number ?? '',
                'tracking_number' => $b->tracking_number ?? '',
            ])->all();
        } else {
            $this->order_number = SalesOrder::nextNumber($companyId);
            $this->order_date = now()->toDateString();
            $this->required_date = now()->toDateString();
            $this->sales_rep_id = auth()->id();
            $this->ship_from_site_id = auth()->user()->site_id;
        }

        if ($this->boxes === []) {
            $this->boxes[] = ['box_number' => '', 'tracking_number' => ''];
        }
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '',
            'qty_ordered' => '1', 'price' => '0', 'discount' => '0',
        ];
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $filledLines = collect($this->lines)->filter(fn ($l) => filled($l['item_code'] ?? null));
        $subtotal = $filledLines->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        return [
            'customers' => Customer::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('company_name')->get(),
            'selectedCustomer' => $this->customer_id ? Customer::query()->with('shippingAddresses')->find($this->customer_id) : null,
            'salesReps' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'shipVias' => ShipVia::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'browseItems' => $this->showBrowse
                ? Item::query()->where('company_id', $companyId)->where('is_inactive', false)->where('can_sell', true)->orderBy('item_code')->limit(80)->get(['id', 'item_code', 'description', 'unit_of_measure', 'list_price'])
                : collect(),
            'subtotal' => $subtotal,
            'orderTotal' => $total,
            'totalLines' => $filledLines->count(),
            'totalItems' => $filledLines->count(),
            'totalQty' => $filledLines->sum(fn ($l) => (float) $l['qty_ordered']),
            'totalShipped' => 0,
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

        $ship = $customer->shippingAddresses->firstWhere('is_primary', true) ?? $customer->shippingAddresses->first();
        if ($ship) {
            $this->ship_to_address_id = $ship->id;
            $this->applyShipAddress($ship);
        } else {
            $this->ship_to_name = $this->bill_to_name;
            $this->ship_to_phone = $this->bill_to_phone;
            $this->ship_to_address = $this->bill_to_address;
            $this->ship_to_city = $this->bill_to_city;
            $this->ship_to_state = $this->bill_to_state;
            $this->ship_to_zip = $this->bill_to_zip;
        }
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
        }
    }

    protected function applyShipAddress($ship): void
    {
        $this->ship_to_name = $ship->name ?? '';
        $this->ship_to_phone = $ship->telephone ?? '';
        $this->ship_to_address = $ship->address ?? '';
        $this->ship_to_city = '';
        $this->ship_to_state = '';
        $this->ship_to_zip = '';
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
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
        $this->lines[] = $this->emptyLine();
        $index = count($this->lines) - 1;
        $this->fillLineFromItem($index, $item);
        $this->itemEntry = '';
        $this->showBrowse = false;
    }

    public function pickBrowseItem(int $itemId): void
    {
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($itemId);
        if (! $item) {
            return;
        }
        $this->lines[] = $this->emptyLine();
        $this->fillLineFromItem(count($this->lines) - 1, $item);
        $this->showBrowse = false;
        $this->itemEntry = '';
    }

    public function toggleBrowse(): void
    {
        $this->showBrowse = ! $this->showBrowse;
    }

    protected function findItem(string $code): ?Item
    {
        return Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)->orWhere('primary_upc', $code);
            })
            ->first();
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = trim(($item->description ?? '').($item->item_line_message ? ' | '.$item->item_line_message : ''));
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['price'] = (string) $item->list_price;
        $this->lines[$index]['qty_ordered'] = $this->lines[$index]['qty_ordered'] ?: '1';
        $this->lines[$index]['discount'] = $this->lines[$index]['discount'] ?: '0';
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

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $subtotal = collect($this->lines)->sum(fn ($l) => ((float) $l['qty_ordered'] * (float) $l['price']) - (float) $l['discount']);
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        $data = [
            'company_id' => auth()->user()->company_id,
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

        DB::transaction(function () use ($data) {
            if ($this->salesOrder) {
                $this->salesOrder->update($data);
                $order = $this->salesOrder->fresh();
                $order->lines()->delete();
                $order->boxes()->delete();
            } else {
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

        <div class="so-body">
            <div @class(['so-header', 'so-header-expand' => $activeTab === 'expand'])>
                {{-- Labels above fields --}}
                <div class="so-header-grid">
                    <div class="so-field-stack">
                        <label class="so-lbl">Order Type:</label>
                        <select wire:model="order_type" class="so-input so-w-ordertype">
                            <option>Sales Order</option>
                            <option>Return</option>
                        </select>
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Order No:</label>
                        <div class="so-lookup-row">
                            <input wire:model="order_number" class="so-input font-mono so-w-orderno" @disabled($salesOrder) />
                            <button type="button" class="so-icon-btn" title="Lookup" tabindex="-1" aria-label="Lookup">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 6h8M6 2v8"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Order Status:</label>
                        <input wire:model="status" class="so-input so-w-status" readonly />
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Priority:</label>
                        <select wire:model="priority" class="so-input so-w-status">
                            <option>Normal</option>
                            <option>High</option>
                            <option>Low</option>
                        </select>
                    </div>

                    <div class="so-field-stack so-field-wide">
                        <label class="so-lbl">Customer:</label>
                        <div class="so-lookup-row">
                            <select wire:model.live="customer_id" class="so-input so-w-customer">
                                <option value="">—</option>
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="so-icon-btn" title="Favorite" tabindex="-1" aria-label="Favorite">
                                <svg viewBox="0 0 12 12" fill="currentColor"><path d="M6 10.2l-3.5-2.1A2.7 2.7 0 016 2.4a2.7 2.7 0 013.5 5.7L6 10.2z"/></svg>
                            </button>
                            <button type="button" class="so-icon-btn" title="Search" tabindex="-1" aria-label="Search">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="5" r="3.2"/><path d="M7.5 7.5L10.5 10.5"/></svg>
                            </button>
                            <a href="{{ route('sales.customers.create') }}" wire:navigate class="so-icon-btn" title="New" aria-label="New customer">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2v8M2 6h8"/></svg>
                            </a>
                            <button type="button" class="so-icon-btn" title="Info" tabindex="-1" aria-label="Info">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="6" cy="6" r="4.2"/><path d="M6 5.2V8.5M6 3.6h.01"/></svg>
                            </button>
                            <button type="button" class="so-icon-btn" title="Browse" tabindex="-1" aria-label="Browse">
                                <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Order Date:</label>
                        <input type="date" wire:model="order_date" class="so-input so-w-date" />
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Required Date:</label>
                        <input type="date" wire:model="required_date" class="so-input so-w-date" />
                    </div>

                    <div class="so-field-stack so-field-wide">
                        <label class="so-lbl">Ship to:</label>
                        <div class="so-lookup-row">
                            <select wire:model.live="ship_to_address_id" class="so-input so-w-customer">
                                <option value="">—</option>
                                @if ($selectedCustomer)
                                    @foreach ($selectedCustomer->shippingAddresses as $addr)
                                        <option value="{{ $addr->id }}">{{ $addr->name ?: $addr->address ?: 'Ship-To #'.$addr->id }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <button type="button" class="so-icon-btn" title="Browse" tabindex="-1" aria-label="Browse ship-to">
                                <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                            </button>
                            <button type="button" class="so-icon-btn" title="More" tabindex="-1" aria-label="More">
                                <svg viewBox="0 0 12 12" fill="currentColor"><path d="M2 4l4 4 4-4"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Customer PO No.:</label>
                        <input wire:model="customer_po_no" class="so-input so-w-date" />
                    </div>
                    <div class="so-field-stack">
                        <label class="so-lbl">Reference No:</label>
                        <input wire:model="reference_no" class="so-input so-w-date" />
                    </div>

                    <div class="so-addr-block">
                        <div class="so-addr-tabs">
                            <button type="button" wire:click="$set('addressTab', 'bill')" @class(['so-addr-tab', 'so-addr-tab-active' => $addressTab === 'bill'])>Bill To Address</button>
                            <button type="button" wire:click="$set('addressTab', 'ship')" @class(['so-addr-tab', 'so-addr-tab-active' => $addressTab === 'ship'])>Ship To Address</button>
                        </div>
                        <table class="so-addr-table">
                            @if ($addressTab === 'bill')
                                <tr>
                                    <td class="so-addr-lbl">Name:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="bill_to_name" class="so-input" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">Phone No.:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="bill_to_phone" class="so-input so-w-phone" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">Address:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="bill_to_address" class="so-input" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">City:</td>
                                    <td class="so-addr-ctl"><input wire:model="bill_to_city" class="so-input so-w-city" /></td>
                                    <td class="so-addr-lbl so-addr-lbl-inline">State:</td>
                                    <td class="so-addr-ctl"><input wire:model="bill_to_state" class="so-input so-w-state" /></td>
                                    <td class="so-addr-lbl so-addr-lbl-inline">ZIP code:</td>
                                    <td class="so-addr-ctl"><input wire:model="bill_to_zip" class="so-input so-w-zip" /></td>
                                </tr>
                            @else
                                <tr>
                                    <td class="so-addr-lbl">Name:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="ship_to_name" class="so-input" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">Phone No.:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="ship_to_phone" class="so-input so-w-phone" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">Address:</td>
                                    <td class="so-addr-ctl" colspan="5"><input wire:model="ship_to_address" class="so-input" /></td>
                                </tr>
                                <tr>
                                    <td class="so-addr-lbl">City:</td>
                                    <td class="so-addr-ctl"><input wire:model="ship_to_city" class="so-input so-w-city" /></td>
                                    <td class="so-addr-lbl so-addr-lbl-inline">State:</td>
                                    <td class="so-addr-ctl"><input wire:model="ship_to_state" class="so-input so-w-state" /></td>
                                    <td class="so-addr-lbl so-addr-lbl-inline">ZIP code:</td>
                                    <td class="so-addr-ctl"><input wire:model="ship_to_zip" class="so-input so-w-zip" /></td>
                                </tr>
                            @endif
                        </table>
                    </div>
                    <div class="so-field-stack so-field-rep">
                        <label class="so-lbl">Sales Rep.:</label>
                        <select wire:model="sales_rep_id" class="so-input so-w-rep">
                            <option value="">—</option>
                            @foreach ($salesReps as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if ($activeTab !== 'shipping')
                <div @class(['so-items-wrap', 'so-items-wrap-tall' => $activeTab === 'expand'])>
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
                        <div class="max-h-40 overflow-auto bg-white">
                            <table class="w-full text-xs">
                                <thead><tr class="bg-slate-100"><th class="px-2 py-1 text-left">Code</th><th class="px-2 py-1 text-left">Description</th><th class="px-2 py-1 text-right">Price</th></tr></thead>
                                <tbody>
                                    @foreach ($browseItems as $bi)
                                        <tr class="hover:bg-sky-50 cursor-pointer" wire:click="pickBrowseItem({{ $bi->id }})">
                                            <td class="px-2 py-0.5 font-mono">{{ $bi->item_code }}</td>
                                            <td class="px-2 py-0.5">{{ $bi->description }}</td>
                                            <td class="px-2 py-0.5 text-right">${{ number_format($bi->list_price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @else
                <div class="so-ship-panel">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-0.5">
                        <div>
                            <div class="so-field"><label>Payment Terms:</label>
                                <select wire:model="payment_term_id" class="so-input" style="max-width:14rem">
                                    <option value="">—</option>
                                    @foreach ($paymentTerms as $pt)<option value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-field"><label>Route:</label>
                                <select wire:model="route_id" class="so-input" style="max-width:14rem">
                                    <option value="">—</option>
                                    @foreach ($routes as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-field"><label>Ship Via:</label>
                                <select wire:model="ship_via_id" class="so-input" style="max-width:14rem">
                                    <option value="">—</option>
                                    @foreach ($shipVias as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="so-field"><label>Ship From:</label>
                                <select wire:model="ship_from_site_id" class="so-input" style="max-width:8rem">
                                    <option value="">—</option>
                                    @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <div class="so-field"><label>Custom Field 1:</label><input wire:model="custom_field_1" class="so-input" /></div>
                            <div class="so-field"><label>Custom Field 2:</label><input wire:model="custom_field_2" class="so-input" /></div>
                            <div class="so-field"><label>Comments:</label><textarea wire:model="comments" rows="3" class="so-input" style="height:auto"></textarea></div>
                        </div>
                    </div>
                </div>
            @endif

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
                        <label class="so-totals-amt">$<input type="text" inputmode="decimal" wire:model.live="tax" class="so-totals-input" /></label>
                    </div>
                    <div class="so-totals-row so-totals-final"><span class="so-totals-lbl">Total:</span><strong class="so-totals-amt">${{ number_format($orderTotal, 2) }}</strong></div>
                </div>
            </div>
        </div>

    </form>

    <div class="so-bottom so-bottom-full">
        <div class="so-bottom-tabs">
            <div class="so-mode-tabs">
                <button type="button" wire:click="$set('activeTab', 'general')" @class(['so-mode-tab', 'so-mode-tab-active' => $activeTab === 'general'])>
                    @if ($activeTab === 'general')<span class="so-mode-check">●</span>@endif General
                </button>
                <button type="button" wire:click="$set('activeTab', 'expand')" @class(['so-mode-tab', 'so-mode-tab-active' => $activeTab === 'expand'])>Expand</button>
                <button type="button" wire:click="$set('activeTab', 'shipping')" @class(['so-mode-tab', 'so-mode-tab-active' => $activeTab === 'shipping'])>Shipping info.</button>
            </div>
        </div>
        <div class="so-bottom-actions">
            <a href="{{ route('sales.orders.index') }}" wire:navigate class="so-btn-cancel">Cancel</a>
            <button type="submit" form="so-form" class="so-btn-save">Save Changes</button>
        </div>
    </div>
</div>
