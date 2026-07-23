<?php

namespace App\Http\Controllers;

use App\Support\ItemMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemMediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:8192'],
            'type' => ['required', 'in:image,thumbnail'],
        ], [
            'file.required' => 'Choose an image file.',
            'file.mimes' => 'Image must be JPG, PNG, GIF, WEBP, or BMP.',
            'file.max' => 'Image must be 8 MB or smaller.',
        ]);

        $directory = $validated['type'] === 'thumbnail' ? 'items/thumbnails' : 'items/images';

        Storage::disk('public')->makeDirectory($directory);

        $path = $request->file('file')->store($directory, 'public');

        if (! $path) {
            return response()->json(['message' => 'Could not store image.'], 422);
        }

        $published = ItemMedia::publishToPublic($path);

        return response()->json([
            'path' => $path,
            'url' => $published ? '/'.$published : '/media/'.$path,
        ]);
    }
}
