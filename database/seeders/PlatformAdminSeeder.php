<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrator']
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'company_id' => null,
                'site_id' => null,
                'role_id' => $adminRole->id,
                'name' => 'Platform Admin',
                'username' => 'platform_admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'is_platform_admin' => true,
            ]
        );
    }
}
