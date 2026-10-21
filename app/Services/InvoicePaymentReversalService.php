<?php

namespace App\Services;

use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;

class InvoicePaymentReversalService
{
    /**
     * Void a saved payment and restore invoice status + customer balance.
     * Voiding a check also voids its returned-check line (and fee); voiding a
     * returned-check line undoes the return (and fee).
     * Returns the change to what the customer owes (excluding fees).
     */
    public function removePayment(int $paymentId, int $companyId): float
    {
        return DB::transaction(function () use ($paymentId, $companyId) {
            $payment = InvoicePayment::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $amount = round((float) $payment->amount, 2);
            $invoiceId = (int) $payment->invoice_id;
            $fee = 0.0;

            if (InvoicePayment::isReturnedCheckMethod($payment->payment_method)) {
                $fee = InvoicePayment::returnedCheckFee($payment->comments);
            } elseif (InvoicePayment::isCheckMethod($payment->payment_method)) {
                $offsets = $this->returnLinesFor($payment);
                $amount = round($amount + (float) $offsets->sum('amount'), 2);
                $fee = (float) $offsets->sum(fn ($o) => InvoicePayment::returnedCheckFee($o->comments));
                $offsets->each->delete();
            }

            $payment->delete();

            $this->restoreInvoice($invoiceId, $amount, -$fee);

            return $amount;
        });
    }

    /**
     * Bounced check: keep the original check, add an offsetting negative "Returned Check" line
     * so the amount is owed again, and charge the returned-check fee as invoice Miscellaneous.
     * Returns the returned check amount.
     */
    public function returnCheck(int $paymentId, int $companyId, ?int $userId = null, float $fee = InvoicePayment::RETURNED_CHECK_FEE): float
    {
        return DB::transaction(function () use ($paymentId, $companyId, $userId, $fee) {
            $payment = InvoicePayment::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if (! InvoicePayment::isCheckMethod($payment->payment_method)) {
                throw new \RuntimeException('Only check payments can be returned.');
            }

            if ($this->returnLinesFor($payment)->isNotEmpty()) {
                throw new \RuntimeException('Check #'.($payment->check_number ?: $payment->id).' is already returned.');
            }

            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0.0001) {
                throw new \RuntimeException('This check has no amount to return.');
            }

            $fee = max(0, round($fee, 2));

            InvoicePayment::query()->create([
                'invoice_id' => $payment->invoice_id,
                'payment_date' => now()->toDateString(),
                'payment_method' => InvoicePayment::RETURNED_CHECK_METHOD,
                'check_number' => $payment->check_number,
                'amount' => -$amount,
                'comments' => InvoicePayment::returnedCheckComment((int) $payment->id, $fee),
                'user_id' => $userId,
            ]);

            $this->restoreInvoice((int) $payment->invoice_id, $amount, $fee);

            return $amount;
        });
    }

    /**
     * Change a saved payment. A higher or lower amount updates the invoice status
     * and the customer balance by the difference.
     */
    public function updatePayment(int $paymentId, int $companyId, array $data): void
    {
        DB::transaction(function () use ($paymentId, $companyId, $data) {
            $payment = InvoicePayment::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if (InvoicePayment::isReturnedCheckMethod($payment->payment_method)) {
                throw new \RuntimeException('Returned-check lines cannot be edited. Void that row instead.');
            }

            $old = round((float) $payment->amount, 2);
            $new = round((float) ($data['amount'] ?? 0), 2);
            if ($new < 0.01) {
                throw new \RuntimeException('Amount must be at least 0.01. Void the payment to remove it.');
            }

            $method = trim((string) ($data['payment_method'] ?? ''));
            if ($method === '' || InvoicePayment::isReturnedCheckMethod($method)) {
                throw new \RuntimeException('Choose Cash, Credit Card, or Check.');
            }

            $check = InvoicePayment::isCheckMethod($method)
                ? trim((string) ($data['check_number'] ?? ''))
                : '';
            if (InvoicePayment::isCheckMethod($method) && $check === '') {
                throw new \RuntimeException('Enter the check number.');
            }

            if ($this->returnLinesFor($payment)->isNotEmpty() && abs($new - $old) > 0.009) {
                throw new \RuntimeException('This check was returned. Void the return before changing the amount.');
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $invoice->unsetRelation('payments');
            $invoice->unsetRelation('credits');
            $room = round($old + max(0, (float) $invoice->invoice_balance), 2);
            if ($new - $room > 0.009) {
                throw new \RuntimeException('Amount cannot be more than $'.number_format($room, 2).' (this payment plus what the invoice still owes).');
            }

            $payment->update([
                'payment_date' => $data['payment_date'] ?? $payment->payment_date,
                'payment_method' => $method,
                'check_number' => $check !== '' ? $check : null,
                'amount' => $new,
                'comments' => trim((string) ($data['comments'] ?? '')) ?: null,
            ]);

            $owedDelta = round($old - $new, 2);
            if (abs($owedDelta) > 0.0001) {
                $this->restoreInvoice((int) $payment->invoice_id, $owedDelta);
            }
        });
    }

    /**
     * Change how much of a credit memo is applied. The invoice balance and the
     * memo's open amount both follow the new figure.
     */
    public function updateCredit(int $invoiceCreditId, int $companyId, float $amount): void
    {
        DB::transaction(function () use ($invoiceCreditId, $companyId, $amount) {
            $credit = InvoiceCredit::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->findOrFail($invoiceCreditId);

            $old = round((float) $credit->amount, 2);
            $new = round($amount, 2);
            if ($new < 0.01) {
                throw new \RuntimeException('Amount must be at least 0.01. Void the credit to remove it.');
            }

            $memo = $credit->credit_memo_id
                ? CreditMemo::query()->lockForUpdate()->find($credit->credit_memo_id)
                : null;
            if ($memo) {
                $otherApplied = round((float) InvoiceCredit::query()
                    ->where('credit_memo_id', $memo->id)
                    ->where('id', '!=', $credit->id)
                    ->sum('amount'), 2);
                $cap = round((float) $memo->amount - $otherApplied, 2);
                if ($new - $cap > 0.009) {
                    throw new \RuntimeException('Amount cannot be more than $'.number_format(max(0, $cap), 2).' available on credit memo '.$memo->memo_number.'.');
                }
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($credit->invoice_id);
            $invoice->unsetRelation('payments');
            $invoice->unsetRelation('credits');
            $room = round($old + max(0, (float) $invoice->invoice_balance), 2);
            if ($new - $room > 0.009) {
                throw new \RuntimeException('Amount cannot be more than $'.number_format($room, 2).' (this credit plus what the invoice still owes).');
            }

            $credit->update(['amount' => $new]);

            if ($memo) {
                $memo->unsetRelation('applications');
                $memo->update([
                    'status' => round((float) $memo->remaining_amount, 2) <= 0.0001 ? 'Applied' : 'Open',
                ]);
            }

            $owedDelta = round($old - $new, 2);
            if (abs($owedDelta) > 0.0001) {
                $this->restoreInvoice((int) $credit->invoice_id, $owedDelta);
            }
        });
    }

    /**
     * Remove an applied credit: the credit memo becomes Open again and the invoice owes the amount.
     * Returns the reversed amount.
     */
    public function removeCredit(int $invoiceCreditId, int $companyId): float
    {
        return DB::transaction(function () use ($invoiceCreditId, $companyId) {
            $credit = InvoiceCredit::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->findOrFail($invoiceCreditId);

            $amount = round((float) $credit->amount, 2);
            $invoiceId = (int) $credit->invoice_id;
            $memoId = (int) $credit->credit_memo_id;
            $credit->delete();

            $memo = CreditMemo::query()->lockForUpdate()->find($memoId);
            if ($memo) {
                $memo->unsetRelation('applications');
                $memo->update([
                    'status' => round((float) $memo->remaining_amount, 2) <= 0.0001 ? 'Applied' : 'Open',
                ]);
            }

            $this->restoreInvoice($invoiceId, $amount);

            return $amount;
        });
    }

    /**
     * Reverse every payment and credit on an invoice so it is fully unpaid again.
     *
     * @return array{payments: float, credits: float, rows: int}
     */
    public function reverseInvoice(int $invoiceId, int $companyId): array
    {
        return DB::transaction(function () use ($invoiceId, $companyId) {
            $invoice = Invoice::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $rows = InvoicePayment::query()->where('invoice_id', $invoice->id)->count()
                + InvoiceCredit::query()->where('invoice_id', $invoice->id)->count();

            $payTotal = 0.0;
            // Originals first so their returned-check lines are voided with them.
            while ($next = InvoicePayment::query()
                ->where('invoice_id', $invoice->id)
                ->orderByRaw('CASE WHEN payment_method = ? THEN 1 ELSE 0 END', [InvoicePayment::RETURNED_CHECK_METHOD])
                ->orderBy('id')
                ->first()) {
                $payTotal += $this->removePayment((int) $next->id, $companyId);
            }

            $creditTotal = 0.0;
            foreach (InvoiceCredit::query()->where('invoice_id', $invoice->id)->pluck('id') as $creditId) {
                $creditTotal += $this->removeCredit((int) $creditId, $companyId);
            }

            return ['payments' => round($payTotal, 2), 'credits' => round($creditTotal, 2), 'rows' => $rows];
        });
    }

    protected function returnLinesFor(InvoicePayment $payment)
    {
        $marker = InvoicePayment::returnedCheckMarker((int) $payment->id);

        return InvoicePayment::query()
            ->where('invoice_id', $payment->invoice_id)
            ->where('payment_method', InvoicePayment::RETURNED_CHECK_METHOD)
            ->where(fn ($q) => $q->where('comments', $marker)->orWhere('comments', 'like', $marker.' ·%'))
            ->lockForUpdate()
            ->get();
    }

    /**
     * $owedDelta: change in what the customer owes from payments/credits.
     * $chargeDelta: change to invoice Miscellaneous (returned-check fee), also owed by the customer.
     */
    protected function restoreInvoice(int $invoiceId, float $owedDelta, float $chargeDelta = 0.0): void
    {
        $invoice = Invoice::query()
            ->lockForUpdate()
            ->find($invoiceId);
        if (! $invoice) {
            return;
        }

        if (abs($chargeDelta) > 0.0001) {
            $chargeDelta = max(-(float) $invoice->miscellaneous, $chargeDelta);
            $invoice->update([
                'miscellaneous' => round((float) $invoice->miscellaneous + $chargeDelta, 4),
                'invoice_total' => round((float) $invoice->invoice_total + $chargeDelta, 4),
            ]);
        }

        $invoice->unsetRelation('payments');
        $invoice->unsetRelation('credits');
        $invoice->load(['payments', 'credits']);
        $invoice->update([
            'status' => round((float) $invoice->invoice_balance, 2) <= 0.0001 ? 'PAID' : 'NOT PAID',
        ]);

        $customerDelta = round($owedDelta + $chargeDelta, 2);
        if (abs($customerDelta) > 0.0001 && $invoice->customer_id) {
            $customer = Customer::query()->lockForUpdate()->find($invoice->customer_id);
            $customer?->update([
                'balance' => round((float) $customer->balance + $customerDelta, 2),
            ]);
        }
    }
}
