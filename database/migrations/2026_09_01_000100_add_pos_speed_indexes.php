<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexIfMissing('item_upcs', 'item_upcs_upc_index', function (Blueprint $table) {
            $table->index('upc', 'item_upcs_upc_index');
        });
        $this->indexIfMissing('item_prices', 'item_prices_alias_code_index', function (Blueprint $table) {
            $table->index('alias_code', 'item_prices_alias_code_index');
        });
        $this->indexIfMissing('item_suppliers', 'item_suppliers_supplier_item_code_index', function (Blueprint $table) {
            $table->index('supplier_item_code', 'item_suppliers_supplier_item_code_index');
        });
        $this->indexIfMissing('invoices', 'invoices_company_id_invoice_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'invoice_date'], 'invoices_company_id_invoice_date_index');
        });
        $this->indexIfMissing('invoices', 'invoices_company_id_status_index', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'invoices_company_id_status_index');
        });
        $this->indexIfMissing('sales_orders', 'sales_orders_company_id_order_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'order_date'], 'sales_orders_company_id_order_date_index');
        });
        $this->indexIfMissing('customers', 'customers_company_id_is_inactive_index', function (Blueprint $table) {
            $table->index(['company_id', 'is_inactive'], 'customers_company_id_is_inactive_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('item_upcs', 'item_upcs_upc_index');
        $this->dropIndexIfExists('item_prices', 'item_prices_alias_code_index');
        $this->dropIndexIfExists('item_suppliers', 'item_suppliers_supplier_item_code_index');
        $this->dropIndexIfExists('invoices', 'invoices_company_id_invoice_date_index');
        $this->dropIndexIfExists('invoices', 'invoices_company_id_status_index');
        $this->dropIndexIfExists('sales_orders', 'sales_orders_company_id_order_date_index');
        $this->dropIndexIfExists('customers', 'customers_company_id_is_inactive_index');
    }

    private function indexIfMissing(string $table, string $index, callable $add): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, $add);
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (method_exists(Schema::class, 'hasIndex')) {
            return Schema::hasIndex($table, $index);
        }

        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(fn ($row) => ($row['name'] ?? '') === $index);
    }
};
