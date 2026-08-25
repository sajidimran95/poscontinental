<?php

namespace App\Services;

use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    /**
     * Apply open credit memos to an invoice (oldest memo first).
     * Returns the total credit amount applied.
     */
    public function applyOpenCreditsToInvoice(Invoice $invoice, bool $adjustCustomerBalance = true): float
    {
        $appliedTotal = 0.0;

        DB::transaction(function () use ($invoice, $adjustCustomerBalance, &$appliedTotal) {
            $invoice = Invoice::query()
                ->with(['payments', 'credits', 'customer'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $due = round((float) $invoice->invoice_balance, 2);
            if ($due <= 0.0001 || ! $invoice->customer_id) {
                return;
            }

            $memos = CreditMemo::query()
                ->where('company_id', $invoice->company_id)
                ->where('customer_id', $invoice->customer_id)
                ->where('status', 'Open')
                ->orderBy('memo_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                ->values();

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

                $appliedTotal += $apply;
                $due = round($due - $apply, 2);
            }

            if ($appliedTotal <= 0.0001) {
                return;
            }

            $invoice->unsetRelation('payments');
            $invoice->unsetRelation('credits');
            $invoice->refresh();
            $invoice->load(['payments', 'credits']);
            $invoice->update([
                'status' => round((float) $invoice->invoice_balance, 2) <= 0.0001 ? 'PAID' : 'NOT PAID',
            ]);

            if ($adjustCustomerBalance && $invoice->customer) {
                $invoice->customer->update([
                    'balance' => max(0, round((float) $invoice->customer->balance - $appliedTotal, 2)),
                ]);
            }
        });

        return round($appliedTotal, 2);
    }
}
