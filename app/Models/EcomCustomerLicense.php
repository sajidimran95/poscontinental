<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcomCustomerLicense extends Model
{
    public const TYPES = [
        'business' => 'Business Certificate',
        'resale' => 'Resale Certificate',
        'tobacco' => 'Tobacco Certificate',
        'id' => 'ID Certificate',
        'other' => 'Other',
    ];

    protected $fillable = [
        'customer_id', 'license_type', 'label', 'certificate_number',
        'expires_at', 'file_path', 'original_name',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function displayLabel(): string
    {
        return $this->label ?: (self::TYPES[$this->license_type] ?? $this->license_type);
    }
}
