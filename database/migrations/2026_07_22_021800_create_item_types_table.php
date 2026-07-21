<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        $defaults = [
            ['code' => 'STD', 'name' => 'Standard Item'],
            ['code' => 'KIT', 'name' => 'Kit'],
            ['code' => 'NONINV', 'name' => 'Non-Inventory'],
            ['code' => 'SVC', 'name' => 'Service'],
        ];

        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($defaults as $row) {
                DB::table('item_types')->insert([
                    'company_id' => $companyId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};
