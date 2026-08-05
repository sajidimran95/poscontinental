<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe all transactional / master data. Keep a clean POS shell:
 * company + site + system roles + one admin user only.
 */
class WipeBusinessDataCommand extends Command
{
    protected $signature = 'pos:wipe-data
                            {--force : Run without confirmation}
                            {--email=admin@gmail.com : Admin email to keep/create}
                            {--password=password : Admin password (only applied when user is created or --reset-password)}
                            {--reset-password : Reset admin password to --password}';

    protected $description = 'Clean database: keep only system roles + admin (and login company/site). All other data removed for manual entry.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This DELETES customers, items, orders, suppliers, and all other business data. Only admin + roles remain. Continue?'
        )) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $driver = Schema::getConnection()->getDriverName();
        $tables = $this->tablesToWipe();

        $this->info('Wiping '.count($tables).' tables…');

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
                $this->line("  truncated: {$table}");
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        $this->seedBaseline();

        $this->newLine();
        $this->info('Done. Database is clean for manual data entry.');
        $this->table(
            ['Keep', 'Value'],
            [
                ['Roles', Role::query()->pluck('name')->implode(', ')],
                ['Users', User::query()->pluck('email')->implode(', ')],
                ['Companies', Company::query()->pluck('name')->implode(', ')],
                ['Sites', Site::query()->pluck('code')->implode(', ')],
                ['Customers', (string) DB::table('customers')->count()],
                ['Items', (string) DB::table('items')->count()],
                ['Sales orders', (string) DB::table('sales_orders')->count()],
            ]
        );

        $email = (string) $this->option('email');
        $this->comment("Login: {$email} / ".($this->option('reset-password') || ! User::query()->where('email', $email)->exists() ? (string) $this->option('password') : '(existing password kept if not --reset-password)'));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function tablesToWipe(): array
    {
        $driver = Schema::getConnection()->getDriverName();
        $database = Schema::getConnection()->getDatabaseName();

        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?',
                [$database, 'BASE TABLE']
            );
            $all = collect($rows)->map(fn ($r) => $r->name)->filter()->values()->all();
        } else {
            $all = Schema::getTableListing();
        }

        $preserve = [
            'migrations',
        ];

        return array_values(array_filter(
            $all,
            fn (string $t) => ! in_array(strtolower($t), $preserve, true)
        ));
    }

    protected function seedBaseline(): void
    {
        $company = Company::query()->create([
            'code' => 'CWI',
            'name' => 'Continental Wholesale Inc',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'company_id' => $company->id,
            'code' => 'WS',
            'name' => 'Main Site',
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'sales_rep', 'label' => 'Sales Rep'],
            ['name' => 'buyer', 'label' => 'Buyer'],
            ['name' => 'warehouse', 'label' => 'Warehouse'],
        ] as $role) {
            Role::query()->create([
                'name' => $role['name'],
                'label' => $role['label'],
                'permissions' => $role['name'] === 'admin' ? null : [],
            ]);
        }

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        User::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'role_id' => $adminRole->id,
            'name' => 'POS Admin',
            'email' => $email,
            'username' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
