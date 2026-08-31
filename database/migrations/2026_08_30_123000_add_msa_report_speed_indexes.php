<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->tryIndex('invoices', 'invoices_company_invoice_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'invoice_date'], 'invoices_company_invoice_date_index');
        });
        $this->tryIndex('sales_order_lines', 'sales_order_lines_order_item_index', function (Blueprint $table) {
            $table->index(['sales_order_id', 'item_id'], 'sales_order_lines_order_item_index');
        });
        $this->tryIndex('inventory_receivings', 'inventory_receivings_company_receipt_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'receipt_date'], 'inventory_receivings_company_receipt_date_index');
        });
        $this->tryIndex('credit_memos', 'credit_memos_company_memo_date_index', function (Blueprint $table) {
            $table->index(['company_id', 'memo_date'], 'credit_memos_company_memo_date_index');
        });
    }

    public function down(): void
    {
        foreach ([
            ['invoices', 'invoices_company_invoice_date_index'],
            ['sales_order_lines', 'sales_order_lines_order_item_index'],
            ['inventory_receivings', 'inventory_receivings_company_receipt_date_index'],
            ['credit_memos', 'credit_memos_company_memo_date_index'],
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
