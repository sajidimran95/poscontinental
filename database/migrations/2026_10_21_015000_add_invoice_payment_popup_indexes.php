<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment popup looks up a customer's open credit memos by company, customer, and status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_memos')) {
            return;
        }

        $name = 'credit_memos_company_customer_status_date_index';
        if ($this->hasIndex('credit_memos', $name)) {
            return;
        }

        Schema::table('credit_memos', function (Blueprint $table) use ($name) {
            $table->index(['company_id', 'customer_id', 'status', 'memo_date'], $name);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_memos') || ! $this->hasIndex('credit_memos', 'credit_memos_company_customer_status_date_index')) {
            return;
        }

        Schema::table('credit_memos', function (Blueprint $table) {
            $table->dropIndex('credit_memos_company_customer_status_date_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            $indexes = Schema::getIndexes($table);

            return collect($indexes)->contains(fn ($row) => ($row['name'] ?? '') === $index);
        } catch (\Throwable) {
            return false;
        }
    }
};
