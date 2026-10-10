<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Item edits saved a new List Price but rewrote the base "Prices by UOM" row with the
 * old price, so Sales Orders kept using it. Move those rows to the current List Price.
 * Only rows still holding the price from before the latest List Price change are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_prices') || ! Schema::hasTable('item_price_histories')) {
            return;
        }

        $fixed = 0;
        $codes = [];

        DB::table('items')
            ->join('item_prices', 'item_prices.item_id', '=', 'items.id')
            ->whereNull('item_prices.price_level_id')
            ->whereRaw('ABS(item_prices.price - items.list_price) >= 0.005')
            ->select([
                'item_prices.id as price_id',
                'item_prices.price as row_price',
                'item_prices.uom as row_uom',
                'items.id as item_id',
                'items.item_code',
                'items.unit_of_measure',
                'items.list_price',
            ])
            ->orderBy('item_prices.id')
            ->chunk(500, function ($rows) use (&$fixed, &$codes) {
                foreach ($rows as $row) {
                    $baseUom = strtoupper(trim((string) $row->unit_of_measure));
                    $rowUom = strtoupper(trim((string) $row->row_uom));
                    if ($rowUom !== '' && $baseUom !== '' && $rowUom !== $baseUom) {
                        continue;
                    }

                    $last = DB::table('item_price_histories')
                        ->where('item_id', $row->item_id)
                        ->whereNotNull('list_price_after')
                        ->orderByDesc('id')
                        ->first(['list_price_before', 'list_price_after']);

                    if (! $last
                        || abs((float) $last->list_price_after - (float) $row->list_price) >= 0.005
                        || abs((float) $last->list_price_before - (float) $row->row_price) >= 0.005) {
                        continue;
                    }

                    DB::table('item_prices')
                        ->where('id', $row->price_id)
                        ->update(['price' => $row->list_price, 'updated_at' => now()]);
                    $fixed++;
                    $codes[] = $row->item_code;
                }
            });

        Log::info('Repaired stale base UOM prices', ['count' => $fixed, 'items' => $codes]);
    }

    public function down(): void
    {
        // Data repair only.
    }
};
