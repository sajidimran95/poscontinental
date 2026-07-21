<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSubstitute extends Model
{
    protected $fillable = [
        'item_id',
        'substitute_item_id',
        'quantity',
        'force_substitute',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'force_substitute' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function substituteItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'substitute_item_id');
    }
}
