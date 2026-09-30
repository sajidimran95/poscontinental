<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecom_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('subtitle', 191)->nullable();
            $table->string('tag', 20)->nullable();
            $table->string('target', 20)->default('category');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand', 191)->nullable();
            $table->string('discount_type', 10)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('ecom_promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecom_promotion_id')->constrained('ecom_promotions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unique(['ecom_promotion_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_promotion_items');
        Schema::dropIfExists('ecom_promotions');
    }
};
