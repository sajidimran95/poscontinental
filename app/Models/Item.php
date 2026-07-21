<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'company_id',
        'item_code',
        'item_type',
        'class',
        'description',
        'extended_description',
        'product_highlights',
        'image_path',
        'thumbnail_path',
        'list_price',
        'msrp',
        'standard_cost',
        'current_cost',
        'last_cost',
        'average_cost',
        'quantity_in_stock',
        'allocated_qty',
        'on_order_qty',
        'back_order_qty',
        'reorder_point',
        'restock_level',
        'lead_time_days',
        'last_received_at',
        'last_ordered_at',
        'last_sold_at',
        'last_count_date',
        'department_id',
        'category_id',
        'subcategory_id',
        'uom_schedule_id',
        'tax_schedule_id',
        'promotion_schedule_id',
        'pricing_method_id',
        'unit_of_measure',
        'is_inactive',
        'can_order',
        'can_sell',
        'allow_back_order',
        'available_on_website',
        'item_tracking',
        'barcode_format',
        'shipping_weight',
        'tare_weight',
        'manufacturer',
        'item_line_message',
        'comments',
        'manu_product_id',
        'manu_promotion_item',
        'manu_promotion_description',
        'manu_promotion_code',
        'manu_base_count',
        'primary_upc',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:4',
            'msrp' => 'decimal:4',
            'standard_cost' => 'decimal:4',
            'current_cost' => 'decimal:4',
            'last_cost' => 'decimal:4',
            'average_cost' => 'decimal:4',
            'quantity_in_stock' => 'decimal:4',
            'allocated_qty' => 'decimal:4',
            'on_order_qty' => 'decimal:4',
            'back_order_qty' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'restock_level' => 'decimal:4',
            'shipping_weight' => 'decimal:4',
            'tare_weight' => 'decimal:4',
            'manu_base_count' => 'decimal:4',
            'last_received_at' => 'date',
            'last_ordered_at' => 'date',
            'last_sold_at' => 'date',
            'last_count_date' => 'date',
            'is_inactive' => 'boolean',
            'can_order' => 'boolean',
            'can_sell' => 'boolean',
            'allow_back_order' => 'boolean',
            'available_on_website' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function uomSchedule(): BelongsTo
    {
        return $this->belongsTo(UomSchedule::class);
    }

    public function taxSchedule(): BelongsTo
    {
        return $this->belongsTo(TaxSchedule::class);
    }

    public function promotionSchedule(): BelongsTo
    {
        return $this->belongsTo(DiscountSchedule::class, 'promotion_schedule_id');
    }

    public function pricingMethod(): BelongsTo
    {
        return $this->belongsTo(PricingMethod::class);
    }

    public function upcs(): HasMany
    {
        return $this->hasMany(ItemUpc::class)->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class)->orderBy('sort_order');
    }

    public function itemSuppliers(): HasMany
    {
        return $this->hasMany(ItemSupplier::class)->orderBy('sort_order');
    }

    public function substitutes(): HasMany
    {
        return $this->hasMany(ItemSubstitute::class)->orderBy('sort_order');
    }

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity_in_stock - (float) $this->allocated_qty;
    }

    public function scopeNewItems(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity_in_stock', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->where('is_inactive', false);
    }
}
