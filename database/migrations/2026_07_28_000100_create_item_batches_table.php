<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 64);
            $table->string('tracking_type', 16)->default('Lot');
            $table->decimal('quantity', 14, 4)->default(0);
            $table->date('expiry_date')->nullable();
            $table->date('received_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'sort_order']);
            $table->index(['company_id', 'batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_batches');
    }
};
