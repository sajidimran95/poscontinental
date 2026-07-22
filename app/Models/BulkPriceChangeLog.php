<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkPriceChangeLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'filter_criteria',
        'adjustment_type',
        'adjustment_value',
        'targets',
        'items_affected',
    ];

    protected function casts(): array
    {
        return [
            'filter_criteria' => 'array',
            'targets' => 'array',
            'adjustment_value' => 'decimal:4',
            'items_affected' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkPriceChangeItem::class);
    }
}
