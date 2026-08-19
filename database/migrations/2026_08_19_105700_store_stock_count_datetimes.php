<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_counts')) {
            return;
        }

        DB::statement('ALTER TABLE stock_counts MODIFY date_created DATETIME NULL');
        DB::statement('ALTER TABLE stock_counts MODIFY last_count_date DATETIME NULL');
        DB::statement('ALTER TABLE stock_counts MODIFY date_processed DATETIME NULL');

        DB::table('stock_counts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];

                if ($this->isMidnightStamp($row->date_created) && filled($row->created_at)) {
                    $updates['date_created'] = $row->created_at;
                }

                if (! filled($row->date_entered) && filled($row->created_at)) {
                    $updates['date_entered'] = $row->created_at;
                }

                if ($this->isMidnightStamp($row->date_processed) && filled($row->updated_at)) {
                    $updates['date_processed'] = $row->updated_at;
                }

                if ($updates !== []) {
                    DB::table('stock_counts')->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_counts')) {
            return;
        }

        DB::statement('ALTER TABLE stock_counts MODIFY date_created DATE NULL');
        DB::statement('ALTER TABLE stock_counts MODIFY last_count_date DATE NULL');
        DB::statement('ALTER TABLE stock_counts MODIFY date_processed DATE NULL');
    }

    private function isMidnightStamp(mixed $value): bool
    {
        $stamp = (string) $value;

        return $stamp !== '' && str_contains($stamp, '00:00:00');
    }
};
