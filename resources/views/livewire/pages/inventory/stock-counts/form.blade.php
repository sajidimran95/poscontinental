<?php

use App\Livewire\Concerns\BrowsesItemsForDocument;
use App\Livewire\Concerns\ReturnsToDeskList;
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
    use BrowsesItemsForDocument {
        openItemBrowse as openDocumentItemBrowse;
    }
    use ReturnsToDeskList;
    public ?StockCount $stockCount = null;

    public string $activeTab = 'general';

    public string $stock_count_no = '';

    public string $date_created = '';

    public string $status = 'New';

    public ?string $last_count_date = null;

    public ?string $date_entered = null;

    public ?string $date_processed = null;

    public ?int $site_id = null;

    public string $description = '';

    public bool $shared_count = false;

    public string $comments = '';

    public ?int $processed_by = null;

    public string $lookupMessage = '';

    public string $itemLookup = '';

    public bool $scanModeActive = false;

    public ?int $selectedLineIndex = null;

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
            $this->date_created = $this->toDateTimeLocal($stockCount->date_created);
            $this->last_count_date = $this->toDateTimeLocal($stockCount->last_count_date) ?: null;
            $this->date_entered = $this->toDateTimeLocal($stockCount->date_entered ?: $stockCount->created_at) ?: null;
            $this->date_processed = $this->toDateTimeLocal($stockCount->date_processed) ?: null;
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
            $this->date_created = $this->toDateTimeLocal(\App\Support\UserTimezone::now());
            $this->site_id = auth()->user()->site_id;
            $this->processed_by = null;
            $this->activeTab = 'expand';
            $this->date_entered = null;
            $this->date_processed = null;
            $prev = StockCount::query()
                ->where('company_id', $companyId)
                ->where('status', 'Processed')
                ->orderByDesc('date_processed')
                ->orderByDesc('id')
                ->first(['date_processed', 'date_created']);
            $this->last_count_date = $this->toDateTimeLocal($prev?->date_processed ?: $prev?->date_created) ?: null;
        }

        if ($this->lines === []) {
            $this->lines[] = $this->emptyLine();
        }
    }

    public function updatedActiveTab($tab): void
    {
        if ($tab !== 'expand' || $this->showBrowse) {
            return;
        }

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('sc-item-entry');
                if (el) el.focus();
            });
        JS);
    }

    public function rendered(): void
    {
        if ($this->showBrowse || $this->activeTab !== 'expand') {
            return;
        }

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('sc-item-entry');
                if (!el) return;
                const a = document.activeElement;
                if (a && a.closest && a.closest('.sc-lines-table, .item-browse, [data-item-browse], .entity-header')) return;
                if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT') && a !== el) return;
                el.focus();
            });
        JS);
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

    protected function toDateTimeLocal(mixed $value): string
    {
        return \App\Support\UserTimezone::toDateTimeLocal($value);
    }

    protected function fromDateTimeLocal(?string $value): ?string
    {
        return \App\Support\UserTimezone::fromDateTimeLocal($value);
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return array_merge($this->documentBrowseViewData(), [
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
            'filledLineCount' => collect($this->lines)->filter(
                fn ($l) => filled($l['item_code'] ?? null) || (int) ($l['item_id'] ?? 0) > 0
            )->count(),
            'isProcessed' => $this->status === 'Processed',
        ]);
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
        if ($this->selectedLineIndex === $i) {
            $this->selectedLineIndex = null;
        } elseif ($this->selectedLineIndex !== null && $this->selectedLineIndex > $i) {
            $this->selectedLineIndex--;
        }
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    /**
     * Scan / Enter: resolve by item code, Primary UPC, aliases, etc.
     */
    public function lookupItem(int $index, ?string $code = null): void
    {
        if ($this->status === 'Processed') {
            return;
        }

        if ($code !== null) {
            $lines = $this->lines;
            $lines[$index]['item_code'] = trim($code);
            $this->lines = $lines;
        }

        $resolved = trim((string) ($this->lines[$index]['item_code'] ?? ''));
        if ($resolved === '') {
            $this->focusLineCode($index);

            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');
        if (! $item) {
            $this->playPosSound('error');
            $this->lookupMessage = '';
            $this->activeTab = 'expand';
            $this->openItemBrowse($index, $resolved);

            return;
        }

        $this->lookupMessage = '';

        // Same item already on another line → keep that row; clear this empty attempt if different index.
        foreach ($this->lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === (int) $item->id && (int) $i !== (int) $index) {
                $this->lines[$index] = $this->emptyLine();
                $this->lookupMessage = $item->item_code.' is already on this count (line '.((int) $i + 1).').';
                $this->playPosSound('warning');
                $this->highlightCountLine((int) $i);

                return;
            }
        }

        $this->fillLineFromItem($index, $item);
        $this->highlightCountLine($index);
        $this->playPosSound('success');
        // Ready next empty line for continuous scan / manual entry.
        $hasEmpty = collect($this->lines)->contains(fn ($l) => ! filled($l['item_code'] ?? null));
        if (! $hasEmpty) {
            $this->addLine();
        }
        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null)) {
                $this->focusLineCode((int) $i);

                return;
            }
        }
        $this->js('requestAnimationFrame(() => { document.getElementById("sc-line-counted-'.$index.'")?.focus(); });');
    }

    public function openItemBrowse(?int $lineIndex = null, ?string $search = null): void
    {
        if ($this->status === 'Processed') {
            return;
        }
        $this->activeTab = 'expand';
        if (($search === null || $search === '') && $lineIndex === null) {
            $search = trim($this->itemLookup);
        }
        $this->openDocumentItemBrowse($lineIndex, $search);
    }

    public function pickBrowseItem(int $itemId, bool $keepBrowseOpen = false): void
    {
        if ($this->status === 'Processed') {
            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('is_inactive', false)
            ->find($itemId);

        if (! $item) {
            $this->playPosSound('error');

            return;
        }

        foreach ($this->lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === (int) $item->id) {
                $this->lookupMessage = $item->item_code.' is already on this count (line '.((int) $i + 1).').';
                $this->lineWarning = $this->lookupMessage;
                $this->lineWarningKind = 'warning';
                $this->playPosSound('warning');
                $this->highlightCountLine((int) $i);
                $this->focusBrowseSearch();

                return;
            }
        }

        $index = $this->resolveCountTargetIndex();
        $this->fillLineFromItem($index, $item);
        $this->highlightCountLine($index);
        $this->lookupMessage = '';
        $this->lineWarning = '';
        $this->playPosSound('success');

        $hasEmpty = collect($this->lines)->contains(fn ($l) => ! filled($l['item_code'] ?? null));
        if (! $hasEmpty) {
            $this->addLine();
        }
        $this->browseLineIndex = $this->firstEmptyCountLineIndex();
        $this->focusBrowseSearch();
    }

    public function addItemFromEntry(?string $code = null): void
    {
        if ($this->status === 'Processed') {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        $this->itemLookup = $code;
        $this->activeTab = 'expand';

        if ($code === '') {
            $this->clearAndFocusEntry();

            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $code, 'any');
        if ($item) {
            $this->itemLookup = '';
            $this->browseLineIndex = null;
            if (! $this->applyScannedItem($item)) {
                $this->clearAndFocusEntry();

                return;
            }
            $this->scanModeActive = true;
            $this->clearAndFocusEntry();

            return;
        }

        $this->playPosSound('error');
        $this->lookupMessage = 'Item '.$code.' was not found.';
        $this->openItemBrowse(null, $code);
    }

    public function autoAddEntryIfExactMatch(?string $code = null): void
    {
        if ($this->status === 'Processed') {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        if ($code === '' || mb_strlen($code) < 2) {
            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $code, 'any');
        if (! $item || $this->codeIsPrefixOfLongerItemCode($code)) {
            return;
        }

        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->browseLineIndex = null;
        if (! $this->applyScannedItem($item)) {
            $this->clearAndFocusEntry();

            return;
        }
        $this->scanModeActive = true;
        $this->clearAndFocusEntry();
    }

    public function focusScanAndAdd(): void
    {
        if ($this->status === 'Processed') {
            return;
        }

        $this->scanModeActive = true;
        $this->lookupMessage = '';

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('sc-item-entry');
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

    protected function applyScannedItem(Item $item): bool
    {
        foreach ($this->lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === (int) $item->id) {
                $this->lookupMessage = $item->item_code.' is already on this count (line '.((int) $i + 1).').';
                $this->playPosSound('warning');
                $this->highlightCountLine((int) $i);

                return false;
            }
        }

        $index = $this->resolveCountTargetIndex();
        $this->fillLineFromItem($index, $item);
        $this->highlightCountLine($index);
        $this->lookupMessage = '';
        $this->lineWarning = '';
        $this->playPosSound('success');

        $hasEmpty = collect($this->lines)->contains(fn ($l) => ! filled($l['item_code'] ?? null));
        if (! $hasEmpty) {
            $this->addLine();
        }

        return true;
    }

    protected function codeIsPrefixOfLongerItemCode(string $code): bool
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
                    });
            })
            ->exists();
    }

    protected function clearAndFocusEntry(): void
    {
        $this->itemLookup = '';
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('sc-item-entry');
                if (!el) return;
                el.value = '';
                el.focus();
            });
        JS);
    }

    protected function resolveCountTargetIndex(): int
    {
        $idx = $this->browseLineIndex;
        if ($idx !== null && isset($this->lines[$idx]) && ! filled($this->lines[$idx]['item_code'] ?? null) && empty($this->lines[$idx]['item_id'])) {
            return (int) $idx;
        }

        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null) && empty($line['item_id'])) {
                return (int) $i;
            }
        }

        $this->addLine();

        return count($this->lines) - 1;
    }

    protected function firstEmptyCountLineIndex(): ?int
    {
        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null) && empty($line['item_id'])) {
                return (int) $i;
            }
        }

        return null;
    }

    public function focusLineScan(int $index): void
    {
        if ($this->status === 'Processed' || ! isset($this->lines[$index])) {
            return;
        }

        $this->js(<<<JS
            requestAnimationFrame(() => {
                const el = document.getElementById('sc-line-code-{$index}');
                if (!el) return;
                el.focus();
                el.select();
                const v = (el.value || '').trim();
                if (v !== '') {
                    \$wire.lookupItem({$index}, v);
                }
            });
        JS);
    }

    public function clearLineItemCode(int $index): void
    {
        if ($this->status === 'Processed' || ! isset($this->lines[$index])) {
            return;
        }

        $this->lines[$index] = $this->emptyLine();
        if (str_contains(strtolower($this->lookupMessage), 'was not found')
            || str_contains(strtolower($this->lookupMessage), 'already on this count')) {
            $this->lookupMessage = '';
        }
        $this->focusLineCode($index);
    }

    protected function fillLineFromItem(int $index, Item $item): void
    {
        $lines = $this->lines;
        $lines[$index]['item_id'] = $item->id;
        $lines[$index]['item_code'] = $item->item_code;
        $lines[$index]['description'] = $item->description ?? '';
        $lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $lines[$index]['in_stock'] = (string) $item->quantity_in_stock;
        $lines[$index]['allocated'] = (string) $item->allocated_qty;
        $this->lines = $lines;
    }

    protected function highlightCountLine(int $index): void
    {
        $this->selectedLineIndex = $index;
        $this->js('requestAnimationFrame(() => {
            const row = document.getElementById("sc-line-row-'.$index.'");
            if (row) row.scrollIntoView({ block: "nearest" });
            const counted = document.getElementById("sc-line-counted-'.$index.'");
            if (counted && !counted.disabled) { counted.focus(); counted.select(); }
        });');
    }

    protected function focusLineCode(int $index, bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("sc-line-code-'.$index.'"); if (el) { el.focus();'.$selectJs.' } });');
    }

    public function updatedLines($value, $key): void
    {
        if (str_ends_with($key, '.counted') && filled($value)) {
            $index = (int) explode('.', $key)[0];
            $this->lines[$index]['count_time'] = \App\Support\UserTimezone::now()->format('Y-m-d H:i:s');
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
                $this->returnToDeskList('inventory.stock-counts.index');
            }

            return;
        }

        $this->validate([
            'stock_count_no' => 'required|string|max:64',
            'site_id' => 'required|integer|exists:sites,id',
            'processed_by' => 'nullable|integer|exists:users,id',
            'date_created' => 'nullable|date',
            'last_count_date' => 'nullable|date',
            'date_processed' => 'nullable|date',
        ], [
            'stock_count_no.required' => 'Count number is required.',
            'site_id.required' => 'Site is required.',
            'site_id.exists' => 'Select a valid site.',
        ]);

        $data = [
            'company_id' => auth()->user()->company_id,
            'stock_count_no' => $this->stock_count_no,
            'date_created' => $this->fromDateTimeLocal($this->date_created),
            'status' => $this->status,
            'last_count_date' => $this->fromDateTimeLocal($this->last_count_date),
            'date_processed' => $this->fromDateTimeLocal($this->date_processed),
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
                $data['date_entered'] = \App\Support\UserTimezone::now();
                $count = StockCount::query()->create($data);
                $this->date_entered = $this->toDateTimeLocal($data['date_entered']);
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
            $this->returnToDeskList('inventory.stock-counts.index');
        }
    }

    public function process(): void
    {
        if ($this->status === 'Processed') {
            return;
        }
        $this->save(false);
        app(InventoryService::class)->processStockCount($this->stockCount->fresh('lines'));
        $this->returnToDeskList('inventory.stock-counts.index');
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
                    <label class="so-form-lbl so-field-req" for="stock_count_no">Count No.</label>
                    <div class="so-form-ctl">
                        <input id="stock_count_no" wire:model="stock_count_no" class="so-input font-mono @error('stock_count_no') is-invalid @enderror" @disabled($stockCount) />
                        @error('stock_count_no') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
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
                            <input id="date_created" type="datetime-local" step="1" wire:model="date_created" class="so-input sc-date" @disabled($isProcessed) />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="status">Count Status</label>
                            <input id="status" wire:model="status" class="so-input so-input-ro sc-date" readonly />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="last_count_date">Last Count Date</label>
                            <input id="last_count_date" type="datetime-local" step="1" wire:model="last_count_date" class="so-input sc-date" @disabled($isProcessed) />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="date_entered">Date Entered</label>
                            <input id="date_entered" type="datetime-local" step="1" wire:model="date_entered" class="so-input so-input-ro sc-date" readonly />
                        </div>
                        <div class="so-form-row so-form-row-side sc-field">
                            <label class="so-form-lbl" for="date_processed">Date Processed</label>
                            <input id="date_processed" type="datetime-local" step="1" wire:model="date_processed" class="so-input sc-date" @disabled($isProcessed) />
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
                            <label class="so-form-lbl so-field-req" for="site_id">Site</label>
                            <div class="so-form-ctl">
                                <select id="site_id" wire:model="site_id" class="so-input @error('site_id') is-invalid @enderror" @disabled($isProcessed)>
                                    <option value="">—</option>
                                    @foreach ($sites as $s)
                                        <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name ?? $s->code }}</option>
                                    @endforeach
                                </select>
                                @error('site_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
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
                            <div class="flex gap-2">
                                <button type="button" wire:click="openItemBrowse" class="desk-btn desk-btn-sm">Browse Items</button>
                                <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                            </div>
                        @endunless
                    </div>

                    @unless ($isProcessed)
                        <div class="so-entry po-order-entry" style="padding:0.65rem 0.75rem 0.5rem;border-bottom:1px solid #e2e8f0">
                            <span class="so-entry-label">Add item — scan or type code</span>
                            <div class="so-scan-bar" role="search" @class(['is-scan-ready' => $scanModeActive]) style="max-width:28rem;min-width:16rem;height:2.15rem">
                                <button
                                    type="button"
                                    wire:click="focusScanAndAdd"
                                    class="so-scan-btn"
                                    title="Scan (F2): click to focus, or add the code already in the box"
                                >
                                    <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                        <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                    </svg>
                                    <span>Scan</span>
                                </button>
                                <input
                                    id="sc-item-entry"
                                    data-pos-item-entry
                                    type="text"
                                    class="so-input so-entry-input font-mono"
                                    placeholder="{{ $scanModeActive ? 'Type full code… adds when exact match' : 'Scan barcode or type full code then ✓' }}"
                                    autocomplete="off"
                                    wire:keydown.enter.prevent="addItemFromEntry($event.target.value)"
                                    wire:keydown.f3.prevent="openItemBrowse"
                                    x-data="{
                                        timer: null,
                                        lastKeyAt: 0,
                                        rapid: false,
                                        scheduleAuto() {
                                            clearTimeout(this.timer);
                                            const scanOn = !!$wire.scanModeActive;
                                            if (!scanOn && !this.rapid) return;
                                            const delay = this.rapid ? 35 : 150;
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
                                            if (e.key === 'F3') {
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
                                    x-on:click.prevent="$wire.addItemFromEntry(document.getElementById('sc-item-entry')?.value || '')"
                                    class="so-icon-btn so-entry-add-btn"
                                    title="Add item (✓)"
                                    aria-label="Add item"
                                >
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                                </button>
                            </div>
                            <button type="button" wire:click="openItemBrowse" class="so-browse-btn" data-pos-browse title="Item list (F3)" style="margin-left:0.5rem">Browse (F3)</button>
                        </div>
                    @endunless

                    @if ($lookupMessage)
                        <div class="desk-flash" style="margin:0.5rem 0.75rem" role="status">{{ $lookupMessage }}</div>
                    @endif
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
                                        $filled = filled($line['item_code'] ?? null) || (int) ($line['item_id'] ?? 0) > 0;
                                        $variance = filled($line['counted'])
                                            ? (float) $line['counted'] - (float) $line['in_stock']
                                            : null;
                                    @endphp
                                    @if ($filled)
                                        <tr
                                            id="sc-line-row-{{ $i }}"
                                            wire:key="sc-line-{{ $i }}-{{ $line['item_id'] ?? 'new' }}"
                                            wire:click="$set('selectedLineIndex', {{ $i }})"
                                            @class(['is-selected' => $selectedLineIndex === $i, 'cursor-pointer'])
                                        >
                                            <td class="font-mono desk-num" title="{{ $line['item_code'] ?? '' }}">
                                                {{ filled($line['item_code'] ?? null) ? $line['item_code'] : '—' }}
                                            </td>
                                            <td class="item-cell-desc" title="{{ $line['description'] }}">{{ $line['description'] ?: '—' }}</td>
                                            <td class="text-center">{{ $line['uom'] ?: '—' }}</td>
                                            <td class="desk-money">{{ number_format((float) $line['in_stock'], 2) }}</td>
                                            <td class="desk-money">{{ number_format((float) $line['allocated'], 2) }}</td>
                                            <td class="text-center">
                                                <input
                                                    id="sc-line-counted-{{ $i }}"
                                                    wire:model.live="lines.{{ $i }}.counted"
                                                    class="so-input text-right item-cell-qty"
                                                    @disabled($isProcessed)
                                                    aria-label="Counted qty line {{ $i + 1 }}"
                                                />
                                            </td>
                                            <td @class(['desk-money', 'sc-var-neg' => $variance !== null && $variance < 0, 'sc-var-pos' => $variance !== null && $variance > 0])>
                                                {{ $variance !== null ? number_format($variance, 2) : '' }}
                                            </td>
                                            <td class="sc-time">{{ $line['count_time'] ? \Illuminate\Support\Carbon::parse($line['count_time'])->format('n/j/Y g:i:s A') : '—' }}</td>
                                            <td class="text-center">
                                                @unless ($isProcessed)
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
                                Scan or type an item code above, or click Browse (F3)
                            </div>
                        @endif
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
