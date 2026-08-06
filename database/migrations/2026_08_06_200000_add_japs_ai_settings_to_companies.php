<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'japs_ai_enabled')) {
                $table->boolean('japs_ai_enabled')->default(false)->after('allow_negative_stock');
            }
            if (! Schema::hasColumn('companies', 'japs_ai_api_key')) {
                $table->text('japs_ai_api_key')->nullable()->after('japs_ai_enabled');
            }
            if (! Schema::hasColumn('companies', 'japs_ai_model')) {
                $table->string('japs_ai_model', 64)->default('gpt-4o-mini')->after('japs_ai_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['japs_ai_enabled', 'japs_ai_api_key', 'japs_ai_model'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
