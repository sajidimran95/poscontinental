<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Compresses sync API JSON. Snapshot pages are ~1.5 MB of JSON and shrink ~30x;
 * slow shared-host connections drop long transfers, so less bytes = fewer failures.
 * The desktop's Guzzle/curl client sends Accept-Encoding and decodes automatically.
 */
class SyncGzip
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse
            || ! function_exists('gzencode')
            || ! str_contains(strtolower((string) $request->header('Accept-Encoding')), 'gzip')
            || $response->headers->has('Content-Encoding')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < 2048) {
            return $response;
        }

        $response->setContent(gzencode($content, 6));
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->remove('Content-Length');

        return $response;
    }
}
