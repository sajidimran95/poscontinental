<?php

namespace App\Policies;

use App\Models\DeliveryRoute;
use App\Models\User;

class DeliveryRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessFeature('delivery.manage', 'view')
            || $user->canAccessFeature('delivery.driver', 'view');
    }

    public function view(User $user, DeliveryRoute $route): bool
    {
        if ((int) $route->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->canAccessFeature('delivery.manage', 'view')) {
            return true;
        }

        $isDriver = $user->isDelivery() || $user->canAccessFeature('delivery.driver', 'view');

        return $isDriver && (int) $route->delivery_user_id === (int) $user->id;
    }

    public function update(User $user, DeliveryRoute $route): bool
    {
        if ((int) $route->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->canAccessFeature('delivery.manage', 'edit')) {
            return true;
        }

        $isDriver = $user->isDelivery() || $user->canAccessFeature('delivery.driver', 'edit');

        return $isDriver && (int) $route->delivery_user_id === (int) $user->id;
    }

    public function assign(User $user): bool
    {
        return $user->canAccessFeature('delivery.manage', 'edit');
    }
}
