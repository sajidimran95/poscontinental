<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
            $table->string('state', 32)->nullable()->after('city');
            $table->string('zip', 32)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('customer_shipping_addresses', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'zip']);
        });
    }
};
