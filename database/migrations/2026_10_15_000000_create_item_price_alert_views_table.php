<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_price_alert_views')) {
            return;
        }

        Schema::create('item_price_alert_views', function (Blueprint $table) {
            $table->id();
            // "u:{user id}" for staff (desk + sale app), "c:{customer id}" for the customer app.
            $table->string('viewer', 32);
            $table->foreignId('item_price_history_id')->constrained('item_price_histories')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['viewer', 'item_price_history_id'], 'item_price_alert_views_viewer_history_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_alert_views');
    }
};
