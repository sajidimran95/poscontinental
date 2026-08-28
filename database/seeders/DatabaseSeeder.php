<?php

namespace Database\Seeders;

use App\Services\SeedAdminService;
use Illuminate\Database\Seeder;

/**
 * Minimal shell only: company, site, roles, one admin.
 * Does not delete other users. No demo items/orders.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(SeedAdminService::class)->ensure(false);
        $this->command?->info(
            $result['created']
                ? 'Admin created: '.$result['email'].' / '.$result['password']
                : 'Admin already present ('.$result['email'].'). Other users were not changed.'
        );
    }
}
