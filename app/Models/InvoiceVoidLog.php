<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceVoidLog extends Model
{
    protected $fillable = [
        'company_id',
        'invoice_id',
        'sales_order_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'invoice_total',
        'invoice_number',
        'order_number',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'invoice_total' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
