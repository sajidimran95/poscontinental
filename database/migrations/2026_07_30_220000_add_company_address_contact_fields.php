<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'address')) {
                $table->string('address', 255)->nullable()->after('name');
            }
            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (! Schema::hasColumn('companies', 'state')) {
                $table->string('state', 2)->nullable()->after('city');
            }
            if (! Schema::hasColumn('companies', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('state');
            }
            if (! Schema::hasColumn('companies', 'phone')) {
                $table->string('phone', 40)->nullable()->after('zip_code');
            }
            if (! Schema::hasColumn('companies', 'fax')) {
                $table->string('fax', 40)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('companies', 'email')) {
                $table->string('email', 255)->nullable()->after('fax');
            }
            if (! Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name', 120)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['address', 'city', 'state', 'zip_code', 'phone', 'fax', 'email', 'contact_name'],
                fn ($c) => Schema::hasColumn('companies', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
