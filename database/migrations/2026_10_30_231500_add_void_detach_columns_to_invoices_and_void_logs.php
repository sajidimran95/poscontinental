<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('voided_sales_order_id')->nullable()->after('sales_order_id');
            $table->string('voided_order_number', 64)->nullable()->after('voided_sales_order_id');
            $table->index('voided_sales_order_id');
        });

        Schema::table('invoice_void_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->after('invoice_id');
            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_void_logs', function (Blueprint $table) {
            $table->dropIndex(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['voided_sales_order_id']);
            $table->dropColumn(['voided_sales_order_id', 'voided_order_number']);
        });
    }
};
