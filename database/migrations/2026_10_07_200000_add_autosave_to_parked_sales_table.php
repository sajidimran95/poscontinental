<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parked_sales', function (Blueprint $table) {
            $table->string('window_id', 64)->nullable()->after('customer_id');
            $table->boolean('is_auto')->default(false)->after('window_id');

            $table->index(['user_id', 'window_id']);
        });
    }

    public function down(): void
    {
        Schema::table('parked_sales', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'window_id']);
            $table->dropColumn(['window_id', 'is_auto']);
        });
    }
};
