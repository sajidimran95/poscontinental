<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_orders', 'delivery_user_id')) {
                    $table->foreignId('delivery_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_orders', 'delivery_date')) {
                    $table->date('delivery_date')->nullable()->after('delivery_user_id');
                }
                if (! Schema::hasColumn('sales_orders', 'delivery_status')) {
                    $table->string('delivery_status', 32)->nullable()->after('delivery_date');
                }
                if (! Schema::hasColumn('sales_orders', 'shipping_latitude')) {
                    $table->decimal('shipping_latitude', 10, 7)->nullable()->after('delivery_status');
                }
                if (! Schema::hasColumn('sales_orders', 'shipping_longitude')) {
                    $table->decimal('shipping_longitude', 10, 7)->nullable()->after('shipping_latitude');
                }
            });
        }

        if (! Schema::hasTable('company_locations')) {
            Schema::create('company_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 8)->nullable();
                $table->string('zip_code', 16)->nullable();
                $table->string('country')->default('USA');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_areas')) {
            Schema::create('delivery_areas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('state');
                $table->string('state_code', 8);
                $table->string('city')->nullable();
                $table->string('zip_code', 16)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_routes')) {
            Schema::create('delivery_routes', function (Blueprint $table) {
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

        if (! Schema::hasTable('delivery_route_orders')) {
            Schema::create('delivery_route_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_route_id')->constrained('delivery_routes')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->unsignedInteger('stop_no')->default(1);
                $table->unsignedInteger('distance_from_previous')->default(0);
                $table->unsignedInteger('estimated_duration_from_previous')->default(0);
                $table->string('status', 32)->default('pending');
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('fail_reason')->nullable();
                $table->text('delivery_notes')->nullable();
                $table->string('ship_to_name')->nullable();
                $table->string('ship_to_phone')->nullable();
                $table->string('ship_to_address')->nullable();
                $table->string('ship_to_city')->nullable();
                $table->string('ship_to_state')->nullable();
                $table->string('ship_to_zip')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();

                $table->unique(['delivery_route_id', 'order_id']);
            });
        }

        $this->seedRoleAndLookups();
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_orders');
        Schema::dropIfExists('delivery_routes');
        Schema::dropIfExists('delivery_areas');
        Schema::dropIfExists('company_locations');

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                foreach (['shipping_longitude', 'shipping_latitude', 'delivery_status', 'delivery_date'] as $col) {
                    if (Schema::hasColumn('sales_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('sales_orders', 'delivery_user_id')) {
                    $table->dropConstrainedForeignId('delivery_user_id');
                }
            });
        }
    }

    private function seedRoleAndLookups(): void
    {
        if (Schema::hasTable('roles')) {
            $exists = DB::table('roles')->where('name', 'delivery')->exists();
            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => 'delivery',
                    'label' => 'Delivery',
                    'permissions' => json_encode(['delivery.driver.view', 'delivery.driver.edit']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('companies') || ! Schema::hasTable('delivery_areas')) {
            return;
        }

        $companyIds = DB::table('companies')->pluck('id');
        $states = [
            ['Michigan', 'MI'],
            ['Illinois', 'IL'],
            ['Wisconsin', 'WI'],
            ['Indiana', 'IN'],
        ];

        foreach ($companyIds as $companyId) {
            foreach ($states as [$state, $code]) {
                $already = DB::table('delivery_areas')
                    ->where('company_id', $companyId)
                    ->where('state_code', $code)
                    ->whereNull('city')
                    ->exists();
                if ($already) {
                    continue;
                }
                DB::table('delivery_areas')->insert([
                    'company_id' => $companyId,
                    'state' => $state,
                    'state_code' => $code,
                    'city' => null,
                    'zip_code' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
