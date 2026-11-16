<?php

namespace App\Providers;

use App\Sync\SyncNode;
use App\Sync\SyncSchema;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the offline desktop sync (config/sync.php) into the app.
 */
class SyncServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Every MySQL connection creates IDs on this node's lane (see SyncNode::connectionInitSql).
        // Set here, before any connection is opened, and re-run by PDO on every reconnect.
        $init = SyncNode::connectionInitSql();
        if (! empty($init)) {
            $options = config('database.connections.mysql.options', []);
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = $init;
            config(['database.connections.mysql.options' => $options]);
        }
    }

    public function boot()
    {
        // synced rows must arrive exactly as sent: '' is not NULL and spaces matter
        $isSyncApi = fn ($request) => $request->is('api/sync/*');
        TrimStrings::skipWhen($isSyncApi);
        ConvertEmptyStringsToNull::skipWhen($isSyncApi);

        if (SyncNode::mode() === 'off') {
            return;
        }

        // NativePHP switches the app to its own SQLite file; this app needs MySQL.
        if (config('nativephp-internal.running')) {
            config([
                'database.default' => 'mysql',
                'queue.default' => 'sync',
                'queue.failed.database' => 'mysql',
                'queue.batching.database' => 'mysql',
                'queue.connections.database.connection' => 'mysql',
            ]);
        }

        // New tables / columns from migrations need fresh triggers.
        Event::listen(MigrationsEnded::class, function () {
            try {
                SyncSchema::installTriggers();
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (SyncNode::isClient()) {
                $schedule->command('sync:run')->everyMinute()->withoutOverlapping(30)->runInBackground();
            }
            if (SyncNode::isServer()) {
                $schedule->command('sync:prune')->dailyAt('03:30');
            }
        });
    }
}
