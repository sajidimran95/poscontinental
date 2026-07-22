<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    protected $fillable = [
        'company_id', 'stock_count_no', 'date_created', 'status', 'last_count_date',
        'date_entered', 'date_processed', 'processed_by', 'site_id', 'description',
        'shared_count', 'comments',
    ];

    protected function casts(): array
    {
        return [
            'date_created' => 'datetime',
            'last_count_date' => 'datetime',
            'date_entered' => 'datetime',
            'date_processed' => 'datetime',
            'shared_count' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class)->orderBy('line_no');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public static function nextNumber(int $companyId): string
    {
        $last = static::query()->where('company_id', $companyId)->orderByDesc('id')->value('stock_count_no');
        $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 300450481966;

        return (string) $n;
    }
}
