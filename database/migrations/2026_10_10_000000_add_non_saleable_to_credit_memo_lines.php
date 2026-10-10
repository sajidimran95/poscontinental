<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('credit_memo_lines', 'non_saleable')) {
            return;
        }

        Schema::table('credit_memo_lines', function (Blueprint $table) {
            $table->boolean('non_saleable')->default(false)->after('line_total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('credit_memo_lines', 'non_saleable')) {
            return;
        }

        Schema::table('credit_memo_lines', function (Blueprint $table) {
            $table->dropColumn('non_saleable');
        });
    }
};
