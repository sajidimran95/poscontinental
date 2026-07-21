<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnToVendorLine extends Model
{
    protected $fillable = [
        'return_to_vendor_id', 'item_id', 'item_code', 'description', 'uom',
        'qty', 'unit_cost', 'extended_cost', 'line_no',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'extended_cost' => 'decimal:4',
        ];
    }

    public function rtv(): BelongsTo
    {
        return $this->belongsTo(ReturnToVendor::class, 'return_to_vendor_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
