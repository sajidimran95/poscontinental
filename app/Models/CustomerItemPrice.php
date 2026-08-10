<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerItemPrice extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'item_id',
        'uom',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Upsert (memorize) a negotiated sell price for one customer + item + UOM.
     */
    public static function memorize(int $companyId, int $customerId, int $itemId, ?string $uom, float $price): self
    {
        $uomKey = filled($uom) ? trim((string) $uom) : '';

        return static::query()->updateOrCreate(
            [
                'customer_id' => $customerId,
                'item_id' => $itemId,
                'uom' => $uomKey,
            ],
            [
                'company_id' => $companyId,
                'price' => max(0, round($price, 4)),
            ]
        );
    }

    public static function findPrice(int $customerId, int $itemId, ?string $uom): ?float
    {
        $uomKey = filled($uom) ? trim((string) $uom) : '';

        $row = static::query()
            ->where('customer_id', $customerId)
            ->where('item_id', $itemId)
            ->where(function ($q) use ($uomKey) {
                $q->where('uom', $uomKey);
                if ($uomKey !== '') {
                    $q->orWhereNull('uom')->orWhere('uom', '');
                }
            })
            ->orderByRaw("CASE WHEN uom = ? THEN 0 ELSE 1 END", [$uomKey])
            ->first();

        return $row ? (float) $row->price : null;
    }
}
