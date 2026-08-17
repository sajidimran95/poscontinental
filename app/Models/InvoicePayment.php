<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'payment_date', 'payment_method', 'check_number', 'amount', 'comments', 'user_id',
    ];

    public static function isCheckMethod(?string $method): bool
    {
        return strcasecmp(trim((string) $method), 'Check') === 0;
    }

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
