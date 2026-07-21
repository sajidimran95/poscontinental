<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditMemoLine extends Model
{
    protected $fillable = [
        'credit_memo_id',
        'item_id',
        'item_code',
        'description',
        'uom',
        'qty',
        'price',
        'line_total',
        'line_no',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'price' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(CreditMemo::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
