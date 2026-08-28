<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('order_source', 32)->default('pos')->after('order_type');
            $table->index(['company_id', 'order_source']);
        });

        DB::table('sales_orders')
            ->where('reference_no', 'CUSTAPP')
            ->update(['order_source' => 'customer']);
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'order_source']);
            $table->dropColumn('order_source');
        });
    }
};
