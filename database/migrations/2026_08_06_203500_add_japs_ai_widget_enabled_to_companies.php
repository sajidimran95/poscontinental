<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'japs_ai_widget_enabled')) {
                $table->boolean('japs_ai_widget_enabled')->default(false)->after('japs_ai_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'japs_ai_widget_enabled')) {
                $table->dropColumn('japs_ai_widget_enabled');
            }
        });
    }
};
