<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'print_secondary_invoice_with_item_barcodes')) {
                $table->boolean('print_secondary_invoice_with_item_barcodes')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'print_secondary_invoice_with_item_barcodes')) {
                $table->dropColumn('print_secondary_invoice_with_item_barcodes');
            }
        });
    }
};
