<?php

namespace App\Support;

use App\Models\Item;

/**
 * Merchandise buckets for invoice / sales-order print footers.
 */
final class DocumentMerchandiseTotals
{
    /**
     * @param  iterable<mixed>  $lines
     * @return array{
     *     cigarette_count: int,
     *     cigarette_qty: float,
     *     cigarette_total: float,
     *     tobacco_count: int,
     *     tobacco_qty: float,
     *     tobacco_total: float,
     *     other_count: int,
     *     other_qty: float,
     *     other_total: float,
     *     all_count: int,
     *     all_qty: float,
     *     all_total: float
     * }
     */
    public static function fromLines(iterable $lines): array
    {
        $cigaretteCount = 0;
        $tobaccoCount = 0;
        $otherCount = 0;
        $cigaretteQty = 0.0;
        $tobaccoQty = 0.0;
        $otherQty = 0.0;
        $cigaretteTotal = 0.0;
        $tobaccoTotal = 0.0;
        $otherTotal = 0.0;

        foreach ($lines as $line) {
            $amount = (float) ($line->line_total ?? 0);
            $qty = (float) ($line->qty_ordered ?? $line->qty ?? 0);
            $item = $line->item ?? null;
            $item = $item instanceof Item ? $item : null;

            if (TobaccoItem::kind($item) === 'cigarettes') {
                $cigaretteCount++;
                $cigaretteQty += $qty;
                $cigaretteTotal += $amount;
            } elseif (TobaccoItem::isTobacco($item)) {
                $tobaccoCount++;
                $tobaccoQty += $qty;
                $tobaccoTotal += $amount;
            } else {
                $otherCount++;
                $otherQty += $qty;
                $otherTotal += $amount;
            }
        }

        return [
            'cigarette_count' => $cigaretteCount,
            'cigarette_qty' => round($cigaretteQty, 2),
            'cigarette_total' => round($cigaretteTotal, 2),
            'tobacco_count' => $tobaccoCount,
            'tobacco_qty' => round($tobaccoQty, 2),
            'tobacco_total' => round($tobaccoTotal, 2),
            'other_count' => $otherCount,
            'other_qty' => round($otherQty, 2),
            'other_total' => round($otherTotal, 2),
            'all_count' => $cigaretteCount + $tobaccoCount + $otherCount,
            'all_qty' => round($cigaretteQty + $tobaccoQty + $otherQty, 2),
            'all_total' => round($cigaretteTotal + $tobaccoTotal + $otherTotal, 2),
        ];
    }
}
