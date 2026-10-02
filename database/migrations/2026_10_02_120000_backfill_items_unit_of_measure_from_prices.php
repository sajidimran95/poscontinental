<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fill empty items.unit_of_measure from the first pricing-row UOM (Browse already shows these).
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                UPDATE items i
                INNER JOIN (
                    SELECT p1.item_id, p1.uom
                    FROM item_prices p1
                    INNER JOIN (
                        SELECT item_id, MIN(CONCAT(LPAD(IFNULL(sort_order, 0), 10, '0'), '-', LPAD(id, 20, '0'))) AS pick
                        FROM item_prices
                        WHERE uom IS NOT NULL AND TRIM(uom) <> ''
                        GROUP BY item_id
                    ) x ON x.item_id = p1.item_id
                        AND CONCAT(LPAD(IFNULL(p1.sort_order, 0), 10, '0'), '-', LPAD(p1.id, 20, '0')) = x.pick
                    WHERE p1.uom IS NOT NULL AND TRIM(p1.uom) <> ''
                ) src ON src.item_id = i.id
                SET i.unit_of_measure = UPPER(TRIM(src.uom))
                WHERE i.unit_of_measure IS NULL OR TRIM(i.unit_of_measure) = ''
            ");

            return;
        }

        // SQLite / tests: row-by-row is fine for in-memory DBs.
        $rows = DB::table('item_prices')
            ->whereNotNull('uom')
            ->where('uom', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['item_id', 'uom']);

        $firstByItem = [];
        foreach ($rows as $row) {
            $id = (int) $row->item_id;
            if (! isset($firstByItem[$id])) {
                $firstByItem[$id] = strtoupper(trim((string) $row->uom));
            }
        }

        foreach ($firstByItem as $itemId => $uom) {
            if ($uom === '') {
                continue;
            }
            DB::table('items')
                ->where('id', $itemId)
                ->where(function ($q) {
                    $q->whereNull('unit_of_measure')->orWhere('unit_of_measure', '');
                })
                ->update(['unit_of_measure' => $uom]);
        }
    }

    public function down(): void
    {
        // Irreversible data fill — leave values in place.
    }
};
