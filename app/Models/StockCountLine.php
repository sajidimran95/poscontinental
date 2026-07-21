<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    protected $fillable = [
        'stock_count_id', 'item_id', 'item_code', 'description', 'uom',
        'in_stock', 'allocated', 'counted', 'count_time', 'line_no',
    ];

    protected function casts(): array
    {
        return [
            'in_stock' => 'decimal:4',
            'allocated' => 'decimal:4',
            'counted' => 'decimal:4',
            'count_time' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
