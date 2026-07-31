<?php

use App\Models\Role;
use App\Models\User;
use App\Support\AppFeatures;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Strip File admin features from non-admin roles (Company Settings, Email, etc.).
        Role::query()->where('name', '!=', 'admin')->each(function (Role $role): void {
            $raw = $role->permissions;
            if (! is_array($raw)) {
                $role->update(['permissions' => AppFeatures::defaultRolePermissionTokens()]);

                return;
            }

            $map = AppFeatures::expand($raw) ?? [];
            $tokens = AppFeatures::withoutRestrictedAdmin(AppFeatures::flatten($map));
            $role->update(['permissions' => $tokens]);
        });

        // Strip the same features from non-admin users' personal overrides.
        User::query()
            ->whereNotNull('permissions')
            ->where(function ($q) {
                $q->whereNull('role_id')
                    ->orWhereHas('role', fn ($r) => $r->where('name', '!=', 'admin'));
            })
            ->each(function (User $user): void {
                if (! is_array($user->permissions)) {
                    return;
                }

                $map = AppFeatures::expand($user->permissions) ?? [];
                $user->update([
                    'permissions' => AppFeatures::withoutRestrictedAdmin(AppFeatures::flatten($map)),
                ]);
            });
    }

    public function down(): void
    {
        // Irreversible permission cleanup.
    }
};
