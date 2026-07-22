<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bulk_price_change_logs')) {
            Schema::create('bulk_price_change_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('filter_criteria')->nullable();
                $table->string('adjustment_type', 32);
                $table->decimal('adjustment_value', 14, 4);
                $table->json('targets')->nullable();
                $table->unsignedInteger('items_affected')->default(0);
                $table->timestamps();

                $table->index(['company_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('bulk_price_change_items')) {
            Schema::create('bulk_price_change_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bulk_price_change_log_id')->constrained('bulk_price_change_logs')->cascadeOnDelete();
                $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
                $table->string('item_code', 64)->nullable();
                $table->decimal('list_price_before', 14, 4)->nullable();
                $table->decimal('list_price_after', 14, 4)->nullable();
                $table->decimal('standard_cost_before', 14, 4)->nullable();
                $table->decimal('standard_cost_after', 14, 4)->nullable();
                $table->decimal('current_cost_before', 14, 4)->nullable();
                $table->decimal('current_cost_after', 14, 4)->nullable();
                $table->timestamps();

                $table->index('bulk_price_change_log_id');
            });
        } elseif (! Schema::hasColumn('bulk_price_change_items', 'item_code')) {
            Schema::table('bulk_price_change_items', function (Blueprint $table) {
                $table->string('item_code', 64)->nullable()->after('item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_price_change_items');
        Schema::dropIfExists('bulk_price_change_logs');
    }
};
