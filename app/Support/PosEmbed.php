<?php

namespace App\Support;

use Illuminate\Http\Request;

class PosEmbed
{
    public static function isEmbed(?Request $request = null): bool
    {
        $request ??= request();
        if ($request->boolean('pos_embed')) {
            return true;
        }

        $dest = strtolower((string) $request->headers->get('Sec-Fetch-Dest', ''));
        if ($dest === 'iframe') {
            return true;
        }
        if ($dest === 'document') {
            return false;
        }

        return $request->cookies->get('pos_iframe') === '1'
            && $request->headers->has('X-Livewire');
    }
}
