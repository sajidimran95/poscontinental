<?php

namespace App\Http\Controllers;

use App\Support\ItemMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaController extends Controller
{
    /**
     * Serve item images without auth or storage symlink.
     */
    public function show(string $path): BinaryFileResponse|StreamedResponse
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $allowed = str_starts_with($path, 'items/images/')
            || str_starts_with($path, 'items/thumbnails/');

        abort_unless($allowed, 404);

        // Prefer public/uploads copy (static-friendly).
        $published = ItemMedia::publishToPublic($path);
        if ($published && is_file(public_path($published))) {
            return response()->file(public_path($published), [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
