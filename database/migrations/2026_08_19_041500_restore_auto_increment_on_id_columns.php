<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
              AND COLUMN_KEY = 'PRI'
              AND EXTRA NOT LIKE '%auto_increment%'
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
                if (in_array($table, $skip, true)) {
                    continue;
                }
                $type = $row->COLUMN_TYPE;
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Intentionally empty: restoring AUTO_INCREMENT is a data-integrity repair.
    }
};
