<?php

use App\Models\Item;
use App\Models\ReturnToVendor;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Return to Vendor')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?ReturnToVendor $rtv = null;

    public string $rtv_number = '';

    public string $rtv_date = '';

    public string $status = 'New';

    public string $reference_no = '';

    public ?int $supplier_id = null;

    public ?int $requested_by_id = null;

    public ?int $site_id = null;

    public string $comments = '';

    public string $discount = '0';

    public string $freight = '0';

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty:string,unit_cost:string}> */
    public array $lines = [];

    public bool $showItemBrowse = false;

    public ?int $browseLineIndex = null;

    public string $itemBrowseSearch = '';

    public string $lookupMessage = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $hasSearch = $this->search !== '';

        $query = ReturnToVendor::query()
            ->with(['supplier', 'requestedBy'])
            ->where('company_id', $companyId)
            ->when($hasSearch, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('rtv_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)->orWhere('supplier_id', 'like', $term))
                        ->orWhereHas('requestedBy', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'))
            ->when($this->favorite === 'returned', fn ($q) => $q->where('status', 'Returned'))
            ->when($this->statusFilter === 'New', fn ($q) => $q->where('status', 'New'))
            ->when($this->statusFilter === 'Returned', fn ($q) => $q->where('status', 'Returned'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if (! $hasSearch && $this->favorite === 'all' && $this->statusFilter === '' && ! $this->showForm) {
            $records = $query->limit(10)->get();
            $total = $records->count();
            $footerNote = '10 most recently updated records with no search criteria.';
            $isPaginated = false;
        } else {
            $records = $query->paginate(50);
            $total = $records->total();
            $footerNote = null;
            $isPaginated = true;
        }

        $listTitle = match (true) {
            $this->statusFilter === 'New', $this->favorite === 'new' => 'Return To Vendor (RTVs) List (New)',
            $this->statusFilter === 'Returned', $this->favorite === 'returned' => 'Return To Vendor (RTVs) List (Returned)',
            default => 'Return To Vendor (RTVs) List',
        };

        $subtotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_cost']);

        return [
            'records' => $records,
            'total' => $total,
            'footerNote' => $footerNote,
            'isPaginated' => $isPaginated,
            'suppliers' => Supplier::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('name')->get(),
            'users' => User::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'selectedSupplier' => $this->supplier_id
                ? Supplier::query()->find($this->supplier_id)
                : null,
            'favorites' => [
                'all' => 'All RTVs',
                'new' => 'New',
                'returned' => 'Returned',
            ],
            'listTitle' => $listTitle,
            'subtotal' => $subtotal,
            'orderTotal' => $subtotal - (float) $this->discount + (float) $this->freight,
            'isReturned' => $this->status === 'Returned',
            'browseItems' => $this->showItemBrowse
                ? Item::query()
                    ->where('company_id', $companyId)
                    ->where('is_inactive', false)
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

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->statusFilter = match ($this->favorite) {
            'new' => 'New',
            'returned' => 'Returned',
            default => $this->statusFilter,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->favorite = match ($this->statusFilter) {
            'New' => 'new',
            'Returned' => 'returned',
            default => 'all',
        };
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function editSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an RTV first.');

            return;
        }

        $this->edit($this->selectedId);
    }

    public function deleteSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an RTV first.');

            return;
        }

        $rtv = ReturnToVendor::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $rtv) {
            session()->flash('status', 'RTV not found.');

            return;
        }

        if ($rtv->status === 'Returned') {
            session()->flash('status', 'Returned RTVs cannot be deleted.');

            return;
        }

        $rtv->lines()->delete();
        $rtv->delete();
        $this->selectedId = null;
        session()->flash('status', 'RTV deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an RTV first.');

            return;
        }

        $this->edit($this->selectedId);
        $this->dispatch('print-rtv');
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null,
            'item_code' => '',
            'description' => '',
            'uom' => '',
            'qty' => '1',
            'unit_cost' => '0',
        ];
    }

    public function startNew(): void
    {
        $companyId = auth()->user()->company_id;
        $this->showForm = true;
        $this->rtv = null;
        $this->lookupMessage = '';
        $this->rtv_number = ReturnToVendor::nextNumber($companyId);
        $this->rtv_date = now()->toDateString();
        $this->status = 'New';
        $this->reference_no = '';
        $this->supplier_id = null;
        $this->requested_by_id = auth()->id();
        $this->site_id = auth()->user()->site_id;
        $this->comments = '';
        $this->discount = '0';
        $this->freight = '0';
        $this->lines = [$this->emptyLine()];
        $this->resetErrorBag();
    }

    public function edit(int $id): void
    {
        $rtv = ReturnToVendor::query()->with('lines')->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        $this->rtv = $rtv;
        $this->showForm = true;
        $this->lookupMessage = '';
        $this->rtv_number = $rtv->rtv_number;
        $this->rtv_date = optional($rtv->rtv_date)?->format('Y-m-d') ?? '';
        $this->status = $rtv->status;
        $this->reference_no = $rtv->reference_no ?? '';
        $this->supplier_id = $rtv->supplier_id;
        $this->requested_by_id = $rtv->requested_by_id;
        $this->site_id = $rtv->site_id;
        $this->comments = $rtv->comments ?? '';
        $this->discount = (string) $rtv->discount;
        $this->freight = (string) $rtv->freight;
        $this->lines = $rtv->lines->map(fn ($l) => [
            'item_id' => $l->item_id,
            'item_code' => $l->item_code ?? '',
            'description' => $l->description ?? '',
            'uom' => $l->uom ?? '',
            'qty' => (string) $l->qty,
            'unit_cost' => (string) $l->unit_cost,
        ])->all() ?: [$this->emptyLine()];
        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function openItemBrowse(?int $lineIndex = null): void
    {
        $this->browseLineIndex = $lineIndex;
        $this->itemBrowseSearch = '';
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

    public function lookupItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            $this->openItemBrowse($index);

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
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
        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['unit_cost'] = (string) ($item->current_cost ?: $item->standard_cost ?: 0);
        if (! filled($this->lines[$index]['qty'] ?? null) || (float) $this->lines[$index]['qty'] <= 0) {
            $this->lines[$index]['qty'] = '1';
        }
    }

    public function save(): void
    {
        if ($this->status === 'Returned') {
            return;
        }

        $this->validate([
            'rtv_number' => 'required',
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        $subtotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_cost']);
        $total = $subtotal - (float) $this->discount + (float) $this->freight;

        $data = [
            'company_id' => auth()->user()->company_id,
            'rtv_number' => $this->rtv_number,
            'rtv_date' => $this->rtv_date ?: null,
            'status' => $this->status,
            'reference_no' => $this->reference_no,
            'supplier_id' => $this->supplier_id,
            'requested_by_id' => $this->requested_by_id,
            'site_id' => $this->site_id,
            'comments' => $this->comments,
            'subtotal' => $subtotal,
            'discount' => $this->discount,
            'freight' => $this->freight,
            'total' => $total,
        ];

        DB::transaction(function () use ($data) {
            if ($this->rtv) {
                $this->rtv->update($data);
                $rtv = $this->rtv->fresh();
                $rtv->lines()->delete();
            } else {
                $rtv = ReturnToVendor::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null)) {
                    continue;
                }
                $qty = (float) $line['qty'];
                $cost = (float) $line['unit_cost'];
                $rtv->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty' => $qty,
                    'unit_cost' => $cost,
                    'extended_cost' => $qty * $cost,
                    'line_no' => $i + 1,
                ]);
            }

            $this->rtv = $rtv->fresh('lines');
        });

        $this->showForm = false;
        session()->flash('status', 'RTV '.$this->rtv_number.' saved.');
    }

    public function process(int $id): void
    {
        $rtv = ReturnToVendor::query()->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processRtv($rtv);
        session()->flash('status', 'RTV processed — stock decremented.');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->showItemBrowse = false;
        $this->lookupMessage = '';
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }
}; ?>

<div class="desk-page {{ $showForm ? 'entity-page' : '' }} relative">
    @unless ($showForm)
        <x-favorite-list :favorites="$favorites" :active="$favorite" />
    @endunless

    <div class="desk-main {{ $showForm ? 'entity-form item-form' : 'desk-main-rail-layout' }}">
        <x-action-bar :title="$showForm ? ($rtv ? 'RTV '.$rtv_number : 'New RTV') : 'Action'" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="contents">
                <div class="entity-body">
                    <div class="entity-header">
                        <div class="so-form-row so-form-row-pair entity-header-row">
                            <label class="so-form-lbl" for="rtv_number">RTV No.</label>
                            <input id="rtv_number" wire:model="rtv_number" class="so-input font-mono" @disabled($rtv) />
                            <span class="so-form-lbl">Status</span>
                            <span @class([
                                'desk-pill',
                                'desk-pill-new' => $status === 'New',
                                'desk-pill-invoiced' => $status === 'Returned',
                                'desk-pill-muted' => ! in_array($status, ['New', 'Returned'], true),
                            ])>{{ $status }}</span>
                        </div>
                        <div class="entity-balance">Total: <strong>${{ number_format($orderTotal, 2) }}</strong></div>
                    </div>

                    <div class="sc-general-grid">
                        <div class="inv-card">
                            <div class="inv-card-title">RTV header</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="rtv_date">RTV Date</label>
                                <input id="rtv_date" type="date" wire:model="rtv_date" class="so-input sc-date" @disabled($isReturned) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="reference_no">Reference No.</label>
                                <input id="reference_no" wire:model="reference_no" class="so-input" @disabled($isReturned) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="site_id">Site</label>
                                <select id="site_id" wire:model="site_id" class="so-input" @disabled($isReturned)>
                                    <option value="">—</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="inv-card">
                            <div class="inv-card-title">Supplier</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="supplier_id">Supplier</label>
                                <select id="supplier_id" wire:model.live="supplier_id" class="so-input" @disabled($isReturned)>
                                    <option value="">— Select supplier —</option>
                                    @foreach ($suppliers as $s)
                                        <option value="{{ $s->id }}">{{ $s->supplier_id }} — {{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl">Supplier ID</label>
                                <input type="text" class="so-input so-input-ro" readonly value="{{ $selectedSupplier?->supplier_id ?: '—' }}" />
                            </div>
                            @error('supplier_id') <p class="text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="requested_by_id">Requested By</label>
                                <select id="requested_by_id" wire:model="requested_by_id" class="so-input" @disabled($isReturned)>
                                    <option value="">—</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="so-form-row so-form-row-side so-form-row-top sc-field">
                                <label class="so-form-lbl" for="comments">Comments</label>
                                <textarea id="comments" wire:model="comments" rows="3" class="so-input so-input-area" @disabled($isReturned) placeholder="Reason for return…"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="entity-section">
                        <div class="entity-section-head">
                            <h3 class="entity-section-title">Return Lines</h3>
                            @unless ($isReturned)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="openItemBrowse" class="desk-btn desk-btn-sm">Browse Items</button>
                                    <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                                </div>
                            @endunless
                        </div>
                        <p class="item-hint" style="border-bottom:1px solid #e2e8f0">
                            Type an existing <strong>Item Code</strong> and press <strong>Enter</strong>, or click <strong>Browse Items</strong> to pick from inventory.
                        </p>
                        @if ($lookupMessage)
                            <div class="desk-flash" style="margin:0.5rem 0.75rem" role="status">{{ $lookupMessage }}</div>
                        @endif
                        <div class="desk-grid item-lines-wrap">
                            <table class="desk-table item-lines-table rtv-lines-table">
                                <colgroup>
                                    <col class="col-code" />
                                    <col class="col-desc" />
                                    <col class="col-uom" />
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
                                        <th class="text-center">Qty</th>
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
                                                        wire:keydown.enter.prevent="lookupItem({{ $i }})"
                                                        class="so-input font-mono item-cell-ctl"
                                                        placeholder="Code + Enter"
                                                        @disabled($isReturned)
                                                    />
                                                    <button type="button" wire:click="openItemBrowse({{ $i }})" class="desk-btn desk-btn-sm" @disabled($isReturned) title="Browse items">…</button>
                                                </div>
                                            </td>
                                            <td><input wire:model="lines.{{ $i }}.description" class="so-input item-cell-ctl" @disabled($isReturned) /></td>
                                            <td class="text-center"><input wire:model="lines.{{ $i }}.uom" class="so-input text-center item-cell-ctl" style="max-width:4rem;margin:0 auto" @disabled($isReturned) /></td>
                                            <td class="text-center"><input wire:model.live="lines.{{ $i }}.qty" class="so-input text-right item-cell-qty" @disabled($isReturned) /></td>
                                            <td class="text-center"><input wire:model.live="lines.{{ $i }}.unit_cost" class="so-input text-right item-cell-qty" @disabled($isReturned) /></td>
                                            <td class="desk-money">${{ number_format((float) $line['qty'] * (float) $line['unit_cost'], 2) }}</td>
                                            <td class="text-center">
                                                @unless ($isReturned)
                                                    <button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                                @endunless
                                            </td>
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
                                <label class="so-form-lbl">RTV Subtotal</label>
                                <span class="entity-value text-right" style="display:block;width:100%">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="discount">Discount</label>
                                <input id="discount" wire:model.live="discount" class="so-input text-right sc-date" @disabled($isReturned) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="freight">Freight</label>
                                <input id="freight" wire:model.live="freight" class="so-input text-right sc-date" @disabled($isReturned) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field po-total-row">
                                <label class="so-form-lbl">RTV Total</label>
                                <strong class="entity-value text-right" style="display:block;width:100%;font-size:1.15rem">${{ number_format($orderTotal, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">RTV</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelForm" class="desk-btn">Cancel</button>
                        @unless ($isReturned)
                            <button type="submit" class="desk-btn desk-btn-primary">Save RTV</button>
                        @endunless
                    </div>
                </div>
            </form>
        @else
            <div class="desk-main-split">
                <div class="desk-main-body">
                    <div class="desk-toolbar orders-toolbar">
                        <label class="desk-toolbar-label" for="rtv-search">Search RTVs:</label>
                        <input
                            id="rtv-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="RTV #, supplier, reference…"
                            class="desk-search orders-search-input"
                            aria-label="Search RTVs"
                        />

                        <div class="orders-toolbar-right">
                            <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                                <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                    <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                    <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                                </svg>
                                New Search
                            </button>
                            <select
                                id="rtv-status-filter"
                                wire:model.live="statusFilter"
                                class="desk-select orders-status-select"
                                aria-label="Status filter"
                            >
                                <option value="">All</option>
                                <option value="New">New</option>
                                <option value="Returned">Returned</option>
                            </select>
                            <button
                                type="button"
                                wire:click="clearSearch"
                                class="so-icon-btn"
                                title="Clear search"
                                aria-label="Clear search"
                            >
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="desk-titlebar">
                        <h2 class="desk-title">{{ $listTitle }}</h2>
                        <span class="desk-title-meta">{{ number_format($total) }} records</span>
                    </div>

                    <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <th>RTV Number</th>
                                    <th>RTV Date</th>
                                    <th class="text-center">Status</th>
                                    <th>Reference No.</th>
                                    <th>Supplier ID</th>
                                    <th>Supplier</th>
                                    <th>Requested By</th>
                                    <th class="desk-money">RTV Subtotal</th>
                                    <th class="desk-money">Discount</th>
                                    <th class="desk-money">Freight</th>
                                    <th class="desk-money">RTV Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records as $rec)
                                    <tr
                                        wire:click="selectRow({{ $rec->id }})"
                                        wire:dblclick="edit({{ $rec->id }})"
                                        @class(['is-selected' => $selectedId === $rec->id, 'cursor-pointer'])
                                    >
                                        <td class="text-center" wire:click.stop>
                                            <input
                                                type="radio"
                                                name="rtv_select"
                                                value="{{ $rec->id }}"
                                                @checked($selectedId === $rec->id)
                                                wire:click="selectRow({{ $rec->id }})"
                                                aria-label="Select RTV {{ $rec->rtv_number }}"
                                            />
                                        </td>
                                        <td class="desk-num">
                                            <button type="button" wire:click.stop="edit({{ $rec->id }})" class="text-sky-700 font-semibold hover:underline">{{ $rec->rtv_number }}</button>
                                        </td>
                                        <td>{{ optional($rec->rtv_date)?->format('n/j/Y') }}</td>
                                        <td class="text-center">
                                            <span @class([
                                                'desk-pill',
                                                'desk-pill-new' => $rec->status === 'New',
                                                'desk-pill-invoiced' => $rec->status === 'Returned',
                                                'desk-pill-muted' => ! in_array($rec->status, ['New', 'Returned'], true),
                                            ])>{{ $rec->status }}</span>
                                        </td>
                                        <td>{{ $rec->reference_no ?: '' }}</td>
                                        <td class="desk-num">{{ $rec->supplier?->supplier_id ?: '—' }}</td>
                                        <td>{{ $rec->supplier?->name ?: '—' }}</td>
                                        <td>{{ $rec->requestedBy?->name ?: '—' }}</td>
                                        <td class="desk-money">${{ number_format($rec->subtotal, 2) }}</td>
                                        <td class="desk-money">${{ number_format($rec->discount, 2) }}</td>
                                        <td class="desk-money">${{ number_format($rec->freight, 2) }}</td>
                                        <td class="desk-money">${{ number_format($rec->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="12">No RTVs found. Use the <strong>+</strong> button to create one.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-record-count :count="$total">
                        @if ($footerNote)
                            <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                        @endif
                        <button type="button" wire:click="startNew" class="desk-btn desk-btn-primary">New RTV</button>
                        @if ($isPaginated)
                            {{ $records->links() }}
                        @endif
                    </x-record-count>
                </div>

                <aside class="desk-rail" aria-label="RTV actions">
                    <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                            <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                            <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="deleteSelected"
                        wire:confirm="Delete the selected RTV? This cannot be undone."
                        class="desk-rail-btn desk-rail-btn-danger"
                        title="Delete selected"
                        aria-label="Delete selected"
                        @disabled(! $selectedId)
                    >
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                            <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                            <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M13 8a5 5 0 11-1.2-3.3"/>
                            <path d="M13 3v3h-3"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="startNew" class="desk-rail-btn desk-rail-btn-primary" title="New RTV" aria-label="New RTV">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M8 3v10M3 8h10"/>
                        </svg>
                    </button>
                </aside>
            </div>
        @endif
    </div>

    @if ($showItemBrowse)
        <div class="desk-modal-backdrop" wire:click.self="closeItemBrowse" role="dialog" aria-modal="true" aria-label="Browse items">
            <div class="desk-modal" style="max-width:48rem">
                <div class="desk-modal-head">
                    <span>Browse Inventory Items</span>
                    <button type="button" wire:click="closeItemBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body">
                    <div class="desk-toolbar" style="padding:0 0 0.75rem;border:0;background:transparent">
                        <label class="desk-toolbar-label" for="rtv-item-browse">Search</label>
                        <input
                            id="rtv-item-browse"
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
                    <p class="item-hint" style="padding:0.65rem 0 0">Click a row or <strong>Add</strong> to put that item on the RTV.</p>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('print-rtv', () => {
        setTimeout(() => { try { window.print(); } catch (e) {} }, 400);
    });
</script>
@endscript
