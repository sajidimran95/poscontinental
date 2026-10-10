<?php

use App\Livewire\Concerns\ManagesInvoicePaymentsModal;
use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Payments')] class extends Component
{
    use SortsDeskList;
    use PaginatesDeskLists;
    use ManagesInvoicePaymentsModal;

    public ?int $customer_id = null;

    /** All payments & credits list */
    public string $plSearch = '';

    /** '' | payment | credit | returned */
    public string $plType = '';

    public string $plFrom = '';

    public string $plTo = '';

    /** @var list<string> "payment:{id}" / "credit:{id}" */
    public array $plKeys = [];

    public bool $plOpen = false;

    public string $customerSearch = '';

    public bool $showCustomerBrowse = false;

    /** @var array<int, bool> */
    public array $selected = [];

    public string $pay_amount = '0';

    public string $pay_method = 'Cash';

    public string $pay_date = '';

    public string $pay_check_number = '';

    public string $checkSearch = '';

    /** When true, open credit memos are applied to selected invoices before cash. */
    public bool $apply_open_credits = true;

    public function mount(): void
    {
        $this->pay_date = now()->toDateString();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $invoices = collect();
        $openCredits = collect();
        $openCreditTotal = 0.0;
        if ($this->customer_id) {
            $invoices = Invoice::query()
                ->with(['payments', 'credits', 'salesOrder'])
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->get()
                ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
                ->values();
            $invoices = $this->sortCollection($invoices, [
                'inv_number' => 'invoice_number',
                'inv_date' => fn ($inv) => optional($inv->invoice_date)?->format('Y-m-d') ?? '',
                'inv_order' => fn ($inv) => (string) ($inv->salesOrder?->order_number ?? ''),
                'inv_total' => fn ($inv) => (float) $inv->invoice_total,
                'inv_balance' => fn ($inv) => (float) $inv->invoice_balance,
            ], 'inv_date', 'desc');

            $openCredits = CreditMemo::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->where('status', 'Open')
                ->orderByDesc('memo_date')
                ->orderByDesc('id')
                ->get()
                ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                ->values();
            $openCredits = $this->sortCollection($openCredits, [
                'cr_number' => 'memo_number',
                'cr_date' => fn ($m) => optional($m->memo_date)?->format('Y-m-d') ?? '',
                'cr_reason' => 'reason',
                'cr_remaining' => fn ($m) => (float) $m->remaining_amount,
            ], 'cr_date', 'desc');

            $openCreditTotal = round((float) $openCredits->sum(fn (CreditMemo $m) => $m->remaining_amount), 2);
        }

        $checkedTotal = round((float) $invoices
            ->filter(fn ($inv) => ! empty($this->selected[$inv->id]))
            ->sum(fn ($inv) => $inv->invoice_balance), 2);

        $creditTowardChecked = $this->apply_open_credits
            ? round(min($openCreditTotal, $checkedTotal), 2)
            : 0.0;
        $cashNeeded = round(max(0, $checkedTotal - $creditTowardChecked), 2);

        $payAmount = max(0, (float) $this->pay_amount);
        $allocationHint = null;
        if ($checkedTotal > 0.0001) {
            if ($creditTowardChecked > 0.0001 && $payAmount <= 0.0001 && $cashNeeded <= 0.0001) {
                $allocationHint = [
                    'type' => 'credit_only',
                    'credit' => $creditTowardChecked,
                    'credit_left' => round($openCreditTotal - $creditTowardChecked, 2),
                ];
            } elseif ($creditTowardChecked > 0.0001 || $payAmount > 0) {
                $coversDue = round($creditTowardChecked + min($payAmount, $cashNeeded), 2);
                $overpay = round(max(0, $payAmount - $cashNeeded), 2);
                $left = round(max(0, $checkedTotal - $coversDue), 2);

                if ($left > 0.0001) {
                    $allocationHint = [
                        'type' => 'partial',
                        'credit' => $creditTowardChecked,
                        'applied' => $coversDue,
                        'left' => $left,
                    ];
                } elseif ($overpay > 0.0001) {
                    $allocationHint = [
                        'type' => 'overpay',
                        'credit' => $creditTowardChecked,
                        'applied' => $checkedTotal,
                        'credit_new' => $overpay,
                    ];
                } else {
                    $allocationHint = [
                        'type' => 'exact',
                        'credit' => $creditTowardChecked,
                        'applied' => $checkedTotal,
                    ];
                }
            }
        }

        $checkHits = collect();
        $checkTerm = trim($this->checkSearch);
        if ($checkTerm !== '') {
            $like = '%'.$checkTerm.'%';
            $checkHits = InvoicePayment::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->where(function ($q) use ($like) {
                    $q->where('check_number', 'like', $like)
                        ->orWhere(function ($inner) use ($like) {
                            $inner->whereRaw('LOWER(payment_method) = ?', ['check'])
                                ->where('comments', 'like', $like);
                        });
                })
                ->with(['invoice.customer', 'invoice.salesOrder'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
            $checkHits = $this->sortCollection($checkHits, [
                'chk_number' => 'check_number',
                'chk_date' => fn ($hit) => optional($hit->payment_date)?->format('Y-m-d') ?? '',
                'chk_customer' => fn ($hit) => mb_strtolower((string) ($hit->invoice?->customer?->company_name ?? '')),
                'chk_invoice' => fn ($hit) => (string) ($hit->invoice?->invoice_number ?? ''),
                'chk_amount' => fn ($hit) => (float) $hit->amount,
            ], 'chk_date', 'desc');
        }

        $term = trim($this->customerSearch);
        $loadCustomers = $this->showCustomerBrowse || ($term !== '' && ! $this->customer_id);
        $browseCustomers = $loadCustomers
            ? $this->customerLookupQuery($companyId, $term, $this->showCustomerBrowse ? 60 : 20)
            : collect();

        $selectedCustomer = $this->customer_id
            ? Customer::query()
                ->where('company_id', $companyId)
                ->find($this->customer_id, ['id', 'customer_id', 'company_name', 'contact'])
            : null;

        $scroll = ($this->plOpen && $this->customer_id)
            ? $this->scrollDeskList($this->paymentListQuery($companyId))
            : ['rows' => collect(), 'hasMore' => false, 'shown' => 0];
        $payList = $scroll['rows'];
        $payListHasMore = $scroll['hasMore'];
        $payListShown = $scroll['shown'];

        $bouncedIds = [];
        $checkInvoiceIds = $payList
            ->filter(fn ($r) => $r->kind === 'payment' && InvoicePayment::isCheckMethod($r->method))
            ->pluck('invoice_id')
            ->unique()
            ->values()
            ->all();
        if ($checkInvoiceIds !== []) {
            $bouncedIds = InvoicePayment::query()
                ->whereIn('invoice_id', $checkInvoiceIds)
                ->where('payment_method', InvoicePayment::RETURNED_CHECK_METHOD)
                ->pluck('comments')
                ->map(fn ($c) => preg_match('/#(\d+)/', (string) $c, $m) ? (int) $m[1] : 0)
                ->filter()
                ->values()
                ->all();
        }

        return [
            'payList' => $payList,
            'payListHasMore' => $payListHasMore,
            'payListShown' => $payListShown,
            'bouncedIds' => $bouncedIds,
            'selectedCustomer' => $selectedCustomer,
            'browseCustomers' => $browseCustomers,
            'openInvoices' => $invoices,
            'openCredits' => $openCredits,
            'openCreditTotal' => $openCreditTotal,
            'checkedTotal' => $checkedTotal,
            'creditTowardChecked' => $creditTowardChecked,
            'cashNeeded' => $cashNeeded,
            'allocationHint' => $allocationHint,
            'checkHits' => $checkHits,
            'isCheckMethod' => InvoicePayment::isCheckMethod($this->pay_method),
            'canEnterPayments' => auth()->user()?->canAccessFeature('sales.payments', 'edit') ?? false,
        ] + $this->paymentsModalViewData((int) $companyId);
    }

    protected function deskSortMap(): array
    {
        return [
            'chk_number' => 'check_number',
            'chk_date' => 'payment_date',
            'chk_customer' => 'id',
            'chk_invoice' => 'id',
            'chk_amount' => 'amount',
            'inv_number' => 'invoice_number',
            'inv_date' => 'invoice_date',
            'inv_order' => 'id',
            'inv_total' => 'invoice_total',
            'inv_balance' => 'id',
            'cr_number' => 'memo_number',
            'cr_date' => 'memo_date',
            'cr_reason' => 'reason',
            'cr_remaining' => 'amount',
            'pl_date' => 'row_date',
            'pl_customer' => 'customer_name',
            'pl_invoice' => 'invoice_number',
            'pl_method' => 'method',
            'pl_ref' => 'ref',
            'pl_amount' => 'amount',
        ];
    }

    /** One list of every saved payment and applied credit (newest first). */
    protected function paymentListQuery(int $companyId)
    {
        $customerId = $this->customer_id;
        $from = $this->plFrom;
        $to = $this->plTo;

        $payments = DB::table('invoice_payments as ip')
            ->join('invoices as i', 'i.id', '=', 'ip.invoice_id')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $companyId)
            ->when($customerId, fn ($q) => $q->where('i.customer_id', $customerId))
            ->when($from !== '', fn ($q) => $q->where('ip.payment_date', '>=', $from))
            ->when($to !== '', fn ($q) => $q->where('ip.payment_date', '<=', $to))
            ->when($this->plType === 'returned', fn ($q) => $q->where('ip.payment_method', InvoicePayment::RETURNED_CHECK_METHOD))
            ->selectRaw("'payment' as kind, ip.id as row_id, ip.invoice_id, ip.payment_date as row_date,
                ip.payment_method as method, ip.check_number as ref, ip.amount, ip.comments,
                i.invoice_number, i.customer_id, c.customer_id as customer_code, c.company_name as customer_name,
                ip.created_at");

        $credits = DB::table('invoice_credits as ic')
            ->join('invoices as i', 'i.id', '=', 'ic.invoice_id')
            ->leftJoin('credit_memos as cm', 'cm.id', '=', 'ic.credit_memo_id')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $companyId)
            ->when($customerId, fn ($q) => $q->where('i.customer_id', $customerId))
            ->when($from !== '', fn ($q) => $q->whereRaw('COALESCE(cm.memo_date, DATE(ic.created_at)) >= ?', [$from]))
            ->when($to !== '', fn ($q) => $q->whereRaw('COALESCE(cm.memo_date, DATE(ic.created_at)) <= ?', [$to]))
            ->selectRaw("'credit' as kind, ic.id as row_id, ic.invoice_id, COALESCE(cm.memo_date, DATE(ic.created_at)) as row_date,
                'Credit Memo' as method, cm.memo_number as ref, ic.amount, cm.reason as comments,
                i.invoice_number, i.customer_id, c.customer_id as customer_code, c.company_name as customer_name,
                ic.created_at");

        $source = match ($this->plType) {
            'payment', 'returned' => $payments,
            'credit' => $credits,
            default => $payments->unionAll($credits),
        };

        $query = DB::query()->fromSub($source, 'pl');

        $term = trim($this->plSearch);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term) {
                $q->where('customer_name', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('invoice_number', 'like', $like)
                    ->orWhere('ref', 'like', $like)
                    ->orWhere('method', 'like', $like)
                    ->orWhere('comments', 'like', $like);
                $num = str_replace([',', '$'], '', $term);
                if (is_numeric($num)) {
                    $q->orWhereRaw('ABS(amount - ?) < 0.005', [(float) $num]);
                }
            });
        }

        $map = $this->deskSortMap();
        $useSort = str_starts_with($this->sortField, 'pl_') && isset($map[$this->sortField]);
        $query->orderBy(
            $useSort ? $map[$this->sortField] : 'row_date',
            $useSort && $this->sortDir === 'asc' ? 'asc' : 'desc'
        );

        return $query->orderByDesc('created_at')->orderByDesc('row_id');
    }

    /** @return list<array{0: string, 1: int}> */
    protected function plSelections(): array
    {
        $out = [];
        foreach ($this->plKeys as $key) {
            if (preg_match('/^(payment|credit):(\d+)$/', (string) $key, $m)) {
                $out[] = [$m[1], (int) $m[2]];
            }
        }

        return $out;
    }

    /** Exactly one selected row, or null. */
    protected function plSelection(): ?array
    {
        $sel = $this->plSelections();

        return count($sel) === 1 ? $sel[0] : null;
    }

    /** @return list<string> */
    protected function plVisibleKeys(): array
    {
        if (! $this->customer_id) {
            return [];
        }

        return $this->scrollDeskList($this->paymentListQuery((int) auth()->user()->company_id))['rows']
            ->map(fn ($r) => $r->kind.':'.$r->row_id)
            ->values()
            ->all();
    }

    protected function plInvoiceIdFor(string $kind, int $id): int
    {
        return $kind === 'credit'
            ? (int) InvoiceCredit::query()->whereKey($id)->value('invoice_id')
            : (int) InvoicePayment::query()->whereKey($id)->value('invoice_id');
    }

    protected function plCanEdit(): bool
    {
        if (auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            return true;
        }
        session()->flash('status', 'Your role cannot change payments. Enable Payments & Credits permission.');

        return false;
    }

    public function plToggle(): void
    {
        $this->plOpen = ! $this->plOpen;
        $this->plKeys = [];
        $this->resetDeskList();
    }

    public function plSelect(string $key): void
    {
        if (in_array($key, $this->plKeys, true)) {
            $this->plKeys = array_values(array_diff($this->plKeys, [$key]));
        } else {
            $this->plKeys[] = $key;
        }
    }

    public function plSelectAll(): void
    {
        $visible = $this->plVisibleKeys();
        $allSelected = $visible !== [] && array_diff($visible, $this->plKeys) === [];
        $this->plKeys = $allSelected ? [] : $visible;
    }

    public function updatedPlSearch(): void
    {
        $this->resetDeskList();
        $this->plKeys = [];
    }

    public function updatedPlType(): void
    {
        $this->resetDeskList();
        $this->plKeys = [];
    }

    public function updatedPlFrom(): void
    {
        $this->resetDeskList();
    }

    public function updatedPlTo(): void
    {
        $this->resetDeskList();
    }

    public function plClearFilters(): void
    {
        $this->plSearch = '';
        $this->plType = '';
        $this->plFrom = '';
        $this->plTo = '';
        $this->plKeys = [];
        $this->resetDeskList();
    }

    public function plRefresh(): void
    {
        $this->resetDeskList();
    }

    public function plVoidSelected(): void
    {
        $sel = $this->plSelections();
        if ($sel === []) {
            session()->flash('status', 'Select one or more payments or credits in the list first.');

            return;
        }
        if (! $this->plCanEdit()) {
            return;
        }

        $svc = app(\App\Services\InvoicePaymentReversalService::class);
        $companyId = (int) auth()->user()->company_id;
        // Originals before returned-check lines, so a check and its return in one selection both clear cleanly.
        usort($sel, fn ($a, $b) => strcmp($a[0], $b[0]) ?: $a[1] <=> $b[1]);

        $done = 0;
        $total = 0.0;
        $errors = [];
        foreach ($sel as [$kind, $id]) {
            $exists = $kind === 'credit'
                ? InvoiceCredit::query()->whereKey($id)->exists()
                : InvoicePayment::query()->whereKey($id)->exists();
            if (! $exists) {
                continue;
            }
            try {
                $total += $kind === 'credit'
                    ? $svc->removeCredit($id, $companyId)
                    : $svc->removePayment($id, $companyId);
                $done++;
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $e->getMessage();
            }
        }

        $this->plKeys = [];
        $msg = $done === 1 ? '1 row voided' : $done.' rows voided';
        $msg .= ' ($'.number_format($total, 2).' owed again). Invoice balances restored.';
        if ($errors !== []) {
            $msg .= ' '.count($errors).' failed: '.$errors[0];
        }
        session()->flash('status', $msg);
    }

    public function plReturnCheck(): void
    {
        $ids = collect($this->plSelections())
            ->filter(fn ($s) => $s[0] === 'payment')
            ->pluck(1)
            ->filter(fn ($id) => InvoicePayment::isCheckMethod(InvoicePayment::query()->whereKey($id)->value('payment_method')))
            ->values();
        if ($ids->isEmpty()) {
            session()->flash('status', 'Select one or more check payments in the list first.');

            return;
        }
        if (! $this->plCanEdit()) {
            return;
        }

        $svc = app(\App\Services\InvoicePaymentReversalService::class);
        $companyId = (int) auth()->user()->company_id;
        $done = 0;
        $total = 0.0;
        $skipped = 0;
        foreach ($ids as $id) {
            try {
                $total += $svc->returnCheck((int) $id, $companyId, (int) auth()->id());
                $done++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $this->plKeys = [];
        $fee = InvoicePayment::RETURNED_CHECK_FEE;
        $msg = $done.' check'.($done === 1 ? '' : 's').' returned. $'.number_format($total, 2)
            .' owed again plus $'.number_format($fee * $done, 2).' returned-check fees (Miscellaneous).';
        if ($skipped) {
            $msg .= ' '.$skipped.' skipped (already returned).';
        }
        session()->flash('status', $msg);
    }

    public function plReverseInvoice(): void
    {
        $sel = $this->plSelections();
        if ($sel === []) {
            session()->flash('status', 'Select one or more payments or credits in the list first.');

            return;
        }
        if (! $this->plCanEdit()) {
            return;
        }

        $invoiceIds = collect($sel)
            ->map(fn ($s) => $this->plInvoiceIdFor($s[0], $s[1]))
            ->filter()
            ->unique()
            ->values();
        if ($invoiceIds->isEmpty()) {
            session()->flash('status', 'Selected rows no longer exist.');

            return;
        }

        $companyId = (int) auth()->user()->company_id;
        $svc = app(\App\Services\InvoicePaymentReversalService::class);
        $reversed = 0;
        foreach ($invoiceIds as $invoiceId) {
            try {
                if ($svc->reverseInvoice((int) $invoiceId, $companyId)['rows'] > 0) {
                    $reversed++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $numbers = Invoice::query()->whereIn('id', $invoiceIds)->pluck('invoice_number')->implode(', ');
        $this->plKeys = [];
        session()->flash('status', $reversed === 0
            ? 'Nothing to reverse on invoice '.$numbers.'.'
            : 'All payments and credits reversed on '.$reversed.' invoice'.($reversed === 1 ? '' : 's').' ('.$numbers.'). Unpaid again.');
    }

    public function plOpenInvoice(): mixed
    {
        $sel = $this->plSelection();
        if (! $sel) {
            session()->flash('status', 'Select exactly one row to open its invoice.');

            return null;
        }

        $invoiceId = $this->plInvoiceIdFor($sel[0], $sel[1]);

        return $invoiceId
            ? $this->redirect(route('sales.invoices.index', ['pay' => $invoiceId]), navigate: true)
            : null;
    }

    public function plPrintReceipt(): void
    {
        $sel = $this->plSelection();
        if (! $sel || $sel[0] !== 'payment') {
            session()->flash('status', 'Select exactly one payment to print its receipt.');

            return;
        }

        $payment = InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->find($sel[1]);
        if (! $payment) {
            session()->flash('status', 'Payment not found.');

            return;
        }

        $this->js('window.open('.json_encode(route('sales.invoices.receipt', [$payment->invoice_id, $payment->id])).', "_blank")');
    }

    public function openCustomerBrowse(): void
    {
        $this->showCustomerBrowse = true;
    }

    public function updatedCustomerSearch(): void
    {
        if ($this->customer_id) {
            $this->customer_id = null;
            $this->updatedCustomerId();
        }
    }

    private function customerLookupQuery(int $companyId, string $term, int $limit)
    {
        return Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('customer_id', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('contact', 'like', $like)
                        ->orWhere('telephone', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('city', 'like', $like);
                });
            })
            ->orderBy('company_name')
            ->limit($limit)
            ->get(['id', 'customer_id', 'company_name', 'contact', 'telephone', 'mobile', 'city', 'state']);
    }

    public function closeCustomerBrowse(): void
    {
        $this->showCustomerBrowse = false;
    }

    public function pickCustomer(int $customerId): void
    {
        $this->customer_id = $customerId;
        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($customerId, ['id', 'customer_id', 'company_name', 'contact']);
        $this->customerSearch = $customer
            ? trim(($customer->customer_id ? $customer->customer_id.' — ' : '').($customer->company_name ?: $customer->contact))
            : '';
        $this->showCustomerBrowse = false;
        $this->updatedCustomerId();
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->updatedCustomerId();
    }

    public function updatedCustomerId(): void
    {
        $this->resetDeskList();
        $this->plKeys = [];
        $this->plOpen = false;
        $this->selected = [];
        $this->pay_amount = '0';
        $this->pay_check_number = '';
        $this->apply_open_credits = true;
    }

    public function updatedSelected(): void
    {
        $this->syncPayAmountToCashNeeded();
    }

    public function updatedApplyOpenCredits(): void
    {
        $this->syncPayAmountToCashNeeded();
    }

    private function syncPayAmountToCashNeeded(): void
    {
        $companyId = auth()->user()->company_id;
        $checked = 0.0;
        foreach ($this->selected as $id => $on) {
            if (! $on) {
                continue;
            }
            $inv = Invoice::query()->with(['payments', 'credits'])->find($id);
            if ($inv) {
                $checked += (float) $inv->invoice_balance;
            }
        }
        $checked = round($checked, 2);

        $creditAvail = 0.0;
        if ($this->apply_open_credits && $this->customer_id) {
            $creditAvail = round((float) CreditMemo::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->where('status', 'Open')
                ->get()
                ->sum(fn (CreditMemo $m) => $m->remaining_amount), 2);
        }

        $cashNeeded = round(max(0, $checked - min($creditAvail, $checked)), 2);
        $this->pay_amount = number_format($cashNeeded, 2, '.', '');
    }

    public function selectAllOpen(): void
    {
        if (! $this->customer_id) {
            return;
        }

        $companyId = auth()->user()->company_id;
        $ids = Invoice::query()
            ->with(['payments', 'credits'])
            ->where('company_id', $companyId)
            ->where('customer_id', $this->customer_id)
            ->get()
            ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
            ->pluck('id');

        $this->selected = [];
        foreach ($ids as $id) {
            $this->selected[(int) $id] = true;
        }
        $this->syncPayAmountToCashNeeded();
    }

    public function clearSelected(): void
    {
        $this->selected = [];
        $this->pay_amount = '0';
    }

    public function updatedPayMethod(): void
    {
        if (! InvoicePayment::isCheckMethod($this->pay_method)) {
            $this->pay_check_number = '';
        }
    }

    public function openCheckHit(int $paymentId): void
    {
        $payment = InvoicePayment::query()
            ->with('invoice')
            ->find($paymentId);
        if (! $payment?->invoice || (int) $payment->invoice->company_id !== (int) auth()->user()->company_id) {
            return;
        }

        $this->customer_id = (int) $payment->invoice->customer_id;
        $this->selected = [];
        $this->pay_amount = '0';
    }

    /**
     * One checked invoice opens the Payments & Credits window; several checked invoices
     * share one payment (oldest first).
     */
    public function startApplyPayment(): void
    {
        $ids = collect($this->selected)->filter()->keys()->map(fn ($id) => (int) $id)->values()->all();
        if (count($ids) === 1) {
            $this->openPayments($ids[0]);

            return;
        }

        $this->applyPayment();
    }

    public function applyPayment(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot apply payments. Enable Payments & Credits permission.');

            return;
        }

        $ids = collect($this->selected)->filter()->keys()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            session()->flash('status', 'Select at least one unpaid invoice.');

            return;
        }

        $payTotal = round((float) $this->pay_amount, 2);
        if ($payTotal < 0) {
            session()->flash('status', 'Payment amount cannot be negative.');

            return;
        }

        if ($payTotal < 0.01 && ! $this->apply_open_credits) {
            session()->flash('status', 'Enter a payment amount, or enable Apply open credits.');

            return;
        }

        $rules = [
            'customer_id' => 'required',
            'pay_method' => 'required',
            'pay_check_number' => InvoicePayment::isCheckMethod($this->pay_method) && $payTotal >= 0.01
                ? 'required|string|max:64'
                : 'nullable|string|max:64',
        ];
        $this->validate($rules, [
            'pay_check_number.required' => 'Enter the check number.',
        ]);

        $checkNumber = InvoicePayment::isCheckMethod($this->pay_method) && $payTotal >= 0.01
            ? trim($this->pay_check_number)
            : null;

        $companyId = (int) auth()->user()->company_id;
        $appliedCash = 0.0;
        $appliedCredit = 0.0;
        $creditAmount = 0.0;
        $creditNumber = null;
        $paidCount = 0;
        $partialCount = 0;

        try {
            DB::transaction(function () use (
                $ids,
                $payTotal,
                $checkNumber,
                $companyId,
                &$appliedCash,
                &$appliedCredit,
                &$creditAmount,
                &$creditNumber,
                &$paidCount,
                &$partialCount
            ) {
                $invoices = Invoice::query()
                    ->with(['payments', 'credits', 'customer'])
                    ->where('company_id', $companyId)
                    ->where('customer_id', $this->customer_id)
                    ->whereIn('id', $ids)
                    ->orderBy('invoice_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                // 1) Apply existing open credit memos to selected invoices (oldest first).
                if ($this->apply_open_credits) {
                    $memos = CreditMemo::query()
                        ->where('company_id', $companyId)
                        ->where('customer_id', $this->customer_id)
                        ->where('status', 'Open')
                        ->orderBy('memo_date')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                        ->values();

                    foreach ($invoices as $invoice) {
                        $due = round((float) $invoice->invoice_balance, 2);
                        if ($due <= 0.0001) {
                            continue;
                        }

                        foreach ($memos as $memo) {
                            if ($due <= 0.0001) {
                                break;
                            }
                            $memoLeft = round((float) $memo->remaining_amount, 2);
                            if ($memoLeft <= 0.0001) {
                                continue;
                            }
                            $apply = round(min($due, $memoLeft), 2);
                            if ($apply <= 0.0001) {
                                continue;
                            }

                            InvoiceCredit::query()->create([
                                'invoice_id' => $invoice->id,
                                'credit_memo_id' => $memo->id,
                                'amount' => $apply,
                            ]);

                            $memo->unsetRelation('applications');
                            $memo->refresh();
                            $memo->update([
                                'status' => round((float) $memo->remaining_amount, 2) <= 0.0001 ? 'Applied' : 'Open',
                            ]);

                            $appliedCredit += $apply;
                            $due = round($due - $apply, 2);
                        }

                        $invoice->unsetRelation('payments');
                        $invoice->unsetRelation('credits');
                        $invoice->refresh();
                        $invoice->load(['payments', 'credits']);
                        $newBal = round((float) $invoice->invoice_balance, 2);
                        $invoice->update(['status' => $newBal <= 0.0001 ? 'PAID' : 'NOT PAID']);
                    }

                    // Refresh invoice collection balances after credits.
                    $invoices = Invoice::query()
                        ->with(['payments', 'credits', 'customer'])
                        ->whereIn('id', $invoices->pluck('id'))
                        ->orderBy('invoice_date')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                }

                // 2) Apply cash / check payment to remaining balances.
                $remaining = $payTotal;
                foreach ($invoices as $invoice) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    $due = round((float) $invoice->invoice_balance, 2);
                    if ($due <= 0.0001) {
                        continue;
                    }
                    $apply = round(min($remaining, $due), 2);
                    if ($apply <= 0.0001) {
                        continue;
                    }

                    InvoicePayment::query()->create([
                        'invoice_id' => $invoice->id,
                        'payment_date' => $this->pay_date,
                        'payment_method' => $this->pay_method,
                        'check_number' => $checkNumber,
                        'amount' => $apply,
                        'comments' => 'Customer payment — auto-allocated oldest first',
                        'user_id' => auth()->id(),
                    ]);

                    $invoice->unsetRelation('payments');
                    $invoice->unsetRelation('credits');
                    $invoice->refresh();
                    $invoice->load(['payments', 'credits']);
                    $newBal = round((float) $invoice->invoice_balance, 2);
                    $invoice->update(['status' => $newBal <= 0.0001 ? 'PAID' : 'NOT PAID']);

                    $appliedCash += $apply;
                    $remaining = round($remaining - $apply, 2);
                    if ($newBal <= 0.0001) {
                        $paidCount++;
                    } else {
                        $partialCount++;
                    }
                }

                // Finalize paid / partial counts for selected invoices.
                $paidCount = 0;
                $partialCount = 0;
                foreach ($invoices as $invoice) {
                    $invoice->refresh();
                    $invoice->load(['payments', 'credits']);
                    $bal = round((float) $invoice->invoice_balance, 2);
                    if ($bal <= 0.0001) {
                        $paidCount++;
                    } else {
                        $partialCount++;
                    }
                }

                if ($remaining > 0.0001) {
                    $creditAmount = $remaining;
                    $candidate = CreditMemo::nextNumber($companyId);
                    while (
                        CreditMemo::query()
                            ->where('company_id', $companyId)
                            ->where('memo_number', $candidate)
                            ->exists()
                    ) {
                        $candidate = (string) (((int) $candidate) + 1);
                    }
                    $creditNumber = $candidate;

                    CreditMemo::query()->create([
                        'company_id' => $companyId,
                        'memo_number' => $candidate,
                        'memo_date' => $this->pay_date ?: now()->toDateString(),
                        'reference_no' => $checkNumber,
                        'reason' => 'Overpayment',
                        'customer_id' => $this->customer_id,
                        'sales_order_id' => null,
                        'amount' => $creditAmount,
                        'status' => 'Open',
                        'comments' => 'Auto credit from customer payment overage ($'.number_format($payTotal, 2).' paid on $'.number_format($appliedCash, 2).' remaining invoice balance).',
                        'restock_inventory' => false,
                    ]);
                }

                $customerDebit = round($appliedCash + $appliedCredit, 2);
                $customer = Customer::query()
                    ->where('company_id', $companyId)
                    ->find($this->customer_id);
                if ($customer && $customerDebit > 0) {
                    $customer->update([
                        'balance' => max(0, round((float) $customer->balance - $customerDebit, 2)),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            session()->flash('status', 'Could not apply payment: '.$e->getMessage());

            return;
        }

        if ($appliedCash <= 0.0001 && $appliedCredit <= 0.0001 && $creditAmount <= 0.0001) {
            session()->flash('status', 'Nothing applied. Select invoices with a balance, or enter a payment amount.');

            return;
        }

        $this->selected = [];
        $this->pay_amount = '0';
        $this->pay_check_number = '';

        $parts = [];
        if ($appliedCredit > 0.0001) {
            $parts[] = 'Applied $'.number_format($appliedCredit, 2).' from open credit';
        }
        if ($appliedCash > 0.0001) {
            $parts[] = 'cash/check $'.number_format($appliedCash, 2);
        }
        $msg = implode(' + ', $parts);
        if ($msg === '') {
            $msg = 'Payment saved';
        }
        $msg .= ' across selected invoices';
        if ($paidCount || $partialCount) {
            $msg .= ' — '.$paidCount.' paid, '.$partialCount.' partial';
        }
        if ($creditAmount > 0.0001) {
            $msg .= '. Overpay $'.number_format($creditAmount, 2).' saved as open credit memo #'.$creditNumber.'.';
        } else {
            $msg .= '.';
        }
        session()->flash('status', $msg);
    }

    public function viewSalesOrder(): mixed
    {
        $id = collect($this->selected)->filter()->keys()->first();
        if (! $id) {
            session()->flash('status', 'Select an invoice first.');

            return null;
        }

        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find((int) $id);

        if (! $invoice?->sales_order_id) {
            session()->flash('status', 'This invoice has no sales order.');

            return null;
        }

        return $this->redirect(route('sales.orders.edit', $invoice->sales_order_id), navigate: true);
    }

    public function printSelectedPayment(): void
    {
        $id = collect($this->selected)->filter()->keys()->first();
        if (! $id) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $url = route('sales.invoices.pdf', (int) $id);
        $this->dispatch('open-invoice-pdf', url: $url);
        $this->js('window.open('.json_encode($url).', "_blank")');
    }

    public function voidPayment(): void
    {
        if ($this->plKeys !== []) {
            $this->plVoidSelected();

            return;
        }

        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot void payments. Enable Payments & Credits permission.');

            return;
        }

        $id = collect($this->selected)->filter()->keys()->first();
        if (! $id) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $payment = InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->where('invoice_id', (int) $id)
            ->where('payment_method', '!=', InvoicePayment::RETURNED_CHECK_METHOD)
            ->orderByDesc('id')
            ->first();

        if (! $payment) {
            session()->flash('status', 'No payment to void on this invoice.');

            return;
        }

        try {
            $amount = app(\App\Services\InvoicePaymentReversalService::class)
                ->removePayment((int) $payment->id, (int) auth()->user()->company_id);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Could not void payment. '.$e->getMessage());

            return;
        }

        session()->flash('status', 'Payment $'.number_format($amount, 2).' voided. Invoice balance restored.');
    }

    public function returnCheckHit(int $paymentId): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot return checks. Enable Payments & Credits permission.');

            return;
        }

        try {
            $amount = app(\App\Services\InvoicePaymentReversalService::class)
                ->returnCheck($paymentId, (int) auth()->user()->company_id, (int) auth()->id());
        } catch (\Throwable $e) {
            session()->flash('status', 'Could not return check. '.$e->getMessage());

            return;
        }

        session()->flash('status', 'Check returned. $'.number_format($amount, 2).' is due again plus $'
            .number_format(InvoicePayment::RETURNED_CHECK_FEE, 2).' returned-check fee (Miscellaneous).');
    }

    public function closeDesk(): mixed
    {
        return $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <div class="desk-main entity-form" style="width:min(100%,70rem)">
        <x-action-bar title="Payments — Customer First">
            <x-slot:menu>
                <x-action-item label="View Sales Order" kbd="Ctrl+O" wire:click="viewSalesOrder" />
                <x-action-item label="Open Invoice Payments & Credits" sep wire:click="plOpenInvoice" />
                <x-action-item label="Print" kbd="Ctrl+P" sep wire:click="printSelectedPayment" />
                <x-action-item label="Print Receipt" wire:click="plPrintReceipt" />
                <x-action-item
                    label="Void Selected Payment / Credit"
                    sep
                    wire:click="voidPayment"
                    wire:confirm="Void the selected payment or credit? The invoice will owe this amount again."
                />
                <x-action-item
                    label="Return Check"
                    wire:click="plReturnCheck"
                    wire:confirm="Return the selected check? The invoice owes it again plus a ${{ number_format(\App\Models\InvoicePayment::RETURNED_CHECK_FEE, 2) }} returned-check fee."
                />
                <x-action-item
                    label="Reverse Invoice Payments"
                    wire:click="plReverseInvoice"
                    wire:confirm="Reverse ALL payments and credits on the selected row's invoice? It becomes fully unpaid again."
                />
                <x-action-item label="Close" kbd="Ctrl+Q" sep wire:click="closeDesk" />
            </x-slot:menu>
        </x-action-bar>

        <div class="entity-body">
            @if (session('status'))
                <div class="desk-flash" role="status">{{ session('status') }}</div>
            @endif

            <div class="entity-grid-2" style="grid-template-columns:minmax(18rem,26rem) minmax(22rem,1fr);gap:0.75rem 1.25rem;max-width:62rem;margin-bottom:1rem;align-items:end">
                <div class="so-form-row">
                    <label class="so-form-lbl" for="payment_check_search">Search by check #</label>
                    <input
                        id="payment_check_search"
                        type="search"
                        wire:model.live.debounce.300ms="checkSearch"
                        class="so-input"
                        style="width:100%;min-width:18rem;height:2.1rem;font-size:14px"
                        placeholder="Enter check number…"
                        autocomplete="off"
                    />
                </div>
                <div class="so-form-row">
                    <label class="so-form-lbl" for="payment_customer_search">Customer</label>
                    <div class="so-form-ctl" style="position:relative">
                        <div class="so-lookup-row">
                            <input
                                id="payment_customer_search"
                                type="search"
                                class="so-input"
                                placeholder="Search customer…"
                                wire:model.live.debounce.200ms="customerSearch"
                                autocomplete="off"
                                aria-label="Search customer"
                                aria-autocomplete="list"
                            />
                            @if ($customer_id)
                                <button type="button" wire:click="clearCustomer" class="so-icon-btn" title="Clear customer" aria-label="Clear customer">×</button>
                            @endif
                            <button type="button" wire:click="openCustomerBrowse" class="so-icon-btn" title="Browse" aria-label="Browse customers">
                                <svg viewBox="0 0 12 12" fill="currentColor"><circle cx="3" cy="6" r="1"/><circle cx="6" cy="6" r="1"/><circle cx="9" cy="6" r="1"/></svg>
                            </button>
                            @if ($customer_id)
                                <button
                                    type="button"
                                    @class(['desk-btn desk-btn-sm pl-list-btn', 'desk-btn-primary' => ! $plOpen])
                                    wire:click="plToggle"
                                    title="Show this customer's payments and credits"
                                >{{ $plOpen ? 'Hide List' : 'List' }}</button>
                            @endif
                        </div>
                        @if (! $showCustomerBrowse && ! $customer_id && trim($customerSearch) !== '')
                            <div class="so-lookup-panel" role="listbox" aria-label="Customer suggestions" style="position:absolute;left:0;right:0;z-index:30;max-height:16rem;margin-top:0.2rem">
                                @forelse ($browseCustomers as $bc)
                                    <button
                                        type="button"
                                        wire:key="pay-suggest-{{ $bc->id }}"
                                        wire:click="pickCustomer({{ $bc->id }})"
                                        class="so-lookup-row-pick"
                                        role="option"
                                        style="display:block;width:100%;text-align:left;border:0;background:transparent;padding:0.45rem 0.6rem;cursor:pointer"
                                    >
                                        <div style="font-weight:700;font-size:13px">{{ $bc->customer_id }} — {{ $bc->company_name ?: $bc->contact }}</div>
                                        <div style="font-size:11px;color:#64748b">{{ collect([$bc->contact, $bc->mobile ?: $bc->telephone, $bc->city])->filter()->implode(' · ') }}</div>
                                    </button>
                                @empty
                                    <div class="text-slate-500" style="padding:0.5rem 0.6rem;font-size:12px">No customers found.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if (trim($checkSearch) !== '')
                <div class="entity-section" style="margin-bottom:1rem">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Check Number Results</h3>
                        <span class="desk-title-meta">{{ $checkHits->count() }} match{{ $checkHits->count() === 1 ? '' : 'es' }}</span>
                    </div>
                    <div class="desk-grid" style="max-height:16rem">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <x-desk-sort-th field="chk_number" label="Check #" />
                                    <x-desk-sort-th field="chk_date" label="Date" />
                                    <x-desk-sort-th field="chk_customer" label="Customer" />
                                    <x-desk-sort-th field="chk_invoice" label="Invoice" />
                                    <x-desk-sort-th field="chk_amount" label="Amount" align="right" />
                                    <th style="width:7.5rem"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $returnedHitIds = $checkHits
                                        ->filter(fn ($rh) => \App\Models\InvoicePayment::isReturnedCheckMethod($rh->payment_method))
                                        ->map(fn ($rh) => preg_match('/#(\d+)/', (string) $rh->comments, $m) ? (int) $m[1] : 0)
                                        ->filter()
                                        ->values()
                                        ->all();
                                @endphp
                                @forelse ($checkHits as $hit)
                                    <tr
                                        wire:key="check-hit-{{ $hit->id }}"
                                        wire:click="openCheckHit({{ $hit->id }})"
                                        class="cursor-pointer"
                                        title="Open this customer’s invoices"
                                    >
                                        <td class="desk-num">{{ $hit->check_number ?: '—' }}</td>
                                        <td>{{ optional($hit->payment_date)?->format('n/j/Y') }}</td>
                                        <td>{{ $hit->invoice?->customer?->customer_id }} — {{ $hit->invoice?->customer?->company_name }}</td>
                                        <td class="desk-num">{{ $hit->invoice?->invoice_number }}</td>
                                        <td class="desk-money">${{ number_format((float) $hit->amount, 2) }}</td>
                                        <td class="text-center" wire:click.stop>
                                            @if (\App\Models\InvoicePayment::isReturnedCheckMethod($hit->payment_method))
                                                <span class="pc-returned-tag">Returned</span>
                                            @elseif (in_array((int) $hit->id, $returnedHitIds, true))
                                                <span class="pc-returned-tag">Bounced</span>
                                            @elseif (\App\Models\InvoicePayment::isCheckMethod($hit->payment_method) && $canEnterPayments)
                                                <button
                                                    type="button"
                                                    class="pc-row-return"
                                                    style="float:none"
                                                    wire:click="returnCheckHit({{ $hit->id }})"
                                                    wire:confirm="Return check #{{ $hit->check_number }} (${{ number_format((float) $hit->amount, 2) }})? Invoice {{ $hit->invoice?->invoice_number }} will owe this amount again plus a ${{ number_format(\App\Models\InvoicePayment::RETURNED_CHECK_FEE, 2) }} returned-check fee."
                                                >Return Check</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="6">No payments found for that check number.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($customer_id)
                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Open Invoices</h3>
                        <span class="desk-title-meta">Check one or many — payment auto-fills oldest first</span>
                        <div style="margin-left:auto;display:flex;gap:0.4rem">
                            <button type="button" class="desk-btn desk-btn-sm" wire:click="selectAllOpen" @disabled(! $canEnterPayments || $openInvoices->isEmpty())>Select all</button>
                            <button type="button" class="desk-btn desk-btn-sm" wire:click="clearSelected" @disabled(! $canEnterPayments)>Clear</button>
                        </div>
                    </div>
                    <div class="desk-grid" style="max-height:22rem">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2.5rem"></th>
                                    <x-desk-sort-th field="inv_number" label="Invoice No." />
                                    <x-desk-sort-th field="inv_date" label="Invoice Date" />
                                    <x-desk-sort-th field="inv_order" label="Order No." />
                                    <x-desk-sort-th field="inv_total" label="Invoice Total" align="right" />
                                    <x-desk-sort-th field="inv_balance" label="Balance Due" align="right" />
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openInvoices as $inv)
                                    <tr @if ($canEnterPayments) wire:dblclick="openPayments({{ $inv->id }})" title="Double-click for Payments &amp; Credits" style="cursor:pointer" @endif>
                                        <td class="text-center"><input type="checkbox" wire:model.live="selected.{{ $inv->id }}" aria-label="Select invoice {{ $inv->invoice_number }}" /></td>
                                        <td class="desk-num">{{ $inv->invoice_number }}</td>
                                        <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                                        <td class="desk-num">{{ $inv->salesOrder?->order_number }}</td>
                                        <td class="desk-money">${{ number_format($inv->invoice_total, 2) }}</td>
                                        <td class="desk-money">${{ number_format($inv->invoice_balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="6">No unpaid invoices for this customer.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($openCredits->isNotEmpty())
                    <div class="entity-section" style="margin-top:1rem">
                        <div class="entity-section-head">
                            <h3 class="entity-section-title">Open Credits</h3>
                            <span class="desk-title-meta">Total ${{ number_format($openCreditTotal, 2) }} — applied first when you pay (unless unchecked)</span>
                        </div>
                        <div class="desk-grid" style="max-height:12rem">
                            <table class="desk-table">
                                <thead>
                                    <tr>
                                        <x-desk-sort-th field="cr_number" label="Memo #" />
                                        <x-desk-sort-th field="cr_date" label="Date" />
                                        <x-desk-sort-th field="cr_reason" label="Reason" />
                                        <x-desk-sort-th field="cr_remaining" label="Remaining" align="right" />
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($openCredits as $memo)
                                        <tr>
                                            <td class="desk-num">{{ $memo->memo_number }}</td>
                                            <td>{{ optional($memo->memo_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $memo->reason ?: '—' }}</td>
                                            <td class="desk-money">${{ number_format($memo->remaining_amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <label class="entity-check" style="margin-top:0.5rem;display:inline-flex;align-items:center;gap:0.4rem">
                            <input type="checkbox" wire:model.live="apply_open_credits" @disabled(! $canEnterPayments) />
                            Apply open credits to selected invoices first
                        </label>
                    </div>
                @endif

                <div class="entity-fieldset" style="margin-top:1rem;max-width:64rem" @class(['opacity-60' => ! $canEnterPayments])>
                    <legend>Apply Payment</legend>
                    @unless ($canEnterPayments)
                        <p class="item-hint" style="padding:0 0 0.5rem">Payments are disabled for your role. Enable <strong>Payments &amp; Credits → Edit</strong> to apply.</p>
                    @endunless
                    <div class="entity-grid-2" style="grid-template-columns:{{ $isCheckMethod ? 'minmax(12rem,1fr) minmax(10rem,1fr) minmax(16rem,1.4fr) minmax(12rem,1fr)' : 'minmax(12rem,1fr) minmax(10rem,1fr) minmax(12rem,1fr)' }};gap:0.75rem 1rem">
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_date_cf">Date</label>
                            <input id="pay_date_cf" type="date" wire:model="pay_date" class="so-input" style="flex:1 1 auto;width:100%;min-width:8.5rem" @disabled(! $canEnterPayments) />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_method_cf">Method</label>
                            <select id="pay_method_cf" wire:model.live="pay_method" class="so-input" @disabled(! $canEnterPayments)>
                                <option>Cash</option>
                                <option>Credit Card</option>
                                <option>Check</option>
                            </select>
                        </div>
                        @if ($isCheckMethod)
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl so-field-req" for="pay_check_number_cf">Check #</label>
                                <input
                                    id="pay_check_number_cf"
                                    type="text"
                                    wire:model="pay_check_number"
                                    class="so-input @error('pay_check_number') is-invalid @enderror"
                                    style="flex:1 1 auto;width:100%;min-width:11rem"
                                    placeholder="Check number"
                                    autocomplete="off"
                                    @disabled(! $canEnterPayments)
                                />
                                @error('pay_check_number')
                                    <span class="so-field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_amount_cf">Cash / check</label>
                            <input id="pay_amount_cf" wire:model.live="pay_amount" class="so-input text-right" style="flex:1 1 auto;width:100%;min-width:7rem" @disabled(! $canEnterPayments) />
                        </div>
                    </div>

                    <div style="margin-top:0.75rem;padding:0.65rem 0.75rem;background:#f8fafc;border:1px solid #cbd5e1;border-radius:0.25rem">
                        <div class="entity-value">Checked invoice total: <strong>${{ number_format($checkedTotal, 2) }}</strong></div>
                        @if ($creditTowardChecked > 0.0001)
                            <div style="margin-top:0.25rem;color:#166534">
                                Open credit to apply: <strong>${{ number_format($creditTowardChecked, 2) }}</strong>
                                → cash still needed: <strong>${{ number_format($cashNeeded, 2) }}</strong>
                            </div>
                        @endif
                        @if ($allocationHint)
                            @if ($allocationHint['type'] === 'credit_only')
                                <p class="item-hint" style="margin:0.35rem 0 0;color:#166534">
                                    Open credit covers these invoices (${{ number_format($allocationHint['credit'], 2) }}).
                                    Credit left after: <strong>${{ number_format($allocationHint['credit_left'], 2) }}</strong>. Cash can stay $0.
                                </p>
                            @elseif ($allocationHint['type'] === 'partial')
                                <p class="item-hint" style="margin:0.35rem 0 0;color:#b45309">
                                    Covers <strong>${{ number_format($allocationHint['applied'], 2) }}</strong>
                                    @if (($allocationHint['credit'] ?? 0) > 0) (incl. ${{ number_format($allocationHint['credit'], 2) }} credit) @endif.
                                    Remaining on selected: <strong>${{ number_format($allocationHint['left'], 2) }}</strong>.
                                </p>
                            @elseif ($allocationHint['type'] === 'overpay')
                                <p class="item-hint" style="margin:0.35rem 0 0;color:#166534">
                                    Pays all <strong>${{ number_format($allocationHint['applied'], 2) }}</strong> due.
                                    Extra <strong>${{ number_format($allocationHint['credit_new'], 2) }}</strong> becomes a new open credit.
                                </p>
                            @else
                                <p class="item-hint" style="margin:0.35rem 0 0;color:#166534">
                                    Exact match — selected invoices paid in full
                                    @if (($allocationHint['credit'] ?? 0) > 0) (using ${{ number_format($allocationHint['credit'], 2) }} open credit) @endif.
                                </p>
                            @endif
                        @else
                            <p class="item-hint" style="margin:0.35rem 0 0">Select invoices. Open credits apply first; enter only any remaining cash/check.</p>
                        @endif
                    </div>

                    <div class="entity-footer-actions" style="margin-top:0.85rem;justify-content:flex-end">
                        <button type="button" wire:click="startApplyPayment" class="desk-btn desk-btn-primary" @disabled(! $canEnterPayments) title="{{ $canEnterPayments ? 'Apply payment' : 'No payment permission' }}">Apply Payment</button>
                    </div>
                </div>
            @else
                @if (trim($checkSearch) === '')
                    <div class="desk-empty-hint">Select a customer to view unpaid invoices and apply payments, or search by check number.</div>
                @endif
            @endif

            @php
                $plSelRows = $payList->filter(fn ($r) => in_array($r->kind.':'.$r->row_id, $plKeys, true))->values();
                $plSelCount = count($plKeys);
                $plSelRow = $plSelCount === 1 ? $plSelRows->first() : null;
                $plSelTotal = (float) $plSelRows->sum(fn ($r) => (float) $r->amount);
                $plSelChecks = $plSelRows->filter(fn ($r) => $r->kind === 'payment'
                    && \App\Models\InvoicePayment::isCheckMethod($r->method)
                    && ! in_array((int) $r->row_id, $bouncedIds, true))->count();
                $plSelInvoices = $plSelRows->pluck('invoice_number')->unique()->values();
                $plAllSelected = $payList->isNotEmpty()
                    && $payList->every(fn ($r) => in_array($r->kind.':'.$r->row_id, $plKeys, true));
            @endphp
            @if ($customer_id && $plOpen)
            <div class="entity-section pl-section" style="margin-top:1.25rem">
                <div class="pl-bar">
                    <span class="pl-title">
                        Payments &amp; Credits
                        <span class="desk-title-meta">{{ number_format($payListShown) }}{{ $payListHasMore ? '+' : '' }}</span>
                    </span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="plSearch"
                        class="so-input pl-search"
                        placeholder="Search invoice #, check #, memo #, amount…"
                        aria-label="Search payments and credits"
                    />
                    <select wire:model.live="plType" class="so-input pl-filter" aria-label="Type">
                        <option value="">All types</option>
                        <option value="payment">Payments</option>
                        <option value="credit">Credits</option>
                        <option value="returned">Returned checks</option>
                    </select>
                    <label class="pl-lbl" for="pl-from">From</label>
                    <input id="pl-from" type="date" wire:model.live="plFrom" class="so-input pl-date" aria-label="From date" />
                    <label class="pl-lbl" for="pl-to">To</label>
                    <input id="pl-to" type="date" wire:model.live="plTo" class="so-input pl-date" aria-label="To date" />
                    @if ($plSearch !== '' || $plType !== '' || $plFrom !== '' || $plTo !== '')
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="plClearFilters">Clear</button>
                    @endif
                </div>

                <div class="pl-actions">
                    <span class="pl-sel-label">
                        @if ($plSelRow)
                            Selected: <strong>{{ $plSelRow->kind === 'credit' ? 'Credit' : $plSelRow->method }}</strong>
                            ${{ number_format((float) $plSelRow->amount, 2) }} · Invoice {{ $plSelRow->invoice_number }}
                        @elseif ($plSelCount > 1)
                            <strong>{{ $plSelCount }} selected</strong> · ${{ number_format($plSelTotal, 2) }}
                            · {{ $plSelInvoices->count() }} invoice{{ $plSelInvoices->count() === 1 ? '' : 's' }}
                            <button type="button" class="pl-clear-sel" wire:click="$set('plKeys', [])">Clear</button>
                        @else
                            Click rows to select (multiple allowed), or tick the header box to select all.
                        @endif
                    </span>
                    <button
                        type="button"
                        class="desk-btn desk-btn-sm pl-btn-danger"
                        wire:click="plVoidSelected"
                        wire:confirm="Void {{ $plSelCount }} selected row{{ $plSelCount === 1 ? '' : 's' }} (${{ number_format($plSelTotal, 2) }})? The invoices will owe these amounts again."
                        @disabled($plSelCount === 0 || ! $canEnterPayments)
                    >Void{{ $plSelCount > 1 ? ' ('.$plSelCount.')' : '' }}</button>
                    <button
                        type="button"
                        class="desk-btn desk-btn-sm pl-btn-warn"
                        wire:click="plReturnCheck"
                        wire:confirm="Return {{ $plSelChecks }} check{{ $plSelChecks === 1 ? '' : 's' }}? Each invoice owes the check again plus a ${{ number_format(\App\Models\InvoicePayment::RETURNED_CHECK_FEE, 2) }} returned-check fee (Miscellaneous)."
                        @disabled($plSelChecks === 0 || ! $canEnterPayments)
                    >Return Check{{ $plSelChecks > 1 ? ' ('.$plSelChecks.')' : '' }}</button>
                    <button
                        type="button"
                        class="desk-btn desk-btn-sm"
                        wire:click="plReverseInvoice"
                        wire:confirm="Reverse ALL payments and credits on invoice {{ $plSelInvoices->implode(', ') }}? {{ $plSelInvoices->count() === 1 ? 'It becomes' : 'They become' }} fully unpaid again."
                        @disabled($plSelCount === 0 || ! $canEnterPayments)
                    >Reverse Payment{{ $plSelInvoices->count() > 1 ? ' ('.$plSelInvoices->count().')' : '' }}</button>
                    <button type="button" class="desk-btn desk-btn-sm" wire:click="plOpenInvoice" @disabled(! $plSelRow)>Open Invoice</button>
                    <button type="button" class="desk-btn desk-btn-sm" wire:click="plPrintReceipt" @disabled(! $plSelRow || $plSelRow->kind !== 'payment')>Print Receipt</button>
                    <button type="button" class="desk-btn desk-btn-sm" wire:click="plRefresh" title="Refresh">↻</button>
                </div>

                <x-desk-scroll-grid :has-more="$payListHasMore" style="max-height:30rem">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem">
                                    <input
                                        type="checkbox"
                                        wire:key="pl-all-{{ $plAllSelected ? 1 : 0 }}"
                                        wire:click.prevent="plSelectAll"
                                        @checked($plAllSelected)
                                        @disabled($payList->isEmpty())
                                        title="Select all shown"
                                        aria-label="Select all shown rows"
                                    />
                                </th>
                                <x-desk-sort-th field="pl_date" label="Date" />
                                <th style="width:5.5rem">Type</th>
                                <x-desk-sort-th field="pl_customer" label="Customer" />
                                <x-desk-sort-th field="pl_invoice" label="Invoice" />
                                <x-desk-sort-th field="pl_method" label="Method" />
                                <x-desk-sort-th field="pl_ref" label="Check / Memo #" />
                                <x-desk-sort-th field="pl_amount" label="Amount" align="right" />
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payList as $row)
                                @php
                                    $rowKey = $row->kind.':'.$row->row_id;
                                    $rowIsReturn = $row->kind === 'payment' && \App\Models\InvoicePayment::isReturnedCheckMethod($row->method);
                                    $rowBounced = $row->kind === 'payment' && in_array((int) $row->row_id, $bouncedIds, true);
                                    $rowSel = in_array($rowKey, $plKeys, true);
                                @endphp
                                <tr
                                    wire:key="pl-{{ $rowKey }}"
                                    wire:click="plSelect('{{ $rowKey }}')"
                                    @class(['is-selected' => $rowSel, 'cursor-pointer'])
                                >
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            wire:key="pl-chk-{{ $rowKey }}-{{ $rowSel ? 1 : 0 }}"
                                            @checked($rowSel)
                                            onclick="event.preventDefault()"
                                            aria-label="Select row"
                                        />
                                    </td>
                                    <td>{{ $row->row_date ? \Illuminate\Support\Carbon::parse($row->row_date)->format('n/j/Y') : '—' }}</td>
                                    <td>
                                        @if ($row->kind === 'credit')
                                            <span class="pl-tag pl-tag-credit">Credit</span>
                                        @elseif ($rowIsReturn)
                                            <span class="pc-returned-tag">Returned</span>
                                        @elseif ($rowBounced)
                                            <span class="pc-returned-tag">Bounced</span>
                                        @else
                                            <span class="pc-saved-tag">Payment</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->customer_code }}{{ $row->customer_code ? ' — ' : '' }}{{ $row->customer_name }}</td>
                                    <td class="desk-num">{{ $row->invoice_number }}</td>
                                    <td>{{ $row->method }}</td>
                                    <td class="desk-num">{{ $row->ref ?: '—' }}</td>
                                    <td class="desk-money">${{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="pl-comments">{{ $row->comments }}</td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="9">No payments or credits found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-desk-scroll-grid>
                <x-desk-load-more :has-more="$payListHasMore" />
            </div>
            @endif
        </div>
    </div>

    @if ($showCustomerBrowse)
        <div
            class="desk-modal-backdrop desk-modal-top"
            wire:click.self="closeCustomerBrowse"
            wire:keydown.escape.window="closeCustomerBrowse"
            role="presentation"
        >
            <div class="desk-modal desk-modal-lg" role="dialog" aria-modal="true" aria-labelledby="pay-cust-title" wire:click.stop>
                <div class="desk-modal-head">
                    <span id="pay-cust-title">Select Customer</span>
                    <button type="button" wire:click="closeCustomerBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body" style="padding:0.75rem">
                    <input
                        type="search"
                        wire:model.live.debounce.200ms="customerSearch"
                        class="so-input"
                        placeholder="Search customer ID, name, phone…"
                        autocomplete="off"
                        style="margin-bottom:0.65rem"
                    />
                    <div class="desk-grid" style="max-height:22rem">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($browseCustomers as $bc)
                                    <tr
                                        wire:key="pay-cust-{{ $bc->id }}"
                                        wire:click="pickCustomer({{ $bc->id }})"
                                        class="cursor-pointer so-lookup-row-pick"
                                    >
                                        <td class="font-mono">{{ $bc->customer_id }}</td>
                                        <td>{{ $bc->company_name }}</td>
                                        <td>{{ $bc->contact }}</td>
                                        <td>{{ $bc->mobile ?: $bc->telephone }}</td>
                                        <td>{{ collect([$bc->city, $bc->state])->filter()->implode(', ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-slate-500 px-2 py-2">No customers found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.partials.payments-credits-modal')
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
