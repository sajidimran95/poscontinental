<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TobaccoStampInventory extends Model
{
    protected $fillable = [
        'company_id', 'period_start', 'period_end',
        'r1_beginning_unaffixed', 'r2_beginning_affixed', 'r3_purchased',
        'r4_affixed', 'r5_ending_unaffixed', 'r6_ending_affixed',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'r1_beginning_unaffixed' => 'decimal:2',
            'r2_beginning_affixed' => 'decimal:2',
            'r3_purchased' => 'decimal:2',
            'r4_affixed' => 'decimal:2',
            'r5_ending_unaffixed' => 'decimal:2',
            'r6_ending_affixed' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
