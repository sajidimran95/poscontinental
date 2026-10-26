<?php

namespace App\Support;

use App\Models\CustomerItemPrice;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\ItemPriceHistory;

class ItemPricing
{
    /**
     * Resolve sell price: a real customer price, then price level, then the current list price.
     * The list price wins over an older UOM row, and over a saved customer price that is still the old list.
     */
    public static function resolve(Item $item, ?int $priceLevelId = null, ?string $uom = null, ?int $customerId = null): float
    {
        $uom = $uom ?? ($item->unit_of_measure ?: null);
        $list = round((float) $item->list_price, 4);

        if ($customerId) {
            $memorized = CustomerItemPrice::findPrice($customerId, (int) $item->id, $uom);
            if ($memorized !== null && ! self::memorizedIsPreviousList($item, $memorized, $list)) {
                return $memorized;
            }
        }

        $prices = $item->relationLoaded('prices') ? $item->prices : $item->prices()->get();

        if ($priceLevelId) {
            $levelRows = $prices->where('price_level_id', $priceLevelId);
            if ($uom) {
                $match = $levelRows->firstWhere('uom', $uom);
                if ($match) {
                    return (float) $match->price;
                }
            }
            $firstLevel = $levelRows->first();
            if ($firstLevel) {
                return (float) $firstLevel->price;
            }
        }

        if ($list > 0 && self::isBaseUom($item, $uom)) {
            return $list;
        }

        $general = $prices->filter(fn (ItemPrice $p) => blank($p->price_level_id));
        if ($uom) {
            $match = $general->firstWhere('uom', $uom) ?? $prices->firstWhere('uom', $uom);
            if ($match) {
                return (float) $match->price;
            }
        }

        $first = $general->first() ?? $prices->first();
        if ($first) {
            return (float) $first->price;
        }

        return $list;
    }

    /** A saved customer price that is only the list price from before the last sales-price change. */
    private static function memorizedIsPreviousList(Item $item, float $memorized, float $list): bool
    {
        if ($list <= 0 || abs($memorized - $list) < 0.005 || ! $item->exists) {
            return false;
        }

        $change = ItemPriceHistory::query()
            ->where('item_id', $item->id)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_SALES, ItemPriceHistory::TYPE_BOTH])
            ->whereNotNull('list_price_before')
            ->whereNotNull('list_price_after')
            ->orderByDesc('id')
            ->first();

        return $change
            && abs($memorized - (float) $change->list_price_before) < 0.005
            && abs($list - (float) $change->list_price_after) < 0.005;
    }

    private static function isBaseUom(Item $item, ?string $uom): bool
    {
        $base = strtoupper(trim((string) ($item->unit_of_measure ?: '')));
        $asked = strtoupper(trim((string) ($uom ?: '')));

        return $base === '' || $asked === '' || $asked === $base;
    }
}
