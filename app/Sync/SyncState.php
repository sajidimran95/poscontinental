<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/** Small key/value store in the sync_state table. */
class SyncState
{
    public static function get(string $key, $default = null)
    {
        $value = DB::table('sync_state')->where('key', $key)->value('value');

        return $value === null ? $default : json_decode($value, true);
    }

    public static function put(string $key, $value): void
    {
        DB::table('sync_state')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now()]
        );
    }

    public static function forget(string $key): void
    {
        DB::table('sync_state')->where('key', $key)->delete();
    }
}
