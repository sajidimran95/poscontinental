<?php

namespace App\Console\Commands;

use App\Sync\SyncClient;
use App\Sync\SyncNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Desktop: set up, download and keep syncing with the online server.
 */
class SyncRun extends Command
{
    protected $signature = 'sync:run
        {--setup : Register this PC (needs --server and --token), then download the server data}
        {--server= : Online server URL, e.g. https://pos.example.com}
        {--token= : Device token from "php artisan sync:device add" on the server}
        {--bootstrap : Replace local data with a full copy of the server}
        {--status : Show sync status}
        {--retry-conflicts : Send again the rows the server refused}
        {--loop : Keep syncing every sync.interval seconds}';

    protected $description = 'Sync this desktop with the online server (push local changes, pull server changes)';

    public function handle(SyncClient $client)
    {
        if ($this->option('status')) {
            $this->line(json_encode($client->status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        if (! SyncNode::isClient()) {
            $this->error('Set SYNC_MODE=client in the desktop .env first.');

            return 1;
        }

        if ($this->option('retry-conflicts')) {
            $this->info($client->retryConflicts().' rows queued, they are sent with the next sync.');
        }

        if ($this->option('setup')) {
            if (! $this->option('server') || ! $this->option('token')) {
                $this->error('--setup needs --server and --token');

                return 1;
            }
            $info = $client->setup($this->option('server'), $this->option('token'));
            $this->info("Registered as \"{$info['name']}\" (node {$info['node_no']}, prefix {$info['invoice_prefix']}).");
        }

        if ($this->option('setup') || $this->option('bootstrap')) {
            if (DB::table('sync_log')->where('origin', 0)->exists() && ! $this->option('setup')
                && ! $this->confirm('There are local changes not sent to the server yet. They will be lost. Continue?')) {
                return 1;
            }
            $this->info('Downloading server data...');
            $done = $client->bootstrap(fn ($message) => $this->line('  '.$message));
            $this->info('Done: '.array_sum($done).' rows in '.count($done).' tables.');

            return 0;
        }

        do {
            $result = $client->run();
            if (! empty($result['busy'])) {
                $this->line('Another sync is running.');
            } elseif ($result['error']) {
                $this->error('['.$result['at'].'] '.$result['error']);
            } else {
                $this->info("[{$result['at']}] pushed {$result['pushed']}, pulled {$result['pulled']}");
            }

            if ($this->option('loop')) {
                sleep(max(5, (int) config('sync.interval')));
            }
        } while ($this->option('loop'));

        return 0;
    }
}
