<?php

namespace App\Http\Controllers;

use App\Sync\DownloadProgress;
use App\Sync\SyncClient;
use App\Sync\SyncNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Native\Desktop\Facades\ChildProcess;

/**
 * Desktop app screens: first-run setup and sync status.
 */
class DesktopSyncController extends Controller
{
    public function __construct(protected SyncClient $client) {}

    protected function setupDone(): bool
    {
        return SyncNode::isConfigured() && $this->client->isBootstrapped();
    }

    public function setup()
    {
        if ($this->setupDone()) {
            return redirect('/login');
        }

        return view('desktop.setup', [
            'settings' => SyncNode::settings() ?? [],
            'progress' => SyncNode::isConfigured() ? DownloadProgress::read() : null,
        ]);
    }

    public function storeSetup(Request $request)
    {
        if ($this->setupDone()) {
            return redirect('/login');
        }
        if ((DownloadProgress::read()['state'] ?? null) === 'running') {
            return redirect('/desktop/setup');
        }
        $request->validate([
            'server_url' => 'required|url',
            'token' => 'required|string|min:20',
        ]);

        try {
            $this->client->setup($request->input('server_url'), trim($request->input('token')));
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['token' => $e->getMessage()]);
        }

        // the download runs in its own process: one long request would block the app's
        // single-threaded PHP server and the screen could not show any progress
        $this->startDownload();

        return redirect('/desktop/setup');
    }

    /** Progress of the background download, polled by the setup screen. */
    public function progress()
    {
        $progress = DownloadProgress::read();
        if ($progress && $progress['state'] === 'running' && $this->nativeRunning()
            && time() - (int) ($progress['started_at'] ?? 0) > 15) {
            try {
                $gone = ChildProcess::get('sync-download') === null;
            } catch (\Throwable $e) {
                $gone = false;
            }
            if ($gone && (DownloadProgress::read()['state'] ?? null) === 'running') {
                DownloadProgress::fail('The download process stopped. Try again.');
            }
            $progress = DownloadProgress::read();
        }

        return response()->json($progress ?? ['state' => 'none']);
    }

    public function retryDownload()
    {
        if ($this->setupDone()) {
            return redirect('/login');
        }
        if (! SyncNode::isConfigured()) {
            DownloadProgress::clear();

            return redirect('/desktop/setup');
        }
        if ((DownloadProgress::read()['state'] ?? null) !== 'running') {
            $this->startDownload();
        }

        return redirect('/desktop/setup');
    }

    protected function nativeRunning(): bool
    {
        return (bool) config('nativephp-internal.running');
    }

    protected function startDownload(): void
    {
        DownloadProgress::start();

        if ($this->nativeRunning()) {
            ChildProcess::artisan(['sync:download'], 'sync-download', persistent: false);

            return;
        }

        // dev (php artisan serve / laragon): plain detached process
        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start \"\" /B {$php} {$artisan} sync:download > NUL 2>&1", 'r'));
        } else {
            exec("nohup {$php} {$artisan} sync:download > /dev/null 2>&1 &");
        }
    }

    public function status(Request $request)
    {
        $status = $this->client->status();
        if ($request->wantsJson()) {
            return response()->json($status);
        }

        return view('desktop.status', [
            'status' => $status,
            'conflicts' => DB::table('sync_conflicts')->where('resolved', 0)->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function syncNow(Request $request)
    {
        set_time_limit(0);
        $result = $this->client->run();

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('desktop.status')->with('sync_result', $result);
    }

    /**
     * Replaces this PC's data with a fresh copy of the server (fixes data missed while the
     * server was not recording changes). Only when everything local was sent first.
     */
    public function redownload()
    {
        set_time_limit(0);
        $result = $this->client->run();
        $pending = (int) DB::table('sync_log')->where('origin', 0)->count();

        if (! empty($result['error']) || $pending > 0 || ! empty(\App\Sync\SyncState::get('pending_push'))) {
            return redirect()->route('desktop.status')->with('sync_result', $result + [
                'error' => 'Re-download stopped: this PC still has '.$pending.' unsent change(s)'
                    .(! empty($result['error']) ? ' ('.$result['error'].')' : '')
                    .'. Connect to the internet, press Sync now, then try again.',
            ]);
        }
        if ((DownloadProgress::read()['state'] ?? null) === 'running') {
            return redirect('/desktop/setup');
        }

        \App\Sync\SyncState::forget('bootstrapped_at');
        $this->startDownload();

        return redirect('/desktop/setup');
    }

    public function resolveConflicts()
    {
        DB::table('sync_conflicts')->where('resolved', 0)->update(['resolved' => 1, 'updated_at' => now()]);

        return redirect()->route('desktop.status');
    }
}
