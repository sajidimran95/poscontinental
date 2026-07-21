<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_id', 64);
            $table->boolean('is_inactive')->default(false);
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 32)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('country', 64)->default('US');
            $table->string('fein_no', 32)->nullable();
            $table->string('phone1', 32)->nullable();
            $table->string('phone2', 32)->nullable();
            $table->string('fax', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('web_page')->nullable();
            $table->boolean('is_tobacco_supplier')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
