<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('stock_count_no', 64);
            $table->date('date_created')->nullable();
            $table->string('status', 32)->default('New');
            $table->date('last_count_date')->nullable();
            $table->date('date_processed')->nullable();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('shared_count')->default(false);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'stock_count_no']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('in_stock', 14, 4)->default(0);
            $table->decimal('allocated', 14, 4)->default(0);
            $table->decimal('counted', 14, 4)->nullable();
            $table->timestamp('count_time')->nullable();
            $table->unsignedSmallInteger('line_no')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
