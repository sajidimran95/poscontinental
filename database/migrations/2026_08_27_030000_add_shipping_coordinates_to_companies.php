<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'shipping_latitude')) {
                $table->decimal('shipping_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('companies', 'shipping_longitude')) {
                $table->decimal('shipping_longitude', 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'shipping_latitude')) {
                $table->dropColumn('shipping_latitude');
            }
            if (Schema::hasColumn('companies', 'shipping_longitude')) {
                $table->dropColumn('shipping_longitude');
            }
        });
    }
};
