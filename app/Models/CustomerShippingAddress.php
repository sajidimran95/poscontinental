<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerShippingAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'address',
        'city',
        'state',
        'zip',
        'telephone',
        'fax',
        'class',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
