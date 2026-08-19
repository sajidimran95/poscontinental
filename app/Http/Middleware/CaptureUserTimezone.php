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

        if (! $tz && $request->hasSession()) {
            $tz = UserTimezone::sanitize($request->session()->get('pos_tz'));
        }

        if ($tz) {
            if ($request->hasSession()) {
                $request->session()->put('pos_tz', $tz);
            }
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }

        return $next($request);
    }
}
