<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imported credits were written onto paid invoices but the memo status stayed Open.
 * A memo is Applied only when its full amount is on invoices and every one of those invoices is PAID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updated = DB::update("
            UPDATE credit_memos cm
            INNER JOIN (
                SELECT credit_memo_id, SUM(amount) AS applied
                FROM invoice_credits
                WHERE credit_memo_id IS NOT NULL
                GROUP BY credit_memo_id
            ) ap ON ap.credit_memo_id = cm.id
            SET cm.status = 'Applied', cm.updated_at = ?
            WHERE cm.status = 'Open'
              AND cm.amount - ap.applied <= 0.01
              AND NOT EXISTS (
                  SELECT 1
                  FROM invoice_credits ic
                  INNER JOIN invoices i ON i.id = ic.invoice_id
                  WHERE ic.credit_memo_id = cm.id
                    AND i.status <> 'PAID'
              )
        ", [now()]);

        Log::info('Marked fully applied credit memos on paid invoices', ['updated' => $updated]);
    }

    public function down(): void
    {
        //
    }
};
