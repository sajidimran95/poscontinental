<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Which tables are synced, their keys / columns, and the change-capture triggers.
 */
class SyncSchema
{
    protected static $cache = [];

    public static function database(): string
    {
        return DB::connection()->getDatabaseName();
    }

    /** All base tables of the database. */
    public static function allTables(): array
    {
        return self::$cache['tables'] ??= collect(DB::select(
            "SELECT TABLE_NAME AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY TABLE_NAME",
            [self::database()]
        ))->pluck('t')->map(fn ($t) => (string) $t)->all();
    }

    public static function isExcluded(string $table): bool
    {
        foreach (config('sync.exclude_tables', []) as $pattern) {
            if (Str::is($pattern, $table)) {
                return true;
            }
        }

        return false;
    }

    public static function isSnapshotOnly(string $table): bool
    {
        return in_array($table, config('sync.snapshot_only_tables', []));
    }

    /** Tables kept in sync continuously. */
    public static function syncedTables(): array
    {
        return array_values(array_filter(self::allTables(), fn ($t) => ! self::isExcluded($t)));
    }

    /** Tables copied when a new desktop is set up. */
    public static function snapshotTables(): array
    {
        return array_values(array_filter(self::allTables(), fn ($t) => ! self::isExcluded($t) || self::isSnapshotOnly($t)));
    }

    public static function isSynced(string $table): bool
    {
        return in_array($table, self::allTables()) && ! self::isExcluded($table);
    }

    /**
     * Column name => data type, without generated columns.
     */
    public static function columns(string $table): array
    {
        return self::$cache['columns'][$table] ??= collect(DB::select(
            "SELECT COLUMN_NAME AS c, DATA_TYPE AS t FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION",
            [self::database(), $table]
        ))->mapWithKeys(fn ($r) => [(string) $r->c => strtolower((string) $r->t)])->all();
    }

    public static function binaryColumns(string $table): array
    {
        return array_keys(array_filter(self::columns($table), fn ($type) => in_array($type, ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'bit'])));
    }

    /**
     * Primary key columns. Tables without a primary key use all columns as key.
     */
    public static function keyColumns(string $table): array
    {
        if (! isset(self::$cache['keys'][$table])) {
            $keys = collect(DB::select(
                "SELECT COLUMN_NAME AS c FROM information_schema.key_column_usage
                WHERE table_schema = ? AND table_name = ? AND constraint_name = 'PRIMARY' ORDER BY ORDINAL_POSITION",
                [self::database(), $table]
            ))->pluck('c')->map(fn ($c) => (string) $c)->all();

            self::$cache['keys'][$table] = $keys ?: array_keys(self::columns($table));
        }

        return self::$cache['keys'][$table];
    }

    public static function deltaColumns(string $table): array
    {
        $cols = config('sync.delta_columns.'.$table, []);

        return array_values(array_intersect($cols, array_keys(self::columns($table))));
    }

    public static function localColumns(string $table): array
    {
        return config('sync.local_columns.'.$table, []);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Stable string id of a row: "table|{"id":"5"}"
     */
    public static function rowKey(string $table, array $pk): string
    {
        ksort($pk);

        return $table.'|'.json_encode(array_map(fn ($v) => $v === null ? null : (string) $v, $pk));
    }

    /** Primary key values of a row. */
    public static function pkOf(string $table, array $row): array
    {
        $pk = [];
        foreach (self::keyColumns($table) as $col) {
            $pk[$col] = $row[$col] ?? null;
        }

        return $pk;
    }

    /** Applies "where pk = ..." to a query. */
    public static function wherePk($query, array $pk)
    {
        foreach ($pk as $col => $value) {
            if ($value === null) {
                $query->whereNull($col);
            } else {
                $query->where($col, $value);
            }
        }

        return $query;
    }

    /** Row from the database -> JSON-safe array. */
    public static function encodeRow(string $table, array $row): array
    {
        foreach (self::binaryColumns($table) as $col) {
            if (isset($row[$col])) {
                $row[$col] = ['__b64' => base64_encode($row[$col])];
            }
        }

        return $row;
    }

    /** JSON array -> row for the database. */
    public static function decodeRow(array $row): array
    {
        foreach ($row as $col => $value) {
            if (is_array($value) && array_key_exists('__b64', $value)) {
                $row[$col] = base64_decode($value['__b64']);
            }
        }

        return $row;
    }

    /**
     * Hash of applied migrations. Server and desktops must run the same code version.
     */
    public static function schemaHash(): string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('migrations')) {
            return '';
        }
        $names = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();

        return md5(implode(',', $names));
    }

    // ------------------------------------------------------------ triggers

    public static function triggerName(string $table, string $suffix): string
    {
        $name = 'zsync_'.$table;
        if (strlen($name) > 56) {
            $name = substr($name, 0, 47).'_'.substr(md5($table), 0, 8);
        }

        return $name.'_'.$suffix;
    }

    protected static function q(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    protected static function jsonPk(string $table, string $alias): string
    {
        $parts = [];
        foreach (self::keyColumns($table) as $col) {
            $parts[] = DB::getPdo()->quote($col).', '.$alias.'.'.self::q($col);
        }

        return 'JSON_OBJECT('.implode(', ', $parts).')';
    }

    protected static function logInsert(string $table, string $op, string $pkSql, string $deltaSql = 'NULL'): string
    {
        return 'INSERT INTO sync_log (table_name, op, pk, deltas, origin) VALUES ('
            .DB::getPdo()->quote($table).", '".$op."', ".$pkSql.', '.$deltaSql.', COALESCE(@sync_origin, 0));';
    }

    /**
     * CREATE TRIGGER statements for a table.
     * Logging is skipped when @sync_skip = 1 (a desktop applying server changes).
     * @sync_origin marks changes the server applies on behalf of a desktop.
     */
    public static function triggerSql(string $table): array
    {
        $t = self::q($table);
        $guard = 'IF COALESCE(@sync_skip, 0) = 0 THEN ';

        $pkSame = implode(' AND ', array_map(fn ($c) => 'NEW.'.self::q($c).' <=> OLD.'.self::q($c), self::keyColumns($table)));

        $deltaSql = 'NULL';
        $deltaCols = self::deltaColumns($table);
        if (! empty($deltaCols)) {
            $parts = [];
            foreach ($deltaCols as $col) {
                $parts[] = DB::getPdo()->quote($col).', COALESCE(NEW.'.self::q($col).', 0) - COALESCE(OLD.'.self::q($col).', 0)';
            }
            $deltaSql = 'JSON_OBJECT('.implode(', ', $parts).')';
        }

        return [
            self::triggerName($table, 'ai') => 'CREATE TRIGGER '.self::q(self::triggerName($table, 'ai')).' AFTER INSERT ON '.$t.' FOR EACH ROW BEGIN '
                .$guard.self::logInsert($table, 'i', self::jsonPk($table, 'NEW')).' END IF; END',

            self::triggerName($table, 'au') => 'CREATE TRIGGER '.self::q(self::triggerName($table, 'au')).' AFTER UPDATE ON '.$t.' FOR EACH ROW BEGIN '
                .$guard
                .'IF '.$pkSame.' THEN '
                .self::logInsert($table, 'u', self::jsonPk($table, 'NEW'), $deltaSql)
                .' ELSE '
                .self::logInsert($table, 'd', self::jsonPk($table, 'OLD'))
                .' '.self::logInsert($table, 'i', self::jsonPk($table, 'NEW'))
                .' END IF; END IF; END',

            self::triggerName($table, 'ad') => 'CREATE TRIGGER '.self::q(self::triggerName($table, 'ad')).' AFTER DELETE ON '.$t.' FOR EACH ROW BEGIN '
                .$guard.self::logInsert($table, 'd', self::jsonPk($table, 'OLD')).' END IF; END',
        ];
    }

    /** Existing sync triggers: name => table */
    public static function existingTriggers(): array
    {
        return collect(DB::select(
            "SELECT TRIGGER_NAME AS n, EVENT_OBJECT_TABLE AS t FROM information_schema.triggers WHERE trigger_schema = ? AND TRIGGER_NAME LIKE 'zsync\_%'",
            [self::database()]
        ))->pluck('t', 'n')->all();
    }

    public static function dropTriggers(): int
    {
        $count = 0;
        foreach (array_keys(self::existingTriggers()) as $name) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.self::q($name));
            $count++;
        }

        return $count;
    }

    /**
     * (Re)creates triggers on every synced table. Safe to run any time, e.g. after migrations.
     */
    public static function installTriggers(): int
    {
        self::flush();
        self::dropTriggers();

        $count = 0;
        foreach (self::syncedTables() as $table) {
            if (empty(self::columns($table))) {
                continue;
            }
            foreach (self::triggerSql($table) as $sql) {
                DB::unprepared($sql);
                $count++;
            }
        }

        return $count;
    }
}
