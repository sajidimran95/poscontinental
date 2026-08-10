<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'secondary_tob_number')) {
                $table->string('secondary_tob_number', 20)->nullable()->after('fein_no');
            }
            if (! Schema::hasColumn('companies', 'secondary_cig_number')) {
                $table->string('secondary_cig_number', 20)->nullable()->after('secondary_tob_number');
            }
        });

        // Copy legacy single state license into both product license fields when empty.
        if (Schema::hasColumn('companies', 'state_license_number')) {
            DB::table('companies')
                ->whereNotNull('state_license_number')
                ->where('state_license_number', '!=', '')
                ->orderBy('id')
                ->each(function ($row) {
                    $updates = [];
                    if (empty($row->secondary_tob_number)) {
                        $updates['secondary_tob_number'] = $row->state_license_number;
                    }
                    if (empty($row->secondary_cig_number)) {
                        $updates['secondary_cig_number'] = $row->state_license_number;
                    }
                    if ($updates !== []) {
                        DB::table('companies')->where('id', $row->id)->update($updates);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['secondary_tob_number', 'secondary_cig_number'],
                fn ($c) => Schema::hasColumn('companies', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
