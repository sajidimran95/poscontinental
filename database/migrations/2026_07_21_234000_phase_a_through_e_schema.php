<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_schedules', function (Blueprint $table) {
            $table->decimal('percent', 8, 4)->default(0)->after('name');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('driver')->nullable()->after('status');
        });

        Schema::create('customer_lookup_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // lead_source | customer_category | account_type
            $table->string('code', 64)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'type', 'name']);
        });

        Schema::create('document_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bulk_price_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('filter_criteria')->nullable();
            $table->string('adjustment_type');
            $table->decimal('adjustment_value', 12, 4)->default(0);
            $table->json('targets')->nullable();
            $table->unsignedInteger('items_affected')->default(0);
            $table->timestamps();
        });

        Schema::create('bulk_price_change_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_price_change_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('list_price_before', 14, 4)->nullable();
            $table->decimal('list_price_after', 14, 4)->nullable();
            $table->decimal('standard_cost_before', 14, 4)->nullable();
            $table->decimal('standard_cost_after', 14, 4)->nullable();
            $table->decimal('current_cost_before', 14, 4)->nullable();
            $table->decimal('current_cost_after', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('tobacco_stamp_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('r1_beginning_unaffixed', 14, 2)->default(0);
            $table->decimal('r2_beginning_affixed', 14, 2)->default(0);
            $table->decimal('r3_purchased', 14, 2)->default(0);
            $table->decimal('r4_affixed', 14, 2)->default(0);
            $table->decimal('r5_ending_unaffixed', 14, 2)->default(0);
            $table->decimal('r6_ending_affixed', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tobacco_stamp_inventories');
        Schema::dropIfExists('bulk_price_change_items');
        Schema::dropIfExists('bulk_price_change_logs');
        Schema::dropIfExists('document_email_logs');
        Schema::dropIfExists('customer_lookup_options');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
        Schema::table('discount_schedules', function (Blueprint $table) {
            $table->dropColumn('percent');
        });
    }
};
