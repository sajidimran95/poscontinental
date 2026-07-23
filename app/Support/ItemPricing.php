<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemPrice;

class ItemPricing
{
    /**
     * Resolve sell price for an item, preferring customer price level then UOM then list.
     */
    public static function resolve(Item $item, ?int $priceLevelId = null, ?string $uom = null): float
    {
        $prices = $item->relationLoaded('prices') ? $item->prices : $item->prices()->get();
        $uom = $uom ?? ($item->unit_of_measure ?: null);

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

        return (float) $item->list_price;
    }
}
