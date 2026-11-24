<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryJournalEntry extends Model
{
    protected $fillable = [
        'company_id', 'item_id', 'site_id', 'source_type', 'source_id', 'reference',
        'qty_change', 'qty_after', 'unit_cost', 'user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:4',
            'qty_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Kind key for journal UI styling: sales|receiving|count|rtv|credit|void|adjust|other
     */
    public function entryKind(): string
    {
        $notes = trim((string) ($this->notes ?? ''));
        $type = (string) ($this->source_type ?? '');
        $base = class_basename($type);

        if ($base === 'InventoryReceiving' || str_contains($notes, 'Receiving')) {
            return 'receiving';
        }
        if ($base === 'StockCount' || str_contains($notes, 'Stock Count')) {
            return 'count';
        }
        if ($base === 'ReturnToVendor' || str_contains($notes, 'Return to Vendor')) {
            return 'rtv';
        }
        if ($base === 'CreditMemo' || str_contains($notes, 'Credit Memo') || str_contains($notes, 'restock')) {
            return 'credit';
        }
        if ($base === 'Invoice' || str_starts_with($notes, 'Sales') || str_contains($notes, 'Void Invoice') || str_contains($notes, 'Invoice revision')) {
            return str_contains($notes, 'Void') ? 'void' : 'sales';
        }
        if ($type === 'stock_adjustment' || $base === 'stock_adjustment') {
            return 'adjust';
        }

        return 'other';
    }

    /** Short title without parentheses: Sales, Receiving, … */
    public function entryKindLabel(): string
    {
        return match ($this->entryKind()) {
            'receiving' => 'Receiving',
            'count' => 'Stock Count',
            'rtv' => 'Return to Vendor',
            'credit' => 'Credit Memo',
            'void' => 'Void Sales',
            'sales' => 'Sales',
            'adjust' => 'Adjustment',
            default => 'Inventory',
        };
    }

    /** Parenthetical reference only, e.g. "Order No.245217" or "". */
    public function entryReferenceLabel(): string
    {
        $full = $this->entryDescription();
        if (preg_match('/\(([^)]+)\)\s*$/', $full, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * Chief-style label: "Sales (Order No.8008)", "Receiving (Receipt No.760088A)".
     */
    public function entryDescription(): string
    {
        $ref = trim((string) ($this->reference ?? ''));
        $notes = trim((string) ($this->notes ?? ''));
        $type = (string) ($this->source_type ?? '');
        $base = class_basename($type);

        if ($base === 'InventoryReceiving' || str_contains($notes, 'Receiving')) {
            return $ref !== '' ? 'Receiving (Receipt No.'.$ref.')' : 'Receiving';
        }

        if ($base === 'StockCount' || str_contains($notes, 'Stock Count')) {
            return $ref !== '' ? 'Stock Count (Count No.'.$ref.')' : 'Stock Count';
        }

        if ($base === 'ReturnToVendor' || str_contains($notes, 'Return to Vendor')) {
            return $ref !== '' ? 'Return to Vendor (RTV No.'.$ref.')' : 'Return to Vendor';
        }

        if ($base === 'CreditMemo' || str_contains($notes, 'Credit Memo') || str_contains($notes, 'restock')) {
            return $ref !== '' ? 'Credit Memo (Memo No.'.$ref.')' : 'Credit Memo';
        }

        if ($base === 'Invoice' || str_starts_with($notes, 'Sales') || str_contains($notes, 'Void Invoice') || str_contains($notes, 'Invoice revision')) {
            if (preg_match('/\(SO\s+([^)]+)\)/i', $notes, $m)) {
                $orderNo = trim($m[1]);
                if (str_contains($notes, 'Void')) {
                    return 'Void Sales (Order No.'.$orderNo.')';
                }

                return 'Sales (Order No.'.$orderNo.')';
            }

            if (str_contains($notes, 'Void')) {
                return $ref !== '' ? 'Void Sales (Invoice No.'.$ref.')' : 'Void Sales';
            }

            return $ref !== '' ? 'Sales (Invoice No.'.$ref.')' : 'Sales';
        }

        if ($type === 'stock_adjustment' || $base === 'stock_adjustment') {
            if ($notes !== '') {
                return $ref !== '' ? $notes.' ('.$ref.')' : $notes;
            }

            return $ref !== '' ? 'Adjustment ('.$ref.')' : 'Adjustment';
        }

        if ($notes !== '') {
            return $notes;
        }

        return $ref !== '' ? $base.' ('.$ref.')' : ($base !== '' ? $base : 'Inventory');
    }
}
