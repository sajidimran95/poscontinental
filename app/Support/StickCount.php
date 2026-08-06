<?php

namespace App\Support;

use App\Models\Item;

/**
 * Shared stick-count math for sales & purchase tobacco reports.
 */
class StickCount
{
    public static function forLine(?Item $item, float|int|string|null $qty): int
    {
        $qty = (float) ($qty ?? 0);
        if ($qty == 0.0) {
            return 0;
        }

        $perUnit = (int) ($item?->tobacco_stick_count ?? 0);
        if ($perUnit <= 0) {
            return 0;
        }

        return (int) round($perUnit * $qty);
    }
}
