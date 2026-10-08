<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unprocessed (New) receivings left behind after another receiving fully received the same PO.
 * They never touched stock, so removing them only clears the list.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ids = DB::table('inventory_receivings as r')
                ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
                ->where('po.status', 'Received')
                ->where('r.status', 'New')
                ->whereNull('r.processed_at')
                ->whereExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('inventory_receivings as p')
                        ->whereColumn('p.purchase_order_id', 'r.purchase_order_id')
                        ->whereColumn('p.id', '!=', 'r.id')
                        ->where('p.status', 'Processed');
                })
                ->pluck('r.id');

            if ($ids->isEmpty()) {
                return;
            }

            DB::table('inventory_receiving_lines')->whereIn('inventory_receiving_id', $ids)->delete();
            DB::table('inventory_receivings')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        //
    }
};
