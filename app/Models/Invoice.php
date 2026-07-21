<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'company_id', 'invoice_number', 'invoice_date', 'sales_order_id', 'customer_id', 'status', 'driver',
        'subtotal', 'total_discount', 'trade_discount', 'freight', 'miscellaneous', 'tax', 'invoice_total',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:4',
            'total_discount' => 'decimal:4',
            'trade_discount' => 'decimal:4',
            'freight' => 'decimal:4',
            'miscellaneous' => 'decimal:4',
            'tax' => 'decimal:4',
            'invoice_total' => 'decimal:4',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(InvoiceCredit::class);
    }

    public function getTotalPaymentsAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getTotalCreditsAttribute(): float
    {
        return (float) $this->credits()->sum('amount');
    }

    public function getInvoiceBalanceAttribute(): float
    {
        return (float) $this->invoice_total - $this->total_payments - $this->total_credits;
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('invoice_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 100001;

        return (string) $n;
    }
}
