<?php

use App\Livewire\Concerns\CustomizesDeskListColumns;
use App\Livewire\Concerns\InteractsWithDeskQuery;
use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\PersistsDeskTabSearch;
use App\Livewire\Concerns\SelectsDeskRows;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ParkedSaleService;
use App\Support\ExcelCsv;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithoutUrlPagination;

new #[Layout('layouts.app'), Title('Orders')] class extends Component
{
    use WithoutUrlPagination;
    use InteractsWithDeskQuery;
    use SortsDeskList;
    use PaginatesDeskLists;
    use PersistsDeskTabSearch;
    use CustomizesDeskListColumns;
    use SelectsDeskRows;

    public string $search = '';

    #[Url(history: false)]
    public string $favorite = 'not_invoiced';

    /** '' | not_invoiced | Invoiced */
    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $customerId = '';

    /** User who created the order (created_by / sales_rep fallback). */
    public string $createdByUserId = '';

    public ?int $selectedId = null;

    public string $permissionNotice = '';

    public int $permissionNoticeTick = 0;

    public bool $compactView = false;

    public bool $showParkedSalesModal = false;

    /** @var array<int, array{id:int,customer_label:?string,line_count:int,total:float,updated_at:?string}> */
    public array $parkedSalesList = [];

    public function mount(): void
    {
        $today = now()->toDateString();
        if ($this->dateFrom === '') {
            $this->dateFrom = $today;
        }
        if ($this->dateTo === '') {
            $this->dateTo = $today;
        }
        $this->bootDeskListColumns();
        $this->hydrateDeskTabSearchFromStore();
    }

    public function updatedSearch(): void
    {
        $this->rememberDeskTabSearch();
        $this->resetDeskList();
    }

    public function updatedFavorite(): void
    {
        if ($this->favorite === 'invoiced') {
            $this->redirect(route('sales.invoices.index'), navigate: true);

            return;
        }

        $this->restoreDeskTabSearch();
        $this->selectedId = null;
        $today = now()->toDateString();
        if ($this->favorite === 'not_invoiced') {
            $this->statusFilter = '';
            $this->dateFrom = $today;
            $this->dateTo = $today;
        } elseif ($this->favorite === 'today') {
            $this->dateFrom = now()->subDay()->toDateString();
            $this->dateTo = $today;
        } elseif ($this->favorite === 'month') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = $today;
        } elseif (in_array($this->favorite, ['all', 'new'], true)) {
            $this->dateFrom = '';
            $this->dateTo = '';
        }
        $this->resetDeskList();
    }

    public function updatedStatusFilter(): void
    {
        if ($this->statusFilter === 'Invoiced') {
            $this->redirect(route('sales.invoices.index'), navigate: true);

            return;
        }

        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function updatedDateFrom(): void
    {
        $this->resetDeskList();
    }

    public function updatedDateTo(): void
    {
        $this->resetDeskList();
    }

    public function updatedCustomerId(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function updatedCreatedByUserId(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function openParkedSalesModal(): void
    {
        $this->parkedSalesList = app(ParkedSaleService::class)
            ->listFor(auth()->user())
            ->map(function ($p) {
                $updated = data_get($p, 'updated_at');

                return [
                    'id' => (int) data_get($p, 'id'),
                    'customer_label' => data_get($p, 'customer_label'),
                    'line_count' => (int) data_get($p, 'line_count'),
                    'total' => (float) data_get($p, 'total'),
                    'updated_at' => is_object($updated) && method_exists($updated, 'format')
                        ? $updated->format('n/j/Y g:i A')
                        : (is_string($updated) ? $updated : null),
                ];
            })
            ->all();
        $this->showParkedSalesModal = true;
    }

    public function closeParkedSalesModal(): void
    {
        $this->showParkedSalesModal = false;
    }

    public function recallParkedSale(int $id): void
    {
        $this->redirect(route('sales.orders.create', ['parked' => $id]), navigate: true);
    }

    public function discardParkedSale(int $id): void
    {
        app(ParkedSaleService::class)->discard(auth()->user(), $id);
        $this->openParkedSalesModal();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetDeskList();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $today = now()->toDateString();
        $this->dateFrom = $today;
        $this->dateTo = $today;
        $this->customerId = '';
        $this->createdByUserId = '';
        $this->selectedId = null;
        $this->clearQueryCriteria();
        $this->resetDeskList();
    }

    public function openDeskQuery(): void
    {
        $this->showQueryModal = true;
        $this->queryStatus = '';
        $fields = $this->deskQueryFields();
        if ($this->queryField === '' || ! isset($fields[$this->queryField])) {
            $this->queryField = (string) (array_key_first($fields) ?? '');
            if ($this->queryField !== '') {
                $this->syncQueryOperatorForField($this->queryField);
            }
        }
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetDeskList();
    }

    public function viewSelected(): mixed
    {
        if (! $this->selectedId) {
            return $this->denyOrderOpen('Select an order first.');
        }

        if (! (auth()->user()?->canAccessFeature('sales.orders', 'view') ?? false)) {
            return $this->denyOrderOpen('Your role cannot view sales orders.');
        }

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            return $this->denyOrderOpen('Order not found.');
        }

        return $this->redirect(route('sales.orders.show', $order), navigate: true);
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            return $this->denyOrderOpen('Select an order first.');
        }

        $order = SalesOrder::query()
            ->with('invoice')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            return $this->denyOrderOpen('Order not found.');
        }

        return $this->redirectToOrder($order);
    }

    public function openOrder(int $id): mixed
    {
        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return null;
        }

        $this->selectedId = $id;

        return $this->redirectToOrder($order);
    }

    public function deleteSelected(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.orders', 'delete')) {
            session()->flash('status', 'Your role cannot delete sales orders.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $order = SalesOrder::query()
            ->with('invoice')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return;
        }

        if ($order->status === 'Invoiced' || $order->invoice) {
            session()->flash('status', 'Invoiced orders cannot be deleted.');

            return;
        }

        $order->loadMissing('lines');
        $itemIds = $order->lines->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $order->lines()->delete();
        $order->delete();

        if ($itemIds !== []) {
            app(\App\Services\InventoryService::class)->syncAllocatedQty($itemIds);
        }

        $this->selectedId = null;
        session()->flash('status', 'Order deleted. Allocated qty released.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return;
        }

        $this->dispatch('open-order-invoice-pdf', url: route('sales.orders.print', $order));
    }

    public function printPickListSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $order = SalesOrder::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return;
        }

        $this->dispatch('open-order-invoice-pdf', url: route('sales.orders.pick-list', $order).'?v='.time());
    }

    public function with(): array
    {
        $this->hydrateDeskTabSearchFromStore();
        $companyId = auth()->user()->company_id;

        $query = SalesOrder::query()
            ->select([
                'id', 'company_id', 'order_number', 'order_type', 'order_source', 'status',
                'order_date', 'ship_date', 'customer_id', 'total', 'created_by', 'sales_rep_id',
            ])
            ->with([
                'customer:id,customer_id,company_name,contact,telephone,address',
                'invoice:id,sales_order_id,invoice_number,status',
            ])
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['Invoiced', 'Cancelled', 'Void', 'Closed'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.sales_order_id', 'sales_orders.id');
            })
            ->when($this->search !== '', function ($q) {
                $raw = trim($this->search);
                $q->where(function ($inner) use ($raw) {
                    if (preg_match('/^[0-9]{3,}$/', $raw)) {
                        $prefix = $raw.'%';
                        $inner->where('order_number', 'like', $prefix)
                            ->orWhereHas('invoice', fn ($inv) => $inv->where('invoice_number', 'like', $prefix));

                        return;
                    }
                    $term = '%'.$raw.'%';
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('invoice', fn ($inv) => $inv->where('invoice_number', 'like', $term))
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term));
                });
            })
            ->when($this->favorite === 'new', fn ($q) => $q->where('status', 'New'));
        $this->constrainDeskDateColumn($query, 'order_date', $this->dateFrom, $this->dateTo);
        $query
            ->when($this->customerId !== '' && ctype_digit((string) $this->customerId), fn ($q) => $q->where('customer_id', (int) $this->customerId))
            ->when($this->createdByUserId !== '' && ctype_digit((string) $this->createdByUserId), function ($q) {
                $uid = (int) $this->createdByUserId;
                $q->where(function ($inner) use ($uid) {
                    $inner->where('created_by', $uid)
                        ->orWhere(function ($o) use ($uid) {
                            $o->whereNull('created_by')->where('sales_rep_id', $uid);
                        });
                });
            })
            ->when($this->queryCriteria !== [], fn ($q) => $this->applyQueryCriteria($q));
        $this->applyDeskSort($query, 'order_date', 'desc');

        $listTitle = match ($this->favorite) {
            'new' => 'Orders List (New)',
            'not_invoiced' => 'Orders List (Not Invoiced)',
            'invoiced' => 'Invoice List',
            'month' => 'Orders List (This Month)',
            'today' => 'Orders List (Today & Yesterday)',
            default => 'Orders List',
        };

        if ($this->statusFilter === 'not_invoiced') {
            $listTitle = 'Orders List (Not Invoiced)';
        } elseif ($this->statusFilter === 'Invoiced') {
            $listTitle = 'Orders List (Invoiced)';
        }

        if ($this->queryCriteria !== []) {
            $listTitle = $this->queryLoadedName !== ''
                ? 'Query: '.$this->queryLoadedName
                : 'Query Results ('.count($this->queryCriteria).' criteria)';
        }

        $scroll = $this->scrollDeskList($query);

        return [
            'orders' => $scroll['rows'],
            'listHasMore' => $scroll['hasMore'],
            'listShown' => $scroll['shown'],
            'favorites' => [
                'not_invoiced' => 'Not Invoiced',
                'all' => 'All Open Orders',
                'new' => 'New Orders',
                'invoiced' => 'Invoices',
                'month' => 'This Month',
                'today' => 'Today & Yesterday',
            ],
            'listTitle' => $listTitle,
            'queryFields' => $this->deskQueryFieldOptions(),
            'queryFieldTypes' => $this->deskQueryFieldTypes(),
            'queryOperators' => $this->deskQueryOperatorOptions(),
            'savedDeskQueries' => $this->loadSavedDeskQueries(),
            'deskQueryTitle' => 'Sales Order Query',
            'filterCustomers' => Cache::remember('orders.filter_customers.v2.'.$companyId, 180, fn () => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->limit(500)
                ->get(['id', 'customer_id', 'company_name'])
                ->map(fn ($c) => [
                    'id' => (int) data_get($c, 'id'),
                    'customer_id' => (string) data_get($c, 'customer_id'),
                    'company_name' => (string) data_get($c, 'company_name'),
                ])
                ->values()
                ->all()),
            'filterUsers' => Cache::remember('orders.filter_users.v1.'.$companyId, 180, fn () => User::assignableSalesRepsQuery($companyId)
                ->get(['id', 'name'])
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                ])
                ->values()
                ->all()),
            'selectedOrder' => $this->selectedId
                ? SalesOrder::query()->where('company_id', $companyId)->find($this->selectedId)
                : null,
            'parkedCount' => Cache::remember(
                'parked.count.'.(int) auth()->id(),
                20,
                fn () => \App\Models\ParkedSale::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', auth()->id())
                    ->count()
            ),
        ] + $this->deskListColumnViewData(1);
    }

    /** @return array<string, array{label: string, column: string, has?: string}> */
    protected function deskQueryFields(): array
    {
        return [
            'order_number' => ['label' => 'Order Number', 'column' => 'order_number'],
            'status' => ['label' => 'Status', 'column' => 'status'],
            'order_type' => ['label' => 'Order Type', 'column' => 'order_type'],
            'order_source' => ['label' => 'Source', 'column' => 'order_source'],
            'priority' => ['label' => 'Priority', 'column' => 'priority'],
            'order_date' => ['label' => 'Order Date', 'column' => 'order_date', 'type' => 'date'],
            'required_date' => ['label' => 'Required Date', 'column' => 'required_date', 'type' => 'date'],
            'customer_po_no' => ['label' => 'Customer PO #', 'column' => 'customer_po_no'],
            'reference_no' => ['label' => 'Reference #', 'column' => 'reference_no'],
            'bill_to_name' => ['label' => 'Bill-to Name', 'column' => 'bill_to_name'],
            'ship_to_name' => ['label' => 'Ship-to Name', 'column' => 'ship_to_name'],
            'total' => ['label' => 'Order Total', 'column' => 'total', 'type' => 'number'],
            'customer_code' => ['label' => 'Customer ID', 'has' => 'customer', 'column' => 'customer_id'],
            'customer_name' => ['label' => 'Customer Name', 'has' => 'customer', 'column' => 'company_name'],
            'customer_contact' => ['label' => 'Customer Contact', 'has' => 'customer', 'column' => 'contact'],
            'customer_phone' => ['label' => 'Customer Phone', 'has' => 'customer', 'column' => 'telephone'],
            'invoice_number' => ['label' => 'Invoice Number', 'has' => 'invoice', 'column' => 'invoice_number'],
            'item_code' => ['label' => 'Item Code', 'has' => 'lines', 'column' => 'item_code'],
            'item_description' => ['label' => 'Item Description', 'has' => 'lines', 'column' => 'description'],
            'ship_date' => ['label' => 'Ship Date', 'column' => 'ship_date', 'type' => 'date'],
            'created_by_name' => ['label' => 'User Name', 'has' => 'createdBy', 'column' => 'name'],
            'customer_city' => ['label' => 'Customer City', 'has' => 'customer', 'column' => 'city'],
            'customer_state' => ['label' => 'Customer State', 'has' => 'customer', 'column' => 'state'],
        ];
    }

    protected function deskListColumnCatalog(): array
    {
        return [
            'order_number' => ['label' => 'Order #', 'type' => 'text'],
            'invoice_number' => ['label' => 'Invoice #', 'type' => 'text'],
            'order_type' => ['label' => 'Type', 'type' => 'text'],
            'order_source' => ['label' => 'Source', 'type' => 'text'],
            'order_date' => ['label' => 'Order Date', 'type' => 'date'],
            'ship_date' => ['label' => 'Ship Date', 'type' => 'date'],
            'status' => ['label' => 'Status', 'type' => 'text'],
            'customer_code' => ['label' => 'Customer ID', 'type' => 'text'],
            'customer_contact' => ['label' => 'Name', 'type' => 'text'],
            'customer_company' => ['label' => 'Company', 'type' => 'text'],
            'customer_address' => ['label' => 'Address', 'type' => 'text'],
            'customer_phone' => ['label' => 'Telephone', 'type' => 'text'],
            'total' => ['label' => 'Total', 'type' => 'money'],
            'invoice_action' => ['label' => 'Invoice', 'type' => 'action'],
        ];
    }

    protected function defaultVisibleColumns(): array
    {
        return ['order_number', 'invoice_number', 'order_type', 'order_source', 'order_date', 'ship_date', 'status', 'customer_code', 'customer_contact', 'customer_company', 'customer_address', 'customer_phone', 'total', 'invoice_action'];
    }

    protected function visibleColumnsSessionKey(): string
    {
        return 'orders_list_columns_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    protected function denyOrderOpen(string $message): null
    {
        $this->permissionNotice = $message;
        $this->permissionNoticeTick++;
        $this->js(
            'window.showPermissionToast && window.showPermissionToast('.json_encode($message).');'
            .'window.playPosAlert && window.playPosAlert("warning")'
        );

        return null;
    }

    protected function redirectToOrder(SalesOrder $order): mixed
    {
        $user = auth()->user();
        if (! $order->canBeEditedBy($user)) {
            return $this->denyOrderOpen('Only the user who created this order can edit it.');
        }

        $held = $order->editLockHolder();
        if ($held && (int) $held['user_id'] !== (int) $user->id) {
            return $this->denyOrderOpen(($held['name'] ?? 'Another user').' has this order open.');
        }

        return $this->redirect(route('sales.orders.edit', $order), navigate: true);
    }

    protected function deskSortMap(): array
    {
        return [
            'order_number' => 'order_number',
            'invoice_number' => ['relation' => 'invoice', 'column' => 'invoice_number'],
            'order_type' => 'order_type',
            'order_source' => 'order_source',
            'order_date' => 'order_date',
            'ship_date' => 'ship_date',
            'status' => 'status',
            'customer_code' => ['relation' => 'customer', 'column' => 'customer_id'],
            'customer_contact' => ['relation' => 'customer', 'column' => 'contact'],
            'customer_company' => ['relation' => 'customer', 'column' => 'company_name'],
            'customer_address' => ['relation' => 'customer', 'column' => 'address'],
            'customer_phone' => ['relation' => 'customer', 'column' => 'telephone'],
            'total' => 'total',
        ];
    }

    protected function deskQuerySessionKey(): string
    {
        return 'sales_orders_query_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    public function invoiceOrder(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $order = SalesOrder::query()->with(['lines', 'customer', 'invoice'])->lockForUpdate()->findOrFail($id);
                abort_unless((int) $order->company_id === (int) auth()->user()->company_id, 403);
                $existingInvoice = $order->relationLoaded('invoice')
                    ? $order->getRelation('invoice')
                    : $order->invoice()->first();
                if ($order->status === 'Invoiced' || $existingInvoice instanceof Invoice) {
                    return;
                }

                $lineDiscount = (float) $order->lines->sum('discount');
                $invoice = Invoice::query()->create([
                    'company_id' => (int) $order->company_id,
                    'invoice_number' => Invoice::nextNumber((int) $order->company_id),
                    'invoice_date' => now()->toDateString(),
                    'sales_order_id' => (int) $order->getKey(),
                    'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
                    'status' => 'NOT PAID',
                    'subtotal' => $order->subtotal,
                    'total_discount' => $lineDiscount,
                    'trade_discount' => $order->trade_discount,
                    'freight' => $order->freight,
                    'miscellaneous' => $order->miscellaneous,
                    'tax' => $order->tax,
                    'invoice_total' => $order->total,
                    'driver' => null,
                ]);

                app(InventoryService::class)->applyInvoiceStock($order, $invoice);

                $order->update(['status' => 'Invoiced']);

                $customer = $order->customer()->first();
                if ($customer instanceof Customer) {
                    $updates = [
                        'last_order_on' => $order->order_date ?? now()->toDateString(),
                        'number_of_orders' => (int) $customer->number_of_orders + 1,
                        'total_sales' => (float) $customer->total_sales + (float) $order->total,
                        'balance' => (float) $customer->balance + (float) $order->total,
                    ];
                    if (blank($customer->customer_since)) {
                        $updates['customer_since'] = $order->order_date ?? now()->toDateString();
                    }
                    $customer->update($updates);
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('status', collect($e->errors())->flatten()->first() ?: 'Unable to invoice order.');

            return;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Unable to invoice order. '.$e->getMessage());

            return;
        }

        session()->flash('status', 'Invoice created. Stock quantities updated.');
    }

    public function createNewOrder(): mixed
    {
        return $this->redirect(route('sales.orders.create'), navigate: true);
    }

    public function invoiceSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return;
        }

        $this->invoiceOrder($this->selectedId);
    }

    public function exportOrders(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an order first.');

            return null;
        }

        $order = SalesOrder::query()
            ->with('lines')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $order) {
            session()->flash('status', 'Order not found.');

            return null;
        }

        $export = [];
        foreach ($order->lines as $line) {
            $qty = (float) $line->qty_ordered;
            $price = (float) $line->price;
            $disc = (float) ($line->discount ?? ($qty * (float) $line->unit_discount));
            $export[] = [
                $line->item_code,
                $line->description,
                $line->uom,
                $qty,
                $price,
                $disc,
                round(($qty * $price) - $disc, 2),
            ];
        }

        $filename = 'order-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $order->order_number).'-lines.csv';

        return ExcelCsv::download($filename, ['Item Code', 'Description', 'UOM', 'Qty Ordered', 'Price', 'Discount', 'Line Total'], $export);
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action">
            <x-slot:menu>
                <x-action-item label="Add New Order" kbd="Ctrl+N" wire:click="createNewOrder" />
                <x-action-item label="View Selected Order" kbd="Ctrl+O" sep wire:click="viewSelected" />
                <x-action-item label="Edit Selected Order" kbd="Ctrl+E" sep wire:click="editSelected" />
                <x-action-item label="Create/Edit Invoice & Payment" kbd="Ctrl+I" sep wire:click="invoiceSelected" />
                <x-action-item label="Delete Selected Order" sep wire:click="deleteSelected" />
                <x-action-item label="Export Orders" sep wire:click="exportOrders" />
                <x-action-item label="Print" kbd="Ctrl+P" sep wire:click="printSelected" />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (filled($permissionNotice))
                    <div
                        class="desk-flash"
                        role="status"
                        data-flash-repeat="1"
                        wire:key="perm-notice-{{ $permissionNoticeTick }}"
                    >{{ $permissionNotice }}</div>
                @endif
                @if (session('status') && trim((string) session('status')) !== trim((string) $permissionNotice))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar" wire:ignore>
                    <label class="desk-toolbar-label" for="orders-search">Search Orders:</label>
                    <input
                        id="orders-search" data-pos-search
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Order #, invoice #, customer…"
                        class="desk-search orders-search-input"
                        aria-label="Search Orders"
                    />
                    <button type="button" wire:click="openDeskQuery" class="desk-btn items-query-btn" title="Query by field">Query</button>
                    @if ($queryCriteria !== [])
                        <button type="button" wire:click="clearQueryCriteria" class="desk-btn desk-btn-sm" title="Clear query">Clear Query</button>
                    @endif

                    <div class="orders-toolbar-right">
                        <label class="desk-toolbar-label" for="orders-date-from">From</label>
                        <input id="orders-date-from" type="date" wire:model.live="dateFrom" class="desk-input" aria-label="Order date from" />
                        <label class="desk-toolbar-label" for="orders-date-to">To</label>
                        <input id="orders-date-to" type="date" wire:model.live="dateTo" class="desk-input" aria-label="Order date to" />
                        <select wire:model.live="customerId" class="desk-select orders-party-select" aria-label="Customer">
                            <option value="">All customers</option>
                            @foreach ($filterCustomers as $cust)
                                @php
                                    $filterCustId = is_array($cust) ? ($cust['id'] ?? '') : (is_object($cust) ? ($cust->id ?? '') : '');
                                    $filterCustCode = is_array($cust) ? ($cust['customer_id'] ?? '') : (is_object($cust) ? ($cust->customer_id ?? '') : '');
                                    $filterCustName = is_array($cust) ? ($cust['company_name'] ?? '') : (is_object($cust) ? ($cust->company_name ?? '') : '');
                                @endphp
                                @if ($filterCustId !== '')
                                    <option value="{{ $filterCustId }}">{{ $filterCustCode }} — {{ $filterCustName }}</option>
                                @endif
                            @endforeach
                        </select>
                        <select wire:model.live="createdByUserId" class="desk-select orders-party-select" aria-label="Created by user" title="User who created the order">
                            <option value="">All users</option>
                            @foreach ($filterUsers as $user)
                                @php
                                    $filterUserId = is_array($user) ? ($user['id'] ?? '') : (is_object($user) ? ($user->id ?? '') : '');
                                    $filterUserName = is_array($user) ? ($user['name'] ?? '') : (is_object($user) ? ($user->name ?? '') : '');
                                @endphp
                                @if ($filterUserId !== '')
                                    <option value="{{ $filterUserId }}">{{ $filterUserName }}</option>
                                @endif
                            @endforeach
                        </select>
                        <select
                            id="orders-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Invoiced filter"
                            title="Invoiced / Not Invoiced"
                        >
                            <option value="">All</option>
                            <option value="not_invoiced">Not Invoiced</option>
                            <option value="Invoiced">Invoiced</option>
                        </select>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($listShown) }}{{ $listHasMore ? '+' : '' }} records</span>
                </div>

                <x-desk-scroll-grid :has-more="$listHasMore" class="desk-grid-responsive {{ $compactView ? 'is-compact' : '' }}">
                    <table class="desk-table desk-table-fit desk-list-table desk-table-resizable" data-col-resize="orders-list" data-excel-grid data-excel-copy-all>
                        <colgroup></colgroup>
                        <thead>
                            <tr>
                                <th class="text-center" data-excel-skip></th>
                                @foreach ($visibleColumnKeys as $colKey)
                                    @php $col = $listColumnCatalog[$colKey]; @endphp
                                    <x-desk-sort-th
                                        :field="$colKey"
                                        :label="$col['label']"
                                        resize
                                        :align="($col['type'] ?? '') === 'money' ? 'money' : 'left'"
                                    />
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                @continue(! $order instanceof \App\Models\SalesOrder)
                                @php $orderId = (int) $order->getKey(); @endphp
                                <tr
                                    wire:key="so-row-{{ $orderId }}"
                                    wire:click="selectRow({{ $orderId }})"
                                    wire:dblclick="openOrder({{ $orderId }})"
                                    @class(['is-selected' => $selectedId === $orderId, 'cursor-pointer'])
                                >
                                    <td class="text-center" data-excel-skip wire:click.stop>
                                        <input
                                            type="radio"
                                            name="order_select"
                                            value="{{ $orderId }}"
                                            @checked($selectedId === $orderId)
                                            wire:click="selectRow({{ $orderId }})"
                                            aria-label="Select order {{ $order->order_number }}"
                                        />
                                    </td>
                                    @foreach ($visibleColumnKeys as $colKey)
                                        @include('livewire.pages.sales.orders.partials.list-cell', ['order' => $order, 'colKey' => $colKey])
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="{{ $columnColspan }}">No orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="desk-list-cards" aria-label="Orders">
                        @forelse ($orders as $order)
                            @continue(! $order instanceof \App\Models\SalesOrder)
                            @php
                                $orderId = (int) $order->getKey();
                                $src = (string) ($order->order_source ?? 'pos');
                                $oc = ($order->relationLoaded('customer') && $order->getRelation('customer') instanceof \App\Models\Customer)
                                    ? $order->getRelation('customer')
                                    : null;
                                $cardInvoice = ($order->relationLoaded('invoice') && $order->getRelation('invoice') instanceof \App\Models\Invoice)
                                    ? $order->getRelation('invoice')
                                    : null;
                            @endphp
                            <article
                                wire:key="so-card-{{ $orderId }}"
                                class="desk-list-card {{ $selectedId === $orderId ? 'is-selected' : '' }}"
                                wire:click="selectRow({{ $orderId }})"
                                wire:dblclick="openOrder({{ $orderId }})"
                            >
                                <div class="desk-list-card__top">
                                    @if ($order->canBeEditedBy(auth()->user()))
                                        <a href="{{ route('sales.orders.edit', $orderId) }}" wire:navigate wire:click.stop class="desk-list-card__id">{{ $order->order_number }}</a>
                                    @else
                                        <button type="button" class="desk-list-card__id desk-link-btn" wire:click.stop="openOrder({{ $orderId }})">{{ $order->order_number }}</button>
                                    @endif
                                    <span @class([
                                        'desk-pill',
                                        'desk-pill-new' => $order->status === 'New',
                                        'desk-pill-invoiced' => $order->status === 'Invoiced',
                                        'desk-pill-muted' => ! in_array($order->status, ['New', 'Invoiced'], true),
                                    ])>{{ $order->status }}</span>
                                </div>
                                <div class="desk-list-card__meta">
                                    <span @class([
                                        'desk-pill',
                                        'desk-pill-muted' => $src === 'pos',
                                        'desk-pill-new' => $src === 'sales',
                                        'desk-pill-invoiced' => $src === 'customer',
                                    ])>{{ $order->sourceLabel() }}</span>
                                    <span>{{ $order->order_type }}</span>
                                    <span>{{ optional($order->order_date)?->format('n/j/Y') }}</span>
                                </div>
                                <div class="desk-list-card__name">{{ $oc?->company_name ?: $oc?->contact ?: '—' }}</div>
                                <div class="desk-list-card__sub">{{ $oc?->customer_id }}{{ $oc?->telephone ? ' · '.$oc->telephone : '' }}</div>
                                <div class="desk-list-card__foot">
                                    <strong class="tabular-nums">${{ number_format($order->total, 2) }}</strong>
                                    @if ($cardInvoice)
                                        <a href="{{ route('sales.invoices.pdf', $cardInvoice->getKey()) }}" target="_blank" rel="noopener" wire:click.stop>Inv {{ $cardInvoice->invoice_number }}</a>
                                    @endif
                                    @if ($order->status !== 'Invoiced')
                                        <button type="button" wire:click.stop="invoiceOrder({{ $orderId }})" class="desk-btn desk-btn-sm">Invoice</button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="desk-list-card is-empty">No orders found.</div>
                        @endforelse
                    </div>
                </x-desk-scroll-grid>

                <x-record-count :count="$listShown" class="is-inline" note="">
                    <button type="button" wire:click="openParkedSalesModal" class="desk-btn">
                        Parked Sales{{ $parkedCount ? ' ('.$parkedCount.')' : '' }}
                    </button>
                    <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-btn desk-btn-primary">New Sales Order</a>
                    <x-desk-load-more :has-more="$listHasMore" />
                </x-record-count>
            </div>

            {{-- Right icon rail: grid, view, edit, delete, print, refresh, + --}}
            <aside class="desk-rail" aria-label="Order actions">
                <x-desk-fields-rail-btn />
                <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                        <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                        <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="openDeskQuery" class="desk-rail-btn" title="Query orders by field" aria-label="Query orders">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                        <circle cx="7" cy="7" r="4.5"/>
                        <path d="M10.5 10.5L14 14"/>
                        <path d="M5.2 7h3.6M7 5.2v3.6" stroke-width="1.3"/>
                    </svg>
                </button>
                <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View order (read only)" aria-label="View order" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit order" aria-label="Edit order" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected order? This cannot be undone."
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
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print invoice / order" aria-label="Print selected" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="printPickListSelected" class="desk-rail-btn" title="Print pick list" aria-label="Print pick list" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="3" y="2" width="10" height="12" rx="1"/>
                        <path d="M5.5 5h5M5.5 7.5h5M5.5 10h3"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
                <a href="{{ route('sales.orders.create') }}" wire:navigate class="desk-rail-btn desk-rail-btn-primary" title="New Sales Order" aria-label="New Sales Order">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                </a>
            </aside>
        </div>
    </div>
    @if ($showParkedSalesModal)
        <div
            class="desk-modal-backdrop desk-modal-top"
            wire:click.self="closeParkedSalesModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="parked-sales-list-title"
        >
            <div class="desk-modal" style="max-width:32rem;" wire:keydown.escape.window="closeParkedSalesModal">
                <div class="desk-modal-head">
                    <span id="parked-sales-list-title">Parked sales</span>
                    <button type="button" wire:click="closeParkedSalesModal" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="padding:0; max-height:22rem; overflow:auto;">
                    @forelse ($parkedSalesList as $parked)
                        <div style="display:flex; align-items:stretch; border-bottom:1px solid #e2e8f0;">
                            <button
                                type="button"
                                wire:click="recallParkedSale({{ $parked['id'] }})"
                                style="flex:1; text-align:left; border:0; background:#fff; padding:.85rem 1rem; cursor:pointer;"
                            >
                                <div style="font-weight:700;">{{ $parked['customer_label'] ?: 'Customer' }}</div>
                                <div style="font-size:.8rem; color:#64748b;">{{ $parked['line_count'] }} item(s) · ${{ number_format($parked['total'], 2) }}@if($parked['updated_at']) · {{ $parked['updated_at'] }}@endif</div>
                            </button>
                            <button
                                type="button"
                                wire:click="discardParkedSale({{ $parked['id'] }})"
                                wire:confirm="Discard this parked sale?"
                                class="desk-modal-close"
                                style="width:2.75rem; position:static;"
                                aria-label="Discard"
                            >×</button>
                        </div>
                    @empty
                        <p style="padding:1rem; color:#64748b; margin:0;">No parked sales.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
    @include('livewire.partials.desk-query-modal')
    <x-desk-column-picker :catalog="$listColumnCatalog" :visible-keys="$visibleColumnKeys" locked="order_number" />
</div>
@script
<script>
    $wire.on('open-order-invoice-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });
</script>
@endscript
