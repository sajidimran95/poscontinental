<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReceiving extends Model
{
    protected $fillable = [
        'company_id', 'receipt_number', 'receipt_date', 'purchase_order_id', 'reference_no',
        'status', 'supplier_id', 'buyer_id', 'site_id', 'received_by', 'shipping_carrier',
        'comments', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryReceivingLine::class)->orderBy('line_no');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('receipt_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 8000001;

        return (string) $n;
    }
}
