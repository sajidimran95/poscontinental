<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'state_license_number')) {
                $table->string('state_license_number', 20)->nullable()->after('fein_no');
            }
            if (! Schema::hasColumn('companies', 'transmitter_account_number')) {
                $table->string('transmitter_account_number', 20)->nullable()->after('state_license_number');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'tobacco_product_type')) {
                $table->string('tobacco_product_type', 32)->nullable()->after('manufacturer');
            }
            if (! Schema::hasColumn('items', 'tobacco_brand_code')) {
                $table->string('tobacco_brand_code', 16)->nullable()->after('tobacco_product_type');
            }
            if (! Schema::hasColumn('items', 'cigarette_pack_size')) {
                $table->unsignedSmallInteger('cigarette_pack_size')->nullable()->after('tobacco_brand_code');
            }
            if (! Schema::hasColumn('items', 'tobacco_total_oz')) {
                $table->decimal('tobacco_total_oz', 14, 4)->nullable()->after('cigarette_pack_size');
            }
            if (! Schema::hasColumn('items', 'tobacco_stick_count')) {
                $table->unsignedBigInteger('tobacco_stick_count')->nullable()->after('tobacco_total_oz');
            }
        });

        Schema::table('tobacco_stamp_inventories', function (Blueprint $table) {
            foreach ([
                'beginning_unaffixed_r1', 'beginning_unaffixed_r2', 'beginning_unaffixed_r3',
                'beginning_unaffixed_r4', 'beginning_unaffixed_r5', 'beginning_unaffixed_r6',
                'ending_unaffixed_r1', 'ending_unaffixed_r2', 'ending_unaffixed_r3',
                'ending_unaffixed_r4', 'ending_unaffixed_r5', 'ending_unaffixed_r6',
                'beginning_affixed_r1', 'beginning_affixed_r2', 'beginning_affixed_r3',
                'beginning_affixed_r4', 'beginning_affixed_r5', 'beginning_affixed_r6',
                'ending_affixed_r1', 'ending_affixed_r2', 'ending_affixed_r3',
                'ending_affixed_r4', 'ending_affixed_r5', 'ending_affixed_r6',
            ] as $col) {
                if (! Schema::hasColumn('tobacco_stamp_inventories', $col)) {
                    $table->unsignedBigInteger($col)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['state_license_number', 'transmitter_account_number'],
                fn ($c) => Schema::hasColumn('companies', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $cols = array_values(array_filter([
                'tobacco_product_type',
                'tobacco_brand_code',
                'cigarette_pack_size',
                'tobacco_total_oz',
                'tobacco_stick_count',
            ], fn ($c) => Schema::hasColumn('items', $c)));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('tobacco_stamp_inventories', function (Blueprint $table) {
            $cols = [];
            foreach ([
                'beginning_unaffixed_r1', 'beginning_unaffixed_r2', 'beginning_unaffixed_r3',
                'beginning_unaffixed_r4', 'beginning_unaffixed_r5', 'beginning_unaffixed_r6',
                'ending_unaffixed_r1', 'ending_unaffixed_r2', 'ending_unaffixed_r3',
                'ending_unaffixed_r4', 'ending_unaffixed_r5', 'ending_unaffixed_r6',
                'beginning_affixed_r1', 'beginning_affixed_r2', 'beginning_affixed_r3',
                'beginning_affixed_r4', 'beginning_affixed_r5', 'beginning_affixed_r6',
                'ending_affixed_r1', 'ending_affixed_r2', 'ending_affixed_r3',
                'ending_affixed_r4', 'ending_affixed_r5', 'ending_affixed_r6',
            ] as $col) {
                if (Schema::hasColumn('tobacco_stamp_inventories', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
