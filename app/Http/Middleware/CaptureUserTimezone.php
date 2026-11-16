<?php

namespace App\Http\Middleware;

use App\Support\UserTimezone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force company timezone from APP_TIMEZONE (ignore browser / cookie).
 */
class CaptureUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        UserTimezone::apply();

        return $next($request);
    }
}
