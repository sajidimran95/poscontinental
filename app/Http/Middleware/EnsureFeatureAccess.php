<?php

namespace App\Http\Middleware;

use App\Support\AppFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['home', 'dashboard', 'profile', 'logout', 'media.show', 'lookups.index', 'admin.terminal'], true)) {
            return $next($request);
        }

        $feature = AppFeatures::featureForRoute($routeName);
        if (! $feature) {
            return $next($request);
        }

        $action = AppFeatures::actionForRoute($routeName);

        if ($user->canAccessFeature($feature, $action)) {
            return $next($request);
        }

        abort(403, 'Your role does not have '.$action.' access to this feature.');
    }
}
