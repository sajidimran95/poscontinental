<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Server: desktop PCs allowed to sync (sync_nodes table).
 * Used by `php artisan sync:device` and the Desktop devices settings page.
 */
class SyncDevices
{
    public static function all()
    {
        return DB::table('sync_nodes')->orderBy('node_no')->get();
    }

    public static function find(int $node_no)
    {
        return DB::table('sync_nodes')->where('node_no', $node_no)->first();
    }

    public static function freeNodeNo(): ?int
    {
        $step = (int) config('sync.id_step');
        $used = DB::table('sync_nodes')->pluck('node_no')->all();

        for ($n = (int) config('sync.server_node_no') + 1; $n <= $step; $n++) {
            if (! in_array($n, $used)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * @return array{node_no:int, prefix:string, token:string}
     */
    public static function add(string $name, ?string $prefix = null): array
    {
        return DB::transaction(function () use ($name, $prefix) {
            DB::table('sync_nodes')->lockForUpdate()->get(['id']);

            $node_no = self::freeNodeNo();
            if (empty($node_no)) {
                throw new RuntimeException('No free node numbers left (max '.((int) config('sync.id_step') - 1).' desktops).');
            }

            $prefix = strtoupper(trim((string) $prefix) ?: 'PC'.$node_no);
            $token = Str::random(48);

            DB::table('sync_nodes')->insert([
                'node_no' => $node_no,
                'name' => trim($name),
                'invoice_prefix' => $prefix,
                'token_hash' => hash('sha256', $token),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['node_no' => $node_no, 'prefix' => $prefix, 'token' => $token];
        });
    }

    /**
     * New token for a device; the old one stops working. Null if not found.
     */
    public static function newToken(int $node_no): ?string
    {
        $token = Str::random(48);
        $updated = DB::table('sync_nodes')->where('node_no', $node_no)
            ->update(['token_hash' => hash('sha256', $token), 'updated_at' => now()]);

        return $updated ? $token : null;
    }

    public static function setActive(int $node_no, bool $active): bool
    {
        return (bool) DB::table('sync_nodes')->where('node_no', $node_no)
            ->update(['is_active' => $active ? 1 : 0, 'updated_at' => now()]);
    }
}
