<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sync\ChangeApplier;
use App\Sync\ChangeCollector;
use App\Sync\FileSync;
use App\Sync\SyncSchema;
use App\Sync\SyncState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Server side of the desktop sync (routes/api.php, prefix api/sync).
 */
class SyncController extends Controller
{
    /** Device info and network settings, called by the desktop setup. */
    public function handshake(Request $request)
    {
        $node = $request->attributes->get('sync_node');

        return response()->json([
            'success' => true,
            'node_no' => (int) $node->node_no,
            'name' => $node->name,
            'invoice_prefix' => $node->invoice_prefix,
            'id_step' => (int) config('sync.id_step'),
            'schema_hash' => Cache::remember('sync.schema_hash', 60, fn () => SyncSchema::schemaHash()),
            'cursor' => (int) DB::table('sync_log')->max('id'),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /** Applies a batch of desktop changes. A batch_id is applied only once. */
    public function push(Request $request)
    {
        // raw body: no input middleware may touch the row values
        $body = json_decode($request->getContent(), true);
        if (! is_array($body) || empty($body['batch_id']) || strlen($body['batch_id']) > 36 || ! is_array($body['changes'] ?? null)) {
            return response()->json(['success' => false, 'message' => 'Invalid batch.'], 422);
        }
        $node = $request->attributes->get('sync_node');
        $batch_id = $body['batch_id'];
        $changes = $body['changes'];
        $duplicate = false;

        $stats = (new ChangeApplier)->apply($changes, 'push', (int) $node->node_no, function () use ($batch_id, $node, $changes, &$duplicate) {
            $duplicate = DB::table('sync_receipts')->where('batch_id', $batch_id)->lockForUpdate()->exists();
            if ($duplicate) {
                return false;
            }
            DB::table('sync_receipts')->insert([
                'batch_id' => $batch_id,
                'node_no' => $node->node_no,
                'changes' => count($changes),
                'created_at' => now(),
            ]);
        });

        DB::table('sync_nodes')->where('id', $node->id)->update(['last_push_at' => now()]);

        return response()->json(['success' => true, 'duplicate' => $duplicate] + $stats);
    }

    /** Changes since the desktop's cursor, made by the server or other desktops. */
    public function pull(Request $request)
    {
        $node = $request->attributes->get('sync_node');
        $cursor = (int) $request->input('cursor', 0);
        $limit = min((int) $request->input('limit', config('sync.batch_size')), 2000) ?: 500;

        if ($cursor < (int) SyncState::get('pruned_up_to', 0)) {
            return response()->json([
                'success' => false,
                'message' => 'This desktop was offline longer than '.config('sync.log_retention_days').' days. Send its pending sales, then download the server data again (sync:run --bootstrap).',
            ], 410);
        }

        // one consistent snapshot for the log entries and the rows they point to
        $result = DB::transaction(function () use ($node, $cursor, $limit) {
            $entries = DB::table('sync_log')
                ->where('id', '>', $cursor)
                ->whereRaw('created_at <= NOW(6) - INTERVAL ? SECOND', [(int) config('sync.pull_lag_seconds')])
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $next = $entries->isEmpty() ? $cursor : (int) $entries->last()->id;
            $foreign = $entries->filter(fn ($e) => (int) $e->origin !== (int) $node->node_no);

            return [
                'changes' => (new ChangeCollector)->collect($foreign),
                'cursor' => $next,
                'more' => $entries->count() >= $limit,
            ];
        });

        DB::table('sync_nodes')->where('id', $node->id)->update([
            'last_pull_at' => now(),
            'last_pull_cursor' => $result['cursor'],
        ]);

        return response()->json(['success' => true] + $result);
    }

    /**
     * Table structure of the server database, so a new desktop gets exactly the same schema
     * (instead of running every migration, some of which seed data).
     */
    public function schema()
    {
        $tables = [];
        foreach (SyncSchema::allTables() as $table) {
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            $sql = $create['Create Table'] ?? array_values($create)[1];
            $tables[] = ['table' => $table, 'sql' => preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql)];
        }

        return response()->json([
            'success' => true,
            'tables' => $tables,
            'migrations' => DB::table('migrations')->orderBy('id')->get(['migration', 'batch']),
        ]);
    }

    /** Tables a new desktop downloads, with the log position to continue from afterwards. */
    public function snapshotTables()
    {
        return response()->json([
            'success' => true,
            'cursor' => (int) DB::table('sync_log')->max('id'),
            'tables' => collect(SyncSchema::snapshotTables())->map(fn ($t) => [
                'table' => $t,
                'rows' => (int) DB::table($t)->count(),
                'key' => SyncSchema::keyColumns($t),
            ])->values(),
        ]);
    }

    /** One page of a table for the initial download. */
    public function snapshot(Request $request)
    {
        $table = (string) $request->input('table');
        if (! in_array($table, SyncSchema::snapshotTables())) {
            return response()->json(['success' => false, 'message' => 'Unknown table.'], 404);
        }
        $limit = min((int) $request->input('limit', config('sync.snapshot_page_size')), 5000) ?: 1000;
        $keyCols = SyncSchema::keyColumns($table);
        $query = DB::table($table)->select(array_keys(SyncSchema::columns($table)));

        $single = count($keyCols) == 1 && in_array(SyncSchema::columns($table)[$keyCols[0]] ?? '', ['int', 'bigint', 'smallint', 'mediumint', 'tinyint']);
        if ($single) {
            // keyset paging on numeric primary key
            $query->where($keyCols[0], '>', (int) $request->input('after', 0))->orderBy($keyCols[0]);
        } else {
            foreach ($keyCols as $col) {
                $query->orderBy($col);
            }
            $query->offset((int) $request->input('offset', 0));
        }
        $rows = $query->limit($limit)->get()->map(fn ($r) => SyncSchema::encodeRow($table, (array) $r))->all();

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'after' => $single && ! empty($rows) ? end($rows)[$keyCols[0]] : null,
            'more' => count($rows) >= $limit,
        ]);
    }

    /** Uploaded files changed since a unix time. */
    public function filesManifest(Request $request)
    {
        return response()->json([
            'success' => true,
            'server_time' => time(),
            'files' => FileSync::manifest((int) $request->input('since', 0)),
        ]);
    }

    public function fileDownload(Request $request)
    {
        $path = FileSync::safePath($request->input('path'));
        if ($path === null || ! is_file(FileSync::absolute($path))) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        return response()->file(FileSync::absolute($path));
    }

    /** A file uploaded on a desktop (product image etc). Existing files of the same size are kept. */
    public function fileUpload(Request $request)
    {
        $request->validate(['path' => 'required|string|max:500', 'file' => 'required|file|max:20480']);
        $path = FileSync::safePath($request->input('path'));
        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'File type or path not allowed.'], 422);
        }

        $file = $request->file('file');
        if (FileSync::needs($path, (int) $file->getSize())) {
            FileSync::write($path, (string) file_get_contents($file->getRealPath()), (int) $request->input('mtime') ?: null);
        }

        return response()->json(['success' => true]);
    }
}
