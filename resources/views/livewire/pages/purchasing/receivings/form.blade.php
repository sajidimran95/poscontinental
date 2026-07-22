<?php

use App\Models\InventoryReceiving;
use App\Models\Site;
use App\Models\User;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Receiving')] class extends Component
{
    public InventoryReceiving $receiving;

    public string $receipt_number = '';

    public string $receipt_date = '';

    public string $reference_no = '';

    public string $status = '';

    public ?int $buyer_id = null;

    public ?int $site_id = null;

    public string $received_by = '';

    public string $shipping_carrier = '';

    public string $comments = '';

    /** @var array<int, array{id:int,item_code:string,description:string,uom:string,qty_ordered:string,qty_received:string,unit_cost:string}> */
    public array $lines = [];

    public function mount(InventoryReceiving $receiving): void
    {
        abort_unless($receiving->company_id === auth()->user()->company_id, 403);
        $this->receiving = $receiving->load(['lines', 'purchaseOrder', 'supplier', 'site', 'buyer']);
        $this->receipt_number = $receiving->receipt_number;
        $this->receipt_date = optional($receiving->receipt_date)?->format('Y-m-d') ?? '';
        $this->reference_no = $receiving->reference_no ?? '';
        $this->status = $receiving->status;
        $this->buyer_id = $receiving->buyer_id;
        $this->site_id = $receiving->site_id;
        $this->received_by = $receiving->received_by ?? '';
        $this->shipping_carrier = $receiving->shipping_carrier ?? '';
        $this->comments = $receiving->comments ?? '';
        $this->lines = $receiving->lines->map(fn ($l) => [
            'id' => $l->id,
            'item_code' => $l->item_code ?? '',
            'description' => $l->description ?? '',
            'uom' => $l->uom ?? '',
            'qty_ordered' => (string) $l->qty_ordered,
            'qty_received' => (string) $l->qty_received,
            'unit_cost' => (string) $l->unit_cost,
        ])->all();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $totalOrdered = collect($this->lines)->sum(fn ($l) => (float) $l['qty_ordered']);
        $totalReceived = collect($this->lines)->sum(fn ($l) => (float) $l['qty_received']);
        $lineTotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty_received'] * (float) $l['unit_cost']);

        return [
            'isProcessed' => $this->status === 'Processed',
            'totalOrdered' => $totalOrdered,
            'totalReceived' => $totalReceived,
            'lineTotal' => $lineTotal,
            'users' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'po' => $this->receiving->purchaseOrder,
        ];
    }

    public function updatedReceivedBy(): void
    {
        // Keep as free-text / selected user name
    }

    public function save(): void
    {
        if ($this->receiving->status === 'Processed') {
            $this->receiving->update([
                'received_by' => $this->received_by ?: null,
                'shipping_carrier' => $this->shipping_carrier ?: null,
                'comments' => $this->comments ?: null,
            ]);
            session()->flash('status', 'Receiving details updated.');

            return;
        }

        $this->receiving->update([
            'receipt_date' => $this->receipt_date ?: null,
            'reference_no' => $this->reference_no,
            'buyer_id' => $this->buyer_id ?: null,
            'site_id' => $this->site_id ?: null,
            'received_by' => $this->received_by ?: null,
            'shipping_carrier' => $this->shipping_carrier,
            'comments' => $this->comments,
        ]);

        foreach ($this->lines as $row) {
            $this->receiving->lines()->where('id', $row['id'])->update([
                'qty_received' => $row['qty_received'],
                'unit_cost' => $row['unit_cost'],
            ]);
        }

        session()->flash('status', 'Receiving saved.');
    }

    public function process(): void
    {
        if ($this->receiving->status === 'Processed') {
            return;
        }

        if (! filled($this->received_by)) {
            $this->received_by = auth()->user()->name;
        }

        $this->save();
        app(InventoryService::class)->processReceiving($this->receiving->fresh('lines'));
        $this->redirect(route('purchasing.receivings.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form">
        <x-action-bar title="Inventory Receiving — {{ $receipt_number }}" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="entity-body">
            <div class="entity-header">
                <div class="so-form-row so-form-row-pair entity-header-row">
                    <label class="so-form-lbl" for="receipt_number">Receipt No.</label>
                    <input id="receipt_number" value="{{ $receipt_number }}" class="so-input font-mono so-input-ro" readonly />
                    <span class="so-form-lbl">Status</span>
                    <span @class([
                        'desk-pill',
                        'desk-pill-new' => $status === 'New',
                        'desk-pill-invoiced' => $status === 'Processed',
                        'desk-pill-muted' => ! in_array($status, ['New', 'Processed'], true),
                    ])>{{ $status }}</span>
                </div>
                <div class="entity-balance">Received: <strong>{{ number_format($totalReceived, 2) }}</strong></div>
            </div>

            <div class="item-price-summary" style="grid-template-columns: repeat(3, minmax(0, 1fr)); max-width: 36rem;">
                <div class="item-price-stat">
                    <span>Qty Ordered</span>
                    <strong>{{ number_format($totalOrdered, 2) }}</strong>
                </div>
                <div class="item-price-stat">
                    <span>Qty Received</span>
                    <strong>{{ number_format($totalReceived, 2) }}</strong>
                </div>
                <div class="item-price-stat">
                    <span>Line Total</span>
                    <strong>${{ number_format($lineTotal, 2) }}</strong>
                </div>
            </div>

            <div class="sc-general-grid">
                <div class="inv-card">
                    <div class="inv-card-title">Receipt header</div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="receipt_date">Receipt Date</label>
                        <input id="receipt_date" type="date" wire:model="receipt_date" class="so-input sc-date" @disabled($isProcessed) />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl">Purchase Ord. #</label>
                        <span class="desk-num" style="padding:0.35rem 0">
                            @if ($po)
                                <a href="{{ route('purchasing.orders.edit', $po) }}" wire:navigate>{{ $po->po_number }}</a>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="reference_no">Reference No.</label>
                        <input id="reference_no" wire:model="reference_no" class="so-input" @disabled($isProcessed) />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl">Status</label>
                        <input type="text" value="{{ $status }}" class="so-input so-input-ro sc-date" readonly />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl">Requisition Date</label>
                        <input
                            type="text"
                            class="so-input so-input-ro sc-date"
                            readonly
                            value="{{ optional($po?->requisition_date)?->format('n/j/Y') ?: '—' }}"
                        />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl">Required Date</label>
                        <input
                            type="text"
                            class="so-input so-input-ro sc-date"
                            readonly
                            value="{{ optional($po?->required_date)?->format('n/j/Y') ?: '—' }}"
                        />
                    </div>
                </div>

                <div class="inv-card">
                    <div class="inv-card-title">Supplier & shipping</div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl">Supplier</label>
                        <input
                            type="text"
                            class="so-input so-input-ro"
                            readonly
                            value="{{ $receiving->supplier?->name ?: '—' }}"
                        />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="buyer_id">Buyer / Requester</label>
                        <select id="buyer_id" wire:model="buyer_id" class="so-input" @disabled($isProcessed)>
                            <option value="">—</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="site_id">Site</label>
                        <select id="site_id" wire:model="site_id" class="so-input" @disabled($isProcessed)>
                            <option value="">—</option>
                            @foreach ($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="received_by">Received By</label>
                        <select id="received_by" wire:model="received_by" class="so-input">
                            <option value="">—</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->name }}">{{ $user->name }}</option>
                            @endforeach
                            @if ($received_by && ! $users->contains('name', $received_by))
                                <option value="{{ $received_by }}">{{ $received_by }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="shipping_carrier">Shipping Carrier</label>
                        <input id="shipping_carrier" wire:model="shipping_carrier" class="so-input" />
                    </div>
                    <div class="so-form-row so-form-row-side so-form-row-top sc-field">
                        <label class="so-form-lbl" for="comments">Comments</label>
                        <textarea id="comments" wire:model="comments" rows="3" class="so-input so-input-area" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            </div>

            <div class="entity-section">
                <div class="entity-section-head">
                    <h3 class="entity-section-title">Receiving Lines</h3>
                    <span class="item-hint" style="padding:0">Enter <strong>Qty Received</strong> for each item, then Process.</span>
                </div>
                <div class="desk-grid item-lines-wrap">
                    <table class="desk-table item-lines-table rcv-lines-table">
                        <colgroup>
                            <col class="col-code" />
                            <col class="col-desc" />
                            <col class="col-uom" />
                            <col class="col-qty" />
                            <col class="col-qty" />
                            <col class="col-cost" />
                            <col class="col-ext" />
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $i => $line)
                                <tr>
                                    <td class="desk-num">{{ $line['item_code'] }}</td>
                                    <td class="item-cell-desc" title="{{ $line['description'] }}">{{ $line['description'] ?: '—' }}</td>
                                    <td class="text-center">{{ $line['uom'] ?: '—' }}</td>
                                    <td class="desk-money">{{ number_format((float) $line['qty_ordered'], 2) }}</td>
                                    <td class="text-center">
                                        <input
                                            wire:model.live="lines.{{ $i }}.qty_received"
                                            class="so-input text-right item-cell-qty"
                                            @disabled($isProcessed)
                                            aria-label="Qty received line {{ $i + 1 }}"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <input
                                            wire:model.live="lines.{{ $i }}.unit_cost"
                                            class="so-input text-right item-cell-qty"
                                            @disabled($isProcessed)
                                            aria-label="Unit cost line {{ $i + 1 }}"
                                        />
                                    </td>
                                    <td class="desk-money">${{ number_format((float) $line['qty_received'] * (float) $line['unit_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr class="is-empty"><td colspan="7">No lines on this receiving.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="entity-footer">
            <div class="entity-tabs" role="tablist" aria-label="Receiving">
                <span class="entity-tab is-active">Receiving</span>
            </div>
            <div class="entity-footer-actions">
                <a href="{{ route('purchasing.receivings.index') }}" wire:navigate class="desk-btn">Cancel</a>
                <button type="submit" class="desk-btn {{ $isProcessed ? 'desk-btn-primary' : '' }}">Save</button>
                @unless ($isProcessed)
                    <button type="button" wire:click="process" wire:confirm="Process receiving and update inventory?" class="desk-btn desk-btn-primary">Process Receiving</button>
                @endunless
            </div>
        </div>
    </form>
</div>
