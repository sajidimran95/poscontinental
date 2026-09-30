<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EcomPromotion extends Model
{
    public const TARGETS = [
        'category' => 'Category',
        'brand' => 'Brand',
        'items' => 'Selected items',
    ];

    public const DISCOUNT_TYPES = [
        'none' => 'No discount (showcase only)',
        'percent' => 'Percent off',
        'fixed' => 'Amount off ($)',
    ];

    protected $fillable = [
        'company_id', 'title', 'subtitle', 'tag', 'target', 'category_id', 'brand',
        'discount_type', 'discount_value', 'starts_on', 'ends_on', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'ecom_promotion_items');
    }

    /** Switched on and inside its date window (dates are inclusive). */
    public function scopeRunning(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public function hasDiscount(): bool
    {
        return $this->discount_type !== 'none' && $this->discount_value > 0;
    }

    public function discountedPrice(float $price): float
    {
        return match ($this->discount_type) {
            'percent' => round($price * (1 - min(100, $this->discount_value) / 100), 2),
            'fixed' => round(max(0, $price - $this->discount_value), 2),
            default => $price,
        };
    }

    /** Short badge text, e.g. "15% OFF" / "$2.00 OFF" (falls back to the tag). */
    public function badge(): ?string
    {
        return match (true) {
            $this->discount_type === 'percent' && $this->discount_value > 0 => rtrim(rtrim(number_format($this->discount_value, 2), '0'), '.').'% OFF',
            $this->discount_type === 'fixed' && $this->discount_value > 0 => '$'.number_format($this->discount_value, 2).' OFF',
            default => $this->tag ?: null,
        };
    }

    public function status(): string
    {
        $today = now()->startOfDay();

        return match (true) {
            ! $this->is_active => 'Off',
            $this->starts_on && $this->starts_on->gt($today) => 'Scheduled',
            $this->ends_on && $this->ends_on->lt($today) => 'Ended',
            default => 'Running',
        };
    }
}
