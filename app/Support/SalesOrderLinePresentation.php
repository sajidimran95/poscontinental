<?php

namespace App\Support;

use App\Models\SalesOrderLine;

class SalesOrderLinePresentation
{
    /**
     * Line message for order/invoice PDFs: order line first, then item master default.
     */
    public static function lineMessage(SalesOrderLine $line): ?string
    {
        $fromLine = trim((string) ($line->line_message ?? ''));
        if ($fromLine !== '') {
            return $fromLine;
        }

        $line->loadMissing('item');
        $fromItem = trim((string) ($line->item?->item_line_message ?? ''));

        return $fromItem !== '' ? $fromItem : null;
    }

    /**
     * U/M for print: line → item.unit_of_measure → first item_prices.uom (many items only store UOM on prices).
     */
    public static function uom(SalesOrderLine $line): string
    {
        $fromLine = trim((string) ($line->uom ?? ''));
        if ($fromLine !== '') {
            return $fromLine;
        }

        $line->loadMissing(['item.prices']);
        $fromItem = trim((string) ($line->item?->unit_of_measure ?? ''));
        if ($fromItem !== '') {
            return $fromItem;
        }

        $prices = $line->item?->prices;
        if ($prices) {
            foreach ($prices as $price) {
                $u = trim((string) ($price->uom ?? ''));
                if ($u !== '') {
                    return $u;
                }
            }
        }

        return '';
    }
}
