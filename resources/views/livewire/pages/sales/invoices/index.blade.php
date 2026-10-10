<?php

use App\Livewire\Concerns\CustomizesDeskListColumns;
use App\Livewire\Concerns\ManagesInvoicePaymentsModal;
use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\PersistsDeskTabSearch;
use App\Livewire\Concerns\SelectsDeskRows;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithoutUrlPagination;

new #[Layout('layouts.app'), Title('Invoices')] class extends Component
{
    use WithoutUrlPagination;
    use SortsDeskList;
    use PaginatesDeskLists;
    use SelectsDeskRows;
    use CustomizesDeskListColumns;
    use PersistsDeskTabSearch;
    use ManagesInvoicePaymentsModal;

    public string $search = '';

    #[Url]
    public string $statusFilter = 'NOT PAID';

    #[Url]
    public ?int $pay = null;

    public string $favorite = 'not_paid';

    /** User who created the related sales order. */
    public string $createdByUserId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedId = null;

    public string $permissionNotice = '';

    public int $permissionNoticeTick = 0;

    public bool $showInvoiceDeliveryDialog = false;

    public string $invoiceDeliveryMode = 'print';

    public ?int $editInvoiceId = null;

    public string $edit_invoice_date = '';

    public string $edit_driver = '';

    public string $edit_trade_discount = '';

    public string $edit_freight = '';

    public string $edit_miscellaneous = '';

    public string $edit_tax = '';

    public string $edit_subtotal = '';

    public ?int $returnInvoiceId = null;

    /** @var array<int|string, string> original line id => return qty */
    public array $returnQty = [];

    public string $returnNote = '';

    public function mount(): void
    {
        $this->bootDeskListColumns();
        $this->favorite = match ($this->statusFilter) {
            'NOT PAID' => 'not_paid',
            'PAID' => 'paid',
            default => $this->favorite ?: 'not_paid',
        };
        if ($this->sortField === '') {
            $this->sortField = 'bill_to';
            $this->sortDir = 'asc';
        }
        if ($this->pay) {
            $id = (int) $this->pay;
            $this->pay = null;
            $ok = Invoice::query()
                ->where('company_id', auth()->user()->company_id)
                ->whereKey($id)
                ->exists();
            if ($ok) {
                $this->openPayments($id);
            }
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = Invoice::query()
            ->select([
                'id', 'company_id', 'invoice_number', 'invoice_date', 'customer_id', 'sales_order_id',
                'status', 'invoice_total', 'subtotal', 'tax', 'freight', 'trade_discount', 'miscellaneous',
                'driver',
            ])
            ->with([
                'customer:id,customer_id,company_name,portal_email',
                'salesOrder:id,order_number,bill_to_name,delivery_status,created_by,order_source,sales_rep_id',
                'salesOrder.createdBy:id,name',
            ])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $raw = trim($this->search);
                $q->where(function ($inner) use ($raw) {
                    if (preg_match('/^[0-9]{3,}$/', $raw)) {
                        $prefix = $raw.'%';
                        $inner->where('invoice_number', 'like', $prefix)
                            ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', $prefix));

                        return;
                    }
                    $term = '%'.$raw.'%';
                    $inner->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('company_name', 'like', $term)
                                ->orWhere('customer_id', 'like', $term);
                        })
                        ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', $term));
                });
            });

        if (in_array($this->statusFilter, ['NOT PAID', 'PAID'], true)) {
            $query->where('status', $this->statusFilter);
        } elseif ($this->favorite === 'not_paid') {
            $query->where('status', 'NOT PAID');
        } elseif ($this->favorite === 'paid') {
            $query->where('status', 'PAID');
        }

        $this->constrainDeskDateColumn($query, 'invoice_date', $this->dateFrom, $this->dateTo);

        if ($this->createdByUserId !== '' && ctype_digit((string) $this->createdByUserId)) {
            $uid = (int) $this->createdByUserId;
            $query->whereHas('salesOrder', function ($o) use ($uid) {
                $o->where(function ($inner) use ($uid) {
                    $inner->where('created_by', $uid)
                        ->orWhere(function ($x) use ($uid) {
                            $x->whereNull('created_by')->where('sales_rep_id', $uid);
                        });
                });
            });
        }

        $sortNeedsSums = in_array($this->sortField, ['payments', 'credits', 'balance'], true);
        if ($sortNeedsSums) {
            $query->withSum('payments', 'amount')->withSum('credits', 'amount');
        }

        $scroll = $this->scrollDeskList($this->applyDeskSort($query, 'invoice_date', 'desc'));
        $invoices = $scroll['rows'];

        if (! $sortNeedsSums) {
            $ids = $invoices->pluck('id')->filter()->all();
            if ($ids !== []) {
                $pays = InvoicePayment::query()
                    ->whereIn('invoice_id', $ids)
                    ->groupBy('invoice_id')
                    ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as s')
                    ->pluck('s', 'invoice_id');
                $credits = InvoiceCredit::query()
                    ->whereIn('invoice_id', $ids)
                    ->groupBy('invoice_id')
                    ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as s')
                    ->pluck('s', 'invoice_id');
                $invoices->each(function (Invoice $inv) use ($pays, $credits) {
                    $inv->setAttribute('payments_sum_amount', (float) ($pays[$inv->id] ?? 0));
                    $inv->setAttribute('credits_sum_amount', (float) ($credits[$inv->id] ?? 0));
                });
            }
        }

        $reversibleIds = $invoices
            ->filter(fn (Invoice $inv) => $inv->status === 'PAID'
                || (float) ($inv->payments_sum_amount ?? 0) > 0.0001
                || (float) ($inv->credits_sum_amount ?? 0) > 0.0001)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'invoices' => $invoices,
            'reversibleIds' => $reversibleIds,
            'listHasMore' => $scroll['hasMore'],
            'listShown' => $scroll['shown'],
            'favorites' => [
                'all' => 'All Invoices',
                'not_paid' => 'NOT PAID',
                'paid' => 'PAID',
                'month' => 'This Month',
                'today' => 'Today',
            ],
            'listTitle' => match (true) {
                $this->statusFilter === 'NOT PAID', $this->favorite === 'not_paid' => 'Invoices List (NOT PAID)',
                $this->statusFilter === 'PAID', $this->favorite === 'paid' => 'Invoices List (PAID)',
                $this->favorite === 'month' => 'Invoices List (This Month)',
                $this->favorite === 'today' => 'Invoices List (Today)',
                default => 'Invoices List',
            },
            'filterUsers' => Cache::remember('orders.filter_users.v1.'.$companyId, 180, fn () => User::assignableSalesRepsQuery($companyId)
                ->get(['id', 'name'])
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                ])
                ->values()
                ->all()),
            'canEnterPayments' => auth()->user()?->canAccessFeature('sales.payments', 'edit') ?? false,
            'canViewInvoice' => auth()->user()?->canAccessFeature('sales.invoices', 'view') ?? false,
            'canEditInvoice' => auth()->user()?->canAccessFeature('sales.invoices', 'edit') ?? false,
            'canVoidInvoice' => auth()->user()?->canAccessFeature('sales.invoices', 'delete') ?? false,
            'editInvoice' => $this->editInvoiceId
                ? Invoice::query()
                    ->with(['customer:id,customer_id,company_name', 'salesOrder:id,order_number'])
                    ->where('company_id', $companyId)
                    ->find($this->editInvoiceId)
                : null,
            'editPreviewTotal' => round(
                (float) str_replace(',', '', $this->edit_subtotal)
                - (float) str_replace(',', '', $this->edit_trade_discount)
                + (float) str_replace(',', '', $this->edit_freight)
                + (float) str_replace(',', '', $this->edit_miscellaneous)
                + (float) str_replace(',', '', $this->edit_tax),
                2
            ),
        ] + $this->paymentsModalViewData($companyId) + $this->returnViewData($companyId) + $this->deskListColumnViewData(1);
    }

    /**
     * @return array{returnInvoice: ?Invoice, returnRows: \Illuminate\Support\Collection, returnPreview: float}
     */
    protected function returnViewData(int $companyId): array
    {
        $invoice = $this->returnInvoiceId
            ? Invoice::query()
                ->with(['customer:id,customer_id,company_name', 'salesOrder.lines', 'payments', 'credits'])
                ->where('company_id', $companyId)
                ->find($this->returnInvoiceId)
            : null;
        $rows = $invoice?->salesOrder
            ? app(\App\Services\InvoiceReturnService::class)->returnableLines($invoice->salesOrder)
            : collect();

        $preview = 0.0;
        foreach ($rows as $row) {
            $q = (float) str_replace(',', '', (string) ($this->returnQty[$row['line']->id] ?? ''));
            if ($q <= 0) {
                continue;
            }
            $q = min($q, $row['returnable']);
            $unitDisc = $row['sold'] > 0 ? (float) $row['line']->discount / $row['sold'] : 0;
            $preview += $q * ((float) $row['line']->price - $unitDisc);
        }

        return [
            'returnInvoice' => $invoice,
            'returnRows' => $rows,
            'returnPreview' => round($preview, 2),
        ];
    }

    public function openReturnItems(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.invoices', 'edit')) {
            session()->flash('status', 'Your role cannot return items on invoices.');

            return;
        }
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }
        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);
        if (! $invoice?->sales_order_id) {
            session()->flash('status', 'This invoice has no sales order lines to return.');

            return;
        }

        $this->returnInvoiceId = (int) $invoice->id;
        $this->returnQty = [];
        $this->returnNote = '';
        $this->resetErrorBag('return');
    }

    public function closeReturnItems(): void
    {
        $this->returnInvoiceId = null;
        $this->returnQty = [];
        $this->returnNote = '';
        $this->resetErrorBag('return');
    }

    public function returnFullLine(int $lineId, string $qty): void
    {
        $this->returnQty[$lineId] = $qty;
    }

    public function returnAllLines(): void
    {
        $invoice = $this->returnInvoiceId ? Invoice::query()->with('salesOrder.lines')->find($this->returnInvoiceId) : null;
        if (! $invoice?->salesOrder) {
            return;
        }
        foreach (app(\App\Services\InvoiceReturnService::class)->returnableLines($invoice->salesOrder) as $row) {
            if ($row['returnable'] > 0) {
                $this->returnQty[$row['line']->id] = rtrim(rtrim(number_format($row['returnable'], 4, '.', ''), '0'), '.');
            }
        }
    }

    public function saveReturnItems(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.invoices', 'edit') || ! $this->returnInvoiceId) {
            return;
        }

        $qty = [];
        foreach ($this->returnQty as $lineId => $raw) {
            $raw = trim(str_replace(',', '', (string) $raw));
            if ($raw === '') {
                continue;
            }
            if (! is_numeric($raw) || (float) $raw < 0) {
                $this->addError('return', 'Return qty must be a positive number.');

                return;
            }
            $qty[(int) $lineId] = (float) $raw;
        }

        try {
            $result = app(\App\Services\InvoiceReturnService::class)->addReturn(
                $this->returnInvoiceId,
                (int) auth()->user()->company_id,
                $qty,
                $this->returnNote,
                (int) auth()->id()
            );
        } catch (\RuntimeException $e) {
            $this->addError('return', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('return', 'Could not save return. '.$e->getMessage());

            return;
        }

        $this->closeReturnItems();
        $msg = 'Returned '.$result['lines'].' item line(s): $'.number_format(abs($result['amount']), 2).' taken off the invoice. Stock added back.';
        if ($result['balance'] < -0.004) {
            $msg .= ' Customer refund due: -$'.number_format(abs($result['balance']), 2).'.';
        }
        session()->flash('status', $msg);
    }

    protected function deskListColumnCatalog(): array
    {
        return [
            'invoice_number' => ['label' => 'Invoice No'],
            'invoice_date' => ['label' => 'Invoice Date'],
            'order_number' => ['label' => 'Order No'],
            'customer_code' => ['label' => 'Customer ID'],
            'bill_to' => ['label' => 'Bill to'],
            'order_source' => ['label' => 'Source', 'type' => 'text'],
            'created_by' => ['label' => 'Created By'],
            'subtotal' => ['label' => 'Subtotal', 'type' => 'money'],
            'total_discount' => ['label' => 'Total Discount', 'type' => 'money'],
            'trade_discount' => ['label' => 'Trade Discount', 'type' => 'money'],
            'freight' => ['label' => 'Freight', 'type' => 'money'],
            'miscellaneous' => ['label' => 'Misc', 'type' => 'money'],
            'invoice_total' => ['label' => 'Invoice Total', 'type' => 'money'],
            'payments' => ['label' => 'Payments', 'type' => 'money'],
            'credits' => ['label' => 'Credits', 'type' => 'money'],
            'balance' => ['label' => 'Balance', 'type' => 'money'],
            'status' => ['label' => 'Status', 'type' => 'center'],
        ];
    }

    protected function defaultVisibleColumns(): array
    {
        return array_keys($this->deskListColumnCatalog());
    }

    protected function visibleColumnsSessionKey(): string
    {
        return 'invoices_list_columns_'.(int) auth()->id().'_'.(int) auth()->user()->company_id;
    }

    protected function deskSortMap(): array
    {
        return [
            'invoice_number' => 'invoice_number',
            'invoice_date' => 'invoice_date',
            'order_number' => ['relation' => 'salesOrder', 'column' => 'order_number'],
            'customer_code' => ['relation' => 'customer', 'column' => 'customer_id'],
            'bill_to' => ['raw' => 'LOWER(COALESCE((SELECT customers.company_name FROM customers WHERE customers.id = invoices.customer_id LIMIT 1), (SELECT sales_orders.bill_to_name FROM sales_orders WHERE sales_orders.id = invoices.sales_order_id LIMIT 1), \'\'))'],
            'subtotal' => 'subtotal',
            'total_discount' => 'total_discount',
            'trade_discount' => 'trade_discount',
            'freight' => 'freight',
            'miscellaneous' => 'miscellaneous',
            'invoice_total' => 'invoice_total',
            'payments' => ['raw' => 'COALESCE(payments_sum_amount, 0)'],
            'credits' => ['raw' => 'COALESCE(credits_sum_amount, 0)'],
            'balance' => ['raw' => '(invoices.invoice_total - COALESCE(payments_sum_amount, 0) - COALESCE(credits_sum_amount, 0))'],
            'status' => 'status',
        ];
    }

    public function updatedFavorite(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
        $today = now()->toDateString();
        if ($this->favorite === 'not_paid') {
            $this->statusFilter = 'NOT PAID';
            // Status is selective; allow all dates so older unpaid invoices stay visible.
            $this->dateFrom = '';
            $this->dateTo = '';
        } elseif ($this->favorite === 'paid') {
            $this->statusFilter = 'PAID';
            $this->dateFrom = $today;
            $this->dateTo = $today;
        } elseif ($this->favorite === 'today') {
            $this->statusFilter = '';
            $this->dateFrom = $today;
            $this->dateTo = $today;
        } elseif ($this->favorite === 'month') {
            $this->statusFilter = '';
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = $today;
        } elseif ($this->favorite === 'all') {
            $this->statusFilter = '';
            $this->dateFrom = '';
            $this->dateTo = '';
        }
    }

    public function updatedDateFrom(): void
    {
        $this->resetDeskList();
    }

    public function updatedDateTo(): void
    {
        $this->resetDeskList();
    }

    public function updatedCreatedByUserId(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
        $this->favorite = match ($this->statusFilter) {
            'NOT PAID' => 'not_paid',
            'PAID' => 'paid',
            default => 'all',
        };
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
        $this->favorite = 'all';
        $this->createdByUserId = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->selectedId = null;
        $this->resetDeskList();
    }

    public function refreshList(): void
    {
        $this->resetDeskList();
    }

    public function resetPage($pageName = 'page'): void
    {
        $this->resetDeskList();
    }

    public function viewSelected(): mixed
    {
        if (! $this->selectedId) {
            return $this->denyInvoiceOpen('Select an invoice first.');
        }

        return $this->openInvoiceView($this->selectedId);
    }

    protected function denyInvoiceOpen(string $message): null
    {
        $this->permissionNotice = $message;
        $this->permissionNoticeTick++;
        $this->js(
            'window.showPermissionToast && window.showPermissionToast('.json_encode($message).');'
            .'window.playPosAlert && window.playPosAlert("warning")'
        );

        return null;
    }

    public function openInvoiceView(int $id): mixed
    {
        if (! (auth()->user()?->canAccessFeature('sales.invoices', 'view') ?? false)) {
            return $this->denyInvoiceOpen('Your role cannot view invoices.');
        }

        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);
        if (! $invoice) {
            return $this->denyInvoiceOpen('Invoice not found.');
        }

        $this->selectedId = $id;

        if ($invoice->sales_order_id) {
            return $this->redirect(
                route('sales.orders.show', $invoice->sales_order_id).'?from=invoices',
                navigate: true
            );
        }

        $this->openInvoicePdf($id);

        return null;
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $invoice = Invoice::query()
            ->with('customer')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $invoice) {
            session()->flash('status', 'Invoice not found.');

            return;
        }

        $this->emailTo = (string) ($invoice->customer?->email ?? '');
        $this->emailSubject = 'Invoice '.$invoice->invoice_number;
        $this->invoiceDeliveryMode = filled($this->emailTo) ? 'both' : 'print';
        $this->showInvoiceDeliveryDialog = true;
    }

    #[On('pos-shortcut-print')]
    public function shortcutPrint(): void
    {
        $this->printSelected();
    }

    public function cancelInvoiceDeliveryDialog(): void
    {
        $this->showInvoiceDeliveryDialog = false;
    }

    public function confirmInvoiceDeliveryDialog(): void
    {
        if (! $this->selectedId) {
            $this->showInvoiceDeliveryDialog = false;

            return;
        }

        $mode = $this->invoiceDeliveryMode;
        $print = in_array($mode, ['print', 'both'], true);
        $email = in_array($mode, ['email', 'both'], true);

        if ($email) {
            $to = trim($this->emailTo);
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                session()->flash('status', 'Enter a valid customer email address.');

                return;
            }

            $invoice = Invoice::query()
                ->where('company_id', auth()->user()->company_id)
                ->find($this->selectedId);

            if (! $invoice) {
                session()->flash('status', 'Invoice not found.');

                return;
            }

            try {
                app(\App\Services\DocumentPdfService::class)->emailInvoice(
                    $invoice,
                    $to,
                    auth()->user(),
                    $this->emailSubject !== '' ? $this->emailSubject : null
                );
                session()->flash('status', 'Invoice emailed to '.$to);
            } catch (\Throwable $e) {
                session()->flash('status', 'Could not email invoice: '.$e->getMessage());

                return;
            }
        }

        $this->showInvoiceDeliveryDialog = false;

        if ($print) {
            $this->openInvoicePdf($this->selectedId);
        }
    }

    public function printPickListSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $invoice = Invoice::query()
            ->with('salesOrder')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $invoice) {
            session()->flash('status', 'Invoice not found.');

            return;
        }

        if (! $invoice->salesOrder) {
            session()->flash('status', 'No sales order linked to this invoice for pick list.');

            return;
        }

        $url = route('sales.invoices.pick-list', $invoice).'?v='.time();
        $this->dispatch('open-invoice-pdf', url: $url);
    }

    public function viewInvoice(int $id): void
    {
        $this->selectedId = $id;
        $this->openInvoicePdf($id);
    }

    protected function openInvoicePdf(int $id): void
    {
        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $invoice) {
            session()->flash('status', 'Invoice not found.');

            return;
        }

        $this->dispatch('open-invoice-pdf', url: route('sales.invoices.pdf', $invoice));
    }

    public function editSelected(): mixed
    {
        if (! $this->selectedId) {
            return $this->denyInvoiceOpen('Select an invoice first.');
        }

        return $this->openInvoiceEdit($this->selectedId);
    }

    public function openInvoiceEdit(int $id): mixed
    {
        $user = auth()->user();
        $invoice = Invoice::query()
            ->with('salesOrder')
            ->where('company_id', $user->company_id)
            ->find($id);
        if (! $invoice) {
            return $this->denyInvoiceOpen('Invoice not found.');
        }

        $this->selectedId = $id;
        $order = $invoice->salesOrder;

        if ($order instanceof SalesOrder) {
            if (! $order->canBeEditedBy($user)) {
                return $this->denyInvoiceOpen('Only the user who created this order can edit it.');
            }

            $held = $order->editLockHolder();
            if ($held && (int) $held['user_id'] !== (int) $user->id && ! $user->isAdmin()) {
                return $this->denyInvoiceOpen(($held['name'] ?? 'Another user').' has this order open.');
            }

            if (! ($user->canAccessFeature('sales.invoices', 'edit') ?? false)) {
                return $this->denyInvoiceOpen('Your role cannot edit invoices.');
            }

            return $this->redirect(
                route('sales.orders.edit', $order).'?from=invoices',
                navigate: true
            );
        }

        if (! ($user->canAccessFeature('sales.invoices', 'edit') ?? false)) {
            return $this->denyInvoiceOpen('Your role cannot edit invoices.');
        }

        $this->editInvoiceId = $id;
        $this->edit_invoice_date = optional($invoice->invoice_date)?->toDateString() ?: now()->toDateString();
        $this->edit_driver = (string) ($invoice->driver ?? '');
        $this->edit_subtotal = number_format((float) $invoice->subtotal, 2, '.', '');
        $this->edit_trade_discount = number_format((float) $invoice->trade_discount, 2, '.', '');
        $this->edit_freight = number_format((float) $invoice->freight, 2, '.', '');
        $this->edit_miscellaneous = number_format((float) $invoice->miscellaneous, 2, '.', '');
        $this->edit_tax = number_format((float) $invoice->tax, 2, '.', '');
        $this->resetErrorBag();

        return null;
    }

    public function closeInvoiceEdit(): void
    {
        $this->editInvoiceId = null;
    }

    public function saveInvoiceEdit(): void
    {
        if (! (auth()->user()?->canAccessFeature('sales.invoices', 'edit') ?? false)) {
            session()->flash('status', 'Your role cannot edit invoices.');

            return;
        }

        $this->validate([
            'edit_invoice_date' => 'required|date',
            'edit_trade_discount' => 'nullable|numeric|min:0',
            'edit_freight' => 'nullable|numeric|min:0',
            'edit_miscellaneous' => 'nullable|numeric|min:0',
            'edit_tax' => 'nullable|numeric|min:0',
        ]);

        if (! $this->editInvoiceId) {
            return;
        }

        try {
            DB::transaction(function () {
                $invoice = Invoice::query()
                    ->with(['payments', 'credits', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($this->editInvoiceId);
                abort_unless((int) $invoice->company_id === (int) auth()->user()->company_id, 403);

                $oldTotal = (float) $invoice->invoice_total;
                $subtotal = (float) $invoice->subtotal;
                $trade = round((float) str_replace(',', '', $this->edit_trade_discount), 4);
                $freight = round((float) str_replace(',', '', $this->edit_freight), 4);
                $misc = round((float) str_replace(',', '', $this->edit_miscellaneous), 4);
                $tax = round((float) str_replace(',', '', $this->edit_tax), 4);
                $newTotal = round($subtotal - $trade + $freight + $misc + $tax, 4);
                $applied = (float) $invoice->payments->sum('amount') + (float) $invoice->credits->sum('amount');
                if ($newTotal + 0.0001 < $applied) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'edit_freight' => 'Invoice total cannot be less than payments and credits already applied ($'.number_format($applied, 2).').',
                    ]);
                }

                $invoice->update([
                    'invoice_date' => $this->edit_invoice_date,
                    'driver' => trim($this->edit_driver) !== '' ? trim($this->edit_driver) : null,
                    'trade_discount' => $trade,
                    'freight' => $freight,
                    'miscellaneous' => $misc,
                    'tax' => $tax,
                    'invoice_total' => $newTotal,
                    'status' => ($newTotal - $applied) <= 0.0001 ? 'PAID' : 'NOT PAID',
                ]);

                $delta = $newTotal - $oldTotal;
                if (abs($delta) > 0.0001 && $invoice->customer_id) {
                    $customer = Customer::query()->lockForUpdate()->find($invoice->customer_id);
                    if ($customer) {
                        $customer->update([
                            'balance' => (float) $customer->balance + $delta,
                        ]);
                    }
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Unable to save invoice. '.$e->getMessage());

            return;
        }

        session()->flash('status', 'Invoice updated.');
        $this->editInvoiceId = null;
    }

    public function markSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $this->openPayments($this->selectedId);
    }

    public function reverseSelectedPayments(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot reverse payments. Enable Payments & Credits permission.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        try {
            $result = app(\App\Services\InvoicePaymentReversalService::class)
                ->reverseInvoice((int) $this->selectedId, (int) auth()->user()->company_id);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Could not reverse payments. '.$e->getMessage());

            return;
        }

        if ($result['rows'] === 0) {
            session()->flash('status', 'This invoice has no payments or credits to reverse.');

            return;
        }
        if (abs($result['payments']) <= 0.0001 && abs($result['credits']) <= 0.0001) {
            session()->flash('status', 'Payments and returned checks removed. Invoice is unpaid again.');

            return;
        }

        $parts = [];
        if (abs($result['payments']) > 0.0001) {
            $parts[] = 'payments $'.number_format($result['payments'], 2);
        }
        if (abs($result['credits']) > 0.0001) {
            $parts[] = 'credits $'.number_format($result['credits'], 2);
        }
        session()->flash('status', 'Reversed '.implode(' and ', $parts).'. Invoice is unpaid again.');
    }

    public function viewSalesOrder(): mixed
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return null;
        }

        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $invoice?->sales_order_id) {
            return $this->denyInvoiceOpen('This invoice has no sales order.');
        }

        return $this->openInvoiceView((int) $invoice->id);
    }

    public function openPaymentsSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $this->openPayments($this->selectedId);
    }

    public function voidSelectedInvoice(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.invoices', 'delete')) {
            session()->flash('status', 'Your role cannot void invoices.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        try {
            DB::transaction(function () {
                $invoice = Invoice::query()
                    ->with(['salesOrder.lines', 'salesOrder.customer', 'payments', 'credits'])
                    ->where('company_id', auth()->user()->company_id)
                    ->lockForUpdate()
                    ->findOrFail($this->selectedId);

                $order = $invoice->salesOrder;
                if ($order) {
                    app(\App\Services\InventoryService::class)->reverseInvoiceStock($order, $invoice);
                    $customer = $order->customer;
                    if ($customer) {
                        $customer->update([
                            'number_of_orders' => max(0, (int) $customer->number_of_orders - 1),
                            'total_sales' => max(0, (float) $customer->total_sales - (float) $invoice->invoice_total),
                            'balance' => (float) $customer->balance - (float) $invoice->invoice_total,
                        ]);
                    }
                    $order->update(['status' => 'New']);
                }

                $invoice->payments()->delete();
                $invoice->credits()->delete();
                $invoice->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Unable to void invoice. '.$e->getMessage());

            return;
        }

        $this->selectedId = null;
        $this->closeModal();
        session()->flash('status', 'Invoice voided. The sales order is open again.');
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action">
            <x-slot:menu>
                <x-action-item label="View Sales Order" kbd="Ctrl+O" wire:click="viewSalesOrder" />
                <x-action-item label="Payments & Credits" sep wire:click="openPaymentsSelected" />
                <x-action-item
                    label="Reverse Payment"
                    wire:click="reverseSelectedPayments"
                    wire:confirm="Reverse all payments and credits on the selected invoice? It will be fully unpaid again and credit memos go back to open."
                    :disabled="! $canEnterPayments"
                    x-bind:disabled="! {{ \Illuminate\Support\Js::from($reversibleIds) }}.includes(Number($wire.selectedId)) || {{ $canEnterPayments ? 'false' : 'true' }}"
                    x-bind:title="{{ \Illuminate\Support\Js::from($reversibleIds) }}.includes(Number($wire.selectedId)) ? 'Reverse all payments on this invoice' : 'Select a paid invoice to reverse its payment'"
                />
                <x-action-item
                    label="Return Items"
                    sep
                    wire:click="openReturnItems"
                    :disabled="! $canEditInvoice"
                    x-bind:disabled="! $wire.selectedId || {{ $canEditInvoice ? 'false' : 'true' }}"
                    title="Return qty from the selected invoice (adds RETURN ITEM lines)"
                />
                <x-action-item label="Print" kbd="Ctrl+P" sep wire:click="printSelected" />
                <x-action-item
                    label="Void Invoice"
                    sep
                    wire:click="voidSelectedInvoice"
                    wire:confirm="Void the selected invoice? The sales order will reopen and stock will be reversed."
                    :disabled="! $canVoidInvoice"
                    x-bind:disabled="! $wire.selectedId || {{ $canVoidInvoice ? 'false' : 'true' }}"
                />
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
                        wire:key="inv-perm-notice-{{ $permissionNoticeTick }}"
                    >{{ $permissionNotice }}</div>
                @endif
                @if (session('status') && trim((string) session('status')) !== trim((string) $permissionNotice))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar" wire:ignore>
                    <label class="desk-toolbar-label" for="invoices-search">Search Invoices:</label>
                    <input
                        id="invoices-search" data-pos-search
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Invoice #, order #, customer, check #…"
                        class="desk-search orders-search-input"
                        aria-label="Search Invoices"
                    />

                    <div class="orders-toolbar-right">
                        <label class="desk-toolbar-label" for="invoices-date-from">From</label>
                        <input id="invoices-date-from" type="date" wire:model.live="dateFrom" class="desk-input" aria-label="Invoice date from" />
                        <label class="desk-toolbar-label" for="invoices-date-to">To</label>
                        <input id="invoices-date-to" type="date" wire:model.live="dateTo" class="desk-input" aria-label="Invoice date to" />
                        <select
                            wire:model.live="createdByUserId"
                            class="desk-select orders-party-select"
                            aria-label="Created by user"
                            title="User who created the order"
                        >
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
                            id="invoice-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Filter by status"
                        >
                            <option value="">All</option>
                            <option value="NOT PAID">NOT PAID</option>
                            <option value="PAID">PAID</option>
                        </select>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($listShown) }}{{ $listHasMore ? '+' : '' }} records</span>
                </div>

                <x-desk-scroll-grid :has-more="$listHasMore" class="desk-grid-responsive">
                    <table class="desk-table desk-table-fit desk-list-table desk-table-resizable" data-col-resize="invoices-list" data-excel-grid data-excel-copy-all>
                        <colgroup></colgroup>
                        <thead>
                            <tr>
                                <th class="text-center" data-excel-skip></th>
                                <x-desk-list-col-headers :catalog="$listColumnCatalog" :keys="$visibleColumnKeys" />
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $inv)
                                <tr
                                    wire:key="inv-row-{{ $inv->id }}"
                                    x-on:click="$wire.selectedId = {{ $inv->id }}; $wire.selectRow({{ $inv->id }})"
                                    wire:dblclick="openInvoiceEdit({{ $inv->id }})"
                                    class="cursor-pointer"
                                    :class="{ 'is-selected': Number($wire.selectedId) === {{ $inv->id }} || {{ $modalInvoiceId === $inv->id ? 'true' : 'false' }} }"
                                >
                                    <td class="text-center" data-excel-skip x-on:click.stop="$wire.selectedId = {{ $inv->id }}; $wire.selectRow({{ $inv->id }})">
                                        <input
                                            type="radio"
                                            name="invoice_select"
                                            value="{{ $inv->id }}"
                                            :checked="Number($wire.selectedId) === {{ $inv->id }}"
                                            aria-label="Select invoice {{ $inv->invoice_number }}"
                                        />
                                    </td>
                                    @foreach ($visibleColumnKeys as $colKey)
                                        @include('livewire.pages.sales.invoices.partials.list-cell', ['inv' => $inv, 'colKey' => $colKey])
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="{{ $columnColspan }}">No invoices. Invoice a sales order from the Orders list.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="desk-list-cards" aria-label="Invoices">
                        @forelse ($invoices as $inv)
                            <article
                                class="desk-list-card"
                                :class="{ 'is-selected': Number($wire.selectedId) === {{ $inv->id }} || {{ $modalInvoiceId === $inv->id ? 'true' : 'false' }} }"
                                x-on:click="$wire.selectedId = {{ $inv->id }}; $wire.selectRow({{ $inv->id }})"
                                wire:dblclick="openInvoiceEdit({{ $inv->id }})"
                            >
                                <div class="desk-list-card__top">
                                    <a href="{{ route('sales.invoices.pdf', $inv) }}" target="_blank" rel="noopener" wire:click.stop class="desk-list-card__id">{{ $inv->invoice_number }}</a>
                                    <span @class([
                                        'desk-pill',
                                        'desk-pill-new' => $inv->status === 'NOT PAID',
                                        'desk-pill-invoiced' => $inv->status === 'PAID',
                                        'desk-pill-muted' => ! in_array($inv->status, ['NOT PAID', 'PAID'], true),
                                    ])>{{ $inv->status }}</span>
                                </div>
                                <div class="desk-list-card__meta">
                                    @php 
                                        $src = (string) ($inv->salesOrder?->order_source ?? 'pos');
                                        $sourceLabel = $src === 'pos' ? 'POS' : \App\Models\SalesOrder::labelForSource($src);
                                    @endphp
                                    <span @class([
                                        'desk-pill',
                                        'desk-pill-muted' => $src === 'pos',
                                        'desk-pill-new' => $src === 'sales',
                                        'desk-pill-invoiced' => $src === 'customer',
                                    ]) @if ($src === \App\Models\SalesOrder::SOURCE_ECOMMERCE) style="{{ \App\Models\SalesOrder::ECOMMERCE_PILL_STYLE }}" @endif>{{ $sourceLabel }}</span>
                                    <span>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</span>
                                    @if ($inv->salesOrder?->order_number)
                                        <span>SO {{ $inv->salesOrder->order_number }}</span>
                                    @endif
                                </div>
                                <div class="desk-list-card__name">{{ $inv->customer?->company_name ?: $inv->salesOrder?->bill_to_name ?: '—' }}</div>
                                <div class="desk-list-card__sub">
                                    {{ $inv->customer?->customer_id }}
                                    @if ($inv->salesOrder?->isCustomerPlaced())
                                        @php
                                            $creatorName = $inv->customer?->company_name ?: $inv->customer?->contact;
                                        @endphp
                                        @if ($creatorName)
                                            · By: {{ $creatorName }}
                                            @if ($inv->customer?->portal_email)
                                                ({{ $inv->customer->portal_email }})
                                            @endif
                                        @endif
                                    @elseif ($inv->salesOrder?->createdBy?->name)
                                        · By: {{ $inv->salesOrder->createdBy->name }}
                                    @endif
                                </div>
                                <div class="desk-list-card__foot">
                                    <span>Total <strong class="tabular-nums">${{ number_format($inv->invoice_total, 2) }}</strong></span>
                                    <span>Bal <strong class="tabular-nums">${{ number_format($inv->invoice_balance, 2) }}</strong></span>
                                </div>
                            </article>
                        @empty
                            <div class="desk-list-card is-empty">No invoices. Invoice a sales order from the Orders list.</div>
                        @endforelse
                    </div>
                </x-desk-scroll-grid>

                <x-record-count :count="$listShown">
                    <x-desk-load-more :has-more="$listHasMore" />
                </x-record-count>
            </div>

            {{-- Right icons: view, print, pick list, edit, payment, void, refresh --}}
            <aside class="desk-rail" aria-label="Invoice actions">
                <x-desk-fields-rail-btn />
                <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="{{ $canViewInvoice ? 'View invoice' : 'No invoice view permission' }}" aria-label="View invoice" :disabled="! $wire.selectedId">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print invoice (F10)" aria-label="Print invoice" data-pos-print :disabled="! $wire.selectedId">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="printPickListSelected" class="desk-rail-btn" title="Print pick list" aria-label="Print pick list" :disabled="! $wire.selectedId">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="3" y="2" width="10" height="12" rx="1"/>
                        <path d="M5.5 5h5M5.5 7.5h5M5.5 10h3"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="{{ $canEditInvoice ? 'Edit invoice' : 'No invoice edit permission' }}" aria-label="Edit invoice" :disabled="! $wire.selectedId">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button type="button" wire:click="markSelected" class="desk-rail-btn" title="{{ $canEnterPayments ? 'Enter payment' : 'No payment permission' }}" aria-label="Enter payment" :disabled="! $wire.selectedId || {{ $canEnterPayments ? 'false' : 'true' }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/>
                        <path d="M5 8.2l2.1 2.1L11.2 6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="voidSelectedInvoice"
                    wire:confirm="Void the selected invoice? The sales order will reopen and stock will be reversed."
                    class="desk-rail-btn desk-rail-btn-danger"
                    title="{{ $canVoidInvoice ? 'Void invoice' : 'No void permission' }}"
                    aria-label="Void invoice"
                    :disabled="! $wire.selectedId || {{ $canVoidInvoice ? 'false' : 'true' }}"
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                        <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
            </aside>
        </div>
    </div>

    @include('livewire.partials.payments-credits-modal')

    @if ($editInvoice)
        <div class="desk-modal-backdrop" wire:click.self="closeInvoiceEdit" role="dialog" aria-modal="true" aria-label="Edit invoice">
            <div class="desk-modal desk-modal-lg">
                <div class="desk-modal-head">
                    <span>Edit invoice {{ $editInvoice->invoice_number }}</span>
                    <button type="button" wire:click="closeInvoiceEdit" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form wire:submit="saveInvoiceEdit" class="desk-modal-body space-y-3">
                    <p class="item-hint" style="margin:0">
                        Line items and stock stay as invoiced. Change date, driver, freight, tax, and other header amounts.
                        @if ($editInvoice->salesOrder?->order_number)
                            Order {{ $editInvoice->salesOrder->order_number }}.
                        @endif
                    </p>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_invoice_date">Invoice date</label>
                        <input id="edit_invoice_date" type="date" wire:model="edit_invoice_date" class="so-input" />
                    </div>
                    @error('edit_invoice_date') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_driver">Driver</label>
                        <input id="edit_driver" type="text" wire:model="edit_driver" class="so-input" autocomplete="off" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl">Subtotal</label>
                        <input type="text" class="so-input text-right" value="${{ number_format((float) $edit_subtotal, 2) }}" readonly />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_trade_discount">Trade discount</label>
                        <input id="edit_trade_discount" type="text" inputmode="decimal" wire:model.live="edit_trade_discount" class="so-input text-right" />
                    </div>
                    @error('edit_trade_discount') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_freight">Freight</label>
                        <input id="edit_freight" type="text" inputmode="decimal" wire:model.live="edit_freight" class="so-input text-right" />
                    </div>
                    @error('edit_freight') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_miscellaneous">Miscellaneous</label>
                        <input id="edit_miscellaneous" type="text" inputmode="decimal" wire:model.live="edit_miscellaneous" class="so-input text-right" />
                    </div>
                    @error('edit_miscellaneous') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="edit_tax">Tax</label>
                        <input id="edit_tax" type="text" inputmode="decimal" wire:model.live="edit_tax" class="so-input text-right" />
                    </div>
                    @error('edit_tax') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl">Invoice total</label>
                        <strong class="tabular-nums">${{ number_format((float) $editPreviewTotal, 2) }}</strong>
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end;gap:.5rem">
                        <button type="button" wire:click="closeInvoiceEdit" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($returnInvoice)
        @php
            $retBalance = (float) $returnInvoice->invoice_balance;
            $retNewTotal = (float) $returnInvoice->invoice_total - $returnPreview;
            $retNewBalance = $retBalance - $returnPreview;
            $retFmt = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.');
        @endphp
        <div class="desk-modal-backdrop" wire:click.self="closeReturnItems" role="dialog" aria-modal="true" aria-label="Return items">
            <div class="desk-modal desk-modal-lg" style="max-width:960px">
                <div class="desk-modal-head">
                    <span>Return items — Invoice {{ $returnInvoice->invoice_number }}
                        @if ($returnInvoice->customer) · {{ $returnInvoice->customer->company_name }} @endif
                    </span>
                    <button type="button" wire:click="closeReturnItems" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form wire:submit="saveReturnItems" wire:confirm="Save this return? RETURN ITEM lines will be added to the invoice and stock goes back in." class="desk-modal-body space-y-3">
                    <p class="item-hint" style="margin:0">
                        Original lines stay on the invoice. Each return is added as a negative line noted <strong>RETURN ITEM</strong>,
                        stock goes back in, and the invoice total drops. If the invoice is already paid, the customer balance goes negative (refund due).
                    </p>

                    <div style="max-height:52vh;overflow:auto;border:1px solid var(--desk-border, #cbd5e1)">
                        <table class="desk-table w-full" style="font-size:.85rem">
                            <thead>
                                <tr>
                                    <th class="text-left">Item</th>
                                    <th class="text-left">Description</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Sold</th>
                                    <th class="text-right">Returned</th>
                                    <th class="text-right">Can return</th>
                                    <th class="text-right" style="width:110px">Return qty</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($returnRows as $row)
                                    @php $ln = $row['line']; @endphp
                                    <tr wire:key="ret-{{ $ln->id }}" @class(['opacity-50' => $row['returnable'] <= 0])>
                                        <td>{{ $ln->item_code }}</td>
                                        <td>{{ $ln->description }}</td>
                                        <td class="text-right tabular-nums">${{ number_format((float) $ln->price, 2) }}</td>
                                        <td class="text-right tabular-nums">{{ $retFmt($row['sold']) }}</td>
                                        <td class="text-right tabular-nums">{{ $row['returned'] > 0 ? $retFmt($row['returned']) : '' }}</td>
                                        <td class="text-right tabular-nums">{{ $retFmt($row['returnable']) }}</td>
                                        <td class="text-right">
                                            @if ($row['returnable'] > 0)
                                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="returnQty.{{ $ln->id }}"
                                                    class="so-input text-right" style="width:90px" placeholder="0" autocomplete="off" />
                                            @endif
                                        </td>
                                        <td>
                                            @if ($row['returnable'] > 0)
                                                <button type="button" class="desk-btn" style="padding:.1rem .5rem"
                                                    wire:click="returnFullLine({{ $ln->id }}, '{{ $retFmt($row['returnable']) }}')">Full</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center">No lines to return.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="return_note">Note / reason</label>
                        <input id="return_note" type="text" wire:model="returnNote" class="so-input" maxlength="200" placeholder="e.g. damaged, wrong item" autocomplete="off" />
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(4,auto);gap:.25rem 1.5rem;justify-content:end" class="tabular-nums">
                        <span>Invoice total</span><strong>${{ number_format((float) $returnInvoice->invoice_total, 2) }}</strong>
                        <span>Return amount</span><strong style="color:#b91c1c">-${{ number_format($returnPreview, 2) }}</strong>
                        <span>New total</span><strong>${{ number_format($retNewTotal, 2) }}</strong>
                        <span>New balance</span>
                        <strong @style(['color:#b91c1c' => $retNewBalance < -0.004])>
                            {{ $retNewBalance < -0.004 ? '-$'.number_format(abs($retNewBalance), 2).' (refund due)' : '$'.number_format($retNewBalance, 2) }}
                        </strong>
                    </div>

                    @error('return') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror

                    <div class="entity-footer-actions" style="justify-content:space-between;gap:.5rem">
                        <button type="button" wire:click="returnAllLines" class="desk-btn">Return all</button>
                        <div style="display:flex;gap:.5rem">
                            <button type="button" wire:click="closeReturnItems" class="desk-btn">Cancel</button>
                            <button type="submit" class="desk-btn desk-btn-primary">Save return</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showInvoiceDeliveryDialog)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="cancelInvoiceDeliveryDialog" role="dialog" aria-modal="true" aria-labelledby="inv-delivery-title">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span id="inv-delivery-title">Invoice delivery</span>
                    <button type="button" wire:click="cancelInvoiceDeliveryDialog" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body space-y-3">
                    <p class="inv-email-note" style="margin:0">Print the invoice, email it to the customer, or both.</p>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="print" />
                        <span>Print only</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="email" />
                        <span>Email only</span>
                    </label>
                    <label class="so-print-opt">
                        <input type="radio" wire:model.live="invoiceDeliveryMode" value="both" />
                        <span>Print &amp; email</span>
                    </label>
                    @if (in_array($invoiceDeliveryMode, ['email', 'both'], true))
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="inv-delivery-email">To</label>
                            <input id="inv-delivery-email" type="email" wire:model="emailTo" class="so-input" placeholder="customer@email.com" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="inv-delivery-subject">Subject</label>
                            <input id="inv-delivery-subject" type="text" wire:model="emailSubject" class="so-input" />
                        </div>
                    @endif
                    <div class="entity-footer-actions" style="justify-content:flex-end;gap:0.5rem">
                        <button type="button" wire:click="cancelInvoiceDeliveryDialog" class="desk-btn">Cancel</button>
                        <button type="button" wire:click="confirmInvoiceDeliveryDialog" class="desk-btn desk-btn-primary">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <x-desk-column-picker :catalog="$listColumnCatalog" :visible-keys="$visibleColumnKeys" locked="invoice_number" />
</div>

@script
<script>
    $wire.on('open-invoice-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
@endscript
