<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 64);
            $table->string('order_type', 64)->default('Sales Order');
            $table->string('status', 32)->default('New');
            $table->string('priority', 32)->default('Normal');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ship_to_address_id')->nullable()->constrained('customer_shipping_addresses')->nullOnDelete();
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_phone', 32)->nullable();
            $table->string('bill_to_address')->nullable();
            $table->string('bill_to_city')->nullable();
            $table->string('bill_to_state', 32)->nullable();
            $table->string('bill_to_zip', 20)->nullable();
            $table->string('ship_to_name')->nullable();
            $table->string('ship_to_phone', 32)->nullable();
            $table->string('ship_to_address')->nullable();
            $table->string('ship_to_city')->nullable();
            $table->string('ship_to_state', 32)->nullable();
            $table->string('ship_to_zip', 20)->nullable();
            $table->date('order_date')->nullable();
            $table->date('required_date')->nullable();
            $table->string('customer_po_no', 64)->nullable();
            $table->string('reference_no', 64)->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('delivery_routes')->nullOnDelete();
            $table->foreignId('ship_via_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ship_from_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->date('ship_date')->nullable();
            $table->unsignedInteger('no_of_boxes')->default(0);
            $table->unsignedInteger('no_of_pallets')->default(0);
            $table->string('custom_field_1')->nullable();
            $table->string('custom_field_2')->nullable();
            $table->text('custom_field_3')->nullable();
            $table->string('custom_field_4')->nullable();
            $table->string('custom_field_5')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('trade_discount', 14, 4)->default(0);
            $table->decimal('freight', 14, 4)->default(0);
            $table->decimal('miscellaneous', 14, 4)->default(0);
            $table->decimal('tax', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty_ordered', 14, 4)->default(0);
            $table->decimal('qty_shipped', 14, 4)->default(0);
            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('discount', 12, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_order_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('box_number', 64)->nullable();
            $table->string('tracking_number', 128)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 64);
            $table->date('invoice_date')->nullable();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('NOT PAID');
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('total_discount', 14, 4)->default(0);
            $table->decimal('trade_discount', 14, 4)->default(0);
            $table->decimal('freight', 14, 4)->default(0);
            $table->decimal('miscellaneous', 14, 4)->default(0);
            $table->decimal('tax', 14, 4)->default(0);
            $table->decimal('invoice_total', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->decimal('amount', 14, 4)->default(0);
            $table->string('comments')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credit_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('memo_number', 64);
            $table->date('memo_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 4)->default(0);
            $table->string('status', 32)->default('Open');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'memo_number']);
        });

        Schema::create('invoice_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_memo_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_credits');
        Schema::dropIfExists('credit_memos');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sales_order_boxes');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
