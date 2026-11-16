<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A later data import recreated some tables without PRIMARY KEY on `id`.
 * MySQL then refuses foreign keys that reference those columns (error 6125).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $skip = ['sessions', 'job_batches'];
        $schema = DB::getDatabaseName();

        $rows = DB::select(
            "
            SELECT TABLE_NAME, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND COLUMN_NAME = 'id'
              AND COLUMN_KEY <> 'PRI'
              AND DATA_TYPE IN ('bigint', 'int', 'mediumint', 'smallint', 'tinyint')
            ",
            [$schema]
        );

        if ($rows === []) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($rows as $row) {
                $table = $row->TABLE_NAME;
                if (in_array($table, $skip, true) || ! Schema::hasTable($table)) {
                    continue;
                }

                $duplicates = DB::select(
                    "SELECT COUNT(*) AS c FROM `{$table}` GROUP BY `id` HAVING c > 1 LIMIT 1"
                );
                if ($duplicates !== []) {
                    continue;
                }

                $type = $row->COLUMN_TYPE;
                DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Restoring primary keys is a data-integrity repair.
    }
};
