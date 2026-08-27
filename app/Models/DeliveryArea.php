<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryArea extends Model
{
    protected $fillable = [
        'company_id', 'state', 'state_code', 'city', 'zip_code',
        'country', 'county', 'latitude', 'longitude', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function label(): string
    {
        $parts = array_filter([$this->city, $this->state, $this->state_code, $this->zip_code]);

        return implode(' · ', $parts) ?: $this->state_code;
    }
}
