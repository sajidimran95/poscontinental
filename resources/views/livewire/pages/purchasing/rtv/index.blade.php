<?php

use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\InventoryReceiving;
use App\Models\InventoryReceivingLine;
use App\Models\ReturnToVendor;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\ItemSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Return to Vendor')] class extends Component
{
    use WithPagination;
    use SortsDeskList;
    use PaginatesDeskLists;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public string $statusFilter = '';

    public bool $showForm = false;

    public bool $viewMode = false;

    public ?ReturnToVendor $rtv = null;

    public string $rtv_number = '';

    public string $rtv_date = '';

    public string $status = 'New';

    public string $reference_no = '';

    public ?int $inventory_receiving_id = null;

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

    public string $itemLookup = '';

    public bool $scanModeActive = false;

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
            ->when($this->statusFilter === 'Returned', fn ($q) => $q->where('status', 'Returned'));

        $query = $this->applyDeskSort($query, 'rtv_date', 'desc');

        $scroll = $this->scrollDeskList($query);
        $records = $scroll['rows'];
        $total = $scroll['shown'];
        $footerNote = null;
        $listHasMore = $scroll['hasMore'];

        $listTitle = match (true) {
            $this->statusFilter === 'New', $this->favorite === 'new' => 'Return To Vendor (RTVs) List (New)',
            $this->statusFilter === 'Returned', $this->favorite === 'returned' => 'Return To Vendor (RTVs) List (Returned)',
            default => 'Return To Vendor (RTVs) List',
        };

        $selectedStatus = null;
        if ($this->selectedId) {
            $selectedStatus = optional($records->firstWhere('id', $this->selectedId))->status
                ?? ReturnToVendor::query()
                    ->where('company_id', $companyId)
                    ->whereKey($this->selectedId)
                    ->value('status');
        }

        $subtotal = collect($this->lines)->sum(fn ($l) => (float) ($l['qty'] ?? 0) * (float) ($l['unit_cost'] ?? 0));

        $linesSig = md5(json_encode(array_map(static fn ($l) => [
            (int) ($l['item_id'] ?? 0),
            (string) ($l['item_code'] ?? ''),
            (string) ($l['qty'] ?? ''),
            (string) ($l['unit_cost'] ?? ''),
            (string) ($l['uom'] ?? ''),
        ], $this->lines)));

        $filledLineCount = collect($this->lines)->filter(
            fn ($l) => filled($l['item_code'] ?? null) || (int) ($l['item_id'] ?? 0) > 0
        )->count();

        $supplierReceivings = ($this->showForm && $this->supplier_id)
            ? InventoryReceiving::query()
                ->with('purchaseOrder:id,po_number')
                ->where('company_id', $companyId)
                ->where('supplier_id', $this->supplier_id)
                ->where('status', 'Processed')
                ->orderByDesc('receipt_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();

        $browseLines = ($this->showItemBrowse && $this->inventory_receiving_id)
            ? InventoryReceivingLine::query()
                ->where('inventory_receiving_id', $this->inventory_receiving_id)
                ->where('qty_received', '>', 0)
                ->when($this->itemBrowseSearch !== '', fn ($q) => ItemSearch::constrainCodeDescription($q, $this->itemBrowseSearch))
                ->orderBy('line_no')
                ->limit(100)
                ->get()
            : collect();

        return [
            'records' => $records,
            'total' => $total,
            'footerNote' => $footerNote,
            'listHasMore' => $listHasMore,
            'canEditSelected' => $this->selectedId && $selectedStatus && $selectedStatus !== 'Returned',
            'linesSig' => $linesSig,
            'filledLineCount' => $filledLineCount,
            'suppliers' => Supplier::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('name')->get(),
            'users' => User::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'selectedSupplier' => $this->supplier_id
                ? Supplier::query()->find($this->supplier_id)
                : null,
            'supplierReceivings' => $supplierReceivings,
            'selectedReceiving' => $this->inventory_receiving_id
                ? $supplierReceivings->firstWhere('id', $this->inventory_receiving_id)
                    ?? InventoryReceiving::query()->with('purchaseOrder:id,po_number')->find($this->inventory_receiving_id)
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
            'isReadonly' => $this->viewMode || $this->status === 'Returned',
            'browseLines' => $browseLines,
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'rtv_number' => 'rtv_number',
            'rtv_date' => 'rtv_date',
            'status' => 'status',
            'reference_no' => 'reference_no',
            'supplier_code' => ['relation' => 'supplier', 'column' => 'supplier_id'],
            'supplier_name' => ['relation' => 'supplier', 'column' => 'name'],
            'requested_by' => ['relation' => 'requestedBy', 'column' => 'name'],
            'subtotal' => 'subtotal',
            'discount' => 'discount',
            'freight' => 'freight',
            'total' => 'total',
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

        $this->edit((int) $this->selectedId);
    }

    public function viewSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an RTV first.');

            return;
        }

        $this->view($this->selectedId);
    }

    public function deleteSelected(): void
    {
        if (! auth()->user()?->canAccessFeature('purchasing.rtv', 'delete')) {
            session()->flash('status', 'Your role cannot delete RTVs.');

            return;
        }

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

        $rtv = ReturnToVendor::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $rtv) {
            session()->flash('status', 'RTV not found.');

            return;
        }

        $this->dispatch('open-rtv-pdf', url: route('purchasing.rtv.print', $rtv));
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null,
            'item_code' => '',
            'description' => '',
            'uom' => '',
            'qty' => '',
            'unit_cost' => '',
        ];
    }

    public function updatedSupplierId($value): void
    {
        $this->inventory_receiving_id = null;
        $this->reference_no = '';
        $this->lines = [$this->emptyLine()];
        $this->lookupMessage = '';
        $this->closeItemBrowse();
    }

    public function updatedInventoryReceivingId($value): void
    {
        if (! filled($value)) {
            $this->inventory_receiving_id = null;
            $this->reference_no = '';
            $this->lines = [$this->emptyLine()];

            return;
        }

        $receiving = InventoryReceiving::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->supplier_id, fn ($q) => $q->where('supplier_id', $this->supplier_id))
            ->find((int) $value);

        if (! $receiving) {
            $this->inventory_receiving_id = null;
            $this->reference_no = '';

            return;
        }

        $this->inventory_receiving_id = $receiving->id;
        $this->reference_no = $receiving->receipt_number;
        if ($receiving->supplier_id) {
            $this->supplier_id = $receiving->supplier_id;
        }
        if ($receiving->site_id) {
            $this->site_id = $receiving->site_id;
        }
        $this->lines = [$this->emptyLine()];
        $this->lookupMessage = 'Receiving '.$receiving->receipt_number.' selected — add items from this receipt only.';
        $this->closeItemBrowse();
    }

    public function startNew(): void
    {
        $companyId = auth()->user()->company_id;
        $this->showForm = true;
        $this->viewMode = false;
        $this->rtv = null;
        $this->lookupMessage = '';
        $this->rtv_number = ReturnToVendor::nextNumber($companyId);
        $this->rtv_date = now()->toDateString();
        $this->status = 'New';
        $this->reference_no = '';
        $this->inventory_receiving_id = null;
        $this->supplier_id = null;
        $this->requested_by_id = auth()->id();
        $this->site_id = auth()->user()->site_id;
        $this->comments = '';
        $this->discount = '0';
        $this->freight = '0';
        $this->lines = [$this->emptyLine()];
        $this->resetErrorBag();
    }

    public function view(int $id): void
    {
        $this->edit($id, true);
    }

    public function edit(int $id, bool $viewMode = false): void
    {
        $rtv = ReturnToVendor::query()->with('lines')->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        $this->rtv = $rtv;
        $this->showForm = true;
        $this->viewMode = $rtv->status === 'Returned' || $viewMode === true;
        $this->lookupMessage = '';
        $this->rtv_number = (string) ($rtv->rtv_number ?? '');
        $this->rtv_date = optional($rtv->rtv_date)?->format('Y-m-d') ?? '';
        $this->status = (string) ($rtv->status ?: 'New');
        $this->reference_no = (string) ($rtv->reference_no ?? '');
        $this->supplier_id = $rtv->supplier_id ? (int) $rtv->supplier_id : null;
        $this->requested_by_id = $rtv->requested_by_id ? (int) $rtv->requested_by_id : null;
        $this->site_id = $rtv->site_id ? (int) $rtv->site_id : null;
        $this->comments = (string) ($rtv->comments ?? '');
        $this->discount = $this->formatQtyDisplay($rtv->discount);
        $this->freight = $this->formatQtyDisplay($rtv->freight);
        $this->lines = $rtv->lines->map(fn ($l) => [
            'item_id' => $l->item_id,
            'item_code' => $l->item_code ?? '',
            'description' => $l->description ?? '',
            'uom' => $l->uom ?? '',
            'qty' => $this->formatQtyDisplay($l->qty),
            'unit_cost' => $this->formatQtyDisplay($l->unit_cost),
        ])->all() ?: [$this->emptyLine()];

        $this->inventory_receiving_id = null;
        if (filled($this->reference_no)) {
            $this->inventory_receiving_id = InventoryReceiving::query()
                ->where('company_id', auth()->user()->company_id)
                ->when($this->supplier_id, fn ($q) => $q->where('supplier_id', $this->supplier_id))
                ->where('receipt_number', $this->reference_no)
                ->value('id');
        }

        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        abort_if($this->viewMode || $this->status === 'Returned', 403);
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        abort_if($this->viewMode || $this->status === 'Returned', 403);
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function openItemBrowse(?int $lineIndex = null, ?string $search = null): void
    {
        abort_if($this->viewMode || $this->status === 'Returned', 403);

        if (! $this->supplier_id) {
            $this->lookupMessage = 'Select a supplier first.';

            return;
        }

        if (! $this->inventory_receiving_id) {
            $this->lookupMessage = 'Select a receiving (Reference) first. Items come from that receipt only.';

            return;
        }

        $this->browseLineIndex = $lineIndex;
        if ($search !== null) {
            $this->itemBrowseSearch = trim($search);
        } elseif ($lineIndex !== null && isset($this->lines[$lineIndex])) {
            $this->itemBrowseSearch = trim((string) ($this->lines[$lineIndex]['item_code'] ?? ''));
        } else {
            $this->itemBrowseSearch = '';
        }
        $this->showItemBrowse = true;
    }

    public function closeItemBrowse(): void
    {
        $this->showItemBrowse = false;
        $this->browseLineIndex = null;
        $this->itemBrowseSearch = '';
    }

    public function pickBrowseReceivingLine(int $receivingLineId): void
    {
        $line = $this->findReceivingLine($receivingLineId);
        if (! $line) {
            return;
        }

        $this->applyReceivingLineToOrder($line);
        $this->closeItemBrowse();
        $this->lookupMessage = 'Added item '.$line->item_code.' from receiving.';
        $this->clearAndFocusEntry();
    }

    /**
     * ✓ / Enter on single entry bar.
     */
    public function addItemFromEntry(?string $code = null): void
    {
        if ($this->viewMode || $this->status === 'Returned') {
            return;
        }

        if (! $this->inventory_receiving_id) {
            $this->lookupMessage = 'Select a receiving (Reference) first.';

            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        $this->itemLookup = $code;

        if ($code === '') {
            $this->clearAndFocusEntry();
            $this->openItemBrowse();

            return;
        }

        $line = $this->findReceivingLineByCode($code);
        if ($line) {
            $this->lookupMessage = '';
            $this->browseLineIndex = null;
            $this->applyReceivingLineToOrder($line);
            $this->scanModeActive = true;
            $this->clearAndFocusEntry();

            return;
        }

        $this->lookupMessage = '';
        $this->openItemBrowse(null, $code);
    }

    /**
     * After typing pause / barcode gun: full exact match only → add line.
     */
    public function autoAddEntryIfExactMatch(?string $code = null): void
    {
        if ($this->viewMode || $this->status === 'Returned' || ! $this->inventory_receiving_id) {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        if ($code === '' || mb_strlen($code) < 2) {
            return;
        }

        if ($this->codeIsPrefixOfLongerReceivingCode($code)) {
            return;
        }

        $line = $this->findReceivingLineByCode($code);
        if (! $line) {
            return;
        }

        $this->lookupMessage = '';
        $this->browseLineIndex = null;
        $this->applyReceivingLineToOrder($line);
        $this->scanModeActive = true;
        $this->clearAndFocusEntry();
    }

    public function focusScanAndAdd(): void
    {
        if ($this->viewMode || $this->status === 'Returned') {
            return;
        }

        if (! $this->inventory_receiving_id) {
            $this->lookupMessage = 'Select a receiving (Reference) first.';

            return;
        }

        $this->scanModeActive = true;
        $this->lookupMessage = '';

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('rtv-item-entry');
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
        $this->lookupMessage = '';
        $this->clearAndFocusEntry();
    }

    protected function clearAndFocusEntry(): void
    {
        $this->itemLookup = '';
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('rtv-item-entry');
                if (!el) return;
                el.value = '';
                el.focus();
            });
        JS);
    }

    /**
     * Bump qty if same item already on RTV, else fill empty / new line.
     */
    protected function applyReceivingLineToOrder(InventoryReceivingLine $recvLine): void
    {
        $lines = array_values($this->lines);
        $itemId = (int) ($recvLine->item_id ?? 0);
        $code = mb_strtolower(trim((string) ($recvLine->item_code ?? '')));

        foreach ($lines as $i => $line) {
            $sameId = $itemId > 0 && (int) ($line['item_id'] ?? 0) === $itemId;
            $sameCode = $code !== '' && mb_strtolower(trim((string) ($line['item_code'] ?? ''))) === $code;
            if ($sameId || $sameCode) {
                $qty = (float) ($line['qty'] ?? 0);
                $lines[$i]['qty'] = $this->formatQtyDisplay($qty + 1);
                $lines[$i]['item_code'] = (string) ($recvLine->item_code ?? $lines[$i]['item_code']);
                $lines[$i]['description'] = (string) ($recvLine->description ?? $lines[$i]['description'] ?? '');
                if (! filled($lines[$i]['uom'] ?? null)) {
                    $lines[$i]['uom'] = $this->resolveUomFromReceivingLine($recvLine);
                }
                $this->lines = $lines;

                return;
            }
        }

        $target = null;
        if ($this->browseLineIndex !== null && isset($lines[$this->browseLineIndex])) {
            $target = (int) $this->browseLineIndex;
            $this->browseLineIndex = null;
        } else {
            foreach ($lines as $i => $line) {
                if (! filled($line['item_code'] ?? null) && empty($line['item_id'])) {
                    $target = (int) $i;
                    break;
                }
            }
        }

        if ($target === null) {
            $lines[] = $this->emptyLine();
            $target = count($lines) - 1;
        }

        $this->lines = $lines;
        $this->fillLineFromReceivingLine($target, $recvLine);

        $hasEmpty = collect($this->lines)->contains(
            fn ($l) => ! filled($l['item_code'] ?? null) && empty($l['item_id'])
        );
        if (! $hasEmpty) {
            $this->lines = array_values(array_merge($this->lines, [$this->emptyLine()]));
        }
    }

    protected function findReceivingLineByCode(string $code): ?InventoryReceivingLine
    {
        if (! $this->inventory_receiving_id) {
            return null;
        }

        $lower = mb_strtolower(trim($code));
        if ($lower === '') {
            return null;
        }

        return InventoryReceivingLine::query()
            ->with('item.prices')
            ->where('inventory_receiving_id', $this->inventory_receiving_id)
            ->where('qty_received', '>', 0)
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(item_code) = ?', [$lower])
                    ->orWhereHas('item', function ($item) use ($lower) {
                        $item->whereRaw('LOWER(COALESCE(primary_upc, ?)) = ?', ['', $lower])
                            ->orWhereHas('upcs', fn ($u) => $u->whereRaw('LOWER(upc) = ?', [$lower]))
                            ->orWhereHas('itemSuppliers', fn ($s) => $s->whereRaw(
                                'LOWER(COALESCE(supplier_item_code, ?)) = ?',
                                ['', $lower]
                            ));
                    });
            })
            ->first();
    }

    /**
     * True when a longer receiving line code/UPC still starts with $code (still typing).
     */
    protected function codeIsPrefixOfLongerReceivingCode(string $code): bool
    {
        if (! $this->inventory_receiving_id) {
            return false;
        }

        $lower = mb_strtolower(trim($code));
        $len = mb_strlen($lower);
        if ($len < 1) {
            return false;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower).'%';

        return InventoryReceivingLine::query()
            ->where('inventory_receiving_id', $this->inventory_receiving_id)
            ->where('qty_received', '>', 0)
            ->where(function ($q) use ($len, $like) {
                $q->where(function ($inner) use ($len, $like) {
                    $inner->whereRaw('CHAR_LENGTH(item_code) > ?', [$len])
                        ->whereRaw('LOWER(item_code) LIKE ?', [$like]);
                })
                    ->orWhereHas('item', function ($item) use ($len, $like) {
                        $item->where(function ($inner) use ($len, $like) {
                            $inner->whereRaw('CHAR_LENGTH(COALESCE(primary_upc, ?)) > ?', ['', $len])
                                ->whereRaw('LOWER(COALESCE(primary_upc, ?)) LIKE ?', ['', $like]);
                        })
                            ->orWhereHas('upcs', function ($upc) use ($len, $like) {
                                $upc->whereRaw('CHAR_LENGTH(upc) > ?', [$len])
                                    ->whereRaw('LOWER(upc) LIKE ?', [$like]);
                            })
                            ->orWhereHas('itemSuppliers', function ($s) use ($len, $like) {
                                $s->whereRaw('CHAR_LENGTH(COALESCE(supplier_item_code, ?)) > ?', ['', $len])
                                    ->whereRaw('LOWER(COALESCE(supplier_item_code, ?)) LIKE ?', ['', $like]);
                            });
                    });
            })
            ->exists();
    }

    protected function findReceivingLine(int $receivingLineId): ?InventoryReceivingLine
    {
        if (! $this->inventory_receiving_id) {
            return null;
        }

        return InventoryReceivingLine::query()
            ->with('item.prices')
            ->where('inventory_receiving_id', $this->inventory_receiving_id)
            ->where('qty_received', '>', 0)
            ->find($receivingLineId);
    }

    protected function resolveUomFromReceivingLine(InventoryReceivingLine $line): string
    {
        if (filled($line->uom)) {
            return (string) $line->uom;
        }

        $item = $line->relationLoaded('item')
            ? $line->item
            : ($line->item_id ? \App\Models\Item::query()->find($line->item_id) : null);

        if ($item && filled($item->unit_of_measure)) {
            return (string) $item->unit_of_measure;
        }

        return 'EA';
    }

    /**
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
            $item = \App\Models\Item::query()->with('prices')->find($itemId);
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

    protected function fillLineFromReceivingLine(int $index, InventoryReceivingLine $line): void
    {
        $lines = array_values($this->lines);
        if (! isset($lines[$index])) {
            $lines[$index] = $this->emptyLine();
        }

        $lines[$index]['item_id'] = $line->item_id ? (int) $line->item_id : null;
        $lines[$index]['item_code'] = (string) ($line->item_code ?? '');
        $lines[$index]['description'] = (string) ($line->description ?? '');
        $lines[$index]['uom'] = $this->resolveUomFromReceivingLine($line);
        $qty = (float) $line->qty_received;
        $lines[$index]['qty'] = $qty > 0 ? $this->formatQtyDisplay($qty) : '1';
        $cost = (float) $line->unit_cost;
        $lines[$index]['unit_cost'] = $this->formatQtyDisplay($cost);
        $this->lines = array_values($lines);
    }

    public function save(bool $closeForm = true): void
    {
        abort_if($this->viewMode || $this->status === 'Returned', 403);

        $this->validate([
            'rtv_number' => 'required|string|max:64',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'inventory_receiving_id' => 'required|integer|exists:inventory_receivings,id',
            'rtv_date' => 'required|date',
        ], [
            'rtv_number.required' => 'RTV number is required.',
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Select a valid supplier.',
            'inventory_receiving_id.required' => 'Select a receiving (Reference).',
            'inventory_receiving_id.exists' => 'Select a valid receiving.',
            'rtv_date.required' => 'RTV date is required.',
        ]);

        $receiving = InventoryReceiving::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('supplier_id', $this->supplier_id)
            ->find($this->inventory_receiving_id);

        if (! $receiving) {
            $this->addError('inventory_receiving_id', 'Receiving must belong to the selected supplier.');

            return;
        }

        $this->reference_no = $receiving->receipt_number;

        $hasLines = collect($this->lines)->contains(fn ($l) => filled($l['item_code'] ?? null) && (float) ($l['qty'] ?? 0) > 0);
        if (! $hasLines) {
            $this->addError('lines', 'Add at least one return line with an item code and quantity.');

            return;
        }

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

        if ($closeForm) {
            $this->showForm = false;
            session()->flash('status', 'RTV '.$this->rtv_number.' saved.');
        }
    }

    public function process(int $id): void
    {
        $rtv = ReturnToVendor::query()->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processRtv($rtv);
        session()->flash('status', 'RTV processed — stock decremented.');
    }

    /** Process the RTV on the form. Save alone does not change stock. */
    public function processCurrent(): void
    {
        abort_if($this->viewMode || $this->status === 'Returned', 403);

        $this->save(closeForm: false);
        if ($this->getErrorBag()->isNotEmpty() || ! $this->rtv?->id) {
            return;
        }

        $rtv = ReturnToVendor::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail((int) $this->rtv->id);

        if ($rtv->status === 'Returned') {
            session()->flash('status', 'RTV already processed.');
            $this->showForm = false;

            return;
        }

        app(InventoryService::class)->processRtv($rtv);
        $this->showForm = false;
        session()->flash('status', 'RTV '.$rtv->rtv_number.' processed — stock reduced.');
    }

    public function processSelected(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $rtv = ReturnToVendor::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail((int) $this->selectedId);

        if ($rtv->status === 'Returned') {
            session()->flash('status', 'That RTV is already processed.');

            return;
        }

        app(InventoryService::class)->processRtv($rtv);
        session()->flash('status', 'RTV '.$rtv->rtv_number.' processed — stock reduced.');
    }

    /** Show 10 not 10.0000 (keeps decimals only when needed). */
    protected function formatQtyDisplay(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $n = (float) $value;
        if (abs($n) < 0.0000001) {
            return '0';
        }

        $formatted = number_format($n, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->viewMode = false;
        $this->showItemBrowse = false;
        $this->lookupMessage = '';
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function createNewRtv(): void
    {
        $this->startNew();
    }

    public function closeDesk(): mixed
    {
        if ($this->showForm) {
            $this->cancelForm();

            return null;
        }

        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page {{ $showForm ? 'entity-page' : '' }} relative">
    @unless ($showForm)
        <x-favorite-list :favorites="$favorites" :active="$favorite" />
    @endunless

    <div class="desk-main {{ $showForm ? 'entity-form item-form' : 'desk-main-rail-layout' }}">
        <x-action-bar :title="$showForm ? ($rtv ? ($viewMode ? 'View RTV '.$rtv_number : 'RTV '.$rtv_number) : 'New RTV') : 'Action'">
            <x-slot:menu>
                @if ($showForm)
                    <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" :disabled="$viewMode || $status === 'Returned'" />
                    <x-action-item label="Cancel" kbd="Ctrl+Q" sep wire:click="cancelForm" />
                @else
                    <x-action-item label="Add New RTV" kbd="Ctrl+N" wire:click="createNewRtv" />
                    <x-action-item label="View/Edit Selected RTV" kbd="Ctrl+E" sep wire:click="editSelected" :disabled="! $canEditSelected" />
                    <x-action-item label="Delete Selected RTV" sep wire:click="deleteSelected" />
                    <x-action-item label="Print" sep wire:click="printSelected" />
                    <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
                @endif
            </x-slot:menu>
        </x-action-bar>

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="contents">
                <fieldset class="so-form-fields">
                <div class="entity-body">
                    <div class="entity-header">
                        <div class="so-form-row so-form-row-pair entity-header-row">
                            <label class="so-form-lbl so-field-req" for="rtv_number">RTV No.</label>
                            <div class="so-form-ctl">
                                <input id="rtv_number" wire:model="rtv_number" class="so-input font-mono @error('rtv_number') is-invalid @enderror" @disabled($rtv) />
                                @error('rtv_number') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <span class="so-form-lbl">Status</span>
                            <span @class([
                                'desk-pill',
                                'desk-pill-new' => $status === 'New',
                                'desk-pill-invoiced' => $status === 'Returned',
                                'desk-pill-muted' => ! in_array($status, ['New', 'Returned'], true),
                            ])>{{ $status }}</span>
                        </div>
                        @error('lines')
                            <div class="mt-1 border border-red-400 bg-red-50 px-2 py-1 text-xs text-red-900" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="entity-balance">Total: <strong>${{ number_format($orderTotal, 2) }}</strong></div>
                    </div>

                    <div class="sc-general-grid">
                        <div class="inv-card">
                            <div class="inv-card-title">RTV header</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl so-field-req" for="rtv_date">RTV Date</label>
                                <div class="so-form-ctl">
                                    <input id="rtv_date" type="date" wire:model="rtv_date" class="so-input sc-date @error('rtv_date') is-invalid @enderror" @disabled($isReadonly) />
                                    @error('rtv_date') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="site_id">Site</label>
                                <select id="site_id" wire:model="site_id" class="so-input" @disabled($isReadonly)>
                                    <option value="">—</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="requested_by_id">Requested By</label>
                                <select id="requested_by_id" wire:model="requested_by_id" class="so-input" @disabled($isReadonly)>
                                    <option value="">—</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="inv-card">
                            <div class="inv-card-title">Supplier &amp; Receiving</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl so-field-req" for="supplier_id">Supplier</label>
                                <div class="so-form-ctl">
                                    <select id="supplier_id" wire:model.live="supplier_id" class="so-input @error('supplier_id') is-invalid @enderror" @disabled($isReadonly)>
                                        <option value="">— Select supplier —</option>
                                        @foreach ($suppliers as $s)
                                            <option value="{{ $s->id }}">{{ $s->supplier_id }} — {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl">Supplier ID</label>
                                <input type="text" class="so-input so-input-ro" readonly value="{{ $selectedSupplier?->supplier_id ?: '—' }}" />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl so-field-req" for="inventory_receiving_id">Reference (Receiving)</label>
                                <div class="so-form-ctl">
                                    <select
                                        id="inventory_receiving_id"
                                        wire:model.live="inventory_receiving_id"
                                        class="so-input @error('inventory_receiving_id') is-invalid @enderror"
                                        @disabled($isReadonly || ! $supplier_id)
                                    >
                                        <option value="">{{ $supplier_id ? '— Select receiving —' : '— Select supplier first —' }}</option>
                                        @foreach ($supplierReceivings as $rcv)
                                            <option value="{{ $rcv->id }}">
                                                {{ $rcv->receipt_number }}
                                                @if ($rcv->receipt_date) — {{ $rcv->receipt_date->format('n/j/Y') }}@endif
                                                @if ($rcv->purchaseOrder) — PO {{ $rcv->purchaseOrder->po_number }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('inventory_receiving_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                                    @if ($supplier_id && $supplierReceivings->isEmpty())
                                        <p class="item-hint" style="border:0;margin:0.35rem 0 0;padding:0">No processed receivings for this supplier.</p>
                                    @elseif ($reference_no && ! $inventory_receiving_id)
                                        <p class="item-hint" style="border:0;margin:0.35rem 0 0;padding:0">Saved ref: {{ $reference_no }} (not linked)</p>
                                    @endif
                                </div>
                            </div>
                            <div class="so-form-row so-form-row-side so-form-row-top sc-field">
                                <label class="so-form-lbl" for="comments">Comments</label>
                                <textarea id="comments" wire:model="comments" rows="3" class="so-input so-input-area" @disabled($isReadonly) placeholder="Reason for return…"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="entity-section">
                        <div class="entity-section-head">
                            <h3 class="entity-section-title">Return Lines</h3>
                            @unless ($isReadonly)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="openItemBrowse" class="desk-btn desk-btn-sm">Browse Items</button>
                                    <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                                </div>
                            @endunless
                        </div>
                        <p class="item-hint" style="border-bottom:1px solid #e2e8f0">
                            Select <strong>supplier</strong> and <strong>receiving</strong> first. Scan/type codes from that receipt only.
                        </p>

                        @unless ($isReadonly)
                            <div class="so-entry po-order-entry" style="padding:0.65rem 0.75rem 0.5rem;border-bottom:1px solid #e2e8f0">
                                <span class="so-entry-label">Add item — scan or type code</span>
                                <div class="so-scan-bar" role="search" @class(['is-scan-ready' => $scanModeActive]) style="max-width:28rem;min-width:16rem;height:2.15rem">
                                    <button
                                        type="button"
                                        wire:click="focusScanAndAdd"
                                        class="so-scan-btn"
                                        title="Scan: full code auto-adds when match"
                                    >
                                        <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                            <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                        </svg>
                                        <span>Scan</span>
                                    </button>
                                    <input
                                        id="rtv-item-entry"
                                        type="text"
                                        class="so-input so-entry-input font-mono"
                                        placeholder="{{ $scanModeActive ? 'Type full code… adds when exact match' : 'Scan or type full code then ✓' }}"
                                        autocomplete="off"
                                        x-data="{
                                            timer: null,
                                            lastKeyAt: 0,
                                            rapid: false,
                                            scheduleAuto() {
                                                clearTimeout(this.timer);
                                                const scanOn = !!$wire.scanModeActive;
                                                if (!scanOn && !this.rapid) return;
                                                const delay = this.rapid ? 100 : 750;
                                                this.timer = setTimeout(() => {
                                                    const v = ($el.value || '').trim();
                                                    if (v.length < 2) { this.rapid = false; return; }
                                                    $wire.autoAddEntryIfExactMatch(v);
                                                    this.rapid = false;
                                                }, delay);
                                            },
                                            onKey(e) {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    clearTimeout(this.timer);
                                                    $wire.addItemFromEntry(($el.value || '').trim());
                                                    this.rapid = false;
                                                    return;
                                                }
                                                if (e.key === 'F2') {
                                                    e.preventDefault();
                                                    clearTimeout(this.timer);
                                                    $wire.openItemBrowse();
                                                    return;
                                                }
                                                const now = Date.now();
                                                if (this.lastKeyAt && (now - this.lastKeyAt) < 50) this.rapid = true;
                                                this.lastKeyAt = now;
                                            }
                                        }"
                                        x-on:keydown="onKey($event)"
                                        x-on:input="scheduleAuto()"
                                        x-on:paste.prevent="
                                            clearTimeout(timer);
                                            const t = ($event.clipboardData || window.clipboardData).getData('text') || '';
                                            $el.value = t.replace(/[\x00-\x1F\x7F]+/g, '').trim();
                                            rapid = false;
                                            if (($el.value || '').trim().length >= 2) {
                                                $wire.addItemFromEntry($el.value);
                                            }
                                        "
                                    />
                                    <button type="button" wire:click="clearItemLookup" class="so-icon-btn" title="Clear" aria-label="Clear">
                                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 3l6 6M9 3L3 9"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click.prevent="$wire.addItemFromEntry(document.getElementById('rtv-item-entry')?.value || '')"
                                        class="so-icon-btn so-entry-add-btn"
                                        title="Add item (✓)"
                                        aria-label="Add item"
                                    >
                                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                                    </button>
                                </div>
                                <button type="button" wire:click="openItemBrowse" class="so-browse-btn" title="Item list (F2)" style="margin-left:0.5rem">Browse (F2)</button>
                            </div>
                        @endunless

                        @if ($lookupMessage)
                            <div class="desk-flash" style="margin:0.5rem 0.75rem" role="status">{{ $lookupMessage }}</div>
                        @endif
                        <div class="desk-grid item-lines-wrap" wire:key="rtv-lines-wrap-{{ $linesSig }}">
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
                                <tbody wire:key="rtv-lines-body-{{ $linesSig }}">
                                    @foreach ($lines as $i => $line)
                                        @php
                                            $filled = filled($line['item_code'] ?? null) || (int) ($line['item_id'] ?? 0) > 0;
                                        @endphp
                                        @if ($filled)
                                            <tr wire:key="rtv-line-row-{{ $i }}-{{ $line['item_id'] ?? 0 }}-{{ $line['item_code'] ?? '' }}">
                                                <td class="font-mono desk-num" title="{{ $line['item_code'] ?? '' }}">
                                                    {{ filled($line['item_code'] ?? null) ? $line['item_code'] : '—' }}
                                                </td>
                                                <td>
                                                    <input wire:model="lines.{{ $i }}.description" class="so-input item-cell-ctl" @disabled($isReadonly) />
                                                </td>
                                                <td class="text-center">
                                                    @if ($isReadonly)
                                                        <span class="font-mono">{{ $line['uom'] ?: '—' }}</span>
                                                    @else
                                                        @php $uomOpts = $this->uomOptionsForLine($i); @endphp
                                                        @if (count($uomOpts) <= 1)
                                                            <span class="font-mono" style="display:inline-block;min-width:2.5rem">
                                                                {{ $line['uom'] ?: ($uomOpts[0] ?? 'EA') }}
                                                            </span>
                                                        @else
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
                                                <td class="text-center">
                                                    <input wire:model.live="lines.{{ $i }}.qty" class="so-input text-right item-cell-qty" placeholder="0" @disabled($isReadonly) />
                                                </td>
                                                <td class="text-center">
                                                    <input wire:model.live="lines.{{ $i }}.unit_cost" class="so-input text-right item-cell-qty" placeholder="0" @disabled($isReadonly) />
                                                </td>
                                                <td class="desk-money">${{ number_format((float) ($line['qty'] ?? 0) * (float) ($line['unit_cost'] ?? 0), 2) }}</td>
                                                <td class="text-center">
                                                    @unless ($isReadonly)
                                                        <button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                                    @endunless
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($filledLineCount === 0)
                                <div class="so-items-empty" role="status" style="padding:1rem;color:#64748b">
                                    Scan or type an item code above, or click Browse Items
                                </div>
                            @endif
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
                                <input id="discount" wire:model.live="discount" class="so-input text-right sc-date" @disabled($isReadonly) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="freight">Freight</label>
                                <input id="freight" wire:model.live="freight" class="so-input text-right sc-date" @disabled($isReadonly) />
                            </div>
                            <div class="so-form-row so-form-row-side sc-field po-total-row">
                                <label class="so-form-lbl">RTV Total</label>
                                <strong class="entity-value text-right" style="display:block;width:100%;font-size:1.15rem">${{ number_format($orderTotal, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                </fieldset>

                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">RTV</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelForm" class="desk-btn">{{ $isReadonly ? 'Close' : 'Cancel' }}</button>
                        @if ($status === 'Returned')
                            @if ($rtv)
                                <a href="{{ route('purchasing.rtv.print', $rtv) }}" target="_blank" rel="noopener" class="desk-btn">Print</a>
                            @endif
                        @elseif ($viewMode && $rtv)
                            <a href="{{ route('purchasing.rtv.print', $rtv) }}" target="_blank" rel="noopener" class="desk-btn">Print</a>
                            <button type="button" wire:click="edit({{ $rtv->id }}, false)" class="desk-btn desk-btn-primary">Edit RTV</button>
                        @else
                            <button type="submit" class="desk-btn">Save RTV</button>
                            <button
                                type="button"
                                wire:click="processCurrent"
                                wire:confirm="Process this RTV and reduce stock now?"
                                class="desk-btn desk-btn-primary"
                            >
                                Process RTV
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        @else
            <div class="desk-main-split">
                <div class="desk-main-body">
                    <div class="desk-toolbar orders-toolbar">
                        <label class="desk-toolbar-label" for="rtv-search">Search RTVs:</label>
                        <input
                            id="rtv-search" data-pos-search
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
                        <span class="desk-title-meta">{{ number_format($total) }}{{ $listHasMore ? '+' : '' }} records</span>
                    </div>

                    <x-desk-scroll-grid :has-more="$listHasMore" class="{{ $compactView ? 'is-compact' : '' }}">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <x-desk-sort-th field="rtv_number" label="RTV Number" />
                                    <x-desk-sort-th field="rtv_date" label="RTV Date" />
                                    <x-desk-sort-th field="status" label="Status" align="center" />
                                    <x-desk-sort-th field="reference_no" label="Reference No." />
                                    <x-desk-sort-th field="supplier_code" label="Supplier ID" />
                                    <x-desk-sort-th field="supplier_name" label="Supplier" />
                                    <x-desk-sort-th field="requested_by" label="Requested By" />
                                    <x-desk-sort-th field="subtotal" label="RTV Subtotal" align="money" />
                                    <x-desk-sort-th field="discount" label="Discount" align="money" />
                                    <x-desk-sort-th field="freight" label="Freight" align="money" />
                                    <x-desk-sort-th field="total" label="RTV Total" align="money" />
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
                    </x-desk-scroll-grid>

                    <x-record-count :count="$total">
                        @if ($footerNote)
                            <span class="text-xs text-slate-600 me-auto">{{ $footerNote }}</span>
                        @endif
                        <button type="button" wire:click="startNew" class="desk-btn desk-btn-primary">New RTV</button>
                        <x-desk-load-more :has-more="$listHasMore" />
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
                    <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                            <circle cx="8" cy="8" r="2"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="editSelected" class="desk-rail-btn" title="{{ $canEditSelected ? 'Edit selected' : 'Processed RTVs cannot be edited' }}" aria-label="Edit selected" @disabled(! $canEditSelected)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="processSelected"
                        wire:confirm="Process selected RTV and reduce stock?"
                        class="desk-rail-btn"
                        title="Process selected (reduce stock)"
                        aria-label="Process selected RTV"
                        @disabled(! $selectedId)
                    >
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M3 8.5l3.2 3.2L13 4.8"/>
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
        <div class="desk-modal-backdrop" wire:click.self="closeItemBrowse" role="dialog" aria-modal="true" aria-label="Browse receiving items">
            <div class="desk-modal" style="max-width:48rem">
                <div class="desk-modal-head">
                    <span>Receiving Items{{ $selectedReceiving ? ' — '.$selectedReceiving->receipt_number : '' }}</span>
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
                            placeholder="Item code, description…"
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
                                    <th class="desk-money">Qty Received</th>
                                    <th class="desk-money">Cost</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($browseLines as $bl)
                                    <tr class="cursor-pointer" wire:click="pickBrowseReceivingLine({{ $bl->id }})">
                                        <td class="desk-num">{{ $bl->item_code }}</td>
                                        <td>{{ $bl->description }}</td>
                                        <td class="text-center">{{ $bl->uom }}</td>
                                        <td class="desk-money">{{ number_format((float) $bl->qty_received, 2) }}</td>
                                        <td class="desk-money">${{ number_format((float) $bl->unit_cost, 2) }}</td>
                                        <td>
                                            <button type="button" wire:click.stop="pickBrowseReceivingLine({{ $bl->id }})" class="desk-btn desk-btn-sm desk-btn-primary">Add</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty"><td colspan="6">No lines on this receiving (or none match your search).</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="item-hint" style="padding:0.65rem 0 0">Only items from the selected receiving are listed. Qty and cost fill from the receipt.</p>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('open-rtv-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank', 'noopener');
    });
</script>
@endscript
