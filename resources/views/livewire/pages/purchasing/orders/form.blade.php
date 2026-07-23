<?php

use App\Models\Item;
use App\Models\PaymentTerm;
use App\Models\PurchaseOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Purchase Order')] class extends Component
{
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

    public string $freight = '0.00';

    public string $trade_discount = '0.00';

    public string $miscellaneous = '0.00';

    public string $tax = '0.00';

    public string $itemLookup = '';

    public bool $showItemBrowse = false;

    public ?int $browseLineIndex = null;

    public string $itemBrowseSearch = '';

    public string $lookupMessage = '';

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,qty_received:string,unit_cost:string}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $this->viewMode = request()->routeIs('purchasing.orders.show');
        $companyId = auth()->user()->company_id;

        if ($purchaseOrder?->exists) {
            abort_unless($purchaseOrder->company_id === $companyId, 403);
            $this->purchaseOrder = $purchaseOrder->load('lines');
            $this->fill($purchaseOrder->only([
                'po_number', 'order_type', 'reference_no', 'status', 'buyer_id', 'ship_to_site_id',
                'supplier_id', 'ship_from', 'payment_term_id', 'ship_via_id', 'comments',
                'freight', 'trade_discount', 'miscellaneous', 'tax',
            ]));
            $this->requisition_date = optional($purchaseOrder->requisition_date)?->format('Y-m-d') ?? '';
            $this->required_date = optional($purchaseOrder->required_date)?->format('Y-m-d') ?? '';
            $this->lines = $purchaseOrder->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'qty_ordered' => (string) $l->qty_ordered,
                'qty_received' => (string) $l->qty_received,
                'unit_cost' => (string) $l->unit_cost,
            ])->all();
        } else {
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
            'qty_ordered' => '1',
            'qty_received' => '0',
            'unit_cost' => '0.00',
        ];
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $subtotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty_ordered'] * (float) $l['unit_cost']);
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

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
            'totalItemsOrdered' => collect($this->lines)->sum(fn ($l) => (float) $l['qty_ordered']),
            'totalItemsReceived' => collect($this->lines)->sum(fn ($l) => (float) $l['qty_received']),
            'tabs' => [
                'general' => 'General',
                'items' => 'Items',
            ],
            'browseItems' => $this->showItemBrowse
                ? Item::query()
                    ->where('company_id', $companyId)
                    ->where('is_inactive', false)
                    ->where('can_order', true)
                    ->when($this->itemBrowseSearch !== '', function ($q) {
                        $term = '%'.$this->itemBrowseSearch.'%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('item_code', 'like', $term)
                                ->orWhere('description', 'like', $term)
                                ->orWhere('primary_upc', 'like', $term);
                        });
                    })
                    ->orderBy('item_code')
                    ->limit(100)
                    ->get(['id', 'item_code', 'description', 'unit_of_measure', 'standard_cost', 'current_cost', 'quantity_in_stock'])
                : collect(),
        ];
    }

    public function openItemBrowse(?int $lineIndex = null): void
    {
        $this->browseLineIndex = $lineIndex;
        $this->itemBrowseSearch = '';
        $this->lookupMessage = '';
        $this->showItemBrowse = true;
    }

    public function closeItemBrowse(): void
    {
        $this->showItemBrowse = false;
        $this->browseLineIndex = null;
        $this->itemBrowseSearch = '';
    }

    public function pickBrowseItem(int $itemId): void
    {
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($itemId);

        if (! $item) {
            return;
        }

        if ($this->browseLineIndex !== null && isset($this->lines[$this->browseLineIndex])) {
            $this->fillLineFromItem($this->browseLineIndex, $item);
        } else {
            $empty = collect($this->lines)->search(fn ($l) => ! filled($l['item_code'] ?? null));
            if ($empty === false) {
                $this->addLine();
                $empty = count($this->lines) - 1;
            }
            $this->fillLineFromItem((int) $empty, $item);
        }

        $this->closeItemBrowse();
        $this->lookupMessage = 'Added item '.$item->item_code.'.';
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function lookupItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            $this->openItemBrowse($index);

            return;
        }

        $companyId = auth()->user()->company_id;
        $item = Item::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)
                    ->orWhere('primary_upc', $code)
                    ->orWhereHas('itemSuppliers', fn ($s) => $s->where('supplier_item_code', $code));
            })
            ->first();

        if (! $item) {
            $this->lookupMessage = 'Item “'.$code.'” not found. Use Browse to pick from inventory.';
            $this->openItemBrowse($index);
            $this->itemBrowseSearch = $code;

            return;
        }

        $this->fillLineFromItem($index, $item);
        $this->lookupMessage = 'Loaded item '.$item->item_code.'.';
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $supplierCost = $item->itemSuppliers()
            ->when($this->supplier_id, fn ($q) => $q->where('supplier_id', $this->supplier_id))
            ->orderByDesc('is_default')
            ->first();

        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['unit_cost'] = (string) ($supplierCost?->last_cost ?: $item->current_cost ?: $item->standard_cost);
        if (! filled($this->lines[$index]['qty_ordered'] ?? null) || (float) $this->lines[$index]['qty_ordered'] <= 0) {
            $this->lines[$index]['qty_ordered'] = '1';
        }
    }

    public function save(): void
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

        $hasLines = collect($this->lines)->contains(fn ($l) => filled($l['item_code'] ?? null) && (float) ($l['qty_ordered'] ?? 0) > 0);
        if (! $hasLines) {
            $this->addError('lines', 'Add at least one line item with an item code and quantity.');
            $this->activeTab = 'items';

            return;
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;
        $subtotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty_ordered'] * (float) $l['unit_cost']);
        $total = $subtotal - (float) $this->trade_discount + (float) $this->freight + (float) $this->miscellaneous + (float) $this->tax;

        $data = [
            'company_id' => auth()->user()->company_id,
            'po_number' => $this->po_number,
            'order_type' => $this->order_type,
            'reference_no' => $this->reference_no,
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
            'trade_discount' => $this->trade_discount,
            'freight' => $this->freight,
            'miscellaneous' => $this->miscellaneous,
            'tax' => $this->tax,
            'total' => $total,
        ];

        DB::transaction(function () use ($data) {
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
                $qty = (float) ($line['qty_ordered'] ?? 0);
                $cost = (float) ($line['unit_cost'] ?? 0);
                $po->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'] ?: null,
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty_ordered' => $qty,
                    'qty_received' => (float) ($line['qty_received'] ?? 0),
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

        $this->redirect(route('purchasing.orders.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form" @class(['item-form-readonly' => $viewMode])>
        <fieldset class="so-form-fields" @disabled($viewMode)>
        <x-action-bar :title="$purchaseOrder ? 'PO '.$po_number : 'New Purchase Order'" />

        <div class="entity-body">
            <div class="entity-header">
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
                @error('lines')
                    <div class="mt-1 border border-red-400 bg-red-50 px-2 py-1 text-xs text-red-900" role="alert">{{ $message }}</div>
                @enderror
                @if ($activeTab === 'items')
                    <div class="entity-balance">Total: <strong>${{ number_format($orderTotal, 2) }}</strong></div>
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
                                <a href="{{ route('lookups.index', ['activeLookup' => 'payment_terms']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
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
                                <a href="{{ route('lookups.index', ['activeLookup' => 'ship_vias']) }}" wire:navigate class="desk-btn desk-btn-sm">+</a>
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
                                <input id="trade_discount_general" wire:model.live="trade_discount" class="so-input text-right" />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl" for="freight_general">Freight</label>
                                <input id="freight_general" wire:model.live="freight" class="so-input text-right" />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field" style="display:block">
                                <label class="so-form-lbl">Order Total</label>
                                <strong class="entity-value text-right" style="display:block;width:100%;font-size:1.1rem">${{ number_format($orderTotal, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <div class="item-price-summary" style="grid-template-columns: repeat(3, minmax(0, 1fr)); max-width: 36rem;">
                    <div class="item-price-stat">
                        <span>Items Ordered</span>
                        <strong>{{ number_format($totalItemsOrdered, 2) }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>Items Received</span>
                        <strong>{{ number_format($totalItemsReceived, 2) }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>Order Total</span>
                        <strong>${{ number_format($orderTotal, 2) }}</strong>
                    </div>
                </div>

                <div class="entity-section" style="margin-top:0">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Order Lines</h3>
                        <div class="flex gap-2">
                            <button type="button" wire:click="openItemBrowse" class="desk-btn desk-btn-sm">Browse Items</button>
                            <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                        </div>
                    </div>
                    <p class="item-hint" style="border-bottom:1px solid #e2e8f0">
                        Type an existing <strong>Item Code</strong> or UPC and press <strong>Enter</strong>, or click <strong>Browse Items</strong> to pick from inventory.
                    </p>
                    @if ($lookupMessage)
                        <div class="desk-flash" style="margin:0.5rem 0.75rem" role="status">{{ $lookupMessage }}</div>
                    @endif
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table po-lines-table">
                            <colgroup>
                                <col class="col-code" />
                                <col class="col-desc" />
                                <col class="col-uom" />
                                <col class="col-qty" />
                                <col class="col-qty" />
                                <col class="col-cost" />
                                <col class="col-ext" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th class="text-center">UOM</th>
                                    <th class="text-center">Qty Ordered</th>
                                    <th class="text-center">Qty Received</th>
                                    <th class="text-center">Cost</th>
                                    <th class="text-center">Extended</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    <tr>
                                        <td>
                                            <div class="so-lookup-row">
                                                <input
                                                    wire:model.blur="lines.{{ $i }}.item_code"
                                                    wire:keydown.tab="lookupItem({{ $i }})"
                                                    wire:keydown.enter.prevent="lookupItem({{ $i }})"
                                                    class="so-input font-mono item-cell-ctl"
                                                    placeholder="Code + Enter"
                                                    aria-label="Item code line {{ $i + 1 }}"
                                                />
                                                <button type="button" wire:click="openItemBrowse({{ $i }})" class="desk-btn desk-btn-sm" title="Browse items">…</button>
                                            </div>
                                        </td>
                                        <td><input wire:model="lines.{{ $i }}.description" class="so-input item-cell-ctl" /></td>
                                        <td class="text-center"><input wire:model="lines.{{ $i }}.uom" class="so-input text-center item-cell-ctl" style="max-width:4rem;margin:0 auto" /></td>
                                        <td class="text-center"><input wire:model.live="lines.{{ $i }}.qty_ordered" class="so-input text-right item-cell-qty" /></td>
                                        <td class="text-center"><input wire:model="lines.{{ $i }}.qty_received" class="so-input text-right item-cell-qty so-input-ro" readonly /></td>
                                        <td class="text-center"><input wire:model.live="lines.{{ $i }}.unit_cost" class="so-input text-right item-cell-qty" /></td>
                                        <td class="desk-money">${{ number_format((float) $line['qty_ordered'] * (float) $line['unit_cost'], 2) }}</td>
                                        <td class="text-center"><button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="po-totals">
                    <div class="inv-card po-totals-card">
                        <div class="inv-card-title">Totals</div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl">Subtotal</label>
                            <span class="entity-value text-right" style="display:block;width:100%">${{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="trade_discount">Discount</label>
                            <input id="trade_discount" wire:model.live="trade_discount" class="so-input text-right sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="freight">Freight</label>
                            <input id="freight" wire:model.live="freight" class="so-input text-right sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="miscellaneous">Miscellaneous</label>
                            <input id="miscellaneous" wire:model.live="miscellaneous" class="so-input text-right sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="tax">Tax</label>
                            <input id="tax" wire:model.live="tax" class="so-input text-right sc-date" />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field po-total-row">
                            <label class="so-form-lbl">Total</label>
                            <strong class="entity-value text-right" style="display:block;width:100%;font-size:1.15rem">${{ number_format($orderTotal, 2) }}</strong>
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

    @if ($showItemBrowse)
        <div class="desk-modal-backdrop" wire:click.self="closeItemBrowse" role="dialog" aria-modal="true" aria-label="Browse items">
            <div class="desk-modal" style="max-width:48rem">
                <div class="desk-modal-head">
                    <span>Browse Inventory Items</span>
                    <button type="button" wire:click="closeItemBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body">
                    <div class="desk-toolbar" style="padding:0 0 0.75rem;border:0;background:transparent">
                        <label class="desk-toolbar-label" for="po-item-browse">Search</label>
                        <input
                            id="po-item-browse"
                            type="search"
                            wire:model.live.debounce.250ms="itemBrowseSearch"
                            class="desk-search"
                            placeholder="Item code, description, UPC…"
                            autofocus
                        />
                    </div>
                    <div class="desk-grid" style="max-height:22rem;border:1px solid #e2e8f0;border-radius:8px">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th class="text-center">UOM</th>
                                    <th class="desk-money">In Stock</th>
                                    <th class="desk-money">Cost</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($browseItems as $bi)
                                    <tr class="cursor-pointer" wire:click="pickBrowseItem({{ $bi->id }})">
                                        <td class="desk-num">{{ $bi->item_code }}</td>
                                        <td>{{ $bi->description }}</td>
                                        <td class="text-center">{{ $bi->unit_of_measure }}</td>
                                        <td class="desk-money">{{ number_format((float) $bi->quantity_in_stock, 2) }}</td>
                                        <td class="desk-money">${{ number_format((float) ($bi->current_cost ?: $bi->standard_cost), 2) }}</td>
                                        <td>
                                            <button type="button" wire:click.stop="pickBrowseItem({{ $bi->id }})" class="desk-btn desk-btn-sm desk-btn-primary">Add</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty"><td colspan="6">No items found. Create items under Inventory → Items first.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint" style="padding:0.65rem 0 0">Click a row or <strong>Add</strong> to put that item on the purchase order.</p>
                </div>
            </div>
        </div>
    @endif
</div>
