<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Turns sync_log entries into a list of row changes to send to the other side.
 *
 * A change is the row's current state (state-based sync), so many edits of one row
 * travel as one change. Counter columns additionally carry the summed +/- deltas.
 *
 * change = [
 *   'table' => 'transactions', 'op' => 'i'|'u'|'d', 'pk' => ['id' => 5],
 *   'row' => [...] (not for d), 'deltas' => ['qty_available' => -2] (u only)
 * ]
 */
class ChangeCollector
{
    /**
     * @param  iterable  $entries  sync_log rows (objects with table_name, op, pk, deltas)
     */
    public function collect(iterable $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            if (! SyncSchema::isSynced($entry->table_name)) {
                continue;
            }
            $pk = json_decode($entry->pk, true) ?: [];
            $key = SyncSchema::rowKey($entry->table_name, $pk);

            if (! isset($groups[$key])) {
                $groups[$key] = ['table' => $entry->table_name, 'pk' => $pk, 'inserted' => false, 'deltas' => []];
            }
            if ($entry->op === 'i') {
                $groups[$key]['inserted'] = true;
                $groups[$key]['deltas'] = [];
            } elseif ($entry->op === 'u' && ! empty($entry->deltas)) {
                foreach (json_decode($entry->deltas, true) ?: [] as $col => $delta) {
                    $groups[$key]['deltas'][$col] = ($groups[$key]['deltas'][$col] ?? 0) + (float) $delta;
                }
            }
        }

        $rows = $this->fetchRows($groups);

        $changes = [];
        foreach ($groups as $key => $group) {
            $row = $rows[$key] ?? null;
            if ($row === null) {
                $changes[] = ['table' => $group['table'], 'op' => 'd', 'pk' => $group['pk']];

                continue;
            }

            $change = [
                'table' => $group['table'],
                'op' => $group['inserted'] ? 'i' : 'u',
                'pk' => $group['pk'],
                'row' => SyncSchema::encodeRow($group['table'], $row),
            ];
            if (! $group['inserted'] && ! empty($group['deltas'])) {
                $change['deltas'] = array_map(fn ($d) => round($d, 6), $group['deltas']);
            }
            $changes[] = $change;
        }

        return $changes;
    }

    /** Current rows keyed by rowKey. Missing key = row deleted. */
    protected function fetchRows(array $groups): array
    {
        $byTable = [];
        foreach ($groups as $group) {
            $byTable[$group['table']][] = $group['pk'];
        }

        $rows = [];
        foreach ($byTable as $table => $pks) {
            $columns = array_keys(SyncSchema::columns($table));
            $keyCols = SyncSchema::keyColumns($table);

            if (count($keyCols) == 1) {
                $col = $keyCols[0];
                $values = array_values(array_unique(array_map(fn ($pk) => $pk[$col] ?? null, $pks)));
                foreach (array_chunk($values, 500) as $chunk) {
                    $found = DB::table($table)->select($columns)->whereIn($col, $chunk)->get();
                    foreach ($found as $row) {
                        $row = (array) $row;
                        $rows[SyncSchema::rowKey($table, SyncSchema::pkOf($table, $row))] = $row;
                    }
                }
            } else {
                foreach ($pks as $pk) {
                    $row = SyncSchema::wherePk(DB::table($table)->select($columns), $pk)->first();
                    if (! empty($row)) {
                        // keyed by the logged pk, the fetched values may be formatted differently
                        $rows[SyncSchema::rowKey($table, $pk)] = (array) $row;
                    }
                }
            }
        }

        return $rows;
    }
}
