<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Diagnostics only: adds a `Server-Timing` header (visible in the browser's
 * DevTools → Network → Timing) so we can see where server time goes:
 * total PHP time, database time and query count, and the slowest queries.
 *
 * Only sent to logged-in administrators. Changes nothing else.
 * Safe to remove later (delete this file and its line in bootstrap/app.php).
 */
class ServerTimingHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $t0 = microtime(true);
        $boot = defined('LARAVEL_START') ? ($t0 - LARAVEL_START) * 1000 : 0.0;

        $count = 0;
        $dbMs = 0.0;
        $slow = [];
        DB::listen(function (QueryExecuted $q) use (&$count, &$dbMs, &$slow) {
            $count++;
            $dbMs += (float) $q->time;
            $slow[] = [(float) $q->time, $q->sql];
            if (count($slow) > 40) {
                usort($slow, fn ($a, $b) => $b[0] <=> $a[0]);
                $slow = array_slice($slow, 0, 5);
            }
        });

        $response = $next($request);

        try {
            $user = $request->user();
            if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
                return $response;
            }

            $total = (microtime(true) - $t0) * 1000;
            usort($slow, fn ($a, $b) => $b[0] <=> $a[0]);
            $top = array_slice($slow, 0, 5);

            $response->headers->set('Server-Timing', sprintf(
                'boot;dur=%.1f, app;dur=%.1f, db;dur=%.1f;desc="%d queries", php;dur=%.1f, dbmax;dur=%.1f',
                $boot,
                $total,
                $dbMs,
                $count,
                max(0, $total - $dbMs),
                $top[0][0] ?? 0
            ));

            $op = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
            $response->headers->set('X-Perf-Info', sprintf(
                'opcache=%s; cached_scripts=%s; hit_rate=%s; mem_used_mb=%s; db=%s',
                is_array($op) && ! empty($op['opcache_enabled']) ? '1' : '0',
                is_array($op) ? ($op['opcache_statistics']['num_cached_scripts'] ?? '?') : '-',
                is_array($op) ? round((float) ($op['opcache_statistics']['opcache_hit_rate'] ?? 0), 1) : '-',
                is_array($op) ? round(((float) ($op['memory_usage']['used_memory'] ?? 0)) / 1048576, 1) : '-',
                (string) config('database.connections.'.config('database.default').'.host')
            ));

            $lines = array_map(
                fn ($s) => round($s[0], 1).'ms '.preg_replace('/\s+/', ' ', mb_substr($s[1], 0, 220)),
                $top
            );
            $response->headers->set('X-Perf-Slow', base64_encode(implode("\n", $lines)));
            $response->headers->set('Access-Control-Expose-Headers', 'Server-Timing, X-Perf-Info, X-Perf-Slow');
        } catch (\Throwable $e) {
            // diagnostics must never break a page
        }

        return $response;
    }
}
