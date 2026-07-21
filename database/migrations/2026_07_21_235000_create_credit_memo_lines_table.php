<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_memo_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_memo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->string('uom', 32)->nullable();
            $table->decimal('qty', 14, 4)->default(0);
            $table->decimal('price', 14, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->unsignedInteger('line_no')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_memo_lines');
    }
};
