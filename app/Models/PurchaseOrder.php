<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'company_id', 'po_number', 'order_type', 'reference_no', 'requisition_date', 'status',
        'buyer_id', 'required_date', 'ship_to_site_id', 'supplier_id', 'ship_from',
        'payment_term_id', 'ship_via_id', 'comments',
        'subtotal', 'trade_discount', 'freight', 'miscellaneous', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'requisition_date' => 'date',
            'required_date' => 'date',
            'subtotal' => 'decimal:4',
            'trade_discount' => 'decimal:4',
            'freight' => 'decimal:4',
            'miscellaneous' => 'decimal:4',
            'tax' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function shipToSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'ship_to_site_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function shipVia(): BelongsTo
    {
        return $this->belongsTo(ShipVia::class);
    }

    public function getTotalItemsOrderedAttribute(): float
    {
        return (float) $this->lines->sum('qty_ordered');
    }

    public function getTotalItemsReceivedAttribute(): float
    {
        return (float) $this->lines->sum('qty_received');
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('po_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 912000061801;

        return (string) $n;
    }
}
