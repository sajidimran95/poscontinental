<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnToVendor extends Model
{
    protected $fillable = [
        'company_id', 'rtv_number', 'rtv_date', 'status', 'reference_no', 'supplier_id',
        'requested_by_id', 'site_id', 'comments', 'subtotal', 'discount', 'freight', 'total',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'rtv_date' => 'date',
            'processed_at' => 'datetime',
            'subtotal' => 'decimal:4',
            'discount' => 'decimal:4',
            'freight' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnToVendorLine::class)->orderBy('line_no');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('rtv_number');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 7000001;

        return (string) $n;
    }
}
