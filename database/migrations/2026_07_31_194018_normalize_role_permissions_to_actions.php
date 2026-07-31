<?php

use App\Support\AppFeatures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->select('id', 'permissions')->get();

        foreach ($roles as $role) {
            $raw = json_decode($role->permissions ?? 'null', true);
            if (! is_array($raw)) {
                continue;
            }

            // Already stored as feature.action tokens — leave alone if any look like tokens
            // and none look like bare legacy feature keys only.
            $map = AppFeatures::expand($raw);
            if ($map === null) {
                continue;
            }

            $tokens = AppFeatures::flatten($map);
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values($tokens)),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible conversion of granular tokens; no-op.
    }
};
