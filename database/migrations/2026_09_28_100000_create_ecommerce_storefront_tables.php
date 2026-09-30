<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('ecommerce_enabled')->default(false);
            $table->string('ecommerce_store_name')->nullable();
            $table->string('ecommerce_tagline')->nullable();
            $table->string('ecommerce_phone', 40)->nullable();
            $table->string('ecommerce_email')->nullable();
            $table->string('ecommerce_hours')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('web_status', 20)->nullable()->index();
            $table->timestamp('web_registered_at')->nullable();
            $table->rememberToken();
        });

        Schema::create('ecom_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ecom_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecom_cart_id')->constrained('ecom_carts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('uom', 20)->nullable();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->timestamps();
            $table->unique(['ecom_cart_id', 'item_id']);
        });

        Schema::create('ecom_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'item_id']);
        });

        Schema::create('ecom_customer_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('license_type', 30);
            $table->string('label')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('ecom_order_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_token', 64)->unique();
            $table->string('payment_method', 30)->default('cod');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->json('timeline')->nullable();
            $table->timestamps();
        });

        Schema::create('ecom_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_contact_messages');
        Schema::dropIfExists('ecom_order_meta');
        Schema::dropIfExists('ecom_customer_licenses');
        Schema::dropIfExists('ecom_wishlists');
        Schema::dropIfExists('ecom_cart_items');
        Schema::dropIfExists('ecom_carts');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['web_status']);
            $table->dropColumn(['web_status', 'web_registered_at', 'remember_token']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'ecommerce_enabled', 'ecommerce_store_name', 'ecommerce_tagline',
                'ecommerce_phone', 'ecommerce_email', 'ecommerce_hours',
            ]);
        });
    }
};
