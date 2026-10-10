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

        $draftPayTotal = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCreditTotal = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $savedBalance = $modalInvoice ? round((float) $modalInvoice->invoice_balance, 2) : 0;
        $pcDelta = $modalInvoice ? $this->pcAdjustDelta($modalInvoice) : 0.0;
        $previewBalance = $modalInvoice
            ? max(0, round($savedBalance + $pcDelta - $draftPayTotal - $draftCreditTotal, 2))
            : 0;

        return [
            'modalInvoice' => $modalInvoice,
            'pcOpenCredits' => $modalInvoice
                ? CreditMemo::query()
                    ->with(['salesOrder'])
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
            'previewBalance' => $previewBalance,
            'savedBalance' => $savedBalance,
            'pcDelta' => $pcDelta,
            'pcNewTotal' => $modalInvoice ? round((float) $modalInvoice->invoice_total + $pcDelta, 2) : 0,
            'previewPayments' => $modalInvoice ? round((float) $modalInvoice->total_payments + $draftPayTotal, 2) : 0,
            'previewCredits' => $modalInvoice ? round((float) $modalInvoice->total_credits + $draftCreditTotal, 2) : 0,
        ];
    }

    public function openPayments(int $id): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot enter payments. Enable Payments & Credits permission.');

            return;
        }

        if (property_exists($this, 'selectedId')) {
            $this->selectedId = $id;
        }
        $this->modalInvoiceId = $id;
        $invoice = Invoice::query()->find($id);
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

        // Same as before: open with normal Cash payment row.
        if ($invoice && $invoice->invoice_balance > 0.0001) {
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
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return 0;
        }

        $draftPay = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCredit = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );

        return max(0, round((float) $invoice->invoice_balance + $this->pcAdjustDelta($invoice) - $draftPay - $draftCredit, 2));
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
        $invoiceDue = round((float) $invoice->invoice_balance + $this->pcAdjustDelta($invoice), 2);
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

    public function addCreditRow(): void
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

        $memo = $openMemos->first(fn (CreditMemo $m) => ! in_array((int) $m->id, $usedIds, true))
            ?? $openMemos->first();

        $usedOfMemo = collect($this->draftCredits)
            ->filter(fn ($r) => (int) ($r['credit_memo_id'] ?? 0) === (int) $memo->id)
            ->sum(fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0)));

        $memoLeft = max(0, round((float) $memo->remaining_amount - $usedOfMemo, 2));
        // Amount against invoice due ignoring cash (credit pays first).
        $invoiceLeft = max(0, round(
            (float) $invoice->invoice_balance
            - collect($this->draftCredits)->sum(fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0))),
            2
        ));
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
            ->where('invoice_id', (int) $this->modalInvoiceId)
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
            ->where('invoice_id', (int) $this->modalInvoiceId)
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
            ->where('invoice_id', (int) $this->modalInvoiceId)
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
            $balance = max(0, (float) $invoice->invoice_balance
                - collect($this->draftCredits)->filter(fn ($r, $i) => $i !== $index)->sum(
                    fn ($r) => (float) str_replace(',', '', (string) ($r['amount'] ?? 0))
                ));

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

        $invoice = Invoice::query()->with('customer')->findOrFail($this->modalInvoiceId);
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

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
        $combined = round($payTotal + $creditTotal, 2);

        // Allow full payoff when float/rounding is slightly over.
        if ($combined > $balance && $combined <= round($balance + 0.02, 2) && $payments->isNotEmpty()) {
            $over = round($combined - $balance, 2);
            $payments = $payments->values();
            $lastIdx = $payments->count() - 1;
            $adjusted = round((float) $payments[$lastIdx]['amount'] - $over, 2);
            if ($adjusted <= 0.0001) {
                $payments->forget($lastIdx);
                $payments = $payments->values();
            } else {
                $row = $payments[$lastIdx];
                $row['amount'] = $adjusted;
                $payments->put($lastIdx, $row);
                $payments = $payments->values();
            }
            $payTotal = round((float) $payments->sum('amount'), 2);
            $combined = round($payTotal + $creditTotal, 2);
        }

        if ($combined > $balance + 0.0001) {
            session()->flash('status', 'Total payments and credits cannot exceed the invoice balance of $'.number_format($balance, 2).'.');

            return;
        }

        $lastPayment = null;
        $savedPayTotal = $payTotal;
        $savedCreditTotal = $creditTotal;

        try {
            DB::transaction(function () use ($invoice, $payments, $credits, &$lastPayment) {
                $this->applyPcAdjustments($invoice);
                $invoice->customer?->refresh();
                $customerDebit = 0.0;

                foreach ($payments as $row) {
                    $lastPayment = InvoicePayment::query()->create([
                        'invoice_id' => $invoice->id,
                        'payment_date' => $row['payment_date'],
                        'payment_method' => $row['payment_method'],
                        'check_number' => InvoicePayment::isCheckMethod($row['payment_method'])
                            ? $row['check_number']
                            : null,
                        'amount' => $row['amount'],
                        'comments' => $row['comments'] !== '' ? $row['comments'] : null,
                        'user_id' => auth()->id(),
                    ]);
                    $customerDebit += $row['amount'];
                }

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

                    $memo->refresh();
                    $memo->update([
                        'status' => $memo->remaining_amount <= 0.0001 ? 'Applied' : 'Open',
                    ]);

                    $customerDebit += $amount;
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
            $parts[] = 'Credit $'.number_format($savedCreditTotal, 2).' applied';
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
