<?php

namespace App\Sync;

use Illuminate\Support\Facades\File;

/**
 * Uploaded files (public/uploads: product images, logos, documents) travel between
 * server and desktops. New or changed files are copied, nothing is deleted.
 */
class FileSync
{
    // never allow scripts / html: an upload lands in a public folder of the server
    const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];

    const SKIP_DIRS = ['temp', 'tmp'];

    public static function root(): string
    {
        return public_path('uploads');
    }

    /** Relative path if it is a safe, allowed file path, else null. */
    public static function safePath(?string $path): ?string
    {
        $path = str_replace('\\', '/', trim((string) $path, '/\\'));
        if ($path === '' || str_contains($path, '..') || str_contains($path, ':') || str_contains($path, "\0")) {
            return null;
        }
        $parts = explode('/', $path);
        if (in_array(strtolower($parts[0]), self::SKIP_DIRS) || str_starts_with(basename($path), '.')) {
            return null;
        }
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED)) {
            return null;
        }

        return $path;
    }

    public static function absolute(string $relative): string
    {
        return self::root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /** Files changed since a unix time: [[path, size, mtime], ...] */
    public static function manifest(int $since = 0): array
    {
        $files = [];
        if (! is_dir(self::root())) {
            return $files;
        }
        foreach (File::allFiles(self::root()) as $file) {
            $relative = self::safePath($file->getRelativePathname());
            if ($relative === null || $file->getMTime() < $since) {
                continue;
            }
            $files[] = ['path' => $relative, 'size' => $file->getSize(), 'mtime' => $file->getMTime()];
        }

        return $files;
    }

    /** True when the local copy is missing or differs from the given size. */
    public static function needs(string $relative, int $size): bool
    {
        $abs = self::absolute($relative);

        return ! is_file($abs) || filesize($abs) !== $size;
    }

    public static function write(string $relative, string $contents, ?int $mtime = null): void
    {
        $abs = self::absolute($relative);
        File::ensureDirectoryExists(dirname($abs));
        file_put_contents($abs, $contents);
        if ($mtime) {
            @touch($abs, $mtime);
        }
    }
}
