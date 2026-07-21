<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('msrp', 12, 4)->default(0)->after('list_price');
            $table->decimal('last_cost', 12, 4)->default(0)->after('current_cost');
            $table->decimal('average_cost', 12, 4)->default(0)->after('last_cost');
            $table->decimal('on_order_qty', 14, 4)->default(0)->after('allocated_qty');
            $table->decimal('back_order_qty', 14, 4)->default(0)->after('on_order_qty');
            $table->decimal('restock_level', 14, 4)->default(0)->after('reorder_point');
            $table->unsignedInteger('lead_time_days')->default(0)->after('restock_level');
            $table->date('last_received_at')->nullable()->after('lead_time_days');
            $table->date('last_ordered_at')->nullable()->after('last_received_at');
            $table->date('last_sold_at')->nullable()->after('last_ordered_at');
            $table->date('last_count_date')->nullable()->after('last_sold_at');
            $table->foreignId('tax_schedule_id')->nullable()->after('uom_schedule_id')->constrained()->nullOnDelete();
            $table->foreignId('promotion_schedule_id')->nullable()->after('tax_schedule_id')->constrained('discount_schedules')->nullOnDelete();
            $table->foreignId('pricing_method_id')->nullable()->after('promotion_schedule_id')->constrained()->nullOnDelete();
            $table->text('extended_description')->nullable()->after('description');
            $table->text('product_highlights')->nullable()->after('extended_description');
            $table->string('image_path')->nullable()->after('product_highlights');
            $table->string('thumbnail_path')->nullable()->after('image_path');
            $table->string('item_tracking', 32)->default('None')->after('available_on_website');
            $table->decimal('shipping_weight', 12, 4)->default(0)->after('item_tracking');
            $table->decimal('tare_weight', 12, 4)->default(0)->after('shipping_weight');
            $table->string('manufacturer')->nullable()->after('tare_weight');
            $table->string('item_line_message')->nullable()->after('manufacturer');
            $table->string('manu_product_id')->nullable()->after('item_line_message');
            $table->string('manu_promotion_item')->nullable()->after('manu_product_id');
            $table->string('manu_promotion_description')->nullable()->after('manu_promotion_item');
            $table->string('manu_promotion_code')->nullable()->after('manu_promotion_description');
            $table->decimal('manu_base_count', 14, 4)->default(0)->after('manu_promotion_code');
        });

        Schema::create('item_upcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('upc', 64);
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'is_primary']);
        });

        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('price', 12, 4)->default(0);
            $table->string('alias_code', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_item_code', 64)->nullable();
            $table->date('last_received_at')->nullable();
            $table->decimal('last_cost', 12, 4)->default(0);
            $table->decimal('avg_cost', 12, 4)->default(0);
            $table->unsignedInteger('lead_time')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('item_substitutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substitute_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->boolean('force_substitute')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_substitutes');
        Schema::dropIfExists('item_suppliers');
        Schema::dropIfExists('item_prices');
        Schema::dropIfExists('item_upcs');

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_method_id');
            $table->dropConstrainedForeignId('promotion_schedule_id');
            $table->dropConstrainedForeignId('tax_schedule_id');
            $table->dropColumn([
                'msrp', 'last_cost', 'average_cost', 'on_order_qty', 'back_order_qty',
                'restock_level', 'lead_time_days', 'last_received_at', 'last_ordered_at',
                'last_sold_at', 'last_count_date', 'extended_description', 'product_highlights',
                'image_path', 'thumbnail_path', 'item_tracking', 'shipping_weight', 'tare_weight',
                'manufacturer', 'item_line_message', 'manu_product_id', 'manu_promotion_item',
                'manu_promotion_description', 'manu_promotion_code', 'manu_base_count',
            ]);
        });
    }
};
