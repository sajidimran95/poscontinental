<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        if (! $customer || ! $customer->canUseCustomerApp()) {
            auth('customer')->logout();

            return redirect()
                ->route('customer.login')
                ->with('status', ['success' => 0, 'msg' => 'Customer app login is not active. Ask your seller to enable it.']);
        }

        return $next($request);
    }
}
