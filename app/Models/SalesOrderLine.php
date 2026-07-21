<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    protected $fillable = [
        'sales_order_id', 'item_id', 'item_code', 'description', 'uom',
        'qty_ordered', 'qty_shipped', 'price', 'discount', 'line_total', 'line_no',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_shipped' => 'decimal:4',
            'price' => 'decimal:4',
            'discount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
