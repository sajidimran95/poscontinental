<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chief left an unprocessed (New) receipt next to the processed one for these POs.
 * Only deleted while still New and the same PO has a Processed receiving; they never touched stock.
 */
return new class extends Migration
{
    private const RECEIPT_NUMBERS = [
        '989865',
        '1010060',
        '20077170260961',
        '1031462',
        '20077170261008',
        '1038542',
        '1045206',
        '260516617',
        '1070521',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $ids = DB::table('inventory_receivings as r')
                ->whereIn('r.receipt_number', self::RECEIPT_NUMBERS)
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
