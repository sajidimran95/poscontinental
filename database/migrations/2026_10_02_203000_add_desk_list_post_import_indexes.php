<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for desk/list ORDER BY + WHERE patterns after large data imports.
 * Safe to re-run: skips indexes that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tryIndex('purchase_orders', 'purchase_orders_company_requisition_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'requisition_date'], 'purchase_orders_company_requisition_date_index');
        });
        $this->tryIndex('purchase_orders', 'purchase_orders_company_status_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'id'], 'purchase_orders_company_status_id_index');
        });

        $this->tryIndex('inventory_receivings', 'inventory_receivings_company_receipt_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'receipt_date'], 'inventory_receivings_company_receipt_date_index');
        });
        $this->tryIndex('inventory_receivings', 'inventory_receivings_company_status_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'id'], 'inventory_receivings_company_status_id_index');
        });

        $this->tryIndex('return_to_vendors', 'return_to_vendors_company_rtv_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'rtv_date'], 'return_to_vendors_company_rtv_date_index');
        });
        $this->tryIndex('return_to_vendors', 'return_to_vendors_company_status_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'id'], 'return_to_vendors_company_status_id_index');
        });

        $this->tryIndex('credit_memos', 'credit_memos_company_memo_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'memo_date'], 'credit_memos_company_memo_date_index');
        });
        $this->tryIndex('credit_memos', 'credit_memos_company_status_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'id'], 'credit_memos_company_status_id_index');
        });

        $this->tryIndex('invoice_credits', 'invoice_credits_credit_memo_id_index', function (Blueprint $table) {
            $table->index('credit_memo_id', 'invoice_credits_credit_memo_id_index');
        });

        $this->tryIndex('item_prices', 'item_prices_item_sort_id_index', function (Blueprint $table) {
            $table->index(['item_id', 'sort_order', 'id'], 'item_prices_item_sort_id_index');
        });

        $this->tryIndex('sales_orders', 'sales_orders_company_status_order_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'order_date'], 'sales_orders_company_status_order_date_index');
        });

        $this->tryIndex('suppliers', 'suppliers_company_inactive_id_index', function (Blueprint $table) {
            $table->index(['company_id', 'is_inactive', 'id'], 'suppliers_company_inactive_id_index');
        });

        $this->tryIndex('stock_counts', 'stock_counts_company_date_created_index', function (Blueprint $table) {
            $table->index(['company_id', 'date_created'], 'stock_counts_company_date_created_index');
        });
    }

    public function down(): void
    {
        foreach ([
            ['purchase_orders', 'purchase_orders_company_requisition_date_index'],
            ['purchase_orders', 'purchase_orders_company_status_id_index'],
            ['inventory_receivings', 'inventory_receivings_company_receipt_date_index'],
            ['inventory_receivings', 'inventory_receivings_company_status_id_index'],
            ['return_to_vendors', 'return_to_vendors_company_rtv_date_index'],
            ['return_to_vendors', 'return_to_vendors_company_status_id_index'],
            ['credit_memos', 'credit_memos_company_memo_date_index'],
            ['credit_memos', 'credit_memos_company_status_id_index'],
            ['invoice_credits', 'invoice_credits_credit_memo_id_index'],
            ['item_prices', 'item_prices_item_sort_id_index'],
            ['sales_orders', 'sales_orders_company_status_order_date_index'],
            ['suppliers', 'suppliers_company_inactive_id_index'],
            ['stock_counts', 'stock_counts_company_date_created_index'],
        ] as [$table, $name]) {
            $this->dropIndexIfExists($table, $name);
        }
    }

    private function tryIndex(string $table, string $name, callable $define): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        try {
            Schema::table($table, $define);
        } catch (\Throwable) {
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $name)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        } catch (\Throwable) {
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            if (method_exists(Schema::class, 'hasIndex')) {
                return Schema::hasIndex($table, $index);
            }
            $indexes = Schema::getIndexes($table);

            return collect($indexes)->contains(fn ($row) => ($row['name'] ?? '') === $index);
        } catch (\Throwable) {
            return false;
        }
    }
};
