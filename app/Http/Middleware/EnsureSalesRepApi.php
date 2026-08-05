<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales Rep mobile API: only active users with role sales_rep (Sales Representative).
 */
class EnsureSalesRepApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated. Sales rep token required.', 401);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return ApiResponse::error('Your account is inactive. Contact your administrator.', 403);
        }

        if (! $user->company_id) {
            return ApiResponse::error('User is not assigned to a company.', 403);
        }

        $user->loadMissing('role');

        if (! $user->isSalesRep()) {
            return ApiResponse::error(
                'Only Sales Representative users can use this app. Ask admin to set your role to Sales Rep.',
                403
            );
        }

        return $next($request);
    }
}
