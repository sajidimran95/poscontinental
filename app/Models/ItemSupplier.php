<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSupplier extends Model
{
    protected $fillable = [
        'item_id',
        'supplier_id',
        'supplier_item_code',
        'last_received_at',
        'last_cost',
        'avg_cost',
        'lead_time',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'last_received_at' => 'date',
            'last_cost' => 'decimal:4',
            'avg_cost' => 'decimal:4',
            'is_default' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
