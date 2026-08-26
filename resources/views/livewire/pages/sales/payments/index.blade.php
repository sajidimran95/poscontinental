<?php

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
    public ?int $customer_id = null;

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
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->get()
                ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
                ->values();
            $invoices = $this->sortCollection($invoices, [
                'inv_number' => 'invoice_number',
                'inv_date' => fn ($inv) => optional($inv->invoice_date)?->format('Y-m-d') ?? '',
                'inv_order' => fn ($inv) => (string) ($inv->salesOrder?->order_number ?? ''),
                'inv_total' => fn ($inv) => (float) $inv->invoice_total,
                'inv_balance' => fn ($inv) => (float) $inv->invoice_balance,
            ], 'inv_date', 'asc');

            $openCredits = CreditMemo::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->where('status', 'Open')
                ->orderBy('memo_date')
                ->orderBy('id')
                ->get()
                ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                ->values();
            $openCredits = $this->sortCollection($openCredits, [
                'cr_number' => 'memo_number',
                'cr_date' => fn ($m) => optional($m->memo_date)?->format('Y-m-d') ?? '',
                'cr_reason' => 'reason',
                'cr_remaining' => fn ($m) => (float) $m->remaining_amount,
            ], 'cr_date', 'asc');

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

        return [
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
        ];
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
        ];
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
}; ?>

<div class="desk-page entity-page">
    <div class="desk-main entity-form" style="width:min(100%,70rem)">
        <x-action-bar title="Payments — Customer First" />

        <div class="entity-body">
            @if (session('status'))
                <div class="desk-flash" role="status">{{ session('status') }}</div>
            @endif

            <div class="entity-grid-2" style="grid-template-columns:minmax(14rem,18rem) minmax(20rem,1fr);gap:0.75rem 1.25rem;max-width:48rem;margin-bottom:1rem;align-items:end">
                <div class="so-form-row">
                    <label class="so-form-lbl" for="payment_check_search">Search by check #</label>
                    <input
                        id="payment_check_search"
                        type="search"
                        wire:model.live.debounce.300ms="checkSearch"
                        class="so-input"
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
                                </tr>
                            </thead>
                            <tbody>
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
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="5">No payments found for that check number.</td>
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
                                    <tr>
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

                <div class="entity-fieldset" style="margin-top:1rem;max-width:48rem" @class(['opacity-60' => ! $canEnterPayments])>
                    <legend>Apply Payment</legend>
                    @unless ($canEnterPayments)
                        <p class="item-hint" style="padding:0 0 0.5rem">Payments are disabled for your role. Enable <strong>Payments &amp; Credits → Edit</strong> to apply.</p>
                    @endunless
                    <div class="entity-grid-2" style="grid-template-columns:repeat({{ $isCheckMethod ? 4 : 3 }},minmax(0,1fr));gap:0.75rem">
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_date_cf">Date</label>
                            <input id="pay_date_cf" type="date" wire:model="pay_date" class="so-input" @disabled(! $canEnterPayments) />
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
                            <input id="pay_amount_cf" wire:model.live="pay_amount" class="so-input text-right" @disabled(! $canEnterPayments) />
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
                        <button type="button" wire:click="applyPayment" class="desk-btn desk-btn-primary" @disabled(! $canEnterPayments) title="{{ $canEnterPayments ? 'Apply payment' : 'No payment permission' }}">Apply Payment</button>
                    </div>
                </div>
            @else
                @if (trim($checkSearch) === '')
                    <div class="desk-empty-hint">Select a customer to view unpaid invoices and apply payments, or search by check number.</div>
                @endif
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
</div>
