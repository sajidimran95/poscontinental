<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_memos', function (Blueprint $table) {
            $table->boolean('restock_inventory')->default(true)->after('comments');
        });
    }

    public function down(): void
    {
        Schema::table('credit_memos', function (Blueprint $table) {
            $table->dropColumn('restock_inventory');
        });
    }
};
