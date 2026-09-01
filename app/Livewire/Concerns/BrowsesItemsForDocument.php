<?php

namespace App\Livewire\Concerns;

use App\Models\Category;
use App\Models\Item;
use App\Models\Subcategory;
use App\Support\ExcelCsv;
use App\Support\ItemSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Sales-order style F2 item browse for documents that add item lines (PO, stock count).
 */
trait BrowsesItemsForDocument
{
    use SortsItemBrowse;
    public bool $showBrowse = false;

    public string $browseSearch = '';

    public bool $browseNewOnly = false;

    public ?int $browseCategoryId = null;

    public ?int $browseSubcategoryId = null;

    public bool $browseSavedSearchOpen = false;

    public bool $browseQtyLtZero = false;

    public ?int $browseSelectedId = null;

    /** @var list<string> */
    public array $browseCheckedIds = [];

    public int $browseChecksVersion = 0;

    /** @var list<array<string, mixed>> */
    public array $browseRows = [];

    public int $browseTotal = 0;

    public bool $browseHasMore = false;

    public bool $browseLoadingMore = false;

    public string $lineWarning = '';

    public string $lineWarningKind = 'warning';

    public ?int $browseLineIndex = null;

    protected const BROWSE_PAGE_SIZE = 80;

    abstract public function pickBrowseItem(int $itemId, bool $keepBrowseOpen = false): void;

    public function openItemBrowse(?int $lineIndex = null, ?string $search = null): void
    {
        if (($this->status ?? '') === 'Processed') {
            return;
        }

        $this->browseLineIndex = $lineIndex;
        $code = $search !== null ? trim($search) : '';
        if ($code === '' && $lineIndex !== null && isset($this->lines[$lineIndex])) {
            $code = trim((string) ($this->lines[$lineIndex]['item_code'] ?? ''));
        }
        $this->browseSearch = $code;
        $this->lineWarning = '';
        if (property_exists($this, 'lookupMessage')) {
            $this->lookupMessage = '';
        }
        $this->showBrowse = true;
        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch();
    }

    public function closeItemBrowse(): void
    {
        $this->closeBrowse();
    }

    public function closeBrowse(): void
    {
        $this->showBrowse = false;
        $this->browseLineIndex = null;
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

    public function updatedBrowseSearch(): void
    {
        if (! $this->showBrowse) {
            return;
        }
        $this->resetBrowseAndLoadFirstPage();
    }

    public function exportBrowseToExcel(): mixed
    {
        $companyId = (int) auth()->user()->company_id;
        $query = $this->applyBrowseOrder($this->browseBaseQuery($companyId));

        if (! $query->exists()) {
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

    public function updatedBrowseNewOnly(): void
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

    public function setBrowseQtyLtZero(bool $on = true): void
    {
        $this->browseQtyLtZero = $on;
        $this->browseSavedSearchOpen = true;
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
        $this->browseSelectedId = $count === 1 ? (int) $this->browseCheckedIds[0] : null;
    }

    public function updatedBrowseCheckedIds(): void
    {
        $this->browseCheckedIds = collect($this->browseCheckedIds)
            ->map(fn ($v) => (string) (int) $v)
            ->filter(fn (string $v) => $v !== '' && $v !== '0')
            ->unique()
            ->values()
            ->all();

        $count = count($this->browseCheckedIds);
        $this->browseSelectedId = $count === 1 ? (int) $this->browseCheckedIds[0] : null;
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
        $this->browseSelectedId = count($ids) === 1 ? (int) $ids[0] : null;
    }

    public function insertBrowseSelected(): void
    {
        $id = $this->resolveBrowseTargetId();
        if ($id === null) {
            $this->lineWarning = count($this->browseCheckedIds) > 1
                ? 'Multiple items checked — use Insert All Checked, or uncheck to a single item.'
                : 'Select one item first.';
            $this->lineWarningKind = 'warning';

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

        foreach ($ids as $itemId) {
            $this->pickBrowseItem($itemId, true);
        }
        $this->browseCheckedIds = [];
        $this->browseSelectedId = null;
        $this->browseChecksVersion++;
        $this->dispatch('browse-checks-cleared');
        $this->js('window.dispatchEvent(new CustomEvent("browse-checks-cleared"))');
    }

    public function openBrowseNewItem(): void
    {
        if (! Route::has('inventory.items.create')) {
            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.create'));
    }

    public function openBrowseEditSelected(): void
    {
        $id = $this->resolveBrowseTargetId();
        if ($id === null || ! Route::has('inventory.items.edit')) {
            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->find($id);
        if (! $item) {
            return;
        }
        $this->dispatch('open-item-record', url: route('inventory.items.edit', $item));
    }

    public function refreshBrowseItems(): void
    {
        if ($this->showBrowse) {
            $this->resetBrowseAndLoadFirstPage();
        }
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

    public function scanBrowseAndPick(?string $code = null): void
    {
        if ($code !== null) {
            $this->browseSearch = trim($code);
        }
        $resolved = trim($this->browseSearch);
        if ($resolved === '') {
            $this->focusBrowseSearch();

            return;
        }
        $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');
        if ($item) {
            $this->browseSearch = '';
            $this->pickBrowseItem((int) $item->id, true);
            $this->resetBrowseAndLoadFirstPage();
            $this->focusBrowseSearch();

            return;
        }
        $this->playPosSound('error');
        $this->resetBrowseAndLoadFirstPage();
        $this->focusBrowseSearch(true);
    }

    public function focusBrowseScan(): void
    {
        if (trim($this->browseSearch) !== '') {
            $this->scanBrowseAndPick();

            return;
        }
        $this->focusBrowseSearch();
    }

    protected function playPosSound(string $kind = 'error'): void
    {
        $this->dispatch('pos-alert', kind: $kind);
        $this->js('window.playPosAlert && window.playPosAlert('.json_encode($kind).')');
    }

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

    protected function browseBaseQuery(int $companyId)
    {
        $newDays = defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30;
        $newSince = now()->subDays($newDays);

        return DB::table('items')
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->when($this->browseNewOnly, fn ($q) => $q->where('created_at', '>=', $newSince))
            ->when($this->browseQtyLtZero, fn ($q) => $q->where('quantity_in_stock', '<', 0))
            ->when($this->browseCategoryId, fn ($q) => $q->where('category_id', $this->browseCategoryId))
            ->when($this->browseSubcategoryId, fn ($q) => $q->where('subcategory_id', $this->browseSubcategoryId))
            ->when(filled($this->browseSearch), fn ($q) => ItemSearch::constrain($q, $this->browseSearch));
    }

    protected function focusBrowseSearch(bool $select = false): void
    {
        $selectJs = $select ? ' el.select();' : '';
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("so-browse-search"); if (el) { el.focus();'.$selectJs.' } });');
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentBrowseViewData(): array
    {
        $companyId = (int) auth()->user()->company_id;

        return [
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
            'itemNewDays' => defined(Item::class.'::NEW_ITEM_DAYS') ? Item::NEW_ITEM_DAYS : 30,
            'oversellingOn' => true,
            'browseSearchPlaceholder' => 'Code, UPC, or words in the description',
        ];
    }
}
