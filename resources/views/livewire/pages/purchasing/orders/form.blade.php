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

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty_ordered:string,qty_received:string,unit_cost:string}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
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
        ];
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
            return;
        }

        $supplierCost = $item->itemSuppliers()
            ->when($this->supplier_id, fn ($q) => $q->where('supplier_id', $this->supplier_id))
            ->orderByDesc('is_default')
            ->first();

        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['unit_cost'] = (string) ($supplierCost?->last_cost ?: $item->current_cost ?: $item->standard_cost);
    }

    public function save(): void
    {
        $this->validate([
            'po_number' => 'required|string|max:64',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'lines.*.item_code' => 'nullable|string|max:64',
            'lines.*.qty_ordered' => 'nullable|numeric',
            'lines.*.unit_cost' => 'nullable|numeric',
        ]);

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

<div>
    <form wire:submit="save" class="chief-panel bg-white flex flex-col min-h-[72vh]">
        <x-action-bar :title="$purchaseOrder ? 'PO '.$po_number : 'New Purchase Order'" />

        <div class="flex-1 p-3 overflow-auto">
            @if ($activeTab === 'general')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10">
                    <div class="space-y-1">
                        <div class="chief-field"><label>PO No.</label><input wire:model="po_number" class="chief-input w-44 font-mono" @disabled($purchaseOrder) /></div>
                        <div class="chief-field">
                            <label>Order Type</label>
                            <select wire:model="order_type" class="chief-input w-40">
                                <option>Standard</option>
                                <option>Drop Ship</option>
                                <option>Blanket</option>
                            </select>
                        </div>
                        <div class="chief-field"><label>Reference No.</label><input wire:model="reference_no" class="chief-input w-44" /></div>
                        <div class="chief-field"><label>Requisition Date</label><input type="date" wire:model="requisition_date" class="chief-input" /></div>
                        <div class="chief-field"><label>Order Status</label><input wire:model="status" class="chief-input w-40 bg-slate-50" readonly /></div>
                        <div class="chief-field">
                            <label>Buyer</label>
                            <select wire:model="buyer_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($buyers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field"><label>Required Date</label><input type="date" wire:model="required_date" class="chief-input" /></div>
                        <div class="chief-field">
                            <label>Ship To</label>
                            <select wire:model="ship_to_site_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Supplier</label>
                            <select wire:model.live="supplier_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->supplier_id }} — {{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($selectedSupplier)
                            <div class="ms-[9.5rem] text-sm text-slate-600 mb-2">
                                {{ $selectedSupplier->address }}<br>
                                {{ $selectedSupplier->city }}, {{ $selectedSupplier->state }} {{ $selectedSupplier->zip_code }}
                            </div>
                        @endif
                        <div class="chief-field"><label>Ship From</label><input wire:model="ship_from" class="chief-input w-56" /></div>
                        <div class="chief-field">
                            <label>Terms</label>
                            <select wire:model="payment_term_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($paymentTerms as $pt)<option value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Ship Via</label>
                            <select wire:model="ship_via_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($shipVias as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field chief-field-top"><label>Comments</label><textarea wire:model="comments" rows="4" class="chief-input w-full max-w-md"></textarea></div>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-slate-600">Enter item code (or supplier SKU) and press Tab / Lookup</p>
                    <button type="button" wire:click="addLine" class="chief-btn text-xs">Add Line</button>
                </div>
                <div class="chief-grid border border-slate-300 overflow-auto mb-3">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>U of M</th>
                                <th class="text-right">Qty Ordered</th>
                                <th class="text-right">Qty Received</th>
                                <th class="text-right">Cost</th>
                                <th class="text-right">Extended</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                <tr>
                                    <td>
                                        <div class="flex gap-1">
                                            <input wire:model.blur="lines.{{ $i }}.item_code" wire:keydown.tab="lookupItem({{ $i }})" wire:keydown.enter.prevent="lookupItem({{ $i }})" class="chief-input w-28 font-mono" />
                                            <button type="button" wire:click="lookupItem({{ $i }})" class="chief-btn text-xs px-1">…</button>
                                        </div>
                                    </td>
                                    <td><input wire:model="lines.{{ $i }}.description" class="chief-input w-full min-w-[10rem]" /></td>
                                    <td><input wire:model="lines.{{ $i }}.uom" class="chief-input w-16" /></td>
                                    <td><input wire:model.live="lines.{{ $i }}.qty_ordered" class="chief-input w-24 text-right" /></td>
                                    <td><input wire:model="lines.{{ $i }}.qty_received" class="chief-input w-24 text-right bg-slate-50" readonly /></td>
                                    <td><input wire:model.live="lines.{{ $i }}.unit_cost" class="chief-input w-24 text-right" /></td>
                                    <td class="text-right pe-2">${{ number_format((float) $line['qty_ordered'] * (float) $line['unit_cost'], 2) }}</td>
                                    <td><button type="button" wire:click="removeLine({{ $i }})" class="text-xs text-red-700">−</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-6 max-w-3xl">
                    <div class="space-y-1 text-sm">
                        <div>Total Items Ordered: <strong>{{ number_format($totalItemsOrdered, 2) }}</strong></div>
                        <div>Total Items Received: <strong>{{ number_format($totalItemsReceived, 2) }}</strong></div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field"><label>Subtotal</label><span class="w-28 text-right">${{ number_format($subtotal, 2) }}</span></div>
                        <div class="chief-field"><label>Trade Discount</label><input wire:model.live="trade_discount" class="chief-input w-28 text-right" /></div>
                        <div class="chief-field"><label>Freight</label><input wire:model.live="freight" class="chief-input w-28 text-right" /></div>
                        <div class="chief-field"><label>Miscellaneous</label><input wire:model.live="miscellaneous" class="chief-input w-28 text-right" /></div>
                        <div class="chief-field"><label>Tax</label><input wire:model.live="tax" class="chief-input w-28 text-right" /></div>
                        <div class="chief-field"><label>Total</label><strong class="w-28 text-right">${{ number_format($orderTotal, 2) }}</strong></div>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-slate-300 bg-slate-100 px-1">
            <div class="flex">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        @class(['px-3 py-1.5 text-sm border-r border-slate-300', 'bg-white font-semibold text-sky-800' => $activeTab === $key])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2 py-2 pe-2">
                <a href="{{ route('purchasing.orders.index') }}" wire:navigate class="chief-btn">Cancel</a>
                <button type="submit" class="chief-btn-primary">Save Changes</button>
            </div>
        </div>
    </form>
</div>
