<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLookupOption extends Model
{
    protected $fillable = ['company_id', 'type', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function optionsFor(int $companyId, string $type)
    {
        return static::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
