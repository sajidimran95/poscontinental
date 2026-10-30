<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_void_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 16);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->decimal('invoice_total', 14, 4)->default(0);
            $table->string('invoice_number', 64)->nullable();
            $table->string('order_number', 64)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_void_logs');
    }
};
