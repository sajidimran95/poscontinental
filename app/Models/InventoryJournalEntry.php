<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryJournalEntry extends Model
{
    protected $fillable = [
        'company_id', 'item_id', 'site_id', 'source_type', 'source_id', 'reference',
        'qty_change', 'qty_after', 'unit_cost', 'user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:4',
            'qty_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
