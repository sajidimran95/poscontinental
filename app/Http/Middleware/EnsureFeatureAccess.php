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
        if ($request->is('pos/tabs/*') || $request->is('team-chat/unread')) {
            return $next($request);
        }
        if (in_array($routeName, ['home', 'dashboard', 'profile', 'logout', 'media.show', 'admin.terminal', 'pos.tabs.open', 'pos.tabs.remember', 'pos.tabs.ensure', 'pos.tabs.close', 'pos.tabs.close-all', 'sales.orders.windows.open', 'sales.orders.windows.close', 'exports.xlsx', 'team-chat.unread'], true)) {
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

        $message = 'Your role does not have '.$action.' access to this feature.';

        if ($request->expectsJson()
            || $request->ajax()
            || $request->header('X-Livewire')
            || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['ok' => false, 'message' => $message], 403);
        }

        return redirect()
            ->route('home')
            ->with('pos_permission', $message);
    }
}
