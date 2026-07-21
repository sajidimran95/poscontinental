<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditMemo extends Model
{
    protected $fillable = [
        'company_id', 'memo_number', 'memo_date', 'customer_id', 'sales_order_id',
        'amount', 'status', 'comments',
    ];

    protected function casts(): array
    {
        return [
            'memo_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditMemoLine::class)->orderBy('line_no');
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('memo_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 50001;

        return (string) $n;
    }
}
