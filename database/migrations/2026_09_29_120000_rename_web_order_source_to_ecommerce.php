<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_orders')->where('order_source', 'web')->update(['order_source' => 'ecommerce']);
    }

    public function down(): void
    {
        DB::table('sales_orders')->where('order_source', 'ecommerce')->update(['order_source' => 'web']);
    }
};
