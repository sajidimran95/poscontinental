<?php

namespace App\Http\Middleware;

use App\Sync\SyncNode;
use App\Sync\SyncSchema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Authenticates a desktop PC on the server by its sync token (Authorization: Bearer ...).
 */
class SyncNodeAuth
{
    public function handle(Request $request, Closure $next, ?string $checkSchema = null)
    {
        if (! SyncNode::isServer()) {
            return response()->json(['success' => false, 'message' => 'Sync server is not enabled (SYNC_MODE=server).'], 404);
        }

        $token = (string) $request->bearerToken();
        $node = $token === '' ? null : DB::table('sync_nodes')
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', 1)
            ->first();

        if (empty($node)) {
            return response()->json(['success' => false, 'message' => 'Invalid or disabled device token.'], 401);
        }

        if ($checkSchema === 'schema') {
            $serverHash = Cache::remember('sync.schema_hash', 60, fn () => SyncSchema::schemaHash());
            if ($request->header('X-Sync-Schema') !== $serverHash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Desktop app version does not match the server. Update the desktop app (or the server) so both run the same version.',
                ], 409);
            }
        }

        DB::table('sync_nodes')->where('id', $node->id)->update([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'app_version' => mb_substr((string) $request->header('X-App-Version'), 0, 30) ?: null,
        ]);

        $request->attributes->set('sync_node', $node);

        return $next($request);
    }
}
