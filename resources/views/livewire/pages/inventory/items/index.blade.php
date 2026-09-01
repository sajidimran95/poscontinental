<?php

use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\Category;
use App\Models\Department;
use App\Models\InventoryJournalEntry;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\Site;
use App\Models\Subcategory;
use App\Services\InventoryService;
use App\Support\ExcelCsv;
use App\Support\ItemSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Items')] class extends Component
{
    use WithPagination;
    use SortsDeskList;
    use PaginatesDeskLists;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'all';

    /** Category filter (id as string, '' = all) */
    #[Url(as: 'category')]
    public string $categoryFilter = '';

    /** '' | active | inactive */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    /** @var list<string> */
    public array $visibleColumns = [];

    public string $scanStatus = '';

    /** Chief-style query builder (LESTHANO popup) */
    public bool $showItemQuery = false;

    public string $queryField = 'quantity_in_stock';

    public string $queryOperator = 'lt';

    public string $queryValue = '0';

    /** Next join when adding another criterion */
    public string $queryJoin = 'and';

    /** value | field */
    public string $queryValueMode = 'value';

    public string $queryCompareField = 'reorder_point';

    /** @var array<int, array{field:string,operator:string,value:string,value_mode:string,compare_field:string,join:string,label:string}> */
    public array $queryCriteria = [];

    public ?int $querySelectedIndex = null;

    public string $querySaveName = '';

    public string $queryLoadedName = '';

    public string $queryStatus = '';

    public string $querySavedPick = '';

    /** Stock adjust + journal track modal */
    public bool $showStockAdjust = false;

    /** 'adjust' | 'track' — which rail action opened the modal */
    public string $stockModalMode = 'adjust';

    public ?int $adjustItemId = null;

    /** set = new total qty | change = add/subtract */
    public string $adjustMode = 'change';

    public string $adjustQty = '';

    public string $adjustNotes = '';

    public string $adjustSiteId = '';

    public string $adjustMessage = '';

    public string $adjustError = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $itemNewDays = Item::NEW_ITEM_DAYS;

        $query = $this->filteredItemsQuery();
        $query = $this->applyDeskSort($query, $this->favorite === 'new' ? 'created_at' : 'id', 'desc');

        $nav = Cache::remember('items.list_nav.'.(int) $companyId, 180, function () use ($companyId, $itemNewDays) {
            $favorites = [
                'all' => 'All Items',
                'new' => 'New Items ('.$itemNewDays.' days)',
                'active' => 'Active Items',
                'inactive' => 'Inactive Items',
                'low_stock' => 'Low Stock',
            ];
            $nodes = [
                ['type' => 'item', 'key' => 'all', 'label' => 'All Items', 'level' => 0],
                ['type' => 'item', 'key' => 'new', 'label' => 'New Items ('.$itemNewDays.' days)', 'level' => 0],
                ['type' => 'item', 'key' => 'active', 'label' => 'Active Items', 'level' => 0],
                ['type' => 'item', 'key' => 'inactive', 'label' => 'Inactive Items', 'level' => 0],
                ['type' => 'item', 'key' => 'low_stock', 'label' => 'Low Stock', 'level' => 0],
            ];
            $departments = Department::query()
                ->with(['categories' => fn ($q) => $q->orderBy('name')->with(['subcategories' => fn ($sq) => $sq->orderBy('name')])])
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'company_id']);
            if ($departments->isNotEmpty()) {
                $nodes[] = ['type' => 'heading', 'label' => 'By Department'];
            }
            foreach ($departments as $dept) {
                $favorites['dept:'.$dept->id] = $dept->name;
                $nodes[] = [
                    'type' => 'item',
                    'key' => 'dept:'.$dept->id,
                    'label' => $dept->name,
                    'level' => 0,
                    'kind' => 'Dept',
                ];
                foreach ($dept->categories as $cat) {
                    $favorites['cat:'.$cat->id] = $cat->name;
                    $nodes[] = [
                        'type' => 'item',
                        'key' => 'cat:'.$cat->id,
                        'label' => $cat->name,
                        'level' => 1,
                        'kind' => 'Category',
                    ];
                    foreach ($cat->subcategories as $sub) {
                        $favorites['sub:'.$sub->id] = $sub->name;
                        $nodes[] = [
                            'type' => 'item',
                            'key' => 'sub:'.$sub->id,
                            'label' => $sub->name,
                            'level' => 2,
                            'kind' => 'Subcat',
                        ];
                    }
                }
            }

            return [
                'favorites' => $favorites,
                'nodes' => $nodes,
                'categories' => Category::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name'])
                    ->map(fn ($c) => ['id' => (int) $c->id, 'code' => $c->code, 'name' => $c->name])
                    ->all(),
                'departmentsFlat' => $departments->map(fn ($d) => ['id' => (int) $d->id, 'code' => $d->code, 'name' => $d->name])->all(),
                'subcategories' => Subcategory::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'category_id'])
                    ->map(fn ($s) => ['id' => (int) $s->id, 'code' => $s->code, 'name' => $s->name, 'category_id' => (int) $s->category_id])
                    ->all(),
            ];
        });
        $favorites = $nav['favorites'];
        $nodes = $nav['nodes'];
        $categories = collect($nav['categories'])->map(fn ($r) => (object) $r);
        $departmentsFlat = collect($nav['departmentsFlat'])->map(fn ($r) => (object) $r);
        $subcategories = collect($nav['subcategories'])->map(fn ($r) => (object) $r);

        $listTitle = 'Items List';
        if ($this->queryCriteria !== []) {
            $listTitle = $this->queryLoadedName !== ''
                ? 'Query: '.$this->queryLoadedName
                : 'Query Results ('.count($this->queryCriteria).' criteria)';
        } elseif ($this->statusFilter === 'active') {
            $listTitle = 'Items List (Active)';
        } elseif ($this->statusFilter === 'inactive') {
            $listTitle = 'Items List (Inactive)';
        } elseif ($this->categoryFilter !== '') {
            $hit = $categories->firstWhere('id', (int) $this->categoryFilter);
            $listTitle = $hit->name ?? 'Items List';
        } elseif (isset($favorites[$this->favorite]) && $this->favorite !== 'all') {
            $listTitle = $favorites[$this->favorite];
        }

        $adjustItem = null;
        $adjustJournal = collect();
        $trackAllItems = $this->showStockAdjust
            && $this->stockModalMode === 'track'
            && ! $this->adjustItemId;

        if ($this->showStockAdjust) {
            if ($this->adjustItemId) {
                $adjustItem = Item::query()
                    ->where('company_id', $companyId)
                    ->find($this->adjustItemId);
            }

            if ($this->stockModalMode === 'track' || $adjustItem) {
                $journalQuery = InventoryJournalEntry::query()
                    ->where('company_id', $companyId)
                    ->with(['site', 'item:id,item_code,description'])
                    ->orderByDesc('id');

                if ($adjustItem) {
                    $journalQuery->where('item_id', $adjustItem->id)->limit(50);
                } else {
                    // All-items track log
                    $journalQuery->limit(150);
                }

                $adjustJournal = $journalQuery->get();
            }
        }

        $catalog = $this->itemListColumnCatalog();
        $visibleKeys = $this->normalizedVisibleColumns();
        $scroll = $this->scrollDeskList($query);

        return [
            'items' => $scroll['rows'],
            'listHasMore' => $scroll['hasMore'],
            'listShown' => $scroll['shown'],
            'favorites' => $favorites,
            'nodes' => $nodes,
            'listTitle' => $listTitle,
            'categoryOptions' => $categories,
            'queryFields' => $this->queryFieldOptions(),
            'queryOperators' => $this->queryOperatorOptions(),
            'queryCategories' => $categories,
            'queryDepartments' => $departmentsFlat,
            'querySubcategories' => $subcategories,
            'savedItemQueries' => $this->loadSavedItemQueries(),
            'adjustItem' => $adjustItem,
            'adjustJournal' => $adjustJournal,
            'trackAllItems' => $trackAllItems,
            'adjustSites' => ($this->showStockAdjust && $this->stockModalMode === 'adjust')
                ? Site::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'itemColumnCatalog' => $catalog,
            'visibleColumnKeys' => $visibleKeys,
            'columnColspan' => count($visibleKeys) + 1,
        ];
    }

    public function mount(): void
    {
        $this->visibleColumns = $this->loadVisibleColumns();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->scanStatus = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->scanStatus = '';
        $this->categoryFilter = '';
        $this->statusFilter = '';
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->queryCriteria = [];
        $this->querySelectedIndex = null;
        $this->queryLoadedName = '';
        $this->queryStatus = '';
        $this->resetPage();
    }

    /**
     * Enter / barcode: exact code/UPC → select that row on this list (do not open details).
     * Pass code from the live input so scanner Enter is not stale.
     */
    public function scanFindItem(?string $code = null): mixed
    {
        if ($code !== null) {
            $this->search = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '');
        }

        $resolved = trim($this->search);
        $this->scanStatus = '';

        if ($resolved === '') {
            $this->scanStatus = 'Type or scan a barcode / item code, then press Enter.';

            return null;
        }

        $companyId = (int) auth()->user()->company_id;
        $item = Item::findByScanCode($companyId, $resolved, 'any');

        if ($item) {
            $this->selectedId = (int) $item->id;
            $this->search = (string) $item->item_code;
            $this->resetPage();
            $this->scanStatus = 'Found: '.$item->item_code;

            return null;
        }

        // Partial / unknown: show filtered item list (do not auto-create).
        $this->resetPage();
        $this->scanStatus = 'No exact match for “'.$resolved.'”. Showing matching items in the list.';

        return null;
    }

    /**
     * Focus SKU field for scanner; if value present, run find (or list filter).
     */
    public function focusScanAndFind(): mixed
    {
        $this->js(<<<'JS'
            const el = document.getElementById('items-search');
            if (!el) return;
            el.focus();
            el.select();
            const v = (el.value || '').trim();
            if (v !== '') {
                $wire.scanFindItem(v);
            }
        JS);

        if (trim($this->search) === '') {
            $this->scanStatus = 'Scan barcode or type SKU, then press Enter — stay on this list.';
        }

        return null;
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->statusFilter = match ($this->favorite) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => $this->statusFilter,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        if ($this->statusFilter === 'active') {
            $this->favorite = 'active';
        } elseif ($this->statusFilter === 'inactive') {
            $this->favorite = 'inactive';
        }
    }

    public function updatedQueryField(): void
    {
        if (in_array($this->queryField, ['category_id', 'department_id', 'subcategory_id'], true)) {
            $this->queryOperator = 'eq';
            $this->queryValue = '';
            $this->queryValueMode = 'value';
        } elseif (in_array($this->queryField, ['can_sell', 'is_inactive'], true)) {
            $this->queryOperator = 'eq';
            $this->queryValue = '1';
            $this->queryValueMode = 'value';
        } elseif ($this->queryField === 'quantity_in_stock' || $this->queryField === 'available_qty') {
            if ($this->queryValue === '') {
                $this->queryValue = '0';
            }
            if ($this->queryOperator === 'contains' || $this->queryOperator === 'starts') {
                $this->queryOperator = 'lt';
            }
        }
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function openItemQuery(): void
    {
        $this->showItemQuery = true;
        $this->queryStatus = '';
        if ($this->queryCriteria === []) {
            $this->queryField = 'quantity_in_stock';
            $this->queryOperator = 'lt';
            $this->queryValue = '0';
            $this->queryValueMode = 'value';
            $this->queryJoin = 'and';
        }
    }

    public function closeItemQuery(): void
    {
        $this->showItemQuery = false;
        $this->queryStatus = '';
    }

    public function setQueryValueMode(string $mode): void
    {
        $this->queryValueMode = $mode === 'field' ? 'field' : 'value';
    }

    public function addQueryCriterion(): void
    {
        $criterion = $this->buildCriterionFromDraft();
        if ($criterion === null) {
            return;
        }

        if ($this->querySelectedIndex !== null && isset($this->queryCriteria[$this->querySelectedIndex])) {
            $this->queryCriteria[$this->querySelectedIndex] = $criterion;
            $this->querySelectedIndex = null;
            $this->queryStatus = 'Criterion updated.';
        } else {
            $this->queryCriteria[] = $criterion;
            $this->queryStatus = 'Criterion added.';
        }

        $this->queryCriteria = array_values($this->queryCriteria);
    }

    public function selectQueryCriterion(int $index): void
    {
        if (! isset($this->queryCriteria[$index])) {
            return;
        }
        $row = $this->queryCriteria[$index];
        $this->querySelectedIndex = $index;
        $this->queryField = $row['field'];
        $this->queryOperator = $row['operator'];
        $this->queryValue = (string) ($row['value'] ?? '');
        $this->queryValueMode = $row['value_mode'] ?? 'value';
        $this->queryCompareField = $row['compare_field'] ?? 'reorder_point';
        $this->queryJoin = $row['join'] ?? 'and';
    }

    public function removeQueryCriterion(): void
    {
        if ($this->querySelectedIndex === null || ! isset($this->queryCriteria[$this->querySelectedIndex])) {
            $this->queryStatus = 'Select a criterion to remove.';

            return;
        }
        unset($this->queryCriteria[$this->querySelectedIndex]);
        $this->queryCriteria = array_values($this->queryCriteria);
        $this->querySelectedIndex = null;
        $this->queryStatus = 'Criterion removed.';
    }

    public function clearQueryCriteria(): void
    {
        $this->queryCriteria = [];
        $this->querySelectedIndex = null;
        $this->queryLoadedName = '';
        $this->queryStatus = 'Criteria cleared.';
        $this->resetPage();
    }

    public function runItemQuery(): void
    {
        // If draft has a value/operator ready and no criteria yet, auto-add.
        if ($this->queryCriteria === []) {
            $criterion = $this->buildCriterionFromDraft(false);
            if ($criterion !== null) {
                $this->queryCriteria[] = $criterion;
            }
        }

        if ($this->queryCriteria === []) {
            $this->queryStatus = 'Add at least one search criterion.';

            return;
        }

        $this->favorite = 'all';
        $this->statusFilter = '';
        $this->selectedId = null;
        $this->showItemQuery = false;
        $this->queryStatus = '';
        $this->resetPage();
    }

    public function saveItemQuery(): void
    {
        $name = trim($this->querySaveName);
        if ($name === '') {
            $this->queryStatus = 'Enter a name to save this search.';

            return;
        }
        if (isset($this->builtInItemQueries()[$name])) {
            $this->queryStatus = '"'.$name.'" is a built-in search. Choose a different name.';

            return;
        }
        if ($this->queryCriteria === []) {
            $this->queryStatus = 'Add criteria before saving.';

            return;
        }

        $saved = $this->userSavedItemQueries();
        $saved[$name] = $this->queryCriteria;
        $this->storeSavedItemQueries($saved);
        $this->queryLoadedName = $name;
        $this->queryStatus = 'Search "'.$name.'" saved.';
        $this->querySaveName = '';
    }

    public function loadItemQuery(string $name): void
    {
        $saved = $this->loadSavedItemQueries();
        if (! isset($saved[$name]) || ! is_array($saved[$name])) {
            $this->queryStatus = 'Saved search not found.';

            return;
        }
        $this->queryCriteria = array_values($saved[$name]);
        $this->queryLoadedName = $name;
        $this->querySelectedIndex = null;
        $this->queryStatus = 'Loaded "'.$name.'". Click Search to run.';
    }

    public function updatedQuerySavedPick(string $name): void
    {
        if ($name === '') {
            return;
        }
        $this->loadItemQuery($name);
        $this->querySavedPick = '';
    }

    public function deleteSavedItemQuery(): void
    {
        $name = trim($this->queryLoadedName !== '' ? $this->queryLoadedName : $this->querySaveName);
        if ($name === '') {
            $this->queryStatus = 'Select or enter a saved search name to delete.';

            return;
        }
        if (isset($this->builtInItemQueries()[$name])) {
            $this->queryStatus = '"'.$name.'" is a built-in search and cannot be deleted.';

            return;
        }
        $saved = $this->userSavedItemQueries();
        if (! isset($saved[$name])) {
            $this->queryStatus = 'Saved search not found.';

            return;
        }
        unset($saved[$name]);
        $this->storeSavedItemQueries($saved);
        if ($this->queryLoadedName === $name) {
            $this->queryLoadedName = '';
        }
        $this->queryStatus = 'Deleted saved search "'.$name.'".';
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function applyColumnPicker($keys = null): void
    {
        $keys = $this->sanitizeColumnKeys(is_array($keys) ? $keys : []);
        if ($keys === []) {
            $keys = ['item_code'];
        }
        $this->visibleColumns = $keys;
        $this->storeVisibleColumns($keys);
    }

    /**
     * @return array<string, array{label: string, type: string}>
     */
    protected function itemListColumnCatalog(): array
    {
        return [
            'id' => ['label' => 'ItemID', 'type' => 'text'],
            'item_code' => ['label' => 'Item Code', 'type' => 'code'],
            'is_new' => ['label' => 'New', 'type' => 'new'],
            'description' => ['label' => 'Item Description', 'type' => 'text'],
            'extended_description' => ['label' => 'Extended Description', 'type' => 'text'],
            'item_line_message' => ['label' => 'Item Message', 'type' => 'text'],
            'item_type' => ['label' => 'Item Type', 'type' => 'text'],
            'class' => ['label' => 'Item Class', 'type' => 'text'],
            'department' => ['label' => 'Department', 'type' => 'text'],
            'category' => ['label' => 'Category', 'type' => 'text'],
            'subcategory' => ['label' => 'Subcategory', 'type' => 'text'],
            'unit_of_measure' => ['label' => 'Unit of Measure', 'type' => 'text'],
            'list_price' => ['label' => 'List Price', 'type' => 'money'],
            'msrp' => ['label' => 'MSR Price', 'type' => 'money'],
            'standard_cost' => ['label' => 'Standard Cost', 'type' => 'money'],
            'current_cost' => ['label' => 'Current Cost', 'type' => 'money'],
            'last_cost' => ['label' => 'Last Cost', 'type' => 'money'],
            'average_cost' => ['label' => 'Average Cost', 'type' => 'money'],
            'quantity_in_stock' => ['label' => 'Quantity In Stock', 'type' => 'qty'],
            'available_quantity' => ['label' => 'Available Quantity', 'type' => 'qty'],
            'allocated_qty' => ['label' => 'Allocated', 'type' => 'qty'],
            'on_order_qty' => ['label' => 'On Order', 'type' => 'qty'],
            'allow_back_order' => ['label' => 'Can Back Order', 'type' => 'bool'],
            'can_sell' => ['label' => 'Can Sell', 'type' => 'can_sell'],
            'is_inactive' => ['label' => 'Inactive', 'type' => 'inactive'],
            'primary_upc' => ['label' => 'UPC', 'type' => 'text'],
            'manufacturer' => ['label' => 'Manufacturer', 'type' => 'text'],
            'msa_reporting' => ['label' => 'MSA Reporting', 'type' => 'bool'],
            'state_reporting' => ['label' => 'State Reporting', 'type' => 'bool'],
            'last_received_at' => ['label' => 'Last Received', 'type' => 'date'],
            'last_sold_at' => ['label' => 'Last Sold', 'type' => 'date'],
            'last_count_date' => ['label' => 'Last Count Date', 'type' => 'date'],
        ];
    }

    protected function deskSortMap(): array
    {
        $map = [];
        foreach (array_keys($this->itemListColumnCatalog()) as $key) {
            $map[$key] = match ($key) {
                'is_new' => 'created_at',
                'department' => ['relation' => 'department', 'column' => 'name'],
                'category' => ['relation' => 'category', 'column' => 'name'],
                'subcategory' => ['relation' => 'subcategory', 'column' => 'name'],
                default => $key,
            };
        }

        return $map;
    }

    /** @return list<string> */
    protected function defaultVisibleColumns(): array
    {
        return [
            'item_code',
            'is_new',
            'description',
            'department',
            'unit_of_measure',
            'list_price',
            'standard_cost',
            'quantity_in_stock',
            'available_quantity',
            'can_sell',
            'is_inactive',
        ];
    }

    /** @return list<string> */
    protected function normalizedVisibleColumns(): array
    {
        $keys = $this->sanitizeColumnKeys($this->visibleColumns);

        return $keys !== [] ? $keys : $this->defaultVisibleColumns();
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function sanitizeColumnKeys(array $keys): array
    {
        $catalog = $this->itemListColumnCatalog();
        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && isset($catalog[$key]) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** @return list<string> */
    protected function loadVisibleColumns(): array
    {
        $saved = Session::get($this->visibleColumnsSessionKey(), []);

        return is_array($saved) && $saved !== []
            ? $this->sanitizeColumnKeys($saved)
            : $this->defaultVisibleColumns();
    }

    /** @param  list<string>  $keys */
    protected function storeVisibleColumns(array $keys): void
    {
        Session::put($this->visibleColumnsSessionKey(), $keys);
    }

    protected function visibleColumnsSessionKey(): string
    {
        return 'items_list_columns_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function printList(): void
    {
        $url = route('inventory.items.print', array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'favorite' => $this->favorite !== 'all' ? $this->favorite : null,
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'title' => $this->listTitleForPrint(),
        ]));

        $this->dispatch('open-items-print', url: $url);
    }

    protected function listTitleForPrint(): string
    {
        if ($this->queryCriteria !== []) {
            return $this->queryLoadedName !== '' ? $this->queryLoadedName : 'Query Results';
        }
        if ($this->favorite === 'new') {
            return 'New Items (last '.Item::NEW_ITEM_DAYS.' days)';
        }
        if ($this->favorite === 'active' || $this->statusFilter === 'active') {
            return 'Active Items';
        }
        if ($this->favorite === 'inactive' || $this->statusFilter === 'inactive') {
            return 'Inactive Items';
        }
        if ($this->favorite === 'low_stock') {
            return 'Low Stock Items';
        }

        return 'Items List';
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an item first.');

            return null;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return null;
        }

        return $this->redirect(route('inventory.items.edit', $item), navigate: true);
    }

    public function openStockAdjust(?int $itemId = null): void
    {
        $this->openStockModal('adjust', $itemId);
    }

    public function openStockTrack(?int $itemId = null): void
    {
        // Explicit item id (e.g. from all-track table link)
        if ($itemId !== null && $itemId > 0) {
            $this->openStockModal('track', $itemId);

            return;
        }

        // Rail: selected row → one item; otherwise → all items
        if ($this->selectedId) {
            $this->openStockModal('track', (int) $this->selectedId);

            return;
        }

        $this->openAllStockTrack();
    }

    /** Company-wide inventory journal (no single item filter). */
    public function openAllStockTrack(): void
    {
        $this->adjustItemId = null;
        $this->stockModalMode = 'track';
        $this->adjustMode = 'change';
        $this->adjustQty = '';
        $this->adjustNotes = '';
        $this->adjustSiteId = '';
        $this->adjustMessage = '';
        $this->adjustError = '';
        $this->showStockAdjust = true;
    }

    protected function openStockModal(string $mode, ?int $itemId = null): void
    {
        if ($mode === 'adjust' && ! auth()->user()?->canAccessFeature('inventory.items', 'edit')) {
            session()->flash('status', 'Your role cannot adjust stock.');

            return;
        }

        $id = $itemId ?: $this->selectedId;
        if (! $id) {
            session()->flash('status', 'Select an item first.');

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return;
        }

        $this->selectedId = (int) $item->id;
        $this->adjustItemId = (int) $item->id;
        $this->stockModalMode = $mode === 'track' ? 'track' : 'adjust';
        $this->adjustMode = 'change';
        $this->adjustQty = '';
        $this->adjustNotes = '';
        $this->adjustSiteId = '';
        $this->adjustMessage = '';
        $this->adjustError = '';
        $this->showStockAdjust = true;
    }

    public function closeStockAdjust(): void
    {
        $this->showStockAdjust = false;
        $this->adjustItemId = null;
        $this->stockModalMode = 'adjust';
        $this->adjustQty = '';
        $this->adjustNotes = '';
        $this->adjustSiteId = '';
        $this->adjustMessage = '';
        $this->adjustError = '';
    }

    public function saveStockAdjust(): void
    {
        if (! auth()->user()?->canAccessFeature('inventory.items', 'edit')) {
            $this->adjustError = 'Your role cannot adjust stock.';

            return;
        }

        if (! $this->adjustItemId) {
            $this->adjustError = 'No item selected.';

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->adjustItemId);

        if (! $item) {
            $this->adjustError = 'Item not found.';

            return;
        }

        $this->adjustError = '';
        $this->adjustMessage = '';

        $qty = is_numeric($this->adjustQty) ? (float) $this->adjustQty : null;
        if ($qty === null) {
            $this->adjustError = 'Enter a valid quantity.';

            return;
        }

        if ($this->adjustMode === 'set' && $qty < 0) {
            $this->adjustError = 'New on-hand quantity cannot be negative.';

            return;
        }

        $siteId = $this->adjustSiteId !== '' ? (int) $this->adjustSiteId : null;
        if ($siteId) {
            $ok = Site::query()
                ->where('company_id', auth()->user()->company_id)
                ->whereKey($siteId)
                ->exists();
            if (! $ok) {
                $this->adjustError = 'Invalid site.';

                return;
            }
        }

        try {
            app(InventoryService::class)->applyManualAdjustment(
                $item,
                $this->adjustMode === 'set' ? 'set' : 'change',
                $qty,
                $this->adjustNotes,
                $siteId,
            );
        } catch (ValidationException $e) {
            $this->adjustError = collect($e->errors())->flatten()->first() ?: 'Adjustment failed.';

            return;
        }

        $item->refresh();
        $this->adjustQty = '';
        $this->adjustNotes = '';
        $this->adjustMessage = 'Stock updated to '.number_format((float) $item->quantity_in_stock, 2).'. Saved in inventory journal.';
    }

    public function openItem(int $id): mixed
    {
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirect(route('inventory.items.edit', $item), navigate: true);
    }

    public function deleteSelected(): void
    {
        if (! auth()->user()?->canAccessFeature('inventory.items', 'delete')) {
            session()->flash('status', 'Your role cannot delete items.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', 'Select an item first.');

            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $item) {
            session()->flash('status', 'Item not found.');

            return;
        }

        if (
            SalesOrderLine::query()->where('item_id', $item->id)->exists()
            || PurchaseOrderLine::query()->where('item_id', $item->id)->exists()
        ) {
            session()->flash('status', 'Item is used on orders and cannot be deleted. Mark it Inactive instead.');

            return;
        }

        $item->delete();
        $this->selectedId = null;
        session()->flash('status', 'Item deleted.');
    }

    public function toggleInactive(int $id): void
    {
        $item = Item::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $item->update(['is_inactive' => ! $item->is_inactive]);
        $this->selectedId = $id;
    }

    public function toggleCanSell(int $id): void
    {
        $item = Item::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $item->update(['can_sell' => ! $item->can_sell]);
        $this->selectedId = $id;
    }

    /**
     * @return array{field:string,operator:string,value:string,value_mode:string,compare_field:string,join:string,label:string}|null
     */
    protected function buildCriterionFromDraft(bool $reportErrors = true): ?array
    {
        $field = $this->queryField;
        $fields = $this->queryFieldOptions();
        if (! isset($fields[$field])) {
            if ($reportErrors) {
                $this->queryStatus = 'Choose a valid field.';
            }

            return null;
        }

        $operator = $this->queryOperator;
        $mode = $this->queryValueMode === 'field' ? 'field' : 'value';
        $compareField = $this->queryCompareField;
        $value = trim((string) $this->queryValue);
        $join = strtolower($this->queryJoin) === 'or' ? 'or' : 'and';

        if ($mode === 'field') {
            if (! isset($fields[$compareField]) || $compareField === $field) {
                if ($reportErrors) {
                    $this->queryStatus = 'Pick a different field to compare.';
                }

                return null;
            }
            if (in_array($operator, ['contains', 'starts', 'empty', 'not_empty'], true)) {
                if ($reportErrors) {
                    $this->queryStatus = 'That operator cannot compare two fields.';
                }

                return null;
            }
        } elseif (! in_array($operator, ['empty', 'not_empty'], true) && $value === '') {
            if ($reportErrors) {
                $this->queryStatus = 'Enter a value for this criterion.';
            }

            return null;
        }

        $label = $this->formatCriterionLabel($field, $operator, $value, $mode, $compareField);

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'value_mode' => $mode,
            'compare_field' => $compareField,
            'join' => $join,
            'label' => $label,
        ];
    }

    protected function formatCriterionLabel(string $field, string $operator, string $value, string $mode, string $compareField): string
    {
        $fields = $this->queryFieldOptions();
        $ops = $this->queryOperatorOptions();
        $fieldLabel = $fields[$field] ?? $field;
        $opLabel = $ops[$operator] ?? $operator;

        if ($mode === 'field') {
            $right = $fields[$compareField] ?? $compareField;
        } elseif (in_array($operator, ['empty', 'not_empty'], true)) {
            $right = '';
        } elseif ($field === 'category_id') {
            $cat = Category::query()->find((int) $value);
            $right = $cat ? trim(($cat->code ? $cat->code.' — ' : '').$cat->name) : $value;
        } elseif ($field === 'department_id') {
            $dept = Department::query()->find((int) $value);
            $right = $dept ? ($dept->name ?: $value) : $value;
        } elseif ($field === 'subcategory_id') {
            $sub = Subcategory::query()->find((int) $value);
            $right = $sub ? trim(($sub->code ? $sub->code.' — ' : '').$sub->name) : $value;
        } elseif (in_array($field, ['can_sell', 'is_inactive'], true)) {
            $right = in_array(strtolower($value), ['1', 'yes', 'true', 'y'], true) ? 'Yes' : 'No';
        } else {
            $right = $value;
        }

        return '( '.$fieldLabel.' | '.$opLabel.($right !== '' ? ' | '.$right : '').' )';
    }

    /** @return array<string, string> */
    protected function queryFieldOptions(): array
    {
        return [
            'quantity_in_stock' => 'Quantity In Stock',
            'available_qty' => 'Available Qty',
            'on_order_qty' => 'On Order Qty',
            'allocated_qty' => 'Allocated Qty',
            'reorder_point' => 'Reorder Point',
            'restock_level' => 'Restock Level',
            'list_price' => 'List Price',
            'standard_cost' => 'Standard Cost',
            'current_cost' => 'Current Cost',
            'item_code' => 'Item Code',
            'description' => 'Description',
            'manufacturer' => 'Manufacturer',
            'primary_upc' => 'Primary UPC',
            'unit_of_measure' => 'Unit of Measure',
            'department_id' => 'Department',
            'category_id' => 'Category',
            'subcategory_id' => 'Subcategory',
            'can_sell' => 'Can Sell',
            'is_inactive' => 'Inactive',
        ];
    }

    /** @return array<string, string> */
    protected function queryOperatorOptions(): array
    {
        return [
            'eq' => 'Equals',
            'ne' => 'Not equal',
            'lt' => 'Less than',
            'lte' => 'Less than or equal',
            'gt' => 'Greater than',
            'gte' => 'Greater than or equal',
            'contains' => 'Contains',
            'starts' => 'Starts with',
            'empty' => 'Is empty',
            'not_empty' => 'Is not empty',
        ];
    }

    protected function applyQueryCriteria($query)
    {
        $rows = $this->queryCriteria;
        if ($rows === []) {
            return $query;
        }

        $query->where(function ($outer) use ($rows) {
            foreach ($rows as $i => $row) {
                $join = strtolower((string) ($row['join'] ?? 'and')) === 'or' ? 'or' : 'and';
                $callback = function ($q) use ($row) {
                    $this->applySingleCriterion($q, $row);
                };

                if ($i === 0) {
                    $outer->where($callback);
                } elseif ($join === 'or') {
                    $outer->orWhere($callback);
                } else {
                    $outer->where($callback);
                }
            }
        });

        return $query;
    }

    protected function applySingleCriterion($q, array $row): void
    {
        $field = (string) ($row['field'] ?? '');
        $operator = (string) ($row['operator'] ?? 'eq');
        $mode = (string) ($row['value_mode'] ?? 'value');
        $value = (string) ($row['value'] ?? '');
        $compareField = (string) ($row['compare_field'] ?? '');

        $column = $this->resolveQueryColumn($field);
        if ($column === null && $field !== 'available_qty') {
            return;
        }

        if ($mode === 'field') {
            $rightCol = $this->resolveQueryColumn($compareField);
            if ($rightCol === null) {
                return;
            }
            if ($field === 'available_qty') {
                $left = '(quantity_in_stock - allocated_qty)';
            } else {
                $left = $column;
            }
            if ($compareField === 'available_qty') {
                $right = '(quantity_in_stock - allocated_qty)';
            } else {
                $right = $rightCol;
            }
            $sqlOp = match ($operator) {
                'ne' => '<>',
                'lt' => '<',
                'lte' => '<=',
                'gt' => '>',
                'gte' => '>=',
                default => '=',
            };
            $q->whereRaw("{$left} {$sqlOp} {$right}");

            return;
        }

        if ($operator === 'empty') {
            if ($field === 'available_qty') {
                $q->whereRaw('(quantity_in_stock - allocated_qty) = 0');
            } elseif (in_array($field, ['category_id', 'department_id', 'subcategory_id'], true)) {
                $q->where(function ($inner) use ($column) {
                    $inner->whereNull($column)->orWhere($column, 0);
                });
            } else {
                $q->where(function ($inner) use ($column) {
                    $inner->whereNull($column)->orWhere($column, '');
                });
            }

            return;
        }

        if ($operator === 'not_empty') {
            if ($field === 'available_qty') {
                $q->whereRaw('(quantity_in_stock - allocated_qty) <> 0');
            } elseif (in_array($field, ['category_id', 'department_id', 'subcategory_id'], true)) {
                $q->whereNotNull($column)->where($column, '>', 0);
            } else {
                $q->whereNotNull($column)->where($column, '<>', '');
            }

            return;
        }

        if ($field === 'available_qty') {
            $num = (float) $value;
            $sqlOp = match ($operator) {
                'ne' => '<>',
                'lt' => '<',
                'lte' => '<=',
                'gt' => '>',
                'gte' => '>=',
                default => '=',
            };
            $q->whereRaw("(quantity_in_stock - allocated_qty) {$sqlOp} ?", [$num]);

            return;
        }

        if (in_array($field, ['can_sell', 'is_inactive'], true)) {
            $bool = in_array(strtolower($value), ['1', 'yes', 'true', 'y'], true);
            if ($operator === 'ne') {
                $q->where($column, '!=', $bool);
            } else {
                $q->where($column, $bool);
            }

            return;
        }

        if (in_array($field, ['category_id', 'department_id', 'subcategory_id'], true)) {
            $id = (int) $value;
            if ($operator === 'ne') {
                $q->where(function ($inner) use ($column, $id) {
                    $inner->where($column, '!=', $id)->orWhereNull($column);
                });
            } else {
                $q->where($column, $id);
            }

            return;
        }

        $numericFields = [
            'quantity_in_stock', 'on_order_qty', 'allocated_qty', 'reorder_point', 'restock_level',
            'list_price', 'standard_cost', 'current_cost',
        ];
        if (in_array($field, $numericFields, true)) {
            $num = (float) $value;
            match ($operator) {
                'ne' => $q->where($column, '!=', $num),
                'lt' => $q->where($column, '<', $num),
                'lte' => $q->where($column, '<=', $num),
                'gt' => $q->where($column, '>', $num),
                'gte' => $q->where($column, '>=', $num),
                default => $q->where($column, $num),
            };

            return;
        }

        // Text fields — contains is case-insensitive partial match (not whole-string exact).
        match ($operator) {
            'ne' => $q->whereRaw("LOWER({$column}) <> LOWER(?)", [$value]),
            'contains' => ItemSearch::constrainColumn($q, $column, $value),
            'starts' => $q->whereRaw("LOWER({$column}) LIKE LOWER(?)", [ItemSearch::escapeLike($value).'%']),
            'lt' => $q->where($column, '<', $value),
            'lte' => $q->where($column, '<=', $value),
            'gt' => $q->where($column, '>', $value),
            'gte' => $q->where($column, '>=', $value),
            default => $q->whereRaw("LOWER({$column}) = LOWER(?)", [$value]),
        };
    }

    protected function resolveQueryColumn(string $field): ?string
    {
        return match ($field) {
            'quantity_in_stock' => 'quantity_in_stock',
            'on_order_qty' => 'on_order_qty',
            'allocated_qty' => 'allocated_qty',
            'reorder_point' => 'reorder_point',
            'restock_level' => 'restock_level',
            'list_price' => 'list_price',
            'standard_cost' => 'standard_cost',
            'current_cost' => 'current_cost',
            'item_code' => 'item_code',
            'description' => 'description',
            'manufacturer' => 'manufacturer',
            'primary_upc' => 'primary_upc',
            'unit_of_measure' => 'unit_of_measure',
            'department_id' => 'department_id',
            'category_id' => 'category_id',
            'subcategory_id' => 'subcategory_id',
            'can_sell' => 'can_sell',
            'is_inactive' => 'is_inactive',
            'available_qty' => null,
            default => null,
        };
    }

    /** Always listed under Load saved search. */
    protected function builtInItemQueries(): array
    {
        return [
            'Quantity less than zero' => [
                [
                    'field' => 'quantity_in_stock',
                    'operator' => 'lt',
                    'value' => '0',
                    'value_mode' => 'value',
                    'compare_field' => 'reorder_point',
                    'join' => 'and',
                    'label' => '( Quantity In Stock | Less than | 0 )',
                ],
            ],
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    protected function loadSavedItemQueries(): array
    {
        return array_merge($this->builtInItemQueries(), $this->userSavedItemQueries());
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    protected function userSavedItemQueries(): array
    {
        $key = $this->savedItemQueriesSessionKey();
        $data = Session::get($key, []);

        return is_array($data) ? $data : [];
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $data */
    protected function storeSavedItemQueries(array $data): void
    {
        Session::put($this->savedItemQueriesSessionKey(), $data);
    }

    protected function savedItemQueriesSessionKey(): string
    {
        return 'items_query_saved_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    public function exportItemsToExcel(): mixed
    {
        $catalog = $this->itemListColumnCatalog();
        $keys = $this->normalizedVisibleColumns();
        $headers = array_map(fn (string $key) => $catalog[$key]['label'], $keys);
        $query = $this->applyDeskSort(
            $this->filteredItemsQuery(),
            $this->favorite === 'new' ? 'created_at' : 'id',
            'desc'
        );

        if (! $query->exists()) {
            $this->scanStatus = 'No items to export.';

            return null;
        }

        $self = $this;

        return ExcelCsv::download('items.csv', $headers, (static function () use ($query, $keys, $catalog, $self) {
            foreach ($query->cursor() as $item) {
                yield array_map(fn (string $key) => $self->excelCellForItem($item, $key, $catalog[$key]), $keys);
            }
        })());
    }

    protected function filteredItemsQuery()
    {
        $companyId = auth()->user()->company_id;

        return Item::query()
            ->with([
                'department:id,code,name',
                'category:id,code,name',
                'subcategory:id,code,name',
            ])
            ->where('company_id', $companyId)
            ->when($this->search !== '', fn ($q) => $q->looseSearch($this->search))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when($this->favorite === 'new', fn ($q) => $q->newItems())
            ->when($this->favorite === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->favorite === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->favorite === 'low_stock', fn ($q) => $q->lowStock())
            ->when(str_starts_with($this->favorite, 'dept:'), fn ($q) => $q->where('department_id', (int) substr($this->favorite, 5)))
            ->when(str_starts_with($this->favorite, 'cat:'), fn ($q) => $q->where('category_id', (int) substr($this->favorite, 4)))
            ->when(str_starts_with($this->favorite, 'sub:'), fn ($q) => $q->where('subcategory_id', (int) substr($this->favorite, 4)))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($this->queryCriteria !== [], fn ($q) => $this->applyQueryCriteria($q));
    }

    /**
     * @param  array{label:string,type:string}  $col
     */
    protected function excelCellForItem(Item $item, string $colKey, array $col): string
    {
        return match ($colKey) {
            'is_new' => $item->isNew() ? 'New' : '',
            'can_sell' => $item->can_sell ? 'Yes' : 'No',
            'is_inactive' => $item->is_inactive ? 'Inactive' : 'Active',
            'allow_back_order', 'msa_reporting', 'state_reporting' => $item->{$colKey} ? 'Yes' : 'No',
            'department' => (string) ($item->department?->name ?? ''),
            'category' => (string) ($item->category?->name ?? ''),
            'subcategory' => (string) ($item->subcategory?->name ?? ''),
            default => match ($col['type']) {
                'money' => $this->excelNumber((float) $item->{$colKey}, 2),
                'qty' => $this->excelNumber((float) ($colKey === 'available_quantity' ? $item->available_quantity : $item->{$colKey}), 2),
                'date' => optional($item->{$colKey})?->format('n/j/Y') ?: '',
                default => filled($item->{$colKey} ?? null) ? (string) $item->{$colKey} : '',
            },
        };
    }

    protected function excelNumber(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    public function createNewItem(): mixed
    {
        return $this->redirect(route('inventory.items.create'), navigate: true);
    }

    public function openUpdatePrices(): mixed
    {
        return $this->redirect(route('inventory.bulk-pricing'), navigate: true);
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :nodes="$nodes" :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action">
            <x-slot:menu>
                <x-action-item label="Add New Item" kbd="Ctrl+N" wire:click="createNewItem" />
                <x-action-item label="View/Edit Selected Item" kbd="Ctrl+E" sep wire:click="editSelected" />
                <x-action-item label="Update Prices" kbd="Ctrl+U" sep wire:click="openUpdatePrices" />
                <x-action-item label="Group Update" sep wire:click="openUpdatePrices" />
                <x-action-item label="Export to Excel" sep wire:click="exportItemsToExcel" />
                <x-action-item label="Delete Selected Item" sep wire:click="deleteSelected" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar items-toolbar">
                    <div class="items-toolbar-left">
                        <div class="items-sku-bar" role="search">
                            <button
                                type="button"
                                class="items-sku-scan"
                                wire:click="focusScanAndFind"
                                title="Scan barcode — focus field; Enter finds the item on this list"
                            >
                                <svg class="items-sku-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <span class="items-sku-search-label" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="7" cy="7" r="4.2"/>
                                    <path d="M10.2 10.2L14 14"/>
                                </svg>
                                Search
                            </span>
                            <input
                                id="items-search" data-pos-search
                                wire:ignore.self
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                wire:keydown.enter.prevent="scanFindItem($event.target.value)"
                                placeholder="Code, UPC, or words in the description"
                                class="items-sku-input"
                                aria-label="Search items by code, UPC, or description (any case, any word order)"
                                autocomplete="off"
                            />
                            @if ($search !== '')
                                <button type="button" wire:click="clearSearch" class="items-sku-clear" title="Clear" aria-label="Clear">×</button>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="openItemQuery"
                            class="desk-btn items-query-btn"
                            title="Advanced search / query"
                        >
                            Query
                        </button>
                    </div>

                    <div class="items-toolbar-mid">
                        <label class="desk-toolbar-label" for="items-category-filter">Category</label>
                        <select
                            id="items-category-filter"
                            wire:model.live="categoryFilter"
                            class="desk-select items-category-select"
                            aria-label="Filter by category"
                        >
                            <option value="">All categories</option>
                            @foreach ($categoryOptions as $cat)
                                <option value="{{ $cat->id }}">
                                    {{ $cat->code ? $cat->code.' — ' : '' }}{{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="orders-toolbar-right">
                        @if (count($queryCriteria) > 0)
                            <button type="button" wire:click="clearQueryCriteria" class="desk-btn desk-btn-sm" title="Clear query criteria">Clear Query</button>
                        @endif
                        <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                            Clear all
                        </button>
                        <select
                            id="items-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Active filter"
                            title="Active / Inactive"
                        >
                            <option value="">All status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button
                            type="button"
                            wire:click="$set('favorite', '{{ $favorite === 'new' ? 'all' : 'new' }}')"
                            @class(['desk-btn desk-btn-sm', 'is-on' => $favorite === 'new'])
                            title="Items created in the last {{ \App\Models\Item::NEW_ITEM_DAYS }} days"
                            aria-pressed="{{ $favorite === 'new' ? 'true' : 'false' }}"
                        >
                            New ({{ \App\Models\Item::NEW_ITEM_DAYS }}d)
                        </button>
                        <button type="button" wire:click="exportItemsToExcel" class="desk-btn" title="Move this list to Excel">
                            Excel
                        </button>
                        <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-btn desk-btn-primary" title="Add new item">+ Item</a>
                    </div>
                </div>
                @if ($scanStatus !== '')
                    <div class="items-scan-status" role="status">{{ $scanStatus }}</div>
                @endif

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($listShown) }}{{ $listHasMore ? '+' : '' }} records</span>
                </div>

                <x-desk-scroll-grid :has-more="$listHasMore" class="{{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table desk-table-fit desk-table-resizable" data-col-resize="items-list" data-excel-grid data-excel-copy-all>
                        <thead>
                            <tr>
                                <th class="text-center desk-sort-th" data-col="_select" data-excel-skip style="width:2.25rem">
                                    <span class="desk-col-resizer" title="Drag to make this column wider or narrower" aria-hidden="true"></span>
                                </th>
                                @foreach ($visibleColumnKeys as $colKey)
                                    @php $col = $itemColumnCatalog[$colKey]; @endphp
                                    <x-desk-sort-th
                                        :field="$colKey"
                                        :label="$col['label']"
                                        resize
                                        :align="in_array($col['type'], ['money', 'qty'], true) ? 'money' : (in_array($col['type'], ['new', 'bool', 'can_sell', 'inactive'], true) ? 'center' : 'left')"
                                    />
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr
                                    wire:click="selectRow({{ $item->id }})"
                                    wire:dblclick="openItem({{ $item->id }})"
                                    @class(['is-selected' => $selectedId === $item->id, 'cursor-pointer'])
                                >
                                    <td class="text-center" data-excel-skip wire:click.stop>
                                        <input
                                            type="radio"
                                            name="item_select"
                                            value="{{ $item->id }}"
                                            @checked($selectedId === $item->id)
                                            wire:click="selectRow({{ $item->id }})"
                                            aria-label="Select item {{ $item->item_code }}"
                                        />
                                    </td>
                                    @foreach ($visibleColumnKeys as $colKey)
                                        @php $col = $itemColumnCatalog[$colKey]; @endphp
                                        @if ($colKey === 'item_code')
                                            <td class="desk-num" data-excel-value="{{ $item->item_code }}">
                                                <a href="{{ route('inventory.items.show', $item) }}" wire:navigate wire:click.stop>{{ $item->item_code }}</a>
                                            </td>
                                        @elseif ($colKey === 'is_new')
                                            <td class="text-center">
                                                @if ($item->isNew())
                                                    <span class="desk-pill desk-pill-new" title="Created within last {{ \App\Models\Item::NEW_ITEM_DAYS }} days">New</span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                        @elseif ($colKey === 'can_sell')
                                            <td class="text-center" wire:click.stop>
                                                <button
                                                    type="button"
                                                    wire:click="toggleCanSell({{ $item->id }})"
                                                    @class([
                                                        'desk-pill',
                                                        'desk-pill-invoiced' => $item->can_sell,
                                                        'desk-pill-muted' => ! $item->can_sell,
                                                    ])
                                                    title="{{ $item->can_sell ? 'Can sell — click to disable' : 'Cannot sell — click to enable' }}"
                                                    aria-label="Toggle can sell"
                                                >{{ $item->can_sell ? 'Yes' : 'No' }}</button>
                                            </td>
                                        @elseif ($colKey === 'is_inactive')
                                            <td class="text-center" wire:click.stop>
                                                <button
                                                    type="button"
                                                    wire:click="toggleInactive({{ $item->id }})"
                                                    @class([
                                                        'desk-pill',
                                                        'desk-pill-muted' => $item->is_inactive,
                                                        'desk-pill-invoiced' => ! $item->is_inactive,
                                                    ])
                                                    title="{{ $item->is_inactive ? 'Inactive — click to activate' : 'Active — click to deactivate' }}"
                                                    aria-label="Toggle inactive"
                                                >{{ $item->is_inactive ? 'Inactive' : 'Active' }}</button>
                                            </td>
                                        @elseif ($colKey === 'allow_back_order')
                                            <td class="text-center">{{ $item->allow_back_order ? 'Yes' : 'No' }}</td>
                                        @elseif ($colKey === 'msa_reporting')
                                            <td class="text-center">{{ $item->msa_reporting ? 'Yes' : 'No' }}</td>
                                        @elseif ($colKey === 'state_reporting')
                                            <td class="text-center">{{ $item->state_reporting ? 'Yes' : 'No' }}</td>
                                        @elseif ($col['type'] === 'money')
                                            <td class="desk-money">${{ number_format((float) $item->{$colKey}, 2) }}</td>
                                        @elseif ($col['type'] === 'qty')
                                            <td class="desk-money">{{ number_format((float) ($colKey === 'available_quantity' ? $item->available_quantity : $item->{$colKey}), 2) }}</td>
                                        @elseif ($col['type'] === 'date')
                                            <td>{{ optional($item->{$colKey})?->format('n/j/Y') ?: '—' }}</td>
                                        @elseif ($colKey === 'department')
                                            <td>{{ $item->department?->name ?: '—' }}</td>
                                        @elseif ($colKey === 'category')
                                            <td>{{ $item->category?->name ?: '—' }}</td>
                                        @elseif ($colKey === 'subcategory')
                                            <td>{{ $item->subcategory?->name ?: '—' }}</td>
                                        @elseif ($colKey === 'description' || $colKey === 'extended_description' || $colKey === 'item_line_message')
                                            @php $text = (string) ($item->{$colKey} ?? ''); @endphp
                                            <td title="{{ $text }}">{{ $text !== '' ? \Illuminate\Support\Str::limit($text, 48) : '—' }}</td>
                                        @else
                                            <td>{{ filled($item->{$colKey} ?? null) ? $item->{$colKey} : '—' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="{{ $columnColspan }}">No items found. Use the <strong>+</strong> button to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-desk-scroll-grid>

                <x-record-count :count="$listShown">
                    <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Item</a>
                    <x-desk-load-more :has-more="$listHasMore" />
                </x-record-count>
            </div>

            {{-- Right icon rail — show/hide fields, compact, query, view, edit, … --}}
            <aside class="desk-rail" aria-label="Item actions">
                <button type="button" class="desk-rail-btn" title="Show/Hide Fields" aria-label="Show/Hide Fields" @click="$dispatch('open-item-fields')">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.35" aria-hidden="true">
                        <rect x="1.5" y="2.5" width="13" height="11" rx="1"/>
                        <path d="M1.5 6h13M6 2.5v11"/>
                        <rect x="9.2" y="8.6" width="5.2" height="5.2" rx="0.6" fill="#fff" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                </button>
                <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="openItemQuery" class="desk-rail-btn" title="Query items (stock &lt; 0, category, …)" aria-label="Query items">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <circle cx="7" cy="7" r="4.5"/>
                        <path d="M10.5 10.5L14 14"/>
                        <path d="M5.2 7h3.6M7 5.2v3.6" stroke-width="1.3"/>
                    </svg>
                </button>
                <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                        <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button type="button" wire:click="openItem({{ $selectedId ?: 0 }})" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="openStockAdjust"
                    class="desk-rail-btn"
                    title="Stock adjust selected item"
                    aria-label="Stock adjust"
                    @disabled(! $selectedId)
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M2.5 12.5h11"/>
                        <path d="M4 12.5V6.5l2.2-2 1.8 3.5 2-1.8 2 3.8"/>
                        <path d="M11.2 4.2l.8-.8.8.8M12 3.4V6"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="openStockTrack"
                    class="desk-rail-btn"
                    title="{{ $selectedId ? 'Track stock for selected item' : 'Track stock for all items' }}"
                    aria-label="Stock track"
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M3 2.5h7.5L13 5v8.5H3z"/>
                        <path d="M10.5 2.5V5H13"/>
                        <path d="M5 7.5h6M5 10h6M5 12.5h4"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected item? This cannot be undone."
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
                <button type="button" wire:click="printList" class="desk-rail-btn" title="Print all filtered items" aria-label="Print items list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.35" aria-hidden="true">
                        <path d="M4 6V2h8v4"/>
                        <rect x="2" y="6" width="12" height="6" rx="1"/>
                        <path d="M4 10h8v4H4v-4z"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
                <a href="{{ route('inventory.items.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Item" aria-label="New Item">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>

    <style>
        .shf-backdrop { z-index: 90 !important; }
        .shf-modal {
            max-width: 34rem;
            width: min(34rem, 96vw);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .shf-body {
            padding: .85rem .9rem .7rem;
            background: #fff;
        }
        .shf-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: .55rem .65rem;
            align-items: stretch;
        }
        .shf-col-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 .35rem;
        }
        .shf-list {
            min-height: 16rem;
            max-height: 20rem;
            overflow: auto;
            border: 1px solid #64748b;
            background: #fff;
            font-size: 13px;
            font-family: Tahoma, "Segoe UI", sans-serif;
        }
        .shf-list-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
            padding: .28rem .5rem;
            cursor: pointer;
            color: #0f172a;
        }
        .shf-list-item:hover { background: #e8f0fe; }
        .shf-list-item.is-selected {
            background: #316ac5;
            color: #fff;
        }
        .shf-list-empty {
            padding: .75rem .5rem;
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }
        .shf-arrows {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: .35rem;
            padding-top: 1.35rem;
        }
        .shf-arrow {
            width: 2rem;
            height: 1.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            background: linear-gradient(180deg, #fff, #e8eef7);
            color: #1e293b;
            cursor: pointer;
            padding: 0;
        }
        .shf-arrow:hover { background: #dbeafe; }
        .shf-arrow:disabled { opacity: .4; cursor: not-allowed; }
        .shf-foot {
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
            padding: .65rem .9rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
    </style>
    <div
        wire:ignore
        x-data="{
            pickerOpen: false,
            catalog: {{ Js::from(collect($itemColumnCatalog)->map(fn ($col) => $col['label'])) }},
            draft: [],
            availableSelected: null,
            visibleSelected: null,
            get availableKeys() {
                return Object.keys(this.catalog).filter((k) => !this.draft.includes(k));
            },
            openPicker() {
                const current = $wire.visibleColumns;
                this.draft = Array.isArray(current) && current.length ? [...current] : {{ Js::from($visibleColumnKeys) }};
                if (!this.draft.length) this.draft = ['item_code'];
                this.visibleSelected = this.draft[0] || null;
                this.availableSelected = this.availableKeys[0] || null;
                this.pickerOpen = true;
            },
            closePicker() { this.pickerOpen = false; },
            showSelected() {
                const key = this.availableSelected;
                if (!key || this.draft.includes(key) || !this.catalog[key]) return;
                this.draft = [...this.draft, key];
                this.visibleSelected = key;
                this.availableSelected = this.availableKeys[0] || null;
            },
            hideSelected() {
                const key = this.visibleSelected;
                if (!key) return;
                this.draft = this.draft.filter((k) => k !== key);
                if (!this.draft.length) this.draft = ['item_code'];
                this.availableSelected = (key === 'item_code' && this.draft.includes('item_code'))
                    ? (this.availableKeys[0] || null)
                    : (this.catalog[key] ? key : (this.availableKeys[0] || null));
                this.visibleSelected = this.draft[0] || null;
            },
            move(delta) {
                const key = this.visibleSelected;
                if (!key) return;
                const i = this.draft.indexOf(key);
                const j = i + delta;
                if (i < 0 || j < 0 || j >= this.draft.length) return;
                const next = [...this.draft];
                const tmp = next[j];
                next[j] = next[i];
                next[i] = tmp;
                this.draft = next;
            },
            apply() {
                this.pickerOpen = false;
                $wire.applyColumnPicker(this.draft);
            }
        }"
        @open-item-fields.window="openPicker()"
    >
        <div
            class="desk-modal-backdrop shf-backdrop"
            x-show="pickerOpen"
            x-cloak
            x-transition.opacity.duration.80ms
            @click.self="closePicker()"
            @keydown.escape.window="if (pickerOpen) closePicker()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="item-fields-title"
        >
            <div class="desk-modal shf-modal" @click.stop>
                <div class="desk-modal-head">
                    <span id="item-fields-title">Show/Hide Fields</span>
                    <button type="button" class="desk-modal-close" aria-label="Close" @click="closePicker()">×</button>
                </div>
                <div class="shf-body">
                    <div class="shf-grid">
                        <div>
                            <p class="shf-col-title">Available Fields</p>
                            <div class="shf-list" role="listbox" aria-label="Available Fields">
                                <template x-for="key in availableKeys" :key="key">
                                    <button
                                        type="button"
                                        role="option"
                                        class="shf-list-item"
                                        :class="{ 'is-selected': availableSelected === key }"
                                        :aria-selected="availableSelected === key ? 'true' : 'false'"
                                        @click="availableSelected = key"
                                        @dblclick.prevent="availableSelected = key; showSelected()"
                                        x-text="catalog[key]"
                                    ></button>
                                </template>
                                <div class="shf-list-empty" x-show="availableKeys.length === 0">All fields are shown.</div>
                            </div>
                        </div>
                        <div class="shf-arrows">
                            <button type="button" class="shf-arrow" title="Show field" aria-label="Show field" :disabled="!availableSelected" @click="showSelected()">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 3l6 5-6 5"/></svg>
                            </button>
                            <button type="button" class="shf-arrow" title="Hide field" aria-label="Hide field" :disabled="!visibleSelected" @click="hideSelected()">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M11 3L5 8l6 5"/></svg>
                            </button>
                            <button type="button" class="shf-arrow" title="Move up" aria-label="Move up" :disabled="!visibleSelected" @click="move(-1)">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 11l5-6 5 6"/></svg>
                            </button>
                            <button type="button" class="shf-arrow" title="Move down" aria-label="Move down" :disabled="!visibleSelected" @click="move(1)">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5l5 6 5-6"/></svg>
                            </button>
                        </div>
                        <div>
                            <p class="shf-col-title">Show these fields in this order</p>
                            <div class="shf-list" role="listbox" aria-label="Shown fields">
                                <template x-for="key in draft" :key="key">
                                    <button
                                        type="button"
                                        role="option"
                                        class="shf-list-item"
                                        :class="{ 'is-selected': visibleSelected === key }"
                                        :aria-selected="visibleSelected === key ? 'true' : 'false'"
                                        @click="visibleSelected = key"
                                        @dblclick.prevent="visibleSelected = key; hideSelected()"
                                        x-text="catalog[key]"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="shf-foot">
                    <button type="button" class="desk-btn desk-btn-primary" @click="apply()">OK</button>
                    <button type="button" class="desk-btn" @click="closePicker()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

@if ($showItemQuery)
    <style>
        .iq-backdrop { z-index: 90 !important; }
        .iq-modal {
            max-width: 36rem;
            width: min(36rem, 96vw);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .iq-body { padding: .75rem .85rem; background: #fff; }
        .iq-section-title {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: .45rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .iq-row {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .5rem;
            align-items: center;
            margin-bottom: .4rem;
        }
        .iq-select, .iq-input {
            height: 2rem !important;
            font-size: 13px !important;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 0 .45rem;
            background: #fff;
            color: #0f172a;
        }
        .iq-select-field { min-width: 11rem; flex: 1 1 10rem; }
        .iq-select-op { min-width: 9rem; flex: 0 1 9rem; }
        .iq-input-val { min-width: 7rem; flex: 1 1 7rem; }
        .iq-select-join { width: 5.25rem; }
        .iq-link {
            background: none;
            border: 0;
            color: #1d4ed8;
            font-size: 12px;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
        }
        .iq-link:hover { color: #1e40af; }
        .iq-list-wrap {
            display: flex;
            gap: .45rem;
            align-items: stretch;
            margin-top: .65rem;
        }
        .iq-list {
            flex: 1 1 auto;
            min-height: 9rem;
            max-height: 14rem;
            overflow: auto;
            border: 1px solid #94a3b8;
            background: #fff;
            font-size: 12px;
            font-family: Tahoma, "Segoe UI", sans-serif;
        }
        .iq-list-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
            padding: .35rem .5rem;
            cursor: pointer;
            color: #0f172a;
        }
        .iq-list-item:hover { background: #f1f5f9; }
        .iq-list-item.is-selected {
            background: #316ac5;
            color: #fff;
        }
        .iq-list-empty {
            padding: .75rem .5rem;
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }
        .iq-side-tools {
            display: flex;
            flex-direction: column;
            gap: .3rem;
            flex-shrink: 0;
        }
        .iq-tool {
            width: 1.85rem;
            height: 1.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            background: linear-gradient(180deg, #fff, #e8eef7);
            color: #1e293b;
            cursor: pointer;
            padding: 0;
        }
        .iq-tool:hover { background: #dbeafe; }
        .iq-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem .75rem;
            padding: .65rem .85rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .iq-foot-links { display: flex; flex-direction: column; gap: .25rem; flex: 1 1 auto; }
        .iq-foot-actions { margin-left: auto; display: flex; gap: .5rem; }
        .iq-status { font-size: 12px; color: #b45309; margin-top: .35rem; min-height: 1rem; }
        .iq-save-row { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
        .iq-save-row .iq-input { width: 10rem; }
        .iq-saved-select { min-width: 10rem; height: 2rem !important; font-size: 12px !important; }
    </style>
    <div class="desk-modal-backdrop iq-backdrop" wire:click.self="closeItemQuery" role="dialog" aria-modal="true" aria-labelledby="item-query-title">
        <div class="desk-modal iq-modal" wire:keydown.escape.window="closeItemQuery">
            <div class="desk-modal-head">
                <span id="item-query-title">Item Query</span>
                <button type="button" wire:click="closeItemQuery" class="desk-modal-close" aria-label="Close">×</button>
            </div>
            <div class="iq-body">
                <div class="iq-section-title">Select criteria</div>
                <div class="iq-row">
                    <select wire:model.live="queryField" class="iq-select iq-select-field" aria-label="Field">
                        @foreach ($queryFields as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="queryOperator" class="iq-select iq-select-op" aria-label="Operator">
                        @foreach ($queryOperators as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($queryValueMode === 'field')
                        <select wire:model="queryCompareField" class="iq-select iq-input-val" aria-label="Compare field">
                            @foreach ($queryFields as $key => $label)
                                @if ($key !== $queryField && ! in_array($key, ['can_sell', 'is_inactive', 'category_id', 'department_id', 'subcategory_id'], true))
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    @elseif (in_array($queryOperator, ['empty', 'not_empty'], true))
                        <span class="text-slate-400 text-xs" style="min-width:7rem">—</span>
                    @elseif ($queryField === 'category_id')
                        <select wire:model="queryValue" class="iq-select iq-input-val" aria-label="Category value">
                            <option value="">— Select category —</option>
                            @foreach ($queryCategories as $cat)
                                <option value="{{ $cat->id }}">{{ strtoupper(trim(($cat->code ? $cat->code.' — ' : '').$cat->name)) }}</option>
                            @endforeach
                        </select>
                    @elseif ($queryField === 'department_id')
                        <select wire:model="queryValue" class="iq-select iq-input-val" aria-label="Department value">
                            <option value="">— Select department —</option>
                            @foreach ($queryDepartments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    @elseif ($queryField === 'subcategory_id')
                        <select wire:model="queryValue" class="iq-select iq-input-val" aria-label="Subcategory value">
                            <option value="">— Select subcategory —</option>
                            @foreach ($querySubcategories as $sub)
                                <option value="{{ $sub->id }}">{{ strtoupper(trim(($sub->code ? $sub->code.' — ' : '').$sub->name)) }}</option>
                            @endforeach
                        </select>
                    @elseif (in_array($queryField, ['can_sell', 'is_inactive'], true))
                        <select wire:model="queryValue" class="iq-select iq-input-val" aria-label="Yes/No value">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    @else
                        <input type="text" wire:model="queryValue" class="iq-input iq-input-val" aria-label="Value" placeholder="0.00" />
                    @endif
                </div>
                <div class="iq-row">
                    <select wire:model="queryJoin" class="iq-select iq-select-join" aria-label="And/Or" title="Join for next criterion">
                        <option value="and">And</option>
                        <option value="or">Or</option>
                    </select>
                    @if ($queryValueMode === 'value')
                        <button type="button" class="iq-link" wire:click="setQueryValueMode('field')">Compare to a field</button>
                    @else
                        <button type="button" class="iq-link" wire:click="setQueryValueMode('value')">Compare to a value</button>
                    @endif
                </div>

                <div class="iq-list-wrap">
                    <div class="iq-list" role="listbox" aria-label="Search criteria">
                        @forelse ($queryCriteria as $i => $crit)
                            <button
                                type="button"
                                role="option"
                                wire:key="iq-crit-{{ $i }}"
                                class="iq-list-item{{ $querySelectedIndex === $i ? ' is-selected' : '' }}"
                                wire:click="selectQueryCriterion({{ $i }})"
                                aria-selected="{{ $querySelectedIndex === $i ? 'true' : 'false' }}"
                            >{{ $crit['label'] ?? '' }}</button>
                        @empty
                            <div class="iq-list-empty">No criteria yet. Choose field / operator / value, then click + to add.</div>
                        @endforelse
                    </div>
                    <div class="iq-side-tools" aria-label="Criteria tools">
                        <button type="button" class="iq-tool" title="Add criterion" aria-label="Add criterion" wire:click="addQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 3v10M3 8h10"/></svg>
                        </button>
                        <button type="button" class="iq-tool" title="Update selected criterion" aria-label="Update selected" wire:click="addQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/></svg>
                        </button>
                        <button type="button" class="iq-tool" title="Remove selected criterion" aria-label="Remove selected" wire:click="removeQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8h10"/></svg>
                        </button>
                    </div>
                </div>
                <div class="iq-status" role="status">{{ $queryStatus }}</div>
            </div>
            <div class="iq-foot">
                <div class="iq-foot-links">
                    <div class="iq-save-row">
                        <input type="text" wire:model="querySaveName" class="iq-input" placeholder="Search name…" aria-label="Saved search name" />
                        <button type="button" class="iq-link" wire:click="saveItemQuery">Save this search</button>
                        <button type="button" class="iq-link" wire:click="deleteSavedItemQuery">Delete this search</button>
                    </div>
                    @if (count($savedItemQueries) > 0)
                        <div class="iq-save-row">
                            <select class="iq-select iq-saved-select" wire:model.live="querySavedPick" aria-label="Load saved search">
                                <option value="">Load saved search…</option>
                                @foreach (array_keys($savedItemQueries) as $savedName)
                                    <option value="{{ $savedName }}" @selected($queryLoadedName === $savedName)>{{ $savedName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="iq-foot-actions">
                    <button type="button" class="desk-btn desk-btn-primary" wire:click="runItemQuery">Search</button>
                    <button type="button" class="desk-btn" wire:click="closeItemQuery">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($showStockAdjust && ($adjustItem || $stockModalMode === 'track'))
    <style>
        .isa-modal { max-width: 52rem; width: min(52rem, 96vw); }
        .isa-modal.is-wide { max-width: 62rem; width: min(62rem, 96vw); }
        .isa-grid {
            display: grid;
            grid-template-columns: minmax(0, 17rem) minmax(0, 1fr);
            gap: .85rem;
        }
        .isa-grid.is-track-only { grid-template-columns: 1fr; }
        @media (max-width: 720px) {
            .isa-grid { grid-template-columns: 1fr; }
        }
        .isa-panel {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: .7rem .75rem;
            background: #fff;
        }
        .isa-panel h4 {
            margin: 0 0 .55rem;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #334155;
        }
        .isa-meta {
            font-size: 13px;
            color: #0f172a;
            margin-bottom: .55rem;
            line-height: 1.35;
        }
        .isa-meta strong { font-weight: 700; }
        .isa-field { display: flex; flex-direction: column; gap: .2rem; margin-bottom: .5rem; }
        .isa-field > span { font-size: 11px; font-weight: 600; color: #475569; }
        .isa-field select, .isa-field input, .isa-field textarea {
            height: 2rem;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 0 .45rem;
            background: #fff;
            color: #0f172a;
        }
        .isa-field textarea { height: auto; min-height: 3.25rem; padding: .4rem .45rem; resize: vertical; }
        .isa-stock {
            font-size: 1.35rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
        }
        .isa-msg { font-size: 12px; color: #15803d; margin: .35rem 0 0; min-height: 1rem; }
        .isa-err { font-size: 12px; color: #b91c1c; margin: .35rem 0 0; min-height: 1rem; }
        .isa-table-wrap { max-height: 18rem; overflow: auto; border: 1px solid #e2e8f0; }
        .isa-table-wrap .desk-table { margin: 0; font-size: 12px; }
        .isa-table-wrap th { position: sticky; top: 0; background: #f8fafc; z-index: 1; }
        .isa-qty-pos { color: #15803d; font-weight: 600; }
        .isa-qty-neg { color: #b91c1c; font-weight: 600; }
        .isa-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .55rem; }
        .isa-item-link {
            background: none;
            border: 0;
            padding: 0;
            color: #1d4ed8;
            font: inherit;
            text-decoration: underline;
            cursor: pointer;
        }
        .isa-item-link:hover { color: #1e40af; }
    </style>
    <div class="desk-modal-backdrop" wire:click.self="closeStockAdjust" role="dialog" aria-modal="true" aria-labelledby="isa-title">
        <div class="desk-modal isa-modal{{ $trackAllItems ? ' is-wide' : '' }}" wire:keydown.escape.window="closeStockAdjust">
            <div class="desk-modal-head">
                <span id="isa-title">
                    @if ($stockModalMode === 'track' && $trackAllItems)
                        Stock Track — All items
                    @elseif ($stockModalMode === 'track' && $adjustItem)
                        Stock Track — {{ $adjustItem->item_code }}
                    @elseif ($adjustItem)
                        Stock Adjust — {{ $adjustItem->item_code }}
                    @else
                        Stock Track
                    @endif
                </span>
                <button type="button" wire:click="closeStockAdjust" class="desk-modal-close" aria-label="Close">×</button>
            </div>
            <div class="desk-modal-body">
                <div class="isa-grid{{ $stockModalMode === 'track' ? ' is-track-only' : '' }}">
                    @if ($stockModalMode === 'adjust' && $adjustItem)
                    <div class="isa-panel">
                        <h4>Adjust stock</h4>
                        <div class="isa-meta">
                            <div><strong>{{ $adjustItem->item_code }}</strong></div>
                            <div>{{ $adjustItem->description }}</div>
                            <div style="margin-top:.35rem">On hand: <span class="isa-stock">{{ number_format((float) $adjustItem->quantity_in_stock, 2) }}</span>
                                <span style="font-size:12px;color:#64748b">{{ $adjustItem->unit_of_measure ?: '' }}</span>
                            </div>
                            <div style="font-size:12px;color:#64748b;margin-top:.15rem">
                                Allocated {{ number_format((float) $adjustItem->allocated_qty, 2) }}
                                · Available {{ number_format((float) $adjustItem->quantity_in_stock - (float) $adjustItem->allocated_qty, 2) }}
                            </div>
                        </div>
                        <label class="isa-field">
                            <span>Mode</span>
                            <select wire:model.live="adjustMode">
                                <option value="change">Add / subtract qty</option>
                                <option value="set">Set new on-hand total</option>
                            </select>
                        </label>
                        <label class="isa-field">
                            <span>{{ $adjustMode === 'set' ? 'New on-hand qty' : 'Qty change (+ in / − out)' }}</span>
                            <input type="number" step="any" wire:model="adjustQty" class="desk-input" inputmode="decimal" placeholder="{{ $adjustMode === 'set' ? 'e.g. 100' : 'e.g. 5 or -3' }}" />
                        </label>
                        @if ($adjustSites->isNotEmpty())
                            <label class="isa-field">
                                <span>Site (optional)</span>
                                <select wire:model="adjustSiteId">
                                    <option value="">—</option>
                                    @foreach ($adjustSites as $site)
                                        <option value="{{ $site->id }}">{{ $site->code }}{{ $site->name ? ' — '.$site->name : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="isa-field">
                            <span>Notes / reason</span>
                            <textarea wire:model="adjustNotes" rows="2" placeholder="Damage, count fix, found stock…"></textarea>
                        </label>
                        <div class="isa-actions">
                            <button type="button" class="desk-btn desk-btn-primary" wire:click="saveStockAdjust" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveStockAdjust">Post adjustment</span>
                                <span wire:loading wire:target="saveStockAdjust">Posting…</span>
                            </button>
                            <button type="button" class="desk-btn" wire:click="closeStockAdjust">Close</button>
                        </div>
                        @if ($adjustError !== '')
                            <p class="isa-err" role="alert">{{ $adjustError }}</p>
                        @endif
                        @if ($adjustMessage !== '')
                            <p class="isa-msg" role="status">{{ $adjustMessage }}</p>
                        @endif
                    </div>
                    @endif
                    <div class="isa-panel">
                        <h4>
                            Inventory track (journal)
                            @if ($stockModalMode === 'track' && $adjustItem)
                                <span style="font-weight:600;text-transform:none;letter-spacing:0;color:#64748b;margin-left:.35rem">
                                    {{ $adjustItem->item_code }} · On hand {{ number_format((float) $adjustItem->quantity_in_stock, 2) }}
                                </span>
                            @elseif ($trackAllItems)
                                <span style="font-weight:600;text-transform:none;letter-spacing:0;color:#64748b;margin-left:.35rem">
                                    Latest movements (all items)
                                </span>
                            @endif
                        </h4>
                        @if ($stockModalMode === 'track' && $adjustItem)
                            <div class="isa-meta" style="margin-bottom:.45rem">
                                <div>{{ $adjustItem->description }}</div>
                                <div style="font-size:12px;color:#64748b;margin-top:.15rem">
                                    Allocated {{ number_format((float) $adjustItem->allocated_qty, 2) }}
                                    · Available {{ number_format((float) $adjustItem->quantity_in_stock - (float) $adjustItem->allocated_qty, 2) }}
                                </div>
                            </div>
                        @elseif ($trackAllItems)
                            <div class="isa-meta" style="margin-bottom:.45rem;font-size:12px;color:#64748b">
                                Select a list row first to track one item only. Click an item code below to open that item’s track.
                            </div>
                        @endif
                        <div class="isa-table-wrap" @if ($stockModalMode === 'track') style="max-height:22rem" @endif>
                            <table class="desk-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        @if ($trackAllItems)
                                            <th>Item</th>
                                        @endif
                                        <th>Source</th>
                                        <th>Ref</th>
                                        <th class="desk-money">Change</th>
                                        <th class="desk-money">After</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($adjustJournal as $entry)
                                        @php
                                            $src = (string) $entry->source_type;
                                            $srcLabel = match (true) {
                                                $src === 'stock_adjustment' => 'Stock Adjust',
                                                str_contains($src, 'Invoice') => 'Invoice',
                                                str_contains($src, 'Receiving') => 'Receiving',
                                                str_contains($src, 'ReturnToVendor') => 'RTV',
                                                str_contains($src, 'StockCount') => 'Stock Count',
                                                str_contains($src, 'CreditMemo') => 'Credit Memo',
                                                default => class_basename($src) ?: $src,
                                            };
                                            $chg = (float) $entry->qty_change;
                                        @endphp
                                        <tr wire:key="adj-j-{{ $entry->id }}">
                                            <td>{{ optional($entry->created_at)?->format('n/j/Y g:ia') }}</td>
                                            @if ($trackAllItems)
                                                <td>
                                                    @if ($entry->item)
                                                        <button
                                                            type="button"
                                                            class="isa-item-link"
                                                            wire:click="openStockTrack({{ $entry->item_id }})"
                                                            title="{{ $entry->item->description }}"
                                                        >{{ $entry->item->item_code }}</button>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $srcLabel }}</td>
                                            <td class="desk-num">{{ $entry->reference ?: '—' }}</td>
                                            <td @class(['desk-money', 'isa-qty-pos' => $chg > 0, 'isa-qty-neg' => $chg < 0])>
                                                {{ $chg > 0 ? '+' : '' }}{{ number_format($chg, 2) }}
                                            </td>
                                            <td class="desk-money">{{ number_format((float) $entry->qty_after, 2) }}</td>
                                            <td>{{ $entry->notes ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr class="is-empty">
                                            <td colspan="{{ $trackAllItems ? 7 : 6 }}">
                                                {{ $trackAllItems ? 'No stock history yet.' : 'No stock history yet for this item.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($stockModalMode === 'track')
                            <div class="isa-actions">
                                @if ($adjustItem && auth()->user()?->canAccessFeature('inventory.items', 'edit'))
                                    <button type="button" class="desk-btn desk-btn-primary" wire:click="openStockAdjust({{ $adjustItem->id }})">Adjust stock…</button>
                                @endif
                                @if ($adjustItem)
                                    <button type="button" class="desk-btn" wire:click="openAllStockTrack">All items track</button>
                                @endif
                                <button type="button" class="desk-btn" wire:click="closeStockAdjust">Close</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>

@script
<script>
    $wire.on('open-items-print', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
@endscript
