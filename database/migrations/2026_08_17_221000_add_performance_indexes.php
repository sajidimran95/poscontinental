<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['company_id', 'primary_upc'], 'items_company_primary_upc_index');
            $table->index(['company_id', 'category_id'], 'items_company_category_index');
        });

        Schema::table('item_upcs', function (Blueprint $table) {
            $table->index('upc', 'item_upcs_upc_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['company_id', 'is_inactive'], 'customers_company_inactive_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'invoices_company_status_index');
            $table->index(['company_id', 'customer_id'], 'invoices_company_customer_index');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index(['company_id', 'customer_id'], 'sales_orders_company_customer_index');
            $table->index(['company_id', 'order_date'], 'sales_orders_company_order_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_company_primary_upc_index');
            $table->dropIndex('items_company_category_index');
        });

        Schema::table('item_upcs', function (Blueprint $table) {
            $table->dropIndex('item_upcs_upc_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_company_inactive_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_company_status_index');
            $table->dropIndex('invoices_company_customer_index');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('sales_orders_company_customer_index');
            $table->dropIndex('sales_orders_company_order_date_index');
        });
    }
};
