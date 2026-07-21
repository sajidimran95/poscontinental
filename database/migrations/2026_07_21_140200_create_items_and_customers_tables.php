<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('item_code', 64);
            $table->string('item_type', 64)->default('Standard Item');
            $table->string('class')->nullable();
            $table->text('description')->nullable();
            $table->decimal('list_price', 12, 4)->default(0);
            $table->decimal('standard_cost', 12, 4)->default(0);
            $table->decimal('current_cost', 12, 4)->default(0);
            $table->decimal('quantity_in_stock', 14, 4)->default(0);
            $table->decimal('allocated_qty', 14, 4)->default(0);
            $table->decimal('reorder_point', 14, 4)->default(0);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uom_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_of_measure', 16)->nullable();
            $table->boolean('is_inactive')->default(false);
            $table->boolean('can_order')->default(true);
            $table->boolean('can_sell')->default(true);
            $table->boolean('allow_back_order')->default(true);
            $table->boolean('available_on_website')->default(false);
            $table->string('barcode_format', 32)->nullable();
            $table->string('primary_upc', 64)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'item_code']);
            $table->index(['company_id', 'is_inactive']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('customer_id', 64);
            $table->boolean('is_inactive')->default(false);
            $table->string('contact')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 32)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('country', 64)->default('US');
            $table->string('telephone', 32)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cigarette_tax_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discount_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_limit_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_route_id')->nullable()->constrained('delivery_routes')->nullOnDelete();
            $table->string('fein_no', 32)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('messages_alerts')->nullable();
            $table->boolean('is_tax_exempt')->default(false);
            $table->string('tax_certificate_no')->nullable();
            $table->date('tax_certificate_exp')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('items');
    }
};
