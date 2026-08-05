<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Item;
use App\Models\User;

/**
 * Company + item rules for selling with zero / negative on-hand stock.
 */
class StockPolicy
{
    public static function company(?Company $company = null): ?Company
    {
        if ($company) {
            return $company;
        }

        $user = auth()->user();
        if ($user instanceof User && $user->company) {
            return $user->company;
        }

        return null;
    }

    /**
     * When true, sales may push quantity_in_stock below zero (oversell).
     * Later purchases add stock: e.g. on-hand -10 + receive 100 = 90.
     */
    public static function allowsNegativeStock(?Company $company = null): bool
    {
        $company = self::company($company);
        if (! $company) {
            return true;
        }

        return (bool) ($company->allow_negative_stock ?? true);
    }

    /**
     * Whether ordered qty may exceed currently available stock for this item.
     */
    public static function allowsOversell(?Company $company = null, ?Item $item = null): bool
    {
        if (self::allowsNegativeStock($company)) {
            return true;
        }

        return $item !== null && (bool) $item->allow_back_order;
    }

    /**
     * Validate order qty. Returns null if OK, or error message.
     */
    public static function orderQtyError(Item $item, float $needed, float $available, ?Company $company = null): ?string
    {
        if ($needed <= 0) {
            return null;
        }

        if (self::allowsOversell($company, $item)) {
            return null;
        }

        if ($available <= 0) {
            return $item->item_code.' has no stock available and cannot be sold (overselling is turned off in File → Overselling Settings).';
        }

        if ($needed > $available + 0.0001) {
            return $item->item_code.' ordered qty ('.number_format($needed, 2).') exceeds available stock ('.number_format($available, 2).').';
        }

        return null;
    }

    /**
     * Invoice ship qty vs on-hand.
     */
    public static function invoiceQtyError(Item $item, float $qty, float $onHand, ?Company $company = null): ?string
    {
        if ($qty <= 0) {
            return null;
        }

        if (self::allowsOversell($company, $item)) {
            return null;
        }

        if ($qty > $onHand + 0.0001) {
            return $item->item_code.' cannot invoice qty '.number_format($qty, 2).' — only '.number_format($onHand, 2).' in stock.';
        }

        return null;
    }
}
