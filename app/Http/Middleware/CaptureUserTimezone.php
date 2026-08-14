<?php

namespace App\Http\Middleware;

use App\Support\UserTimezone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $tz = UserTimezone::sanitize($request->cookie('pos_tz'))
            ?: UserTimezone::sanitize($request->header('X-Timezone'));

        if ($tz && $request->hasSession()) {
            $request->session()->put('pos_tz', $tz);
        }

        return $next($request);
    }
}
