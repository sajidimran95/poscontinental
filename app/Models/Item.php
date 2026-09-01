<?php

namespace App\Models;

use App\Support\ItemSearch;
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
        'tobacco_product_type',
        'tobacco_brand_code',
        'cigarette_pack_size',
        'tobacco_total_oz',
        'tobacco_stick_count',
        'msa_reporting',
        'state_reporting',
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
            'tobacco_total_oz' => 'decimal:4',
            'cigarette_pack_size' => 'integer',
            'tobacco_stick_count' => 'integer',
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
            'msa_reporting' => 'boolean',
            'state_reporting' => 'boolean',
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

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class)->orderBy('sort_order');
    }

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity_in_stock - (float) $this->allocated_qty;
    }

    /** Days after create that an item carries the automatic "New" tag. */
    public const NEW_ITEM_DAYS = 30;

    /**
     * True while the item is still within NEW_ITEM_DAYS of first create.
     * Tag is automatic from created_at — no separate DB flag; after 30 days it is gone.
     */
    public function isNew(): bool
    {
        if (! $this->created_at) {
            return false;
        }

        return $this->created_at->gte(now()->subDays(self::NEW_ITEM_DAYS));
    }

    public function scopeNewItems(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(self::NEW_ITEM_DAYS));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity_in_stock', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->where('is_inactive', false);
    }

    public function scopeLooseSearch(Builder $query, ?string $search): Builder
    {
        ItemSearch::constrain($query, $search);

        return $query;
    }

    /**
     * Match scanned barcode / typed code to an item.
     *
     * Never matches by database id. Short typed codes on sell (under 8 chars)
     * match item_code only, so typing "12" cannot add a different SKU whose
     * UPC / alias / supplier code happens to be "12".
     *
     * Longer scans still match:
     * - item_code
     * - primary_upc / item_upcs.upc
     * - item_prices.alias_code
     * - item_suppliers.supplier_item_code (not used for sell)
     *
     * @param  'any'|'sell'|'order'  $mode
     */
    public static function findByScanCode(int $companyId, string $code, string $mode = 'any'): ?self
    {
        $code = trim($code);
        $code = preg_replace('/[\x00-\x1F\x7F]/', '', $code) ?? $code;
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $lower = mb_strtolower($code);
        $shortSell = $mode === 'sell' && mb_strlen($lower) < 8;

        $scoped = function () use ($companyId, $mode) {
            $query = static::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false);
            if ($mode === 'sell') {
                $query->where('can_sell', true);
            } elseif ($mode === 'order') {
                $query->where('can_order', true);
            }

            return $query;
        };

        $query = $scoped()->where(function ($q) use ($code, $lower, $shortSell, $mode) {
            $q->where('item_code', $code)
                ->orWhereRaw('LOWER(item_code) = ?', [$lower]);

            if (! $shortSell) {
                $q->orWhere('primary_upc', $code)
                    ->orWhereRaw('LOWER(COALESCE(primary_upc, ?)) = ?', ['', $lower])
                    ->orWhereHas('upcs', function ($u) use ($code, $lower) {
                        $u->where('upc', $code)->orWhereRaw('LOWER(upc) = ?', [$lower]);
                    })
                    ->orWhereHas('prices', function ($p) use ($code, $lower) {
                        $p->whereNotNull('alias_code')
                            ->where('alias_code', '!=', '')
                            ->where(function ($a) use ($code, $lower) {
                                $a->where('alias_code', $code)
                                    ->orWhereRaw('LOWER(alias_code) = ?', [$lower]);
                            });
                    });

                if ($mode !== 'sell') {
                    $q->orWhereHas('itemSuppliers', function ($s) use ($code) {
                        $s->where('supplier_item_code', $code);
                    });
                }
            }
        });

        return $query->with(['prices', 'taxSchedule'])->first();
    }

    public static function itemMatchesScanCode(self $item, string $code, string $mode = 'any'): bool
    {
        $lower = mb_strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $code) ?? $code));
        if ($lower === '') {
            return false;
        }

        if (mb_strtolower(trim((string) $item->item_code)) === $lower) {
            return true;
        }

        if ($mode === 'sell' && mb_strlen($lower) < 8) {
            return false;
        }

        if (mb_strtolower(trim((string) ($item->primary_upc ?? ''))) === $lower) {
            return true;
        }

        $item->loadMissing(['upcs', 'prices', 'itemSuppliers']);

        foreach ($item->upcs as $upc) {
            if (mb_strtolower(trim((string) $upc->upc)) === $lower) {
                return true;
            }
        }

        foreach ($item->prices as $price) {
            if (mb_strtolower(trim((string) ($price->alias_code ?? ''))) === $lower) {
                return true;
            }
        }

        if ($mode !== 'sell') {
            foreach ($item->itemSuppliers as $supplier) {
                if (mb_strtolower(trim((string) ($supplier->supplier_item_code ?? ''))) === $lower) {
                    return true;
                }
            }
        }

        return false;
    }
}
