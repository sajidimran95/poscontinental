<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoices imported from Chief as PAID whose credits did not fully come over:
 * memos older than the 2-year import window, credits applied in Chief without a memo link,
 * and a memo Chief applied only partly. Target credit totals are Chief's Invoices_tbl.TotalCredits.
 */
return new class extends Migration
{
    /** invoice_number => Chief TotalCredits */
    private const TARGET_CREDITS = [
        '95645' => 225.84,
        '90252' => 1832.28,
        '86123' => 69.57,
        '85930' => 770.18,
        '91478' => 1115.42,
        '94680' => 122.97,
        '94458' => 181.07,
        '92933' => 70.35,
        '87519' => 1231.07,
    ];

    /** Chief memos dated before the import window: invoice_number => [memo_number, memo_date, amount] */
    private const OLD_MEMOS = [
        '95645' => ['1362', '2024-09-11', 225.84],
        '90252' => ['1259', '2023-10-24', 1276.35],
        '86123' => ['1301', '2024-02-27', 69.57],
        '85930' => ['1368', '2024-10-02', 770.18],
    ];

    /** Memo Chief applied only partly: invoice_number => [memo_number, applied amount] */
    private const PARTIAL_MEMOS = [
        '87519' => ['1393', 1231.07],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            foreach (self::TARGET_CREDITS as $number => $target) {
                $invoice = DB::table('invoices')->where('invoice_number', $number)->first();
                if (! $invoice) {
                    continue;
                }

                if (isset(self::OLD_MEMOS[$number])) {
                    [$memoNumber, $memoDate, $amount] = self::OLD_MEMOS[$number];
                    $memoId = DB::table('credit_memos')
                        ->where('company_id', $invoice->company_id)
                        ->where('memo_number', $memoNumber)
                        ->value('id');
                    if (! $memoId) {
                        $memoId = DB::table('credit_memos')->insertGetId([
                            'company_id' => $invoice->company_id,
                            'memo_number' => $memoNumber,
                            'memo_date' => $memoDate,
                            'customer_id' => $invoice->customer_id,
                            'amount' => $amount,
                            'status' => 'Open',
                            'comments' => 'Credit Memo',
                            'restock_inventory' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    $linked = DB::table('invoice_credits')
                        ->where('invoice_id', $invoice->id)
                        ->where('credit_memo_id', $memoId)
                        ->exists();
                    if (! $linked) {
                        DB::table('invoice_credits')->insert([
                            'invoice_id' => $invoice->id,
                            'credit_memo_id' => $memoId,
                            'amount' => $amount,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                if (isset(self::PARTIAL_MEMOS[$number])) {
                    [$memoNumber, $applied] = self::PARTIAL_MEMOS[$number];
                    $memoId = DB::table('credit_memos')
                        ->where('company_id', $invoice->company_id)
                        ->where('memo_number', $memoNumber)
                        ->value('id');
                    if ($memoId) {
                        DB::table('invoice_credits')
                            ->where('invoice_id', $invoice->id)
                            ->where('credit_memo_id', $memoId)
                            ->update(['amount' => $applied, 'updated_at' => $now]);
                    }
                }

                $current = (float) DB::table('invoice_credits')->where('invoice_id', $invoice->id)->sum('amount');
                $missing = round($target - $current, 2);
                if ($missing > 0.009) {
                    DB::table('invoice_credits')->insert([
                        'invoice_id' => $invoice->id,
                        'credit_memo_id' => null,
                        'amount' => $missing,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $paid = (float) DB::table('invoice_payments')->where('invoice_id', $invoice->id)->sum('amount');
                $credits = (float) DB::table('invoice_credits')->where('invoice_id', $invoice->id)->sum('amount');
                $balance = round((float) $invoice->invoice_total - $paid - $credits, 2);
                DB::table('invoices')->where('id', $invoice->id)->update([
                    'status' => $balance <= 0.009 ? 'PAID' : 'NOT PAID',
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
