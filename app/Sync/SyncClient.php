<?php

namespace App\Sync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Desktop side of the sync: setup, first full download, push and pull.
 */
class SyncClient
{
    protected function http(?string $url = null, ?string $token = null): PendingRequest
    {
        $settings = SyncNode::settings() ?? [];
        $url = rtrim($url ?? ($settings['server_url'] ?? ''), '/');
        if ($url === '') {
            throw new RuntimeException('Server URL is not set. Run the desktop setup first.');
        }

        return Http::baseUrl($url.'/api/sync')
            ->withToken($token ?? ($settings['token'] ?? ''))
            ->acceptJson()
            ->withHeaders([
                'X-Sync-Schema' => SyncSchema::schemaHash(),
                'X-App-Version' => (string) config('nativephp.version', config('author.app_version', '')),
            ])
            ->timeout(config('sync.http_timeout'))
            ->connectTimeout(15);
    }

    protected function call(string $method, string $uri, array $data = [], ?string $url = null, ?string $token = null): array
    {
        $response = $this->http($url, $token)->{$method}($uri, $data);
        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || empty($json['success'])) {
            $message = is_array($json) && ! empty($json['message']) ? $json['message'] : 'HTTP '.$response->status();
            throw new RuntimeException('Server: '.$message);
        }

        return $json;
    }

    /** Registers this PC with the server using the device token made on the server. */
    public function setup(string $server_url, string $token): array
    {
        $info = $this->call('get', 'handshake', [], $server_url, $token);

        SyncNode::save([
            'server_url' => rtrim($server_url, '/'),
            'token' => $token,
            'node_no' => (int) $info['node_no'],
            'id_step' => (int) $info['id_step'],
            'invoice_prefix' => $info['invoice_prefix'],
            'name' => $info['name'],
            'configured_at' => now()->toDateTimeString(),
        ]);

        // new auto-increment lane for this connection
        DB::statement(SyncNode::connectionInitSql());

        // until the first download finished, the local tables are replaced by the server's
        if (! $this->isBootstrapped()) {
            $this->installSchema();
        }

        return $info;
    }

    /** Creates the server's tables in the empty local database. */
    public function installSchema(): void
    {
        $schema = $this->call('get', 'schema');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($schema['tables'] as $t) {
                DB::statement('DROP TABLE IF EXISTS `'.str_replace('`', '', $t['table']).'`');
                DB::unprepared($t['sql']);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        DB::table('migrations')->truncate();
        foreach (array_chunk($schema['migrations'], 200) as $chunk) {
            DB::table('migrations')->insert($chunk);
        }

        SyncSchema::installTriggers();
    }

    /**
     * Replaces the local data with a full copy of the server database.
     */
    public function bootstrap(?callable $progress = null): array
    {
        $progress ??= function ($message) {};
        $info = $this->call('get', 'snapshot/tables');
        $localTables = SyncSchema::allTables();
        $done = [];

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('SET @sync_skip = 1');
        try {
            foreach ($info['tables'] as $t) {
                $table = $t['table'];
                if (! in_array($table, $localTables)) {
                    $progress("skip {$table} (not in local database)");

                    continue;
                }
                $progress("{$table}: {$t['rows']} rows");
                DB::table($table)->truncate();

                $after = 0;
                $offset = 0;
                do {
                    $page = $this->call('get', 'snapshot', ['table' => $table, 'after' => $after, 'offset' => $offset]);
                    $columns = SyncSchema::columns($table);
                    $rows = array_map(fn ($r) => array_intersect_key(SyncSchema::decodeRow($r), $columns), $page['rows']);
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $after = $page['after'] ?? $after;
                    $offset += count($rows);
                } while (! empty($page['more']));

                $done[$table] = $offset;
            }
        } finally {
            DB::statement('SET @sync_skip = NULL');
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        // nothing local is pending after a full copy
        DB::table('sync_log')->truncate();
        SyncState::forget('pending_push');
        SyncState::put('pull_cursor', (int) $info['cursor']);
        SyncState::put('bootstrapped_at', now()->toDateTimeString());

        $progress('catching up with changes made during the download...');
        $this->pull();

        $progress('downloading uploaded files (images, logos)...');
        SyncState::forget('files_pulled_at');
        SyncState::put('files_pushed_at', time());
        $this->syncFiles();

        return $done;
    }

    public function isBootstrapped(): bool
    {
        return Schema::hasTable('sync_state') && ! empty(SyncState::get('bootstrapped_at'));
    }

    /** Sends local changes to the server. Returns number of changes sent. */
    public function push(): int
    {
        $sent = 0;
        $batchSize = (int) config('sync.batch_size');

        while (true) {
            $batch = SyncState::get('pending_push');
            if (empty($batch)) {
                $batch = $this->buildBatch($batchSize);
                if ($batch === null) {
                    break;
                }
            }

            // a resent batch keeps its batch_id, the server ignores it if it already got it
            $response = $this->call('post', 'push', ['batch_id' => $batch['batch_id'], 'changes' => $batch['changes']]);

            // rows the server refused stay visible here and can be resent (sync:run --retry-conflicts)
            foreach ($response['conflict_rows'] ?? [] as $c) {
                DB::table('sync_conflicts')->insert([
                    'direction' => 'push',
                    'node_no' => 0,
                    'table_name' => $c['table'],
                    'pk' => json_encode($c['pk']),
                    'error' => 'Server refused: '.$c['error'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (array_chunk($batch['ids'], 1000) as $ids) {
                DB::table('sync_log')->whereIn('id', $ids)->delete();
            }
            SyncState::forget('pending_push');
            $sent += count($batch['changes']);

            if (count($batch['ids']) < $batchSize) {
                break;
            }
        }

        return $sent;
    }

    /** Next batch of unpushed changes, saved before sending so a retry sends exactly the same. */
    protected function buildBatch(int $limit): ?array
    {
        return DB::transaction(function () use ($limit) {
            $entries = DB::table('sync_log')->where('origin', 0)->orderBy('id')->limit($limit)->get();
            if ($entries->isEmpty()) {
                return null;
            }

            // later entries of the same rows must travel in this batch too
            $keys = [];
            foreach ($entries as $e) {
                $keys[SyncSchema::rowKey($e->table_name, json_decode($e->pk, true) ?: [])] = true;
            }
            foreach (DB::table('sync_log')->where('origin', 0)->where('id', '>', $entries->last()->id)->orderBy('id')->cursor() as $e) {
                if (isset($keys[SyncSchema::rowKey($e->table_name, json_decode($e->pk, true) ?: [])])) {
                    $entries->push($e);
                }
            }

            $batch = [
                'batch_id' => (string) Str::uuid(),
                'ids' => $entries->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'changes' => (new ChangeCollector)->collect($entries),
            ];
            SyncState::put('pending_push', $batch);

            return $batch;
        });
    }

    /** Queues rows the server refused to be sent again (after the cause is fixed). */
    public function retryConflicts(): int
    {
        $conflicts = DB::table('sync_conflicts')->where('direction', 'push')->where('resolved', 0)->get();
        foreach ($conflicts as $c) {
            DB::table('sync_log')->insert(['table_name' => $c->table_name, 'op' => 'i', 'pk' => $c->pk, 'origin' => 0]);
        }
        DB::table('sync_conflicts')->whereIn('id', $conflicts->pluck('id'))->update(['resolved' => 1, 'updated_at' => now()]);

        return $conflicts->count();
    }

    /** Gets server changes. Returns number of changes applied. */
    public function pull(): int
    {
        if (! empty(SyncState::get('pending_push'))) {
            throw new RuntimeException('Local changes are waiting to be sent, pull skipped.');
        }

        $applied = 0;
        $applier = new ChangeApplier;
        do {
            $cursor = (int) SyncState::get('pull_cursor', 0);
            $page = $this->call('get', 'pull', ['cursor' => $cursor]);

            if (! empty($page['changes'])) {
                $stats = $applier->apply($page['changes'], 'pull');
                $applied += $stats['applied'];
            }
            SyncState::put('pull_cursor', (int) $page['cursor']);
        } while (! empty($page['more']) && (int) $page['cursor'] > $cursor);

        return $applied;
    }

    /**
     * Copies new/changed uploaded files both ways. Returns [downloaded, uploaded].
     */
    public function syncFiles(): array
    {
        $downloaded = 0;
        $uploaded = 0;

        // server -> desktop
        $since = (int) SyncState::get('files_pulled_at', 0);
        $manifest = $this->call('get', 'files', ['since' => max(0, $since - 120)]);
        foreach ($manifest['files'] as $file) {
            $path = FileSync::safePath($file['path']);
            if ($path === null || ! FileSync::needs($path, (int) $file['size'])) {
                continue;
            }
            $response = $this->http()->get('files/download', ['path' => $path]);
            if ($response->successful()) {
                FileSync::write($path, $response->body(), (int) $file['mtime']);
                $downloaded++;
            }
        }
        SyncState::put('files_pulled_at', (int) $manifest['server_time']);

        // desktop -> server
        $started = time();
        $since = (int) SyncState::get('files_pushed_at', 0);
        foreach (FileSync::manifest(max(0, $since - 120)) as $file) {
            $abs = FileSync::absolute($file['path']);
            $response = $this->http()
                ->attach('file', fopen($abs, 'r'), basename($abs))
                ->post('files/upload', ['path' => $file['path'], 'mtime' => $file['mtime']]);
            if (! $response->successful()) {
                throw new RuntimeException('File upload failed for '.$file['path'].': HTTP '.$response->status());
            }
            $uploaded++;
        }
        SyncState::put('files_pushed_at', $started);

        return [$downloaded, $uploaded];
    }

    /** One sync round: push then pull. Never throws, result is saved for the status screen. */
    public function run(): array
    {
        $result = ['pushed' => 0, 'pulled' => 0, 'error' => null, 'at' => now()->toDateTimeString()];

        $lock = Cache::lock('sync-run', 1800);
        if (! $lock->get()) {
            return $result + ['busy' => true];
        }

        try {
            // a --loop process keeps running across app updates that add tables
            SyncSchema::flush();

            if (! SyncNode::isConfigured() || ! $this->isBootstrapped()) {
                throw new RuntimeException('Desktop is not set up yet.');
            }
            $result['pushed'] = $this->push();
            $result['pulled'] = $this->pull();
            SyncState::put('last_success_at', $result['at']);

            try {
                $result['files'] = $this->syncFiles();
            } catch (\Throwable $e) {
                $result['files_error'] = $e->getMessage();
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        } finally {
            $lock->release();
        }

        SyncState::put('last_run', $result);

        return $result;
    }

    public function status(): array
    {
        return [
            'mode' => SyncNode::mode(),
            'configured' => SyncNode::isConfigured(),
            'node' => collect(SyncNode::settings() ?? [])->except('token')->all(),
            'bootstrapped_at' => SyncState::get('bootstrapped_at'),
            'last_success_at' => SyncState::get('last_success_at'),
            'last_run' => SyncState::get('last_run'),
            'pending_changes' => (int) DB::table('sync_log')->where('origin', 0)->count(),
            'pull_cursor' => (int) SyncState::get('pull_cursor', 0),
            'conflicts' => (int) DB::table('sync_conflicts')->where('resolved', 0)->count(),
        ];
    }
}
