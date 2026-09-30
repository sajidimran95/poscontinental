<?php

namespace App\Http\Middleware;

use App\Support\Store\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(StoreContext::enabled(), 404);

        return $next($request);
    }
}
