<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['company_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'username']);
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['username', 'is_active']);
        });

        Schema::dropIfExists('roles');
    }
};
