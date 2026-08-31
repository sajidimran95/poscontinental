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
        if (array_key_exists('payments_sum_amount', $this->attributes)) {
            return (float) $this->attributes['payments_sum_amount'];
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    public function getTotalCreditsAttribute(): float
    {
        if (array_key_exists('credits_sum_amount', $this->attributes)) {
            return (float) $this->attributes['credits_sum_amount'];
        }

        if ($this->relationLoaded('credits')) {
            return (float) $this->credits->sum('amount');
        }

        return (float) $this->credits()->sum('amount');
    }

    public function getInvoiceBalanceAttribute(): float
    {
        return (float) $this->invoice_total - $this->total_payments - $this->total_credits;
    }

    /**
     * Other unpaid invoices for this customer (invoice no + due amount).
     *
     * @return array{lines: list<array{invoice_number: string, invoice_date: ?string, balance: float}>, total: float}
     */
    public static function previousOpenInvoices(int $companyId, ?int $customerId, ?int $exceptInvoiceId = null): array
    {
        if (! $customerId) {
            return ['lines' => [], 'total' => 0.0];
        }

        $rows = static::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->when($exceptInvoiceId, fn ($q) => $q->where('id', '!=', $exceptInvoiceId))
            ->whereRaw("UPPER(COALESCE(status, '')) NOT IN ('PAID', 'VOID', 'CANCELLED')")
            ->withSum('payments as payments_sum_amount', 'amount')
            ->withSum('credits as credits_sum_amount', 'amount')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'invoice_date', 'invoice_total']);

        $lines = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $balance = round(max(0, (float) $row->invoice_total - (float) ($row->payments_sum_amount ?? 0) - (float) ($row->credits_sum_amount ?? 0)), 2);
            if ($balance < 0.005) {
                continue;
            }
            $lines[] = [
                'invoice_number' => (string) $row->invoice_number,
                'invoice_date' => optional($row->invoice_date)?->format('m/d/Y'),
                'balance' => $balance,
            ];
            $total += $balance;
        }

        return ['lines' => $lines, 'total' => round($total, 2)];
    }

    public static function previousOpenBalance(int $companyId, ?int $customerId, ?int $exceptInvoiceId = null): float
    {
        return self::previousOpenInvoices($companyId, $customerId, $exceptInvoiceId)['total'];
    }

    public static function nextNumber(int|string $companyId): string
    {
        $companyId = (int) $companyId;
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('invoice_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 100001;

        return (string) $n;
    }
}
