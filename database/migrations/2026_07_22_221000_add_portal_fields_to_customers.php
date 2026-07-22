<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'portal_email')) {
                $table->string('portal_email')->nullable()->after('email');
            }
            if (! Schema::hasColumn('customers', 'portal_password')) {
                $table->string('portal_password')->nullable()->after('portal_email');
            }
            if (! Schema::hasColumn('customers', 'portal_active')) {
                $table->boolean('portal_active')->default(false)->after('portal_password');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['company_id', 'portal_email'], 'customers_company_portal_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_company_portal_email_index');
            $table->dropColumn(['portal_email', 'portal_password', 'portal_active']);
        });
    }
};
