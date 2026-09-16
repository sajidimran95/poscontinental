<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPriceHistory extends Model
{
    public const TYPE_COST = 'cost';

    public const TYPE_SALES = 'sales_price';

    public const TYPE_BOTH = 'both';

    public const SOURCE_PO = 'purchase_order';

    public const SOURCE_RECEIVING = 'receiving';

    public const SOURCE_ITEM_EDIT = 'item_edit';

    public const SOURCE_BULK = 'bulk_pricing';

    protected $fillable = [
        'company_id',
        'item_id',
        'user_id',
        'change_type',
        'source',
        'source_id',
        'reference',
        'cost_before',
        'cost_after',
        'list_price_before',
        'list_price_after',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_before' => 'decimal:4',
            'cost_after' => 'decimal:4',
            'list_price_before' => 'decimal:4',
            'list_price_after' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
