<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReceivingLine extends Model
{
    protected $fillable = [
        'inventory_receiving_id', 'purchase_order_line_id', 'item_id', 'item_code',
        'description', 'uom', 'qty_ordered', 'qty_received', 'unit_cost', 'line_no',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(InventoryReceiving::class, 'inventory_receiving_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
