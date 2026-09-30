<?php

namespace App\Support\Store;

use App\Models\Customer;

class WholesaleAccount
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * Online-store approval. Accounts created in the POS with Customer App access count as approved.
     */
    public static function status(Customer $customer): string
    {
        $status = (string) $customer->web_status;
        if (in_array($status, [self::PENDING, self::APPROVED, self::REJECTED], true)) {
            return $status;
        }

        return $customer->portal_active ? self::APPROVED : self::PENDING;
    }

    public static function isApproved(Customer $customer): bool
    {
        return ! $customer->is_inactive && static::status($customer) === self::APPROVED;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::APPROVED => 'Approved wholesale account',
            self::REJECTED => 'Application rejected',
            default => 'Pending approval',
        };
    }
}
