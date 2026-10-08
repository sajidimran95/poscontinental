<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse role (and warehouse users with their own permission list) can view and edit sales orders.
 * Roles/users with NULL permissions already have full access and are left alone.
 */
return new class extends Migration
{
    private const GRANTS = ['sales.orders.view', 'sales.orders.edit'];

    private const OFF = 'sales.orders.off';

    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['warehouse'])
                    ->orWhereRaw('LOWER(label) LIKE ?', ['warehouse%']);
            })
            ->pluck('id');

        foreach (DB::table('roles')->whereIn('id', $roleIds)->get(['id', 'permissions']) as $role) {
            $updated = $this->withGrants($role->permissions);
            if ($updated !== null) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => $updated, 'updated_at' => now()]);
            }
        }

        foreach (DB::table('users')->whereIn('role_id', $roleIds)->get(['id', 'permissions']) as $user) {
            $updated = $this->withGrants($user->permissions);
            if ($updated !== null) {
                DB::table('users')->where('id', $user->id)->update(['permissions' => $updated]);
            }
        }
    }

    public function down(): void
    {
        //
    }

    /** JSON with the grants added, or null when nothing needs to change. */
    private function withGrants(?string $json): ?string
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $list = json_decode($json, true);
        if (! is_array($list) || $list === []) {
            return null;
        }

        $before = $list;
        $list = array_values(array_filter($list, fn ($p) => $p !== self::OFF));
        foreach (self::GRANTS as $grant) {
            if (! in_array($grant, $list, true)) {
                $list[] = $grant;
            }
        }

        return $list === $before ? null : json_encode($list, JSON_UNESCAPED_SLASHES);
    }
};
