<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_price_histories')) {
            return;
        }

        Schema::create('item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('change_type', 32); // cost | sales_price | both
            $table->string('source', 64); // purchase_order | receiving | item_edit | bulk_pricing
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 128)->nullable();
            $table->decimal('cost_before', 14, 4)->nullable();
            $table->decimal('cost_after', 14, 4)->nullable();
            $table->decimal('list_price_before', 14, 4)->nullable();
            $table->decimal('list_price_after', 14, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
            $table->index(['company_id', 'change_type', 'created_at']);
            $table->index(['item_id', 'change_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_histories');
    }
};
