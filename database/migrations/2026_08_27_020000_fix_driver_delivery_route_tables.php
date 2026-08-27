<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // POS lookups already own `delivery_routes` (code/name). Planned driver routes need a separate table.
        if (! Schema::hasTable('driver_delivery_routes')) {
            Schema::create('driver_delivery_routes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delivery_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_location_id')->nullable()->constrained('company_locations')->nullOnDelete();
                $table->date('route_date');
                $table->string('status', 32)->default('planned');
                $table->unsignedInteger('total_orders')->default(0);
                $table->unsignedInteger('total_distance')->default(0);
                $table->unsignedInteger('estimated_duration')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('start_name')->nullable();
                $table->string('start_address')->nullable();
                $table->decimal('start_latitude', 10, 7)->nullable();
                $table->decimal('start_longitude', 10, 7)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'route_date']);
                $table->index(['delivery_user_id', 'route_date']);
            });
        }

        if (Schema::hasTable('delivery_route_orders')) {
            Schema::table('delivery_route_orders', function (Blueprint $table) {
                try {
                    $table->dropForeign(['delivery_route_id']);
                } catch (\Throwable) {
                    // Foreign key name may differ if the original create was skipped.
                }
            });
            DB::table('delivery_route_orders')->delete();
            Schema::table('delivery_route_orders', function (Blueprint $table) {
                $table->foreign('delivery_route_id')
                    ->references('id')
                    ->on('driver_delivery_routes')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('delivery_areas')) {
            Schema::table('delivery_areas', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_areas', 'country')) {
                    $table->string('country', 80)->default('USA')->after('zip_code');
                }
                if (! Schema::hasColumn('delivery_areas', 'county')) {
                    $table->string('county', 80)->nullable()->after('country');
                }
                if (! Schema::hasColumn('delivery_areas', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('county');
                }
                if (! Schema::hasColumn('delivery_areas', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });

            DB::table('delivery_areas')->whereNull('city')->update(['city' => '']);
            DB::table('delivery_areas')->whereNull('zip_code')->update(['zip_code' => '']);

            Schema::table('delivery_areas', function (Blueprint $table) {
                $table->index('state_code');
                $table->index('city');
                $table->index('zip_code');
            });

            try {
                Schema::table('delivery_areas', function (Blueprint $table) {
                    $table->unique(['company_id', 'state_code', 'city', 'zip_code'], 'delivery_areas_place_unique');
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_route_orders') && Schema::hasTable('delivery_routes')) {
            Schema::table('delivery_route_orders', function (Blueprint $table) {
                try {
                    $table->dropForeign(['delivery_route_id']);
                } catch (\Throwable) {
                }
            });
        }
        Schema::dropIfExists('driver_delivery_routes');
    }
};
