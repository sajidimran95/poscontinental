<?php

namespace App\Http\Controllers;

use App\Sync\SyncClient;
use App\Sync\SyncNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);
    }

    public function storeSetup(Request $request)
    {
        if ($this->setupDone()) {
            return redirect('/login');
        }
        $request->validate([
            'server_url' => 'required|url',
            'token' => 'required|string|min:20',
        ]);

        set_time_limit(0);
        try {
            $info = $this->client->setup($request->input('server_url'), trim($request->input('token')));
            $done = $this->client->bootstrap();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['token' => $e->getMessage()]);
        }

        return redirect('/login')->with('status', [
            'success' => 1,
            'msg' => 'Desktop "'.$info['name'].'" is ready: '.array_sum($done).' records downloaded. Log in with your usual account.',
        ]);
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

    public function resolveConflicts()
    {
        DB::table('sync_conflicts')->where('resolved', 0)->update(['resolved' => 1, 'updated_at' => now()]);

        return redirect()->route('desktop.status');
    }
}
