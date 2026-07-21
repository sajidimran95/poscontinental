<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesOrder extends Model
{
    protected $fillable = [
        'company_id', 'order_number', 'order_type', 'status', 'priority', 'customer_id', 'ship_to_address_id',
        'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
        'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
        'order_date', 'required_date', 'customer_po_no', 'reference_no', 'sales_rep_id',
        'payment_term_id', 'route_id', 'ship_via_id', 'ship_from_site_id', 'ship_date',
        'no_of_boxes', 'no_of_pallets', 'custom_field_1', 'custom_field_2', 'custom_field_3',
        'custom_field_4', 'custom_field_5', 'comments',
        'subtotal', 'trade_discount', 'freight', 'miscellaneous', 'tax', 'total', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'required_date' => 'date',
            'ship_date' => 'date',
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
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_no');
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(SalesOrderBox::class)->orderBy('sort_order');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('order_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 243074;

        return (string) $n;
    }
}
