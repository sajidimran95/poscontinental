<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may use the Sales PWA (/sale). Same sales permissions as desktop roles.
 * Admins use the main POS login, not this app.
 */
class SaleAppAccess
{
    public static function allows(?User $user): bool
    {
        if (! $user || ! $user->is_active || ! $user->company_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        if ($user->isSalesRep()) {
            return true;
        }

        return $user->canAccessFeature('sales.orders', 'view');
    }

    public static function denyMessage(?User $user): string
    {
        if (! $user) {
            return 'Please sign in.';
        }

        if ($user->isAdmin()) {
            return 'Admin cannot use Sales App login. Use the main POS login. This portal is for sales representatives only.';
        }

        if (! $user->is_active) {
            return 'Your account is inactive. Ask an administrator to activate it.';
        }

        if (! $user->canAccessFeature('sales.orders', 'view') && ! $user->isSalesRep()) {
            return 'Your account does not have sales access. Ask admin to enable Sales Orders View on your Sales Rep role.';
        }

        return 'Access denied.';
    }
}
