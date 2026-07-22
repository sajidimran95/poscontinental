<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->timestamp('date_entered')->nullable()->after('last_count_date');
            $table->foreignId('processed_by')->nullable()->after('date_processed')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn('date_entered');
        });
    }
};
