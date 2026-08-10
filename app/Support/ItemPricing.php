<?php

namespace App\Support;

use App\Models\CustomerItemPrice;
use App\Models\Item;
use App\Models\ItemPrice;

class ItemPricing
{
    /**
     * Resolve sell price: memorized customer price → price level → UOM/list.
     */
    public static function resolve(Item $item, ?int $priceLevelId = null, ?string $uom = null, ?int $customerId = null): float
    {
        $uom = $uom ?? ($item->unit_of_measure ?: null);

        if ($customerId) {
            $memorized = CustomerItemPrice::findPrice($customerId, (int) $item->id, $uom);
            if ($memorized !== null) {
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
