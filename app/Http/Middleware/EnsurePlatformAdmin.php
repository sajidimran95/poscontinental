<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Platform admin only.'], 403);
            }

            abort(403, 'Platform admin only.');
        }

        return $next($request);
    }
}
