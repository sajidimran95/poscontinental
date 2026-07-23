<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'mail_mailer')) {
                $table->string('mail_mailer', 32)->default('log')->after('customer_app_api_active');
                $table->string('mail_host')->nullable()->after('mail_mailer');
                $table->unsignedInteger('mail_port')->nullable()->after('mail_host');
                $table->string('mail_username')->nullable()->after('mail_port');
                $table->text('mail_password')->nullable()->after('mail_username');
                $table->string('mail_encryption', 16)->nullable()->after('mail_password');
                $table->string('mail_from_address')->nullable()->after('mail_encryption');
                $table->string('mail_from_name')->nullable()->after('mail_from_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
            ]);
        });
    }
};
