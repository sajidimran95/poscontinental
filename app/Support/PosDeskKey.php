<?php

namespace App\Support;

class PosDeskKey
{
    public static function fromUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/home';
        $path = rtrim($path, '/');
        if ($path === '' || $path === '/') {
            $path = '/home';
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['pos_embed']);

        if (str_contains($path, '/sales/orders/create')) {
            $w = isset($query['w']) ? (string) $query['w'] : 'active';

            return 'so:'.$w;
        }

        if (str_ends_with($path, '/create')) {
            return $path;
        }

        // One edit desk per list (list URL stays /foo; edit/show is /foo/edit).
        if (preg_match('#^(.+)/(\d+)(?:/(?:edit|show|print))?$#', $path, $m)) {
            return $m[1].'/edit';
        }
        if (preg_match('#^(.+)/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?:/(?:edit|show))?$#i', $path, $m)) {
            return $m[1].'/edit';
        }

        return $path === '' ? '/home' : $path;
    }
}
