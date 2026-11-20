<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Speed: gzip Livewire update responses (the JSON sent back on every click).
 *
 * The live host (LiteSpeed) compresses normal pages but sends these POST/JSON
 * responses uncompressed — 80–700 KB per click on Sales Order / item browse.
 * Same bytes after the browser decompresses them; nothing else changes.
 * Skips anything already encoded, streamed, files, small bodies, or clients
 * that do not accept gzip.
 */
class CompressLivewireResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasHeader('X-Livewire')
            || ! function_exists('gzencode')
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->headers->has('Content-Encoding')
            || ! str_contains(strtolower((string) $request->header('Accept-Encoding')), 'gzip')
        ) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < 2048) {
            return $response;
        }

        $gz = gzencode($content, 5);
        if ($gz === false) {
            return $response;
        }

        $response->setContent($gz);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($gz));
        $vary = $response->headers->get('Vary');
        $response->headers->set('Vary', $vary ? $vary.', Accept-Encoding' : 'Accept-Encoding');

        return $response;
    }
}
