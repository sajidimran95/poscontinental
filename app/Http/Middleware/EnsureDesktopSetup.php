<?php

namespace App\Http\Middleware;

use App\Sync\SyncNode;
use App\Sync\SyncState;
use Closure;
use Illuminate\Http\Request;

/**
 * Desktop app: send every page to the setup screen until this PC is registered
 * and has downloaded the server data.
 */
class EnsureDesktopSetup
{
    public function handle(Request $request, Closure $next)
    {
        if (! SyncNode::isClient() || $request->is('desktop/setup', 'desktop/setup/*', '_native/*')) {
            return $next($request);
        }

        try {
            $ready = SyncNode::isConfigured() && ! empty(SyncState::get('bootstrapped_at'));
        } catch (\Throwable $e) {
            $ready = false; // local database not migrated yet
        }

        if (! $ready) {
            return redirect('/desktop/setup');
        }

        return $next($request);
    }
}
