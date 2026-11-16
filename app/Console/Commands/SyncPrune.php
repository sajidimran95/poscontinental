<?php

namespace App\Console\Commands;

use App\Sync\SyncNode;
use App\Sync\SyncState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Server: delete old change log entries and push receipts.
 */
class SyncPrune extends Command
{
    protected $signature = 'sync:prune';

    protected $description = 'Delete sync log entries older than sync.log_retention_days';

    public function handle()
    {
        if (! SyncNode::isServer()) {
            return 0;
        }

        $before = now()->subDays((int) config('sync.log_retention_days'));
        $maxId = DB::table('sync_log')->where('created_at', '<', $before)->max('id');

        $deleted = 0;
        if ($maxId) {
            do {
                $count = DB::table('sync_log')->where('id', '<=', $maxId)->limit(5000)->delete();
                $deleted += $count;
            } while ($count > 0);
            SyncState::put('pruned_up_to', (int) $maxId);
        }
        DB::table('sync_receipts')->where('created_at', '<', $before)->delete();

        $this->info("{$deleted} old sync log entries deleted.");

        return 0;
    }
}
