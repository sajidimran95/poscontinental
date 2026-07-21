<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'is_inactive',
        'name',
        'contact_name',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'fein_no',
        'phone1',
        'phone2',
        'fax',
        'email',
        'web_page',
        'is_tobacco_supplier',
    ];

    protected function casts(): array
    {
        return [
            'is_inactive' => 'boolean',
            'is_tobacco_supplier' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }
}
