<?php

namespace App\Http\Middleware;

use App\Support\SaleAppAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSaleApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sale');
        $user?->loadMissing('role');
        if (! SaleAppAccess::allows($user)) {
            auth('sale')->logout();

            return redirect()
                ->route('sale.login')
                ->with('status', ['success' => 0, 'msg' => SaleAppAccess::denyMessage($user)]);
        }

        return $next($request);
    }
}
