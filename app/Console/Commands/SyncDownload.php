<?php

namespace App\Console\Commands;

use App\Sync\DownloadProgress;
use App\Sync\SyncClient;
use Illuminate\Console\Command;

/**
 * Desktop: first full download, started in the background by the setup screen.
 * Progress goes to storage/app/sync/download.json so the screen can show it.
 */
class SyncDownload extends Command
{
    protected $signature = 'sync:download';

    protected $description = 'Download the full server database (background job of the desktop setup screen)';

    protected $hidden = true;

    public function handle(SyncClient $client)
    {
        set_time_limit(0);
        DownloadProgress::line('Connecting to the server...');
        try {
            $done = $client->bootstrap(function ($message, $percent = null) {
                $this->line(($percent !== null ? "[{$percent}%] " : '').$message);
                DownloadProgress::line($message, $percent);
            });
            DownloadProgress::finish(array_sum($done));
        } catch (\Throwable $e) {
            report($e);
            DownloadProgress::fail($e->getMessage());

            return 1;
        }

        return 0;
    }
}
