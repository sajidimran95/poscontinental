<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 64)->nullable();
            $table->decimal('qty_change', 14, 4);
            $table->decimal('qty_after', 14, 4)->nullable();
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'item_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('po_number', 64);
            $table->string('order_type', 64)->default('Standard');
            $table->string('reference_no', 64)->nullable();
            $table->date('requisition_date')->nullable();
            $table->string('status', 32)->default('New');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('required_date')->nullable();
            $table->foreignId('ship_to_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ship_from')->nullable();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ship_via_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comments')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('trade_discount', 14, 4)->default(0);
            $table->decimal('freight', 14, 4)->default(0);
            $table->decimal('miscellaneous', 14, 4)->default(0);
            $table->decimal('tax', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'po_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty_ordered', 14, 4)->default(0);
            $table->decimal('qty_received', 14, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('extended_cost', 14, 4)->default(0);
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_receivings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 64);
            $table->date('receipt_date')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no', 64)->nullable();
            $table->string('status', 32)->default('New');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('received_by')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
        });

        Schema::create('inventory_receiving_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_receiving_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty_ordered', 14, 4)->default(0);
            $table->decimal('qty_received', 14, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->timestamps();
        });

        Schema::create('return_to_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('rtv_number', 64);
            $table->date('rtv_date')->nullable();
            $table->string('status', 32)->default('New');
            $table->string('reference_no', 64)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comments')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount', 14, 4)->default(0);
            $table->decimal('freight', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'rtv_number']);
        });

        Schema::create('return_to_vendor_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_to_vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('extended_cost', 14, 4)->default(0);
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_to_vendor_lines');
        Schema::dropIfExists('return_to_vendors');
        Schema::dropIfExists('inventory_receiving_lines');
        Schema::dropIfExists('inventory_receivings');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('inventory_journal_entries');
    }
};
