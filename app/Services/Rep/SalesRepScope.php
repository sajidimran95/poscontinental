<?php

namespace App\Services\Rep;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Section 11.9: a sales rep sees only their assigned customers.
 */
class SalesRepScope
{
    public static function customersQuery(User $user): Builder
    {
        return Customer::query()
            ->where('company_id', $user->company_id)
            ->where('sales_rep_id', $user->id);
    }

    public static function assertCustomerAccess(User $user, Customer $customer): void
    {
        abort_unless(
            (int) $customer->company_id === (int) $user->company_id
            && (int) $customer->sales_rep_id === (int) $user->id,
            403,
            'You can only access customers assigned to you.'
        );
    }

    public static function salesOrdersQuery(User $user): Builder
    {
        return SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where(function (Builder $q) use ($user) {
                $q->where('sales_rep_id', $user->id)
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('sales_rep_id', $user->id));
            });
    }

    public static function assertOrderAccess(User $user, SalesOrder $order): void
    {
        abort_unless((int) $order->company_id === (int) $user->company_id, 403);

        $allowed = (int) $order->sales_rep_id === (int) $user->id
            || (
                $order->customer
                && (int) $order->customer->sales_rep_id === (int) $user->id
            );

        abort_unless($allowed, 403, 'You can only access orders for your assigned customers.');
    }
}
