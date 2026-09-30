<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcomWishlist extends Model
{
    protected $fillable = ['customer_id', 'item_id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
