<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpreadsheetDownloadController extends Controller
{
    public function show(string $token): BinaryFileResponse
    {
        $payload = Cache::pull('xlsx_dl:'.$token);
        if (! is_array($payload) || (int) ($payload['user'] ?? 0) !== (int) auth()->id()) {
            abort(404);
        }

        $path = (string) ($payload['path'] ?? '');
        $name = (string) ($payload['name'] ?? 'export.xlsx');
        if ($path === '' || ! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
