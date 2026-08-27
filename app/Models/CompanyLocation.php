<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyLocation extends Model
{
    protected $fillable = [
        'company_id', 'name', 'address', 'city', 'state', 'state_code', 'zip_code',
        'country', 'latitude', 'longitude', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveryRoutes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }

    public function formattedAddress(): string
    {
        return collect([$this->address, $this->city, $this->state_code ?: $this->state, $this->zip_code, $this->country])
            ->filter()
            ->implode(', ');
    }
}
