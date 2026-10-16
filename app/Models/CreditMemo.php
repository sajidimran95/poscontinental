<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CreditMemo extends Model
{
    protected $fillable = [
        'company_id', 'memo_number', 'memo_date', 'reference_no', 'reason', 'customer_id', 'sales_order_id',
        'amount', 'status', 'comments', 'restock_inventory',
    ];

    protected function casts(): array
    {
        return [
            'memo_date' => 'date',
            'amount' => 'decimal:4',
            'restock_inventory' => 'boolean',
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

    public function applications(): HasMany
    {
        return $this->hasMany(InvoiceCredit::class);
    }

    public function getAppliedAmountAttribute(): float
    {
        return (float) $this->applications()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->applied_amount);
    }

    /** Unused credit (open memos, e.g. overpayments) the customer still has on account. */
    public static function openCreditTotal(int $companyId, ?int $customerId): float
    {
        if (! $customerId) {
            return 0.0;
        }

        $applied = DB::table('invoice_credits')
            ->selectRaw('credit_memo_id, SUM(amount) as applied')
            ->groupBy('credit_memo_id');

        return round((float) DB::table('credit_memos as cm')
            ->leftJoinSub($applied, 'ap', 'ap.credit_memo_id', '=', 'cm.id')
            ->where('cm.company_id', $companyId)
            ->where('cm.customer_id', $customerId)
            ->where('cm.status', 'Open')
            ->selectRaw('SUM(GREATEST(cm.amount - COALESCE(ap.applied, 0), 0)) as t')
            ->value('t'), 2);
    }

    public static function nextNumber(int $companyId): string
    {
        // Imported memos are not in id order, so use the highest numeric memo number.
        $max = (int) static::query()
            ->where('company_id', $companyId)
            ->whereRaw("memo_number REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(memo_number AS UNSIGNED)'));
        $n = $max > 0 ? $max + 1 : 50001;
        while (static::query()->where('company_id', $companyId)->where('memo_number', (string) $n)->exists()) {
            $n++;
        }

        return (string) $n;
    }
}
