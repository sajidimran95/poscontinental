<?php

namespace App\Console\Commands;

use App\Sync\SyncSchema;
use Illuminate\Console\Command;

class SyncTriggers extends Command
{
    protected $signature = 'sync:triggers {--drop : Remove the sync triggers}';

    protected $description = 'Install (or remove) the change-capture triggers used by the desktop sync';

    public function handle()
    {
        if ($this->option('drop')) {
            $this->info(SyncSchema::dropTriggers().' sync triggers removed.');

            return 0;
        }

        try {
            $count = SyncSchema::installTriggers();
        } catch (\Throwable $e) {
            $this->error('Could not create triggers: '.$e->getMessage());
            $this->line('The database user needs the TRIGGER privilege. If MySQL binary logging is on, '
                .'log_bin_trust_function_creators must be 1 (ask the hosting provider).');

            return 1;
        }

        $this->info($count.' triggers installed on '.count(SyncSchema::syncedTables()).' tables.');

        return 0;
    }
}
