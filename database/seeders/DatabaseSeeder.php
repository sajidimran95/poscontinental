<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Minimal shell only: company, site, roles, admin.
 * No demo customers/items/orders — add those in POS manually.
 *
 * Optional demo: php artisan db:seed --class=DemoDataSeeder
 * (requires look ups / base data from full seed first if needed).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
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

        $adminRole = Role::query()->where('name', 'admin')->first();

        User::query()->firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'company_id' => $company->id,
                'site_id' => $site->id,
                'role_id' => $adminRole?->id,
                'name' => 'POS Admin',
                'username' => 'admin@gmail.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        Customer::ensureWalkIn((int) $company->id);

        $this->command?->info('Minimal seed: company + site + roles + admin + Walk-in customer.');
    }
}
