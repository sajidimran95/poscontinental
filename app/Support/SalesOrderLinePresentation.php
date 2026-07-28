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
}
