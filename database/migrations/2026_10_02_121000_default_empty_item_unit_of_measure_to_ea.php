<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Items with no inventory UOM and no pricing UOM still show "—" on the list.
        // Default them to EA (base UOM used by this company's schedules).
        DB::table('items')
            ->where(function ($q) {
                $q->whereNull('unit_of_measure')->orWhere('unit_of_measure', '');
            })
            ->update(['unit_of_measure' => 'EA']);
    }

    public function down(): void
    {
        // Leave defaults in place.
    }
};
