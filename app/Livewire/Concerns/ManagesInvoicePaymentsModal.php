<?php

namespace App\Livewire\Concerns;

use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments & Credits window for a single invoice (shared by Sales → Invoices and Sales → Payments).
 * Pair with the view partial `livewire.partials.payments-credits-modal`.
 */
trait ManagesInvoicePaymentsModal
{
    public ?int $modalInvoiceId = null;

    /** Invoices covered by the open Payments & Credits window. One id, or several paid together. */
    public array $modalInvoiceIds = [];

    public string $driver = '';

    public string $driverSavedAt = '';

    /** @var list<array{key: string, payment_date: string, payment_method: string, check_number: string, amount: string, comments: string}> */
    public array $draftPayments = [];

    /** @var list<array{key: string, credit_memo_id: string, amount: string}> */
    public array $draftCredits = [];

    public int $selectedPaymentIndex = -1;

    public int $selectedCreditIndex = -1;

    public ?int $lastPaymentId = null;

    public string $emailTo = '';

    public string $emailSubject = '';

    public bool $showEmailForm = false;

    /** Payments & Credits window: invoice adjustments (trade discount lowers, freight / misc raise the total). */
    public string $pc_trade_discount = '';

    public string $pc_freight = '';

    public string $pc_miscellaneous = '';

    /**
     * @return array<string, mixed>
     */
    protected function paymentsModalViewData(int $companyId): array
    {
        $modalInvoice = $this->modalInvoiceId
            ? Invoice::query()
                ->with([
                    'customer',
                    'salesOrder.salesRep',
                    'salesOrder.paymentTerm',
                    'payments',
                    'credits.creditMemo.salesOrder',
                ])
                ->find($this->modalInvoiceId)
            : null;

        $isGroupPay = $this->isGroupPay();
        $modalInvoices = $isGroupPay
            ? $this->groupInvoices()
            : collect($modalInvoice ? [$modalInvoice] : []);

        $draftPayTotal = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCreditTotal = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $savedBalance = $isGroupPay
            ? round((float) $modalInvoices->sum(fn (Invoice $i) => (float) $i->invoice_balance), 2)
            : ($modalInvoice ? round((float) $modalInvoice->invoice_balance, 2) : 0);
        $pcDelta = $isGroupPay || ! $modalInvoice ? 0.0 : $this->pcAdjustDelta($modalInvoice);
        $dueNow = max(0, round($savedBalance + $pcDelta, 2));
        $creditTowardDue = min($draftCreditTotal, $dueNow);
        $cashTowardDue = min($draftPayTotal, max(0, round($dueNow - $creditTowardDue, 2)));
        $overpayAmount = max(0, round($draftPayTotal - $cashTowardDue, 2));
        $previewBalance = $modalInvoice
            ? max(0, round($dueNow - $creditTowardDue - $cashTowardDue, 2))
            : 0;

        return [
            'modalInvoice' => $modalInvoice,
            'pcOpenCredits' => $modalInvoice
                ? CreditMemo::query()
                    ->with(['salesOrder:id,order_number'])
                    ->withAppliedSum()
                    ->where('company_id', $companyId)
                    ->where('customer_id', $modalInvoice->customer_id)
                    ->where('status', 'Open')
                    ->orderByDesc('id')
                    ->get()
                    ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                    ->values()
                : collect(),
            'hasCreditSalesOrder' => Cache::remember('schema.credit_memos.sales_order_id', 86400, fn () => Schema::hasColumn('credit_memos', 'sales_order_id')),
            'draftPayTotal' => $draftPayTotal,
            'draftCreditTotal' => $draftCreditTotal,
            'overpayAmount' => $overpayAmount,
            'previewBalance' => $previewBalance,
            'savedBalance' => $savedBalance,
            'pcDelta' => $pcDelta,
            'pcNewTotal' => $isGroupPay
                ? round((float) $modalInvoices->sum(fn (Invoice $i) => (float) $i->invoice_total), 2)
                : ($modalInvoice ? round((float) $modalInvoice->invoice_total + $pcDelta, 2) : 0),
            'previewPayments' => $isGroupPay
                ? round((float) $modalInvoices->sum(fn (Invoice $i) => (float) $i->total_payments) + $draftPayTotal, 2)
                : ($modalInvoice ? round((float) $modalInvoice->total_payments + $draftPayTotal, 2) : 0),
            'previewCredits' => $isGroupPay
                ? round((float) $modalInvoices->sum(fn (Invoice $i) => (float) $i->total_credits) + $draftCreditTotal, 2)
                : ($modalInvoice ? round((float) $modalInvoice->total_credits + $draftCreditTotal, 2) : 0),
            'isGroupPay' => $isGroupPay,
            'modalInvoices' => $modalInvoices,
            'payAllocation' => $isGroupPay
                ? $this->allocationPreview($modalInvoices, $draftPayTotal, $draftCreditTotal)
                : [],
        ];
    }

    /** @return list<int> */
    protected function modalGroupIds(): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->modalInvoiceIds))));
        if ($ids === [] && $this->modalInvoiceId) {
            return [(int) $this->modalInvoiceId];
        }

        return $ids;
    }

    protected function isGroupPay(): bool
    {
        return count($this->modalGroupIds()) > 1;
    }

    protected function groupInvoices()
    {
        return Invoice::query()
            ->with([
                'customer',
                'salesOrder.salesRep',
                'salesOrder.paymentTerm',
                'salesOrder.lines',
                'payments',
                'credits.creditMemo.salesOrder',
            ])
            ->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $this->modalGroupIds())
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    protected function modalBalance(): float
    {
        if ($this->isGroupPay()) {
            return round((float) $this->groupInvoices()->sum(fn (Invoice $i) => (float) $i->invoice_balance), 2);
        }

        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return 0.0;
        }

        return round((float) $invoice->invoice_balance + $this->pcAdjustDelta($invoice), 2);
    }

    /**
     * Oldest invoices are paid in full. Whatever is left is only subtracted from the next invoice.
     *
     * @return list<array{invoice_number: string, due: float, applied: float, left: float, paid: bool}>
     */
    protected function allocationPreview($invoices, float $payTotal, float $creditTotal): array
    {
        $cashLeft = round(max(0, $payTotal), 2);
        $creditLeft = round(max(0, $creditTotal), 2);
        $rows = [];
        foreach ($invoices as $invoice) {
            $due = round((float) $invoice->invoice_balance, 2);
            if ($due <= 0.0001) {
                continue;
            }
            $fromCredit = round(min($due, $creditLeft), 2);
            $creditLeft = round($creditLeft - $fromCredit, 2);
            $fromCash = round(min(max(0, $due - $fromCredit), $cashLeft), 2);
            $cashLeft = round($cashLeft - $fromCash, 2);
            $left = round(max(0, $due - $fromCredit - $fromCash), 2);
            $rows[] = [
                'invoice_number' => (string) $invoice->invoice_number,
                'due' => $due,
                'applied' => round($fromCredit + $fromCash, 2),
                'left' => $left,
                'paid' => $left <= 0.0001,
            ];
        }

        return $rows;
    }

    public function openPayments(int $id): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot enter payments. Enable Payments & Credits permission.');

            return;
        }

        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if ($invoice && strtoupper(trim((string) $invoice->status)) === 'VOID') {
            session()->flash('status', 'Invoice #'.$invoice->invoice_number.' is VOID. Unvoid it first — payments and credits are not allowed on voided invoices.');

            return;
        }

        if (property_exists($this, 'selectedId')) {
            $this->selectedId = $id;
        }
        $this->modalInvoiceId = $id;
        $this->modalInvoiceIds = [$id];
        $this->driver = $invoice?->driver ?? '';
        $this->driverSavedAt = '';
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
        $this->showEmailForm = false;
        $this->emailTo = $invoice?->customer?->email ?? '';
        $this->emailSubject = $invoice ? 'Invoice '.$invoice->invoice_number : '';

        $this->loadPcAdjustments($invoice);

        if ($invoice && $invoice->invoice_balance > 0.0001) {
            $this->addPaymentRow();
        }
    }

    /** Same Payments & Credits window, for every checked invoice. */
    public function openPaymentsGroup(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) <= 1) {
            if ($ids !== []) {
                $this->openPayments($ids[0]);
            }

            return;
        }

        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot enter payments. Enable Payments & Credits permission.');

            return;
        }

        $invoices = Invoice::query()
            ->with('customer')
            ->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $ids)
            ->whereRaw("UPPER(COALESCE(status, '')) NOT IN ('VOID', 'CANCELLED', 'CANCELED')")
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
            ->values();

        if ($invoices->isEmpty()) {
            session()->flash('status', 'No payable invoices selected. VOID invoices cannot take payments or credits.');

            return;
        }

        if ($invoices->count() <= 1) {
            if ($invoices->isNotEmpty()) {
                $this->openPayments((int) $invoices->first()->id);
            }

            return;
        }

        $first = $invoices->first();
        $this->modalInvoiceIds = $invoices->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->modalInvoiceId = (int) $first->id;
        $this->driver = '';
        $this->driverSavedAt = '';
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
        $this->showEmailForm = false;
        $this->emailTo = $first->customer?->email ?? '';
        $this->emailSubject = 'Invoices '.$invoices->pluck('invoice_number')->implode(', ');
        $this->pc_trade_discount = number_format((float) $invoices->sum('trade_discount'), 2, '.', '');
        $this->pc_freight = number_format((float) $invoices->sum('freight'), 2, '.', '');
        $this->pc_miscellaneous = number_format((float) $invoices->sum('miscellaneous'), 2, '.', '');

        if ($invoices->sum(fn (Invoice $i) => (float) $i->invoice_balance) > 0.0001) {
            $this->addPaymentRow();
        }
    }

    protected function loadPcAdjustments(?Invoice $invoice): void
    {
        $this->pc_trade_discount = $invoice ? number_format((float) $invoice->trade_discount, 2, '.', '') : '';
        $this->pc_freight = $invoice ? number_format((float) $invoice->freight, 2, '.', '') : '';
        $this->pc_miscellaneous = $invoice ? number_format((float) $invoice->miscellaneous, 2, '.', '') : '';
        $this->resetErrorBag(['pc_trade_discount', 'pc_freight', 'pc_miscellaneous']);
    }

    protected function pcAmount(string $value): float
    {
        return round(max(0, (float) str_replace([',', '$'], '', trim($value))), 2);
    }

    /**
     * Change to the invoice total from the adjustment fields vs what is saved.
     * Relative to the saved total so imported invoices with odd totals stay correct.
     */
    protected function pcAdjustDelta(Invoice $invoice): float
    {
        if ((int) $this->modalInvoiceId !== (int) $invoice->id || $this->pc_trade_discount === '' && $this->pc_freight === '' && $this->pc_miscellaneous === '') {
            return 0.0;
        }

        return round(
            - ($this->pcAmount($this->pc_trade_discount) - round((float) $invoice->trade_discount, 2))
            + ($this->pcAmount($this->pc_freight) - round((float) $invoice->freight, 2))
            + ($this->pcAmount($this->pc_miscellaneous) - round((float) $invoice->miscellaneous, 2)),
            2
        );
    }

    public function updatedPcTradeDiscount(): void
    {
        $this->afterPcAdjustChanged();
    }

    public function updatedPcFreight(): void
    {
        $this->afterPcAdjustChanged();
    }

    public function updatedPcMiscellaneous(): void
    {
        $this->afterPcAdjustChanged();
    }

    protected function afterPcAdjustChanged(): void
    {
        $this->syncCashDraftsAfterCredits();
    }

    /**
     * Save trade discount / freight / misc on the invoice. Call inside a transaction.
     */
    protected function applyPcAdjustments(Invoice $invoice): float
    {
        $delta = $this->pcAdjustDelta($invoice);
        if (abs($delta) < 0.005) {
            return 0.0;
        }

        $invoice->loadMissing(['payments', 'credits']);
        $newTotal = round((float) $invoice->invoice_total + $delta, 4);
        $applied = round((float) $invoice->payments->sum('amount') + (float) $invoice->credits->sum('amount'), 2);
        if ($newTotal < 0 || $newTotal + 0.0001 < $applied) {
            throw new \RuntimeException('New total $'.number_format($newTotal, 2).' cannot be less than payments and credits already applied ($'.number_format($applied, 2).').');
        }

        $invoice->update([
            'trade_discount' => $this->pcAmount($this->pc_trade_discount),
            'freight' => $this->pcAmount($this->pc_freight),
            'miscellaneous' => $this->pcAmount($this->pc_miscellaneous),
            'invoice_total' => $newTotal,
            'status' => ($newTotal - $applied) <= 0.0001 ? 'PAID' : 'NOT PAID',
        ]);

        if ($invoice->customer_id) {
            $customer = Customer::query()->lockForUpdate()->find($invoice->customer_id);
            $customer?->update(['balance' => round((float) $customer->balance + $delta, 2)]);
        }

        return $delta;
    }

    public function closeModal(): void
    {
        $this->modalInvoiceId = null;
        $this->modalInvoiceIds = [];
        $this->pc_trade_discount = '';
        $this->pc_freight = '';
        $this->pc_miscellaneous = '';
        $this->showEmailForm = false;
        $this->driverSavedAt = '';
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
    }

    public function updatedDriver(): void
    {
        $this->persistDriver();
    }

    public function saveDriver(): void
    {
        $this->persistDriver();
    }

    protected function persistDriver(): void
    {
        if (! $this->modalInvoiceId) {
            return;
        }
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice || $invoice->company_id !== auth()->user()->company_id) {
            return;
        }
        $invoice->update(['driver' => $this->driver !== '' ? trim($this->driver) : null]);
        $this->driverSavedAt = now()->format('g:i:s A');
    }

    public function addPaymentRow(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot enter payments.');

            return;
        }

        $this->pushPaymentRow(true);
    }

    public function addRemainingDuePayment(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot enter payments.');

            return;
        }

        $this->pushPaymentRow(true);
    }

    protected function pushPaymentRow(bool $fillRemaining = true): void
    {
        $due = round($this->remainingDraftDue(), 2);

        // Do not open extra blank $0.00 rows when nothing is left to pay.
        if ($due <= 0.0001) {
            session()->flash('status', 'No remaining balance. Remove or lower an amount first to add another payment.');

            return;
        }

        $this->draftPayments[] = [
            'key' => uniqid('pay_', true),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'check_number' => '',
            'amount' => $fillRemaining ? number_format($due, 2, '.', '') : '',
            'comments' => '',
        ];
        $this->selectedPaymentIndex = count($this->draftPayments) - 1;
    }

    protected function remainingDraftDue(): float
    {
        $draftPay = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCredit = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );

        return max(0, round($this->modalBalance() - $draftPay - $draftCredit, 2));
    }

    /**
     * After credit amounts change, drop or shrink cash drafts so payment is credit — not cash.
     */
    protected function syncCashDraftsAfterCredits(): void
    {
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return;
        }

        $creditTotal = round((float) collect($this->draftCredits)->sum(
            fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0))
        ), 2);
        $invoiceDue = $this->isGroupPay()
            ? $this->modalBalance()
            : round((float) $invoice->invoice_balance + $this->pcAdjustDelta($invoice), 2);
        $cashAllowed = round(max(0, $invoiceDue - $creditTotal), 2);

        if ($cashAllowed <= 0.0001) {
            $this->draftPayments = [];
            $this->selectedPaymentIndex = -1;

            return;
        }

        if ($this->draftPayments === []) {
            return;
        }

        // Keep a single cash row for leftover only.
        $first = $this->draftPayments[0];
        $first['amount'] = number_format($cashAllowed, 2, '.', '');
        $this->draftPayments = [$first];
        $this->selectedPaymentIndex = 0;
    }

    public function removePaymentRow(): void
    {
        if ($this->selectedPaymentIndex < 0 || ! isset($this->draftPayments[$this->selectedPaymentIndex])) {
            if (count($this->draftPayments) === 0) {
                return;
            }
            $this->selectedPaymentIndex = count($this->draftPayments) - 1;
        }

        array_splice($this->draftPayments, $this->selectedPaymentIndex, 1);
        $this->draftPayments = array_values($this->draftPayments);
        $this->selectedPaymentIndex = count($this->draftPayments) > 0
            ? min($this->selectedPaymentIndex, count($this->draftPayments) - 1)
            : -1;
    }

    public function selectPaymentRow(int $index): void
    {
        $this->selectedPaymentIndex = $index;
    }

    public function applyOpenCredit(int $memoId): void
    {
        $this->addCreditRow($memoId);
    }

    public function addCreditRow(int $memoId = 0): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot apply credits. Enable Payments & Credits permission.');

            return;
        }

        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return;
        }

        $openMemos = CreditMemo::query()
            ->withAppliedSum()
            ->where('company_id', auth()->user()->company_id)
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'Open')
            ->orderBy('memo_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
            ->values();

        if ($openMemos->isEmpty()) {
            $this->redirect(route('sales.credit-memos.index', [
                'new' => 1,
                'customer_id' => $invoice->customer_id,
            ]), navigate: true);

            return;
        }

        $usedIds = collect($this->draftCredits)
            ->map(fn ($r) => (int) ($r['credit_memo_id'] ?? 0))
            ->filter()
            ->all();

        $memo = $memoId > 0
            ? $openMemos->firstWhere('id', $memoId)
            : ($openMemos->first(fn (CreditMemo $m) => ! in_array((int) $m->id, $usedIds, true))
                ?? $openMemos->first());
        if (! $memo) {
            session()->flash('status', 'That credit memo is not open for this customer.');

            return;
        }

        $usedOfMemo = collect($this->draftCredits)
            ->filter(fn ($r) => (int) ($r['credit_memo_id'] ?? 0) === (int) $memo->id)
            ->sum(fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0)));

        $memoLeft = max(0, round((float) $memo->remaining_amount - $usedOfMemo, 2));
        // Amount against invoice due ignoring cash (credit pays first).
        $covered = collect($this->draftCredits)->sum(fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0)));
        $invoiceLeft = $this->isGroupPay()
            ? max(0, round($this->modalBalance() - $covered, 2))
            : max(0, round((float) $invoice->invoice_balance - $covered, 2));
        $apply = round(min($memoLeft, $invoiceLeft), 2);

        if ($apply <= 0.0001) {
            session()->flash('status', 'Invoice is already covered by selected credits. No cash needed.');
            $this->syncCashDraftsAfterCredits();

            return;
        }

        $this->draftCredits[] = [
            'key' => uniqid('cr_', true),
            'credit_memo_id' => (string) $memo->id,
            'amount' => number_format($apply, 2, '.', ''),
        ];
        $this->selectedCreditIndex = count($this->draftCredits) - 1;
        $this->syncCashDraftsAfterCredits();

        $left = round($memoLeft - $apply, 2);
        $msg = '−$'.number_format($apply, 2).' on this invoice.';
        if ($left > 0.009) {
            $msg .= ' $'.number_format($left, 2).' stays on the customer (memo '.$memo->memo_number.').';
        }
        session()->flash('status', $msg);
    }

    public function removeCreditRow(): void
    {
        if ($this->selectedCreditIndex < 0 || ! isset($this->draftCredits[$this->selectedCreditIndex])) {
            if (count($this->draftCredits) === 0) {
                return;
            }
            $this->selectedCreditIndex = count($this->draftCredits) - 1;
        }

        array_splice($this->draftCredits, $this->selectedCreditIndex, 1);
        $this->draftCredits = array_values($this->draftCredits);
        $this->selectedCreditIndex = count($this->draftCredits) > 0
            ? min($this->selectedCreditIndex, count($this->draftCredits) - 1)
            : -1;
        $this->syncCashDraftsAfterCredits();
    }

    public function selectCreditRow(int $index): void
    {
        $this->selectedCreditIndex = $index;
    }

    public function removeSavedPayment(int $paymentId): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot remove payments. Enable Payments & Credits permission.');

            return;
        }

        $ok = InvoicePayment::query()
            ->whereKey($paymentId)
            ->whereIn('invoice_id', $this->modalGroupIds())
            ->exists();
        if (! $ok) {
            session()->flash('status', 'Payment not found on this invoice.');

            return;
        }

        try {
            $amount = app(\App\Services\InvoicePaymentReversalService::class)
                ->removePayment($paymentId, (int) auth()->user()->company_id);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Could not remove payment. '.$e->getMessage());

            return;
        }

        if ($this->lastPaymentId === $paymentId) {
            $this->lastPaymentId = null;
        }
        $this->loadPcAdjustments(Invoice::query()->find($this->modalInvoiceId));
        session()->flash('status', $amount < 0
            ? 'Returned check undone. $'.number_format(abs($amount), 2).' counted as paid again and the fee was removed.'
            : 'Payment $'.number_format($amount, 2).' voided. Invoice balance restored.');
    }

    public function returnSavedCheck(int $paymentId): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot return checks. Enable Payments & Credits permission.');

            return;
        }

        $ok = InvoicePayment::query()
            ->whereKey($paymentId)
            ->whereIn('invoice_id', $this->modalGroupIds())
            ->exists();
        if (! $ok) {
            session()->flash('status', 'Check payment not found on this invoice.');

            return;
        }

        try {
            $amount = app(\App\Services\InvoicePaymentReversalService::class)
                ->returnCheck($paymentId, (int) auth()->user()->company_id, (int) auth()->id());
        } catch (\Throwable $e) {
            session()->flash('status', 'Could not return check. '.$e->getMessage());

            return;
        }

        $this->loadPcAdjustments(Invoice::query()->find($this->modalInvoiceId));
        session()->flash('status', 'Check returned. $'.number_format($amount, 2).' is due again plus $'
            .number_format(InvoicePayment::RETURNED_CHECK_FEE, 2).' returned-check fee (Miscellaneous).');
    }

    public function removeSavedCredit(int $invoiceCreditId): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot remove credits. Enable Payments & Credits permission.');

            return;
        }

        $ok = InvoiceCredit::query()
            ->whereKey($invoiceCreditId)
            ->whereIn('invoice_id', $this->modalGroupIds())
            ->exists();
        if (! $ok) {
            session()->flash('status', 'Credit not found on this invoice.');

            return;
        }

        try {
            $amount = app(\App\Services\InvoicePaymentReversalService::class)
                ->removeCredit($invoiceCreditId, (int) auth()->user()->company_id);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('status', 'Could not remove credit. '.$e->getMessage());

            return;
        }

        session()->flash('status', 'Credit $'.number_format($amount, 2).' removed. Credit memo is open again.');
    }

    public function updatedDraftCredits($value, string $key): void
    {
        // When a credit memo is selected, default amount to min(remaining, invoice balance) — credit first, not cash.
        if (str_ends_with($key, '.credit_memo_id')) {
            $parts = explode('.', $key);
            $index = (int) ($parts[0] ?? -1);
            if ($index < 0 || ! isset($this->draftCredits[$index])) {
                return;
            }

            $memoId = (int) ($this->draftCredits[$index]['credit_memo_id'] ?? 0);
            if ($memoId <= 0) {
                $this->syncCashDraftsAfterCredits();

                return;
            }

            $memo = CreditMemo::query()->find($memoId);
            $invoice = Invoice::query()->find($this->modalInvoiceId);
            if (! $memo || ! $invoice) {
                return;
            }

            $usedElsewhere = collect($this->draftCredits)
                ->filter(fn ($r, $i) => $i !== $index && (int) ($r['credit_memo_id'] ?? 0) === $memoId)
                ->sum(fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0)));

            $remaining = max(0, (float) $memo->remaining_amount - $usedElsewhere);
            $otherCredits = collect($this->draftCredits)->filter(fn ($r, $i) => $i !== $index)->sum(
                fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0))
            );
            $balance = $this->isGroupPay()
                ? max(0, round($this->modalBalance() - $otherCredits, 2))
                : max(0, (float) $invoice->invoice_balance - $otherCredits);

            $this->draftCredits[$index]['amount'] = number_format(min($remaining, $balance), 2, '.', '');
            $this->syncCashDraftsAfterCredits();

            return;
        }

        if (str_ends_with($key, '.amount')) {
            $this->syncCashDraftsAfterCredits();
        }
    }

    public function saveAll(bool $print = false): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot save payments. Enable Payments & Credits permission.');

            return;
        }

        if ($this->isGroupPay()) {
            $this->saveGroup($print);

            return;
        }

        $invoice = Invoice::query()->with('customer')->findOrFail($this->modalInvoiceId);
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        if (strtoupper(trim((string) $invoice->status)) === 'VOID') {
            session()->flash('status', 'Invoice #'.$invoice->invoice_number.' is VOID. Unvoid it before payments or credits.');

            return;
        }

        if ($this->driver !== ($invoice->driver ?? '')) {
            $invoice->update(['driver' => $this->driver !== '' ? trim($this->driver) : null]);
        }

        $payments = collect($this->draftPayments)
            ->map(fn ($r) => [
                'payment_date' => trim((string) ($r['payment_date'] ?? '')),
                'payment_method' => trim((string) ($r['payment_method'] ?? '')),
                'check_number' => trim((string) ($r['check_number'] ?? '')),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
                'comments' => trim((string) ($r['comments'] ?? '')),
            ])
            ->filter(fn ($r) => $r['amount'] > 0.0001)
            ->values();

        $credits = collect($this->draftCredits)
            ->map(fn ($r) => [
                'credit_memo_id' => (int) ($r['credit_memo_id'] ?? 0),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
            ])
            ->filter(fn ($r) => $r['credit_memo_id'] > 0 && $r['amount'] > 0.0001)
            ->values();

        $pcDelta = $this->pcAdjustDelta($invoice);

        if ($payments->isEmpty() && $credits->isEmpty() && abs($pcDelta) >= 0.005) {
            try {
                DB::transaction(function () use ($invoice) {
                    $locked = Invoice::query()->with(['payments', 'credits'])->lockForUpdate()->findOrFail($invoice->id);
                    $this->applyPcAdjustments($locked);
                });
            } catch (\Throwable $e) {
                session()->flash('status', $e->getMessage());

                return;
            }
            $invoice->refresh();
            session()->flash('status', 'Invoice total updated to $'.number_format((float) $invoice->invoice_total, 2)
                .'. Remaining due $'.number_format((float) $invoice->invoice_balance, 2).'.');
            if ($print) {
                $this->openPdfInBrowser(route('sales.invoices.pdf', $invoice));
            }
            $this->closeModal();

            return;
        }

        if ($payments->isEmpty() && $credits->isEmpty()) {
            $invoice->refresh();
            if ($invoice->payments()->exists() || $invoice->credits()->exists()) {
                $due = round((float) $invoice->invoice_balance, 2);
                if ($due > 0.0001) {
                    session()->flash('status', 'Previous payment is already saved. Remaining due $'.number_format($due, 2).' — enter amount in the new row, then Save.');
                    if (count($this->draftPayments) === 0) {
                        $this->addPaymentRow();
                    }
                } else {
                    session()->flash('status', 'Invoice is already paid. Nothing new to save.');
                    if ($print) {
                        $last = $invoice->payments()->latest('id')->first();
                        $this->openPdfInBrowser(
                            $last
                                ? route('sales.invoices.receipt', [$invoice, $last])
                                : route('sales.invoices.pdf', $invoice)
                        );
                    }
                }
            } else {
                session()->flash('status', 'Add at least one payment or credit before saving.');
            }

            return;
        }

        foreach ($payments as $i => $row) {
            if ($row['payment_date'] === '' || $row['payment_method'] === '') {
                session()->flash('status', 'Payment row '.($i + 1).' needs a date and method.');

                return;
            }
            if (InvoicePayment::isCheckMethod($row['payment_method']) && $row['check_number'] === '') {
                session()->flash('status', 'Payment row '.($i + 1).' needs a check number.');

                return;
            }
        }

        $balance = round((float) $invoice->invoice_balance + $pcDelta, 2);
        $payTotal = round((float) $payments->sum('amount'), 2);
        $creditTotal = round((float) $credits->sum('amount'), 2);

        if ($creditTotal > $balance + 0.0001) {
            session()->flash('status', 'Credits cannot exceed the invoice balance of $'.number_format($balance, 2).'.');

            return;
        }

        $lastPayment = null;
        $savedPayTotal = 0.0;
        $savedCreditTotal = 0.0;
        $overpayCreated = 0.0;
        $overpayMemoNumber = null;
        $leftOnCustomer = [];

        try {
            DB::transaction(function () use (
                $invoice,
                $payments,
                $credits,
                $payTotal,
                &$lastPayment,
                &$leftOnCustomer,
                &$savedPayTotal,
                &$savedCreditTotal,
                &$overpayCreated,
                &$overpayMemoNumber
            ) {
                $this->applyPcAdjustments($invoice);
                $invoice->customer?->refresh();
                $customerDebit = 0.0;

                foreach ($credits as $row) {
                    $memo = CreditMemo::query()->lockForUpdate()->findOrFail($row['credit_memo_id']);
                    abort_unless(
                        $memo->company_id === $invoice->company_id
                        && (int) $memo->customer_id === (int) $invoice->customer_id
                        && $memo->status === 'Open',
                        403
                    );

                    $remaining = (float) $memo->remaining_amount;
                    $amount = min($row['amount'], $remaining);
                    if ($amount <= 0.0001) {
                        throw new \RuntimeException('Credit memo '.$memo->memo_number.' has no remaining balance.');
                    }

                    InvoiceCredit::query()->create([
                        'invoice_id' => $invoice->id,
                        'credit_memo_id' => $memo->id,
                        'amount' => $amount,
                    ]);

                    $memo->unsetRelation('applications');
                    $memo->refresh();
                    $left = round((float) $memo->remaining_amount, 2);
                    $memo->update([
                        'status' => $left <= 0.0001 ? 'Applied' : 'Open',
                    ]);
                    if ($left > 0.009) {
                        $leftOnCustomer[] = 'Memo '.$memo->memo_number.' $'.number_format($left, 2).' stays on the customer';
                    }

                    $savedCreditTotal = round($savedCreditTotal + $amount, 2);
                    $customerDebit += $amount;
                }

                $invoice->unsetRelation('payments');
                $invoice->unsetRelation('credits');
                $invoice->refresh();
                $invoice->load(['payments', 'credits']);
                $due = round((float) $invoice->invoice_balance, 2);
                $cashLeft = $payTotal;

                foreach ($payments as $row) {
                    if ($cashLeft <= 0.0001) {
                        break;
                    }
                    $rowAmt = round(min((float) $row['amount'], $cashLeft), 2);
                    $apply = round(min($rowAmt, max(0, $due)), 2);
                    $cashLeft = round($cashLeft - $rowAmt, 2);

                    if ($apply > 0.0001) {
                        $lastPayment = InvoicePayment::query()->create([
                            'invoice_id' => $invoice->id,
                            'payment_date' => $row['payment_date'],
                            'payment_method' => $row['payment_method'],
                            'check_number' => InvoicePayment::isCheckMethod($row['payment_method'])
                                ? $row['check_number']
                                : null,
                            'amount' => $apply,
                            'comments' => $row['comments'] !== '' ? $row['comments'] : null,
                            'user_id' => auth()->id(),
                        ]);
                        $savedPayTotal = round($savedPayTotal + $apply, 2);
                        $customerDebit += $apply;
                        $due = round($due - $apply, 2);
                    }
                }

                $overpay = round(max(0, $payTotal - $savedPayTotal), 2);
                if ($overpay > 0.0001) {
                    $ref = collect($payments)
                        ->map(fn ($r) => InvoicePayment::isCheckMethod($r['payment_method']) ? $r['check_number'] : '')
                        ->filter()
                        ->first();
                    $memoDate = collect($payments)->pluck('payment_date')->filter()->first() ?: now()->toDateString();
                    $memo = $this->createOverpaymentCreditMemo(
                        (int) $invoice->company_id,
                        (int) $invoice->customer_id,
                        $overpay,
                        $memoDate,
                        $ref ? (string) $ref : null,
                        $payTotal,
                        $savedPayTotal
                    );
                    $overpayCreated = $overpay;
                    $overpayMemoNumber = $memo->memo_number;
                }

                $invoice->unsetRelation('payments');
                $invoice->unsetRelation('credits');
                $invoice->refresh();
                $invoice->load(['payments', 'credits']);
                $invoice->update([
                    'status' => round((float) $invoice->invoice_balance, 2) <= 0.0001 ? 'PAID' : 'NOT PAID',
                ]);

                if ($invoice->customer && $customerDebit > 0) {
                    $invoice->customer->update([
                        'balance' => max(0, round((float) $invoice->customer->balance - $customerDebit, 2)),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());

            return;
        }

        $this->lastPaymentId = $lastPayment?->id;
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
        $this->modalInvoiceId = $invoice->id;

        $invoice->unsetRelation('payments');
        $invoice->unsetRelation('credits');
        $invoice->refresh();
        $invoice->load(['payments', 'credits']);

        $parts = [];
        if (abs($pcDelta) >= 0.005) {
            $parts[] = 'Invoice total updated to $'.number_format((float) $invoice->invoice_total, 2);
        }
        if ($savedPayTotal > 0) {
            $parts[] = 'Payment $'.number_format($savedPayTotal, 2).' saved';
        }
        if ($savedCreditTotal > 0) {
            $parts[] = '−$'.number_format($savedCreditTotal, 2).' on the invoice';
        }
        if ($overpayCreated > 0.0001) {
            $parts[] = 'Overpayment $'.number_format($overpayCreated, 2).' saved as open credit memo '.$overpayMemoNumber;
        }
        if ($leftOnCustomer !== []) {
            $parts[] = implode('. ', $leftOnCustomer);
        }
        $msg = implode('. ', $parts).'. Status: '.$invoice->status.'.';
        $remainingDue = round((float) $invoice->invoice_balance, 2);
        if ($remainingDue > 0.0001) {
            $msg .= ' Remaining due $'.number_format($remainingDue, 2).'.';
        } else {
            $msg .= ' Invoice is fully paid.';
        }
        session()->flash('status', $msg);

        if ($print) {
            if ($lastPayment) {
                $this->openPdfInBrowser(route('sales.invoices.receipt', [$invoice, $lastPayment]));
            } else {
                $this->openPdfInBrowser(route('sales.invoices.pdf', $invoice));
            }
        }

        $this->closeModal();
    }

    /**
     * One payment amount across the checked invoices. Oldest invoices are paid in full.
     * Any shortfall is only subtracted from the next invoice, which stays open for that remainder.
     */
    protected function saveGroup(bool $print): void
    {
        $invoices = $this->groupInvoices();
        if ($invoices->count() < 2) {
            session()->flash('status', 'Select at least two invoices.');

            return;
        }

        $customerId = (int) $invoices->first()->customer_id;
        if ($invoices->contains(fn (Invoice $i) => (int) $i->customer_id !== $customerId)) {
            session()->flash('status', 'Selected invoices must be for the same customer.');

            return;
        }

        $payments = collect($this->draftPayments)
            ->map(fn ($r) => [
                'payment_date' => trim((string) ($r['payment_date'] ?? '')),
                'payment_method' => trim((string) ($r['payment_method'] ?? '')),
                'check_number' => trim((string) ($r['check_number'] ?? '')),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
                'comments' => trim((string) ($r['comments'] ?? '')),
            ])
            ->filter(fn ($r) => $r['amount'] > 0.0001)
            ->values();

        $credits = collect($this->draftCredits)
            ->map(fn ($r) => [
                'credit_memo_id' => (int) ($r['credit_memo_id'] ?? 0),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
            ])
            ->filter(fn ($r) => $r['credit_memo_id'] > 0 && $r['amount'] > 0.0001)
            ->values();

        if ($payments->isEmpty() && $credits->isEmpty()) {
            session()->flash('status', 'Add at least one payment or credit before saving.');

            return;
        }

        foreach ($payments as $i => $row) {
            if ($row['payment_date'] === '' || $row['payment_method'] === '') {
                session()->flash('status', 'Payment row '.($i + 1).' needs a date and method.');

                return;
            }
            if (InvoicePayment::isCheckMethod($row['payment_method']) && $row['check_number'] === '') {
                session()->flash('status', 'Payment row '.($i + 1).' needs a check number.');

                return;
            }
        }

        $balance = round((float) $invoices->sum(fn (Invoice $i) => (float) $i->invoice_balance), 2);
        $payTotal = round((float) $payments->sum('amount'), 2);
        $creditTotal = round((float) $credits->sum('amount'), 2);
        if ($creditTotal > $balance + 0.0001) {
            session()->flash('status', 'Credits cannot exceed the invoice balance of $'.number_format($balance, 2).'.');

            return;
        }

        $lastPayment = null;
        $results = [];
        $overpayCreated = 0.0;
        $overpayMemoNumber = null;

        try {
            DB::transaction(function () use (
                $invoices,
                $payments,
                $credits,
                $customerId,
                $payTotal,
                &$lastPayment,
                &$results,
                &$overpayCreated,
                &$overpayMemoNumber
            ) {
                $locked = Invoice::query()
                    ->whereIn('id', $invoices->pluck('id'))
                    ->orderBy('invoice_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $creditPool = $credits->map(fn ($r) => $r)->all();
                $applied = 0.0;

                foreach ($locked as $invoice) {
                    $invoice->unsetRelation('payments');
                    $invoice->unsetRelation('credits');
                    $invoice->load(['payments', 'credits']);
                    $due = round((float) $invoice->invoice_balance, 2);

                    foreach ($creditPool as $k => $row) {
                        if ($due <= 0.0001 || $row['amount'] <= 0.0001) {
                            continue;
                        }
                        $memo = CreditMemo::query()->lockForUpdate()->findOrFail($row['credit_memo_id']);
                        abort_unless(
                            $memo->company_id === $invoice->company_id
                            && (int) $memo->customer_id === (int) $invoice->customer_id
                            && $memo->status === 'Open',
                            403
                        );
                        $apply = round(min($due, $row['amount'], (float) $memo->remaining_amount), 2);
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
                            'status' => $memo->remaining_amount <= 0.0001 ? 'Applied' : 'Open',
                        ]);
                        $creditPool[$k]['amount'] = round($row['amount'] - $apply, 2);
                        $due = round($due - $apply, 2);
                        $applied += $apply;
                    }
                }

                $cashPool = $payments->map(fn ($r) => $r)->all();
                $cashApplied = 0.0;
                foreach ($locked as $invoice) {
                    $invoice->unsetRelation('payments');
                    $invoice->unsetRelation('credits');
                    $invoice->refresh();
                    $invoice->load(['payments', 'credits']);
                    $due = round((float) $invoice->invoice_balance, 2);

                    foreach ($cashPool as $k => $row) {
                        if ($due <= 0.0001 || $row['amount'] <= 0.0001) {
                            continue;
                        }
                        $apply = round(min($due, $row['amount']), 2);
                        $lastPayment = InvoicePayment::query()->create([
                            'invoice_id' => $invoice->id,
                            'payment_date' => $row['payment_date'],
                            'payment_method' => $row['payment_method'],
                            'check_number' => InvoicePayment::isCheckMethod($row['payment_method'])
                                ? $row['check_number']
                                : null,
                            'amount' => $apply,
                            'comments' => $row['comments'] !== '' ? $row['comments'] : null,
                            'user_id' => auth()->id(),
                        ]);
                        $cashPool[$k]['amount'] = round($row['amount'] - $apply, 2);
                        $due = round($due - $apply, 2);
                        $applied += $apply;
                        $cashApplied = round($cashApplied + $apply, 2);
                    }

                    $invoice->unsetRelation('payments');
                    $invoice->unsetRelation('credits');
                    $invoice->refresh();
                    $invoice->load(['payments', 'credits']);
                    $left = round(max(0, (float) $invoice->invoice_balance), 2);
                    $invoice->update([
                        'status' => $left <= 0.0001 ? 'PAID' : 'NOT PAID',
                    ]);
                    $results[] = [
                        'number' => (string) $invoice->invoice_number,
                        'left' => $left,
                    ];
                }

                $overpay = round(max(0, $payTotal - $cashApplied), 2);
                if ($overpay > 0.0001) {
                    $first = $locked->first();
                    $ref = collect($payments)
                        ->map(fn ($r) => InvoicePayment::isCheckMethod($r['payment_method']) ? $r['check_number'] : '')
                        ->filter()
                        ->first();
                    $memoDate = collect($payments)->pluck('payment_date')->filter()->first() ?: now()->toDateString();
                    $memo = $this->createOverpaymentCreditMemo(
                        (int) $first->company_id,
                        $customerId,
                        $overpay,
                        $memoDate,
                        $ref ? (string) $ref : null,
                        $payTotal,
                        $cashApplied
                    );
                    $overpayCreated = $overpay;
                    $overpayMemoNumber = $memo->memo_number;
                }

                if ($applied > 0.0001 && $customerId) {
                    $customer = Customer::query()->lockForUpdate()->find($customerId);
                    $customer?->update([
                        'balance' => max(0, round((float) $customer->balance - $applied, 2)),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());

            return;
        }

        $this->lastPaymentId = $lastPayment?->id;
        $lines = [];
        foreach ($results as $row) {
            $lines[] = $row['left'] <= 0.0001
                ? 'Invoice '.$row['number'].' paid in full'
                : 'Invoice '.$row['number'].' still due $'.number_format($row['left'], 2);
        }
        if ($overpayCreated > 0.0001) {
            $lines[] = 'Overpayment $'.number_format($overpayCreated, 2).' saved as open credit memo '.$overpayMemoNumber;
        }
        session()->flash('status', implode('. ', $lines).'.');

        if ($print && $lastPayment) {
            $this->openPdfInBrowser(route('sales.invoices.receipt', [$lastPayment->invoice_id, $lastPayment->id]));
        }

        if (property_exists($this, 'selected')) {
            $this->selected = [];
        }

        $this->closeModal();
    }

    protected function createOverpaymentCreditMemo(
        int $companyId,
        int $customerId,
        float $amount,
        string $memoDate,
        ?string $referenceNo,
        float $paidTotal,
        float $appliedCash,
    ): CreditMemo {
        $candidate = CreditMemo::nextNumber($companyId);
        while (
            CreditMemo::query()
                ->where('company_id', $companyId)
                ->where('memo_number', $candidate)
                ->exists()
        ) {
            $candidate = (string) (((int) $candidate) + 1);
        }

        return CreditMemo::query()->create([
            'company_id' => $companyId,
            'memo_number' => $candidate,
            'memo_date' => $memoDate,
            'reference_no' => $referenceNo,
            'reason' => 'Overpayment',
            'customer_id' => $customerId,
            'sales_order_id' => null,
            'amount' => round($amount, 2),
            'status' => 'Open',
            'comments' => 'Auto credit from customer payment overage ($'.number_format($paidTotal, 2).' paid on $'.number_format($appliedCash, 2).' remaining invoice balance).',
            'restock_inventory' => false,
        ]);
    }

    protected function openPdfInBrowser(string $url): void
    {
        // Open once only (dispatch listener already window.open's — do not also call js open).
        $this->dispatch('open-invoice-pdf', url: $url);
    }

    public function savePayments(): void
    {
        $this->saveAll(false);
    }

    public function saveAndPrint(): void
    {
        $this->saveAll(true);
    }
}
