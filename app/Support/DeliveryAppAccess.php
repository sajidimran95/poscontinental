<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may use the Delivery PWA (/delivery). Desktop POS is unchanged.
 */
class DeliveryAppAccess
{
    public static function allows(?User $user): bool
    {
        if (! $user || ! $user->is_active || ! $user->company_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        return $user->isDelivery() || $user->canAccessFeature('delivery.driver', 'view');
    }

    public static function denyMessage(?User $user): string
    {
        if (! $user) {
            return 'Please sign in.';
        }

        if ($user->isAdmin()) {
            return 'Admin cannot use Delivery App login. Use the main POS. This app is for delivery drivers only.';
        }

        if (! $user->is_active) {
            return 'Your account is inactive.';
        }

        return 'Your account does not have Delivery access. Ask admin to assign the Delivery role.';
    }
}
