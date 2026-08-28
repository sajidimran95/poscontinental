<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasIndex('sales_orders', 'sales_orders_company_status_id_index')) {
                $table->index(['company_id', 'status', 'id'], 'sales_orders_company_status_id_index');
            }
            if (! Schema::hasIndex('sales_orders', 'sales_orders_company_order_number_index')) {
                $table->index(['company_id', 'order_number'], 'sales_orders_company_order_number_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_company_invoice_number_index')) {
                $table->index(['company_id', 'invoice_number'], 'invoices_company_invoice_number_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('sales_orders_company_status_id_index');
            $table->dropIndex('sales_orders_company_order_number_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_company_invoice_number_index');
        });
    }
};
