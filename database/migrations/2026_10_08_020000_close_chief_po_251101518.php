<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PO 251101518 was closed in Chief without its single line being received.
 * Close it here too and drop the unprocessed receiving created for it; stock is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $po = DB::table('purchase_orders')->where('po_number', '251101518')->first();
            if (! $po) {
                return;
            }

            $ids = DB::table('inventory_receivings')
                ->where('purchase_order_id', $po->id)
                ->where('status', 'New')
                ->whereNull('processed_at')
                ->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('inventory_receiving_lines')->whereIn('inventory_receiving_id', $ids)->delete();
                DB::table('inventory_receivings')->whereIn('id', $ids)->delete();
            }

            DB::table('purchase_orders')->where('id', $po->id)->update([
                'status' => 'Received',
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        //
    }
};
