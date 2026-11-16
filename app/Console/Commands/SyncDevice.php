<?php

namespace App\Console\Commands;

use App\Sync\SyncDevices;
use App\Sync\SyncNode;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Server: manage desktop PCs allowed to sync.
 * Same actions are available in the web panel: File → Desktop devices.
 */
class SyncDevice extends Command
{
    protected $signature = 'sync:device
        {action=list : list | add | disable | enable | token}
        {name? : Device name (add) or node number (disable/enable/token)}
        {--prefix= : Invoice number prefix for this PC, e.g. PC1}';

    protected $description = 'Register and manage desktop PCs that sync with this server';

    public function handle()
    {
        if (! SyncNode::isServer()) {
            $this->error('Set SYNC_MODE=server in .env on the online server first.');

            return 1;
        }

        return match ($this->argument('action')) {
            'add' => $this->add(),
            'disable' => $this->setActive(false),
            'enable' => $this->setActive(true),
            'token' => $this->newToken(),
            default => $this->list(),
        };
    }

    protected function list()
    {
        $this->table(
            ['Node', 'Name', 'Prefix', 'Active', 'Last seen', 'Last push', 'Last pull', 'Version'],
            SyncDevices::all()->map(fn ($n) => [
                $n->node_no, $n->name, $n->invoice_prefix, $n->is_active ? 'yes' : 'no',
                $n->last_seen_at, $n->last_push_at, $n->last_pull_at, $n->app_version,
            ])->all()
        );

        return 0;
    }

    protected function add()
    {
        $name = $this->argument('name') ?: $this->ask('Device name (e.g. Main shop PC1)');

        try {
            $device = SyncDevices::add($name, $this->option('prefix'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info("Device \"{$name}\" registered as node {$device['node_no']}, invoice prefix {$device['prefix']}.");
        $this->line('Device token (shown only once, enter it in the desktop setup screen):');
        $this->line($device['token']);

        return 0;
    }

    protected function setActive(bool $active)
    {
        $updated = SyncDevices::setActive((int) $this->argument('name'), $active);
        $this->info($updated ? 'Done.' : 'Device not found.');

        return $updated ? 0 : 1;
    }

    protected function newToken()
    {
        $token = SyncDevices::newToken((int) $this->argument('name'));
        if (! $token) {
            $this->error('Device not found.');

            return 1;
        }
        $this->line('New device token: '.$token);

        return 0;
    }
}
