<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('label');
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->foreignId('price_level_id')->nullable()->after('item_id')->constrained('price_levels')->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'fein_no')) {
                $table->string('fein_no', 32)->nullable()->after('name');
            }
        });

        Schema::table('credit_memos', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_memos', 'reference_no')) {
                $table->string('reference_no', 64)->nullable()->after('memo_date');
            }
            if (! Schema::hasColumn('credit_memos', 'reason')) {
                $table->string('reason', 255)->nullable()->after('reference_no');
            }
        });

        $all = json_encode(array_keys(\App\Support\AppFeatures::all()));
        DB::table('roles')->whereNull('permissions')->update(['permissions' => $all]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_level_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'fein_no')) {
                $table->dropColumn('fein_no');
            }
        });

        Schema::table('credit_memos', function (Blueprint $table) {
            if (Schema::hasColumn('credit_memos', 'reference_no')) {
                $table->dropColumn('reference_no');
            }
            if (Schema::hasColumn('credit_memos', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
