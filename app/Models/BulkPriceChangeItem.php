<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkPriceChangeItem extends Model
{
    protected $fillable = [
        'bulk_price_change_log_id',
        'item_id',
        'item_code',
        'list_price_before',
        'list_price_after',
        'standard_cost_before',
        'standard_cost_after',
        'current_cost_before',
        'current_cost_after',
    ];

    protected function casts(): array
    {
        return [
            'list_price_before' => 'decimal:4',
            'list_price_after' => 'decimal:4',
            'standard_cost_before' => 'decimal:4',
            'standard_cost_after' => 'decimal:4',
            'current_cost_before' => 'decimal:4',
            'current_cost_after' => 'decimal:4',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(BulkPriceChangeLog::class, 'bulk_price_change_log_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
