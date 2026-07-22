<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized. Customer portal token required.'], 401);
        }

        if ($user->is_inactive) {
            return response()->json(['message' => 'Customer account is inactive.'], 403);
        }

        $company = $user->company;
        if ($company && ! $company->customer_app_api_active) {
            return response()->json(['message' => 'Customer app API is deactivated.'], 403);
        }

        return $next($request);
    }
}
