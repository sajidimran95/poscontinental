<?php

namespace App\Http\Middleware;

use App\Support\DeliveryAppAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('delivery');
        $user?->loadMissing('role');
        if (! DeliveryAppAccess::allows($user)) {
            auth('delivery')->logout();

            return redirect()
                ->route('delivery.app.login')
                ->with('status', ['success' => 0, 'msg' => DeliveryAppAccess::denyMessage($user)]);
        }

        return $next($request);
    }
}
