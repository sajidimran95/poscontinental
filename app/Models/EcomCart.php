<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcomCart extends Model
{
    protected $fillable = ['company_id', 'customer_id', 'session_id'];

    public function items(): HasMany
    {
        return $this->hasMany(EcomCartItem::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
