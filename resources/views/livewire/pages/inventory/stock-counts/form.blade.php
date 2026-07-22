<?php

use App\Models\Item;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stock Count')] class extends Component
{
    public ?StockCount $stockCount = null;

    public string $activeTab = 'general';

    public string $stock_count_no = '';

    public string $date_created = '';

    public string $status = 'New';

    public ?string $last_count_date = null;

    public ?string $date_processed = null;

    public ?int $site_id = null;

    public string $description = '';

    public bool $shared_count = false;

    public string $comments = '';

    public ?int $processed_by = null;

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,in_stock:string,allocated:string,counted:string,count_time:?string}> */
    public array $lines = [];

    public function mount(?StockCount $stockCount = null): void
    {
        $companyId = auth()->user()->company_id;

        if ($stockCount?->exists) {
            abort_unless($stockCount->company_id === $companyId, 403);
            $this->stockCount = $stockCount->load(['lines', 'processedByUser']);
            $this->fill($stockCount->only([
                'stock_count_no', 'status', 'site_id', 'description', 'shared_count', 'comments', 'processed_by',
            ]));
            $this->date_created = optional($stockCount->date_created)?->format('Y-m-d') ?? '';
            $this->last_count_date = optional($stockCount->last_count_date)?->format('Y-m-d');
            $this->date_processed = optional($stockCount->date_processed)?->format('Y-m-d');
            $this->lines = $stockCount->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'in_stock' => (string) $l->in_stock,
                'allocated' => (string) $l->allocated,
                'counted' => $l->counted !== null ? (string) $l->counted : '',
                'count_time' => optional($l->count_time)?->format('Y-m-d H:i:s'),
            ])->all();
        } else {
            $this->stock_count_no = StockCount::nextNumber($companyId);
            $this->date_created = now()->toDateString();
            $this->site_id = auth()->user()->site_id;
            $this->processed_by = null;
            $prev = StockCount::query()
                ->where('company_id', $companyId)
                ->where('status', 'Processed')
                ->orderByDesc('date_processed')
                ->value('date_processed');
            $this->last_count_date = optional($prev)?->format('Y-m-d');
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
            'in_stock' => '0',
            'allocated' => '0',
            'counted' => '',
            'count_time' => null,
        ];
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->where(function ($q) {
                    $q->where('is_active', true);
                    if ($this->processed_by) {
                        $q->orWhere('id', $this->processed_by);
                    }
                })
                ->orderBy('name')
                ->get(),
            'tabs' => [
                'general' => 'General',
                'expand' => 'Expand',
                'comments' => 'Comments',
            ],
            'totalItemsCounted' => collect($this->lines)->filter(fn ($l) => filled($l['counted'] ?? null))->count(),
            'totalQtyCounted' => collect($this->lines)->sum(fn ($l) => (float) ($l['counted'] ?: 0)),
            'isProcessed' => $this->status === 'Processed',
        ];
    }

    public function addLine(): void
    {
        if ($this->status === 'Processed') {
            return;
        }
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        if ($this->status === 'Processed') {
            return;
        }
        unset($this->lines[$i]);
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

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)->orWhere('primary_upc', $code);
            })
            ->first();

        if (! $item) {
            return;
        }

        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['in_stock'] = (string) $item->quantity_in_stock;
        $this->lines[$index]['allocated'] = (string) $item->allocated_qty;
    }

    public function updatedLines($value, $key): void
    {
        if (str_ends_with($key, '.counted') && filled($value)) {
            $index = (int) explode('.', $key)[0];
            $this->lines[$index]['count_time'] = now()->format('Y-m-d H:i:s');
        }
    }

    public function updatedProcessedBy(): void
    {
        if (! $this->stockCount?->exists) {
            return;
        }

        $userId = $this->processed_by ?: null;
        if ($userId) {
            $exists = User::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('id', $userId)
                ->exists();
            if (! $exists) {
                $this->processed_by = $this->stockCount->processed_by;
                session()->flash('status', 'Invalid user selected.');

                return;
            }
        }

        $this->stockCount->update(['processed_by' => $userId]);
        session()->flash('status', 'Processed By updated.');
    }

    public function save(bool $redirect = true): void
    {
        if ($this->status === 'Processed') {
            if ($this->stockCount?->exists) {
                $this->updatedProcessedBy();
            }
            if ($redirect) {
                $this->redirect(route('inventory.stock-counts.index'), navigate: true);
            }

            return;
        }

        $this->validate([
            'stock_count_no' => 'required|string|max:64',
            'site_id' => 'nullable|integer',
            'processed_by' => 'nullable|integer',
        ]);

        $data = [
            'company_id' => auth()->user()->company_id,
            'stock_count_no' => $this->stock_count_no,
            'date_created' => $this->date_created ?: null,
            'status' => $this->status,
            'last_count_date' => $this->last_count_date ?: null,
            'date_processed' => $this->date_processed ?: null,
            'processed_by' => $this->processed_by ?: null,
            'site_id' => $this->site_id ?: null,
            'description' => $this->description,
            'shared_count' => $this->shared_count,
            'comments' => $this->comments,
        ];

        DB::transaction(function () use ($data) {
            if ($this->stockCount) {
                $this->stockCount->update($data);
                $count = $this->stockCount->fresh();
                $count->lines()->delete();
            } else {
                $data['date_entered'] = now();
                $count = StockCount::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null)) {
                    continue;
                }
                $count->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'in_stock' => $line['in_stock'] ?: 0,
                    'allocated' => $line['allocated'] ?: 0,
                    'counted' => filled($line['counted'] ?? null) ? $line['counted'] : null,
                    'count_time' => $line['count_time'] ?: null,
                    'line_no' => $i + 1,
                ]);
            }

            $this->stockCount = $count->fresh('lines');
        });

        if ($redirect) {
            $this->redirect(route('inventory.stock-counts.index'), navigate: true);
        }
    }

    public function process(): void
    {
        if ($this->status === 'Processed') {
            return;
        }
        $this->save(false);
        app(InventoryService::class)->processStockCount($this->stockCount->fresh('lines'));
        $this->redirect(route('inventory.stock-counts.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form">
        <x-action-bar :title="$stockCount ? 'Stock Count '.$stock_count_no : 'New Stock Count'" />

        <div class="entity-body">
            @if (session('status'))
                <div class="desk-flash" role="status">{{ session('status') }}</div>
            @endif

            <div class="entity-header">
                <div class="so-form-row so-form-row-pair entity-header-row">
                    <label class="so-form-lbl" for="stock_count_no">Count No.</label>
                    <input id="stock_count_no" wire:model="stock_count_no" class="so-input font-mono" @disabled($stockCount) />
                    <span class="so-form-lbl">Status</span>
                    <span @class([
                        'desk-pill',
                        'desk-pill-new' => $status === 'New',
                        'desk-pill-invoiced' => $status === 'Processed',
                        'desk-pill-muted' => ! in_array($status, ['New', 'Processed'], true),
                    ])>{{ $status }}</span>
                </div>
                @if ($activeTab === 'expand')
                    <div class="entity-balance">Counted: <strong>{{ $totalItemsCounted }}</strong> items</div>
                @endif
            </div>

            @if ($activeTab === 'general')
                <div class="sc-general-grid">
                    <div class="inv-card">
                        <div class="inv-card-title">Count header</div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="date_created">Date Created</label>
                            <input id="date_created" type="date" wire:model="date_created" class="so-input sc-date" @disabled($isProcessed) />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="status">Count Status</label>
                            <input id="status" wire:model="status" class="so-input so-input-ro sc-date" readonly />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="last_count_date">Last Count Date</label>
                            <input id="last_count_date" type="date" wire:model="last_count_date" class="so-input sc-date" @disabled($isProcessed) />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="date_processed">Date Processed</label>
                            <input id="date_processed" type="date" wire:model="date_processed" class="so-input sc-date" @disabled($isProcessed) />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="processed_by">Processed By</label>
                            <select id="processed_by" wire:model.live="processed_by" class="so-input">
                                <option value="">—</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-card-title">Site & description</div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="site_id">Site</label>
                            <select id="site_id" wire:model="site_id" class="so-input" @disabled($isProcessed)>
                                <option value="">—</option>
                                @foreach ($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name ?? $s->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side so-form-row-top sc-field">
                            <label class="so-form-lbl" for="description">Description</label>
                            <textarea id="description" wire:model="description" rows="4" class="so-input so-input-area" @disabled($isProcessed) placeholder="Optional notes for this count…"></textarea>
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <span class="so-form-lbl"></span>
                            <label class="entity-check"><input type="checkbox" wire:model="shared_count" @disabled($isProcessed) /> Shared Count</label>
                        </div>
                    </div>
                </div>

            @elseif ($activeTab === 'expand')
                <div class="item-price-summary" style="grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 28rem;">
                    <div class="item-price-stat">
                        <span>Items Counted</span>
                        <strong>{{ $totalItemsCounted }}</strong>
                    </div>
                    <div class="item-price-stat">
                        <span>Qty Counted</span>
                        <strong>{{ number_format($totalQtyCounted, 2) }}</strong>
                    </div>
                </div>

                <div class="entity-section" style="margin-top:0">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Count Lines</h3>
                        @unless ($isProcessed)
                            <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Item</button>
                        @endunless
                    </div>
                    <p class="item-hint" style="border-bottom:1px solid #e2e8f0">Enter item code or UPC, then press Enter — barcode scan supported.</p>
                    <div class="desk-grid item-lines-wrap">
                        <table class="desk-table item-lines-table sc-lines-table">
                            <colgroup>
                                <col class="col-code" />
                                <col class="col-desc" />
                                <col class="col-uom" />
                                <col class="col-qty" />
                                <col class="col-qty" />
                                <col class="col-qty" />
                                <col class="col-qty" />
                                <col class="col-time" />
                                <col class="col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th class="text-center">UOM</th>
                                    <th class="text-center">In Stock</th>
                                    <th class="text-center">Allocated</th>
                                    <th class="text-center">Counted</th>
                                    <th class="text-center">Variance</th>
                                    <th>Count Time</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    @php
                                        $variance = filled($line['counted'])
                                            ? (float) $line['counted'] - (float) $line['in_stock']
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="so-lookup-row">
                                                <input
                                                    wire:model.blur="lines.{{ $i }}.item_code"
                                                    wire:keydown.enter.prevent="lookupItem({{ $i }})"
                                                    class="so-input font-mono item-cell-ctl"
                                                    placeholder="Code + Enter"
                                                    @disabled($isProcessed)
                                                />
                                                <button type="button" wire:click="lookupItem({{ $i }})" class="desk-btn desk-btn-sm" @disabled($isProcessed) title="Lookup item">…</button>
                                            </div>
                                        </td>
                                        <td class="item-cell-desc" title="{{ $line['description'] }}">{{ $line['description'] ?: '—' }}</td>
                                        <td class="text-center">{{ $line['uom'] ?: '—' }}</td>
                                        <td class="desk-money">{{ number_format((float) $line['in_stock'], 2) }}</td>
                                        <td class="desk-money">{{ number_format((float) $line['allocated'], 2) }}</td>
                                        <td class="text-center">
                                            <input
                                                wire:model.live="lines.{{ $i }}.counted"
                                                class="so-input text-right item-cell-qty"
                                                @disabled($isProcessed)
                                                aria-label="Counted qty line {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td @class(['desk-money', 'sc-var-neg' => $variance !== null && $variance < 0, 'sc-var-pos' => $variance !== null && $variance > 0])>
                                            {{ $variance !== null ? number_format($variance, 2) : '' }}
                                        </td>
                                        <td class="sc-time">{{ $line['count_time'] ?: '—' }}</td>
                                        <td class="text-center">
                                            @unless ($isProcessed)
                                                <button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @else
                <div class="inv-card" style="max-width:48rem">
                    <div class="inv-card-title">Comments & notes</div>
                    <div class="item-stack-field">
                        <label class="item-stack-lbl" for="comments">Comments</label>
                        <textarea id="comments" wire:model="comments" rows="10" class="so-input so-input-area" @disabled($isProcessed) placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            @endif
        </div>

        <div class="entity-footer">
            <div class="entity-tabs" role="tablist" aria-label="Stock count sections">
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
                <a href="{{ route('inventory.stock-counts.index') }}" wire:navigate class="desk-btn">Cancel</a>
                @unless ($isProcessed)
                    <button type="submit" class="desk-btn">Save</button>
                    <button type="button" wire:click="process" wire:confirm="Process stock count and update inventory?" class="desk-btn desk-btn-primary">Process</button>
                @endunless
            </div>
        </div>
    </form>
</div>
