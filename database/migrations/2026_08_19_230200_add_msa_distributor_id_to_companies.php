<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'msa_distributor_id')) {
                $table->string('msa_distributor_id', 20)->nullable()->after('secondary_cig_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'msa_distributor_id')) {
                $table->dropColumn('msa_distributor_id');
            }
        });
    }
};
