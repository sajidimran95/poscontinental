<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcomCartItem extends Model
{
    protected $fillable = ['ecom_cart_id', 'item_id', 'uom', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcomCart::class, 'ecom_cart_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
