<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcomOrderMeta extends Model
{
    protected $table = 'ecom_order_meta';

    protected $fillable = [
        'company_id', 'sales_order_id', 'customer_id', 'tracking_token',
        'payment_method', 'email', 'phone', 'timeline',
    ];

    protected function casts(): array
    {
        return ['timeline' => 'array'];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
