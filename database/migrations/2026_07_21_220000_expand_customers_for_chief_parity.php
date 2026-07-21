<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('telephone2', 32)->nullable()->after('telephone');
            $table->string('mobile', 32)->nullable()->after('telephone2');
            $table->string('fax', 32)->nullable()->after('mobile');
            $table->string('web_page')->nullable()->after('email');
            $table->text('comments')->nullable()->after('messages_alerts');
            $table->string('lead_source')->nullable()->after('sales_rep_id');
            $table->string('customer_category')->nullable()->after('lead_source');
            $table->boolean('opt_out_catalog')->default(false)->after('customer_category');
            $table->boolean('opt_out_email')->default(false);
            $table->boolean('opt_out_telemarketing')->default(false);
            $table->boolean('opt_out_mobile')->default(false);
            $table->boolean('opt_out_all')->default(false);
            $table->string('account_type', 64)->nullable()->after('fein_no');
            $table->date('customer_since')->nullable();
            $table->date('last_order_on')->nullable();
            $table->unsignedInteger('number_of_orders')->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->unsignedInteger('bad_checks_count')->default(0);
            $table->unsignedInteger('replacements_count')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->string('order_day', 16)->nullable();
            $table->string('location_no', 64)->nullable();
            $table->boolean('drivers_accept_returns')->default(false);
            $table->boolean('certificate_on_file')->default(false);
            $table->boolean('is_employee')->default(false);
            $table->string('owner_name')->nullable();
            $table->text('owner_ssn')->nullable();
            $table->string('owner_address')->nullable();
            $table->string('owner_city')->nullable();
            $table->string('owner_state', 32)->nullable();
            $table->string('owner_zip', 20)->nullable();
            $table->string('owner_country', 64)->nullable();
            $table->string('owner_telephone', 32)->nullable();
            $table->string('owner_fax', 32)->nullable();
            $table->string('owner_email')->nullable();
        });

        Schema::create('customer_shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('telephone', 32)->nullable();
            $table->string('fax', 32)->nullable();
            $table->string('class', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_shipping_addresses');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'telephone2', 'mobile', 'fax', 'web_page', 'comments', 'lead_source', 'customer_category',
                'opt_out_catalog', 'opt_out_email', 'opt_out_telemarketing', 'opt_out_mobile', 'opt_out_all',
                'account_type', 'customer_since', 'last_order_on', 'number_of_orders', 'total_sales',
                'bad_checks_count', 'replacements_count', 'returns_count', 'order_day', 'location_no',
                'drivers_accept_returns', 'certificate_on_file', 'is_employee',
                'owner_name', 'owner_ssn', 'owner_address', 'owner_city', 'owner_state', 'owner_zip',
                'owner_country', 'owner_telephone', 'owner_fax', 'owner_email',
            ]);
        });
    }
};
