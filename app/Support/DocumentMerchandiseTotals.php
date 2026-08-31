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
     *     cigarette_total: float,
     *     tobacco_count: int,
     *     tobacco_total: float,
     *     other_count: int,
     *     other_total: float,
     *     all_count: int,
     *     all_total: float
     * }
     */
    public static function fromLines(iterable $lines): array
    {
        $cigaretteCount = 0;
        $tobaccoCount = 0;
        $otherCount = 0;
        $cigaretteTotal = 0.0;
        $tobaccoTotal = 0.0;
        $otherTotal = 0.0;

        foreach ($lines as $line) {
            $amount = (float) ($line->line_total ?? 0);
            $item = $line->item ?? null;
            $item = $item instanceof Item ? $item : null;

            if (TobaccoItem::kind($item) === 'cigarettes') {
                $cigaretteCount++;
                $cigaretteTotal += $amount;
            } elseif (TobaccoItem::isTobacco($item)) {
                $tobaccoCount++;
                $tobaccoTotal += $amount;
            } else {
                $otherCount++;
                $otherTotal += $amount;
            }
        }

        return [
            'cigarette_count' => $cigaretteCount,
            'cigarette_total' => round($cigaretteTotal, 2),
            'tobacco_count' => $tobaccoCount,
            'tobacco_total' => round($tobaccoTotal, 2),
            'other_count' => $otherCount,
            'other_total' => round($otherTotal, 2),
            'all_count' => $cigaretteCount + $tobaccoCount + $otherCount,
            'all_total' => round($cigaretteTotal + $tobaccoTotal + $otherTotal, 2),
        ];
    }
}
