<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;

class SeedAdminService
{
    public const EMAIL = 'admin@gmail.com';

    public const PASSWORD = 'password';

    /**
     * Ensure company, site, roles, Walk-in, and one admin user.
     * Never deletes other users or business data.
     *
     * @return array{created: bool, promoted: bool, password_reset: bool, email: string, password: string|null}
     */
    public function ensure(bool $resetPassword = false): array
    {
        $company = Company::query()->firstOrCreate(
            ['code' => 'CWI'],
            [
                'name' => 'Continental Wholesale Inc',
                'is_active' => true,
            ]
        );

        $site = Site::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WS'],
            ['name' => 'Main Site', 'is_active' => true]
        );

        foreach ([
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'sales_rep', 'label' => 'Sales Rep'],
            ['name' => 'buyer', 'label' => 'Buyer'],
            ['name' => 'warehouse', 'label' => 'Warehouse'],
            ['name' => 'delivery', 'label' => 'Delivery'],
        ] as $role) {
            Role::query()->firstOrCreate(
                ['name' => $role['name']],
                [
                    'label' => $role['label'],
                    'permissions' => match ($role['name']) {
                        'admin' => null,
                        'delivery' => ['delivery.driver.view', 'delivery.driver.edit'],
                        default => [],
                    },
                ]
            );
        }

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $user = User::query()->where('email', self::EMAIL)->first();
        $created = false;
        $promoted = false;
        $passwordReset = false;

        if (! $user) {
            $user = User::query()->create([
                'company_id' => $company->id,
                'site_id' => $site->id,
                'role_id' => $adminRole->id,
                'name' => 'POS Admin',
                'email' => self::EMAIL,
                'username' => self::EMAIL,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $created = true;
            $passwordReset = true;
        } else {
            $updates = [
                'is_active' => true,
                'company_id' => $user->company_id ?: $company->id,
                'site_id' => $user->site_id ?: $site->id,
            ];
            if ((int) $user->role_id !== (int) $adminRole->id) {
                $updates['role_id'] = $adminRole->id;
                $promoted = true;
            }
            if ($resetPassword) {
                $updates['password'] = self::PASSWORD;
                $passwordReset = true;
            }
            $user->fill($updates);
            $user->save();
        }

        Customer::ensureWalkIn((int) $company->id);

        return [
            'created' => $created,
            'promoted' => $promoted,
            'password_reset' => $passwordReset,
            'email' => self::EMAIL,
            'password' => $passwordReset ? self::PASSWORD : null,
        ];
    }

    public function adminExists(): bool
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->exists();
    }
}
