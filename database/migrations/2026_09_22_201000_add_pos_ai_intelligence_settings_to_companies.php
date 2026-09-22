<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'japs_ai_digest_enabled')) {
                $table->boolean('japs_ai_digest_enabled')->default(false)->after('japs_ai_widget_enabled');
            }
            if (! Schema::hasColumn('companies', 'japs_ai_match_tolerance')) {
                $table->decimal('japs_ai_match_tolerance', 5, 2)->default(2)->after('japs_ai_digest_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'japs_ai_match_tolerance')) {
                $table->dropColumn('japs_ai_match_tolerance');
            }
            if (Schema::hasColumn('companies', 'japs_ai_digest_enabled')) {
                $table->dropColumn('japs_ai_digest_enabled');
            }
        });
    }
};
