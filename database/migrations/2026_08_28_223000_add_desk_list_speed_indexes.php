<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->tryIndex('sales_orders', 'sales_orders_company_id_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'id'], 'sales_orders_company_id_id_index');
        });
        $this->tryIndex('invoices', 'invoices_company_id_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'id'], 'invoices_company_id_id_index');
        });
        $this->tryIndex('invoices', 'invoices_company_status_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'id'], 'invoices_company_status_id_index');
        });
        $this->tryIndex('invoice_payments', 'invoice_payments_invoice_id_index', function (Blueprint $table) {
            $table->index('invoice_id', 'invoice_payments_invoice_id_index');
        });
        $this->tryIndex('invoice_credits', 'invoice_credits_invoice_id_index', function (Blueprint $table) {
            $table->index('invoice_id', 'invoice_credits_invoice_id_index');
        });
        $this->tryIndex('items', 'items_company_inactive_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'is_inactive', 'id'], 'items_company_inactive_id_index');
        });
        $this->tryIndex('items', 'items_company_created_at_index', function (Blueprint $table) {
            $table->index(['company_id', 'created_at'], 'items_company_created_at_index');
        });
        $this->tryIndex('item_upcs', 'item_upcs_upc_prefix_index', function (Blueprint $table) {
            $table->index(['upc'], 'item_upcs_upc_prefix_index');
        });
    }

    public function down(): void
    {
        foreach ([
            ['sales_orders', 'sales_orders_company_id_id_index'],
            ['invoices', 'invoices_company_id_id_index'],
            ['invoices', 'invoices_company_status_id_index'],
            ['invoice_payments', 'invoice_payments_invoice_id_index'],
            ['invoice_credits', 'invoice_credits_invoice_id_index'],
            ['items', 'items_company_inactive_id_index'],
            ['items', 'items_company_created_at_index'],
            ['item_upcs', 'item_upcs_upc_prefix_index'],
        ] as [$table, $name]) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            } catch (\Throwable) {
            }
        }
    }

    private function tryIndex(string $table, string $name, callable $define): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            if (method_exists(Schema::class, 'hasIndex') && Schema::hasIndex($table, $name)) {
                return;
            }
        } catch (\Throwable) {
        }
        try {
            Schema::table($table, $define);
        } catch (\Throwable) {
        }
    }
};
