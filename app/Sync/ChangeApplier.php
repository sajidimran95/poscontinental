<?php

namespace App\Sync;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Writes row changes received from the other side.
 *
 * push (server receiving a desktop batch): counter columns get "+ delta", other
 *   columns are overwritten (last writer wins). Server triggers log the change with
 *   @sync_origin = desktop node, so the other desktops pull it but the sender does not.
 *
 * pull (desktop receiving server changes): rows are written as the server has them,
 *   with logging switched off (@sync_skip). Rows this desktop changed but has not
 *   pushed yet keep their local values; only counters are moved to
 *   "server value + local unpushed delta".
 */
class ChangeApplier
{
    protected $stats = ['applied' => 0, 'skipped' => 0, 'conflicts' => 0, 'conflict_rows' => []];

    public function apply(array $changes, string $direction, int $node_no = 0, ?callable $inTransaction = null): array
    {
        $this->stats = ['applied' => 0, 'skipped' => 0, 'conflicts' => 0, 'conflict_rows' => []];

        DB::transaction(function () use ($changes, $direction, $node_no, $inTransaction) {
            $this->stats = ['applied' => 0, 'skipped' => 0, 'conflicts' => 0, 'conflict_rows' => []];

            if ($inTransaction && $inTransaction() === false) {
                return;
            }

            $pending = $direction === 'pull' ? $this->pendingLocalChanges() : [];

            $this->begin($direction, $node_no);
            try {
                foreach ($changes as $change) {
                    $this->applyChange($change, $direction, $node_no, $pending);
                }
            } finally {
                $this->end();
            }
        }, 3);

        return $this->stats;
    }

    protected function begin(string $direction, int $node_no): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        if ($direction === 'pull') {
            DB::statement('SET @sync_skip = 1');
        } else {
            DB::statement('SET @sync_origin = '.(int) $node_no);
        }
    }

    protected function end(): void
    {
        DB::statement('SET @sync_skip = NULL, @sync_origin = NULL');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Unpushed local changes on a desktop: rowKey => ['deleted' => bool, 'deltas' => []] */
    protected function pendingLocalChanges(): array
    {
        $pending = [];
        foreach (DB::table('sync_log')->where('origin', 0)->orderBy('id')->cursor() as $entry) {
            $key = SyncSchema::rowKey($entry->table_name, json_decode($entry->pk, true) ?: []);
            $pending[$key] ??= ['deleted' => false, 'deltas' => []];

            if ($entry->op === 'd') {
                $pending[$key]['deleted'] = true;
            } elseif ($entry->op === 'i') {
                $pending[$key]['deleted'] = false;
                $pending[$key]['deltas'] = [];
            } elseif (! empty($entry->deltas)) {
                foreach (json_decode($entry->deltas, true) ?: [] as $col => $delta) {
                    $pending[$key]['deltas'][$col] = ($pending[$key]['deltas'][$col] ?? 0) + (float) $delta;
                }
            }
        }

        return $pending;
    }

    protected function applyChange(array $change, string $direction, int $node_no, array $pending): void
    {
        $table = $change['table'] ?? '';
        if (! SyncSchema::isSynced($table)) {
            $this->stats['skipped']++;

            return;
        }

        $columns = SyncSchema::columns($table);
        $pk = array_intersect_key($change['pk'] ?? [], $columns);
        if (empty($pk)) {
            $this->stats['skipped']++;

            return;
        }
        $key = SyncSchema::rowKey($table, $pk);
        $local = $pending[$key] ?? null;

        try {
            if ($change['op'] === 'd') {
                if ($local !== null) {
                    // changed here and not pushed yet: local version wins
                    $this->stats['skipped']++;

                    return;
                }
                SyncSchema::wherePk(DB::table($table), $pk)->delete();
                $this->stats['applied']++;

                return;
            }

            $row = array_intersect_key(SyncSchema::decodeRow($change['row'] ?? []), $columns);
            $exists = SyncSchema::wherePk(DB::table($table), $pk)->exists();

            if (! $exists) {
                if ($local !== null && $local['deleted']) {
                    $this->stats['skipped']++;

                    return;
                }
                DB::table($table)->insert($row);
                $this->stats['applied']++;

                return;
            }

            $deltaCols = SyncSchema::deltaColumns($table);
            $update = array_diff_key($row, $pk, array_flip(SyncSchema::localColumns($table)));

            if ($local !== null) {
                if ($local['deleted']) {
                    $this->stats['skipped']++;

                    return;
                }
                // keep local values, only re-base counters on the server value
                $update = [];
                foreach ($deltaCols as $col) {
                    if (array_key_exists($col, $row)) {
                        $update[$col] = (float) $row[$col] + ($local['deltas'][$col] ?? 0);
                    }
                }
            } elseif ($direction === 'push' && $change['op'] === 'u') {
                foreach ($deltaCols as $col) {
                    unset($update[$col]);
                    $delta = (float) ($change['deltas'][$col] ?? 0);
                    if ($delta != 0) {
                        $update[$col] = DB::raw('COALESCE(`'.$col.'`, 0) + '.sprintf('%.6F', $delta));
                    }
                }
            }

            if (! empty($update)) {
                SyncSchema::wherePk(DB::table($table), $pk)->update($update);
            }
            $this->stats['applied']++;
        } catch (QueryException $e) {
            // deadlock / lock timeout: let the whole transaction retry
            if (in_array($e->errorInfo[1] ?? null, [1213, 1205])) {
                throw $e;
            }
            $this->conflict($direction, $node_no, $table, $pk, $change['row'] ?? null, $e->getMessage());
        }
    }

    protected function conflict(string $direction, int $node_no, string $table, array $pk, $row, string $error): void
    {
        $this->stats['conflicts']++;
        $this->stats['conflict_rows'][] = ['table' => $table, 'pk' => $pk, 'error' => mb_substr($error, 0, 1000)];

        DB::table('sync_conflicts')->insert([
            'direction' => $direction,
            'node_no' => $node_no,
            'table_name' => $table,
            'pk' => json_encode($pk),
            'row' => $row === null ? null : json_encode($row),
            'error' => mb_substr($error, 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
