<?php

namespace App\Console\Commands;

use App\Services\SeedAdminService;
use Illuminate\Console\Command;

class SeedAdminCommand extends Command
{
    protected $signature = 'pos:seed-admin
                            {--reset-password : Set admin@gmail.com password back to "password"}';

    protected $description = 'Create or restore one admin user. Does not delete other users or POS data.';

    public function handle(SeedAdminService $seed): int
    {
        $result = $seed->ensure((bool) $this->option('reset-password'));

        if ($result['created']) {
            $this->info('Admin user created.');
        } elseif ($result['promoted']) {
            $this->info('Existing user promoted to Administrator.');
        } else {
            $this->info('Admin user already present. Other users were not changed.');
        }

        $this->line('Username / email: '.$result['email']);
        if ($result['password']) {
            $this->line('Password: '.$result['password']);
        } else {
            $this->line('Password: unchanged (use --reset-password to set it to "password").');
        }

        return self::SUCCESS;
    }
}
