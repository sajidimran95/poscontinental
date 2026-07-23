<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ItemMedia
{
    /**
     * Publish a storage/app/public file into public/uploads so the web server
     * can serve it without a storage symlink (common live/shared-hosting issue).
     */
    public static function publishToPublic(string $path): ?string
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (! str_starts_with($path, 'items/images/') && ! str_starts_with($path, 'items/thumbnails/')) {
            return null;
        }

        $publicRelative = 'uploads/'.$path;
        $publicFull = public_path($publicRelative);

        if (is_file($publicFull)) {
            return $publicRelative;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        File::ensureDirectoryExists(dirname($publicFull));
        File::put($publicFull, Storage::disk('public')->get($path));

        return is_file($publicFull) ? $publicRelative : null;
    }

    public static function forgetPublicCopy(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        $publicFull = public_path('uploads/'.$path);

        if (is_file($publicFull)) {
            @unlink($publicFull);
        }
    }

    public static function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        $published = self::publishToPublic($path);

        if ($published) {
            $version = @filemtime(public_path($published)) ?: time();

            return '/'.$published.'?v='.$version;
        }

        // Fallback: Laravel media route (no symlink required).
        if (Storage::disk('public')->exists($path)) {
            $version = Storage::disk('public')->lastModified($path);

            return '/media/'.$path.'?v='.$version;
        }

        // Last resort: storage link URL.
        if (is_file(public_path('storage/'.$path))) {
            $version = @filemtime(public_path('storage/'.$path)) ?: time();

            return '/storage/'.$path.'?v='.$version;
        }

        return null;
    }

    public static function exists(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));

        return Storage::disk('public')->exists($path)
            || is_file(public_path('uploads/'.$path))
            || is_file(public_path('storage/'.$path));
    }

    /**
     * Copy every item image/thumbnail from storage into public/uploads.
     *
     * @return array{copied:int, skipped:int, errors:int}
     */
    public static function syncAll(): array
    {
        $copied = 0;
        $skipped = 0;
        $errors = 0;

        foreach (['items/images', 'items/thumbnails'] as $directory) {
            if (! Storage::disk('public')->exists($directory)) {
                continue;
            }

            foreach (Storage::disk('public')->files($directory) as $file) {
                try {
                    $before = is_file(public_path('uploads/'.$file));
                    $result = self::publishToPublic($file);
                    if ($result === null) {
                        $errors++;
                    } elseif ($before) {
                        $skipped++;
                    } else {
                        $copied++;
                    }
                } catch (\Throwable) {
                    $errors++;
                }
            }
        }

        return compact('copied', 'skipped', 'errors');
    }
}
